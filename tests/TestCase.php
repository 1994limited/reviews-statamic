<?php

namespace NineteenNinetyFour\Reviews\Tests;

use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\ServiceProvider;
use NineteenNinetyFour\Reviews\Settings;
use NineteenNinetyFour\Reviews\Spam;
use Illuminate\Support\Facades\Crypt;
use Statamic\Facades\Antlers;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\User;
use Statamic\Testing\AddonTestCase;
use Statamic\Testing\Concerns\FakesRoles;
use Statamic\Testing\Concerns\PreventsSavingStacheItemsToDisk;

abstract class TestCase extends AddonTestCase
{
    use FakesRoles, PreventsSavingStacheItemsToDisk;

    protected string $addonServiceProvider = ServiceProvider::class;

    /** The storage driver the test runs with. */
    protected string $driver = 'file';

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('reviews.driver', $this->driver);
        $app['config']->set('reviews.drivers.file.directory', __DIR__.'/__fixtures__/dev-null/reviews');
        $app['config']->set('reviews.drivers.file.requests', __DIR__.'/__fixtures__/dev-null/reviews-requests.json');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('view.paths', [__DIR__.'/__fixtures__/views']);
        // No waiting in tests: the time trap is tested on its own.
        $app['config']->set('reviews.spam.minimum_seconds', 0);
        // Rate limits are tested on their own.
        $app['config']->set('reviews.throttle.submit', 1000);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Settings::flush();
        Cache::flush();

        if ($this->driver === 'database') {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        Collection::make('products')->routes('/products/{slug}')->save();
        Collection::make('pages')->routes('/{slug}')->save();
    }

    protected function tearDown(): void
    {
        @unlink(__DIR__.'/__fixtures__/dev-null/reviews-requests.json');

        parent::tearDown();
    }

    protected function product(string $id = 'book', string $title = 'The Long Field', bool $published = true): \Statamic\Contracts\Entries\Entry
    {
        $entry = Entry::make()->collection('products')->id($id)->slug(str($title)->slug())->published($published)->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    /**
     * An entry in a dated collection that hides future and past entries,
     * as for events: Statamic reports it scheduled or expired.
     */
    protected function event(string $id, string $title, string $date): \Statamic\Contracts\Entries\Entry
    {
        Collection::make('events')->dated(true)->futureDateBehavior('private')->pastDateBehavior('private')->routes('/events/{slug}')->save();

        $entry = Entry::make()->collection('events')->id($id)->slug(str($title)->slug())->date($date)->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    protected function unrouted(string $id, string $title): \Statamic\Contracts\Entries\Entry
    {
        Collection::make('snippets')->save();

        $entry = Entry::make()->collection('snippets')->id($id)->slug(str($title)->slug())->data(['title' => $title]);
        $entry->save();

        return $entry;
    }

    protected function user(string $email = 'reader@example.com', array $permissions = [], ?string $name = null): \Statamic\Contracts\Auth\User
    {
        if ($permissions) {
            $this->setTestRoles(['tester' => ['access cp', ...$permissions]]);
        }

        $user = User::make()->email($email)->id((string) str($email)->before('@')->slug());

        if ($name) {
            $user->set('name', $name);
        }

        if ($permissions) {
            $user->assignRole('tester');
        }

        $user->save();

        return $user;
    }

    protected function editor(array $permissions = ['view reviews', 'moderate reviews', 'reply to reviews', 'delete reviews']): \Statamic\Contracts\Auth\User
    {
        return $this->user('editor@example.com', $permissions, 'Eddie Editor');
    }

    /**
     * A review saved straight to storage.
     */
    protected function review(array $data = []): Review
    {
        $review = Review::make();
        $review->entry = $data['entry'] ?? 'book';
        $review->site = $data['site'] ?? 'default';
        $review->rating = $data['rating'] ?? 5;
        $review->ratings = $data['ratings'] ?? [];
        $review->title = array_key_exists('title', $data) ? $data['title'] : 'Lovely';
        $review->body = array_key_exists('body', $data) ? $data['body'] : 'A very good read.';
        $review->name = $data['name'] ?? 'Jo Bloggs';
        $review->email = array_key_exists('email', $data) ? $data['email'] : 'jo@example.com';
        $review->user = $data['user'] ?? null;
        $review->status = $data['status'] ?? ReviewStatus::Approved;
        $review->verified = $data['verified'] ?? false;
        $review->helpful = $data['helpful'] ?? 0;
        $review->photos = $data['photos'] ?? [];

        if (isset($data['created_at'])) {
            $review->createdAt = \Carbon\CarbonImmutable::parse($data['created_at']);
        }

        if ($review->status === ReviewStatus::Approved) {
            $review->approvedAt = now()->toImmutable();
        }

        app(Reviews::class)->save($review);

        return $review;
    }

    /**
     * What a person sends from the review form.
     */
    protected function submission(array $data = []): array
    {
        return $data + [
            'entry' => 'book',
            'rating' => 4,
            'title' => 'Worth it',
            'body' => 'I enjoyed this a lot.',
            'name' => 'Sam Reader',
            'email' => 'sam@example.com',
            Spam::TIME_FIELD => Crypt::encryptString((string) (time() - 60)),
        ];
    }

    /**
     * Render Antlers as a site template would (trusted, so tags run).
     */
    protected function antlers(string $template, array $data = []): string
    {
        return (string) Antlers::parse($template, $data, true);
    }

    protected function reviews(): Reviews
    {
        return app(Reviews::class);
    }
}
