<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Core\Sync\SyncException;
use NineteenNinetyFour\Reviews\Import\Imports;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Sync\Source;
use NineteenNinetyFour\Reviews\Sync\Sync;
use NineteenNinetyFour\Reviews\Tests\FakeClient;
use NineteenNinetyFour\Reviews\Tests\RefusingRepository;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
        @unlink(storage_path('reviews-sources.yaml'));
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('reviews-sources.yaml'));

        parent::tearDown();
    }

    protected function sync(): Sync
    {
        return app(Sync::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function source(array $data = []): Source
    {
        $source = Source::fromArray('', $data + ['type' => 'judgeme', 'name' => 'Judge.me', 'settings' => ['shop_domain' => 'x.myshopify.com', 'api_token' => 'secret-token']]);
        $this->assertSame([], $this->sync()->save($source));

        return $source;
    }

    /**
     * @return array<int, Review>
     */
    protected function all(): array
    {
        return $this->reviews()->repository()->get(ReviewQuery::make()->sort('oldest'))->all();
    }

    #[Test]
    public function secret_keys_are_stored_encrypted(): void
    {
        $source = $this->source();

        $this->assertStringNotContainsString('secret-token', $this->stored());
        $this->assertSame('secret-token', $this->sync()->find($source->id)->settings['api_token']);
    }

    #[Test]
    public function a_source_needs_its_settings_and_only_some_platforms_can_count(): void
    {
        $errors = $this->sync()->save(Source::fromArray('', ['type' => 'judgeme', 'name' => 'J', 'settings' => ['shop_domain' => 'x']]));
        $this->assertArrayHasKey('settings.api_token', $errors);

        $errors = $this->sync()->save(Source::fromArray('', ['type' => 'trustpilot', 'name' => 'TP', 'count_in_summary' => true, 'settings' => ['business_unit' => 'b', 'api_key' => 'k', 'profile_url' => 'https://uk.trustpilot.com/review/x']]));
        $this->assertArrayHasKey('count_in_summary', $errors);

        $this->assertSame([], $this->sync()->save(Source::fromArray('', ['type' => 'feefo', 'name' => 'Feefo', 'count_in_summary' => true, 'settings' => ['merchant' => 'x']])));
    }

    #[Test]
    public function a_sync_adds_then_follows_the_platform(): void
    {
        $item = ['id' => 1, 'rating' => 5, 'product_handle' => 'the-long-field', 'body' => 'Good', 'reviewer' => ['name' => 'Fi Reader']];
        $source = $this->source();

        $this->sync()->client = new FakeClient([['reviews' => [$item]]]);
        $this->assertSame(1, $this->sync()->run($source)->added);

        $review = $this->all()[0];
        $this->assertSame('book', $review->entry);
        $this->assertSame($source->id, $review->sourceId);
        $this->assertFalse($review->counted);
        $this->assertNotNull($this->sync()->find($source->id)->lastSynced);

        // The platform hides it: so does the sync.
        $this->sync()->client = new FakeClient([['reviews' => [['hidden' => true] + $item]]]);
        $this->assertSame(1, $this->sync()->run($this->sync()->find($source->id))->updated);
        $this->assertSame(ReviewStatus::Rejected, $this->all()[0]->status);
    }

    #[Test]
    public function two_shops_on_one_platform_never_mix(): void
    {
        $reply = fn (string $body) => ['reviews' => [['id' => 1, 'rating' => 5, 'product_handle' => 'the-long-field', 'body' => $body, 'reviewer' => ['name' => 'Gil']]]];
        $a = $this->source(['name' => 'A']);
        $b = $this->source(['name' => 'B']);

        $this->sync()->client = new FakeClient([$reply('From A')]);
        $this->sync()->run($a);
        $this->sync()->client = new FakeClient([$reply('From B')]);
        $this->sync()->run($b);

        $this->assertEqualsCanonicalizing(['From A', 'From B'], array_map(fn ($r) => $r->body, $this->all()));
    }

    #[Test]
    public function a_failed_sync_is_recorded_and_tried_again_next_time(): void
    {
        $source = $this->source();
        $this->sync()->client = new FakeClient([null]);

        try {
            $this->sync()->run($source);
            $this->fail('The sync should have failed.');
        } catch (SyncException) {
        }

        $saved = $this->sync()->find($source->id);
        $this->assertNotNull($saved->lastError);
        $this->assertNull($saved->lastSynced);
        $this->assertTrue($saved->isDue());
    }

    #[Test]
    public function the_command_syncs_what_s_due(): void
    {
        $due = $this->source(['name' => 'Due']);
        $this->source(['name' => 'Not due', 'last_synced' => CarbonImmutable::now()->subHour()->toIso8601String()]);
        $this->sync()->client = new FakeClient([['reviews' => []]]);

        $this->artisan('reviews:sync')->expectsOutputToContain('Due: 0 added')->assertSuccessful();

        $this->assertNotNull($this->sync()->find($due->id)->lastSynced);
    }

    #[Test]
    public function environment_variables_stay_as_they_are(): void
    {
        putenv('JUDGEME_TEST_TOKEN=from-env');
        $_ENV['JUDGEME_TEST_TOKEN'] = 'from-env';

        try {
            $source = $this->source(['settings' => ['shop_domain' => 'x', 'api_token' => '$JUDGEME_TEST_TOKEN']]);
            $this->assertStringContainsString('$JUDGEME_TEST_TOKEN', $this->stored());
            $this->assertSame('from-env', $this->sync()->find($source->id)->resolvedSettings()['api_token']);
        } finally {
            putenv('JUDGEME_TEST_TOKEN');
            unset($_ENV['JUDGEME_TEST_TOKEN']);
        }
    }

    protected function stored(): string
    {
        if (config('reviews.driver') === 'database') {
            return (string) json_encode(DB::table('review_sources')->get());
        }

        return (string) file_get_contents(storage_path('reviews-sources.yaml'));
    }

    #[Test]
    public function a_review_that_can_t_be_saved_keeps_the_sync_from_counting_as_done(): void
    {
        $source = $this->source();

        // The repository refuses to save.
        $this->app->instance(ReviewRepository::class, new RefusingRepository(app(ReviewRepository::class)));

        foreach ([Reviews::class, Imports::class, Sync::class] as $class) {
            $this->app->forgetInstance($class);
        }

        $this->sync()->client = new FakeClient([['reviews' => [['id' => 1, 'rating' => 5, 'product_handle' => 'the-long-field', 'body' => 'Fine', 'reviewer' => ['name' => 'Ok']]]]]);
        $report = $this->sync()->run($this->sync()->find($source->id));

        $this->assertSame(1, $report->failed);
        $saved = $this->sync()->find($source->id);
        $this->assertNull($saved->lastSynced);
        $this->assertStringContainsString('couldn’t be saved', (string) $saved->lastError);
    }

    #[Test]
    public function a_finishing_sync_keeps_an_editor_s_changes_to_its_source(): void
    {
        $source = $this->source(['name' => 'Before']);
        $running = $this->sync()->find($source->id);

        // An editor renames it and changes its key while it syncs.
        $edited = $this->sync()->find($source->id);
        $edited->name = 'After';
        $edited->settings['api_token'] = 'new-token';
        $this->sync()->save($edited);

        $this->sync()->client = new FakeClient([['reviews' => []]]);
        $this->sync()->run($running);

        $saved = $this->sync()->find($source->id);
        $this->assertSame('After', $saved->name);
        $this->assertSame('new-token', $saved->settings['api_token']);
        $this->assertNotNull($saved->lastSynced);
    }

    #[Test]
    public function a_source_syncs_once_at_a_time(): void
    {
        $source = $this->source();
        $lock = Cache::lock('reviews:sync:'.$source->id, 60);
        $lock->get();

        try {
            $this->expectException(SyncException::class);
            $this->sync()->run($source);
        } finally {
            $lock->release();
        }
    }
}
