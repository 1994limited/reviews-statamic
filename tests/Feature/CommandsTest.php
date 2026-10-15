<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\Database\EloquentReviewRepository;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Stache\StacheReviewRepository;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\User;

class CommandsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    #[Test]
    public function transfer_refuses_until_the_tables_exist(): void
    {
        $this->review();

        $this->artisan('reviews:transfer', ['--from' => 'file', '--to' => 'database', '--force' => true])
            ->expectsOutputToContain('php please vendor:publish --tag=reviews-migrations')
            ->assertFailed();
    }

    #[Test]
    public function transfer_checks_its_options(): void
    {
        $this->artisan('reviews:transfer', ['--from' => 'file', '--to' => 'file'])->assertFailed();
        $this->artisan('reviews:transfer', ['--from' => 'browser', '--to' => 'database'])->assertFailed();
    }

    #[Test]
    public function transfer_copies_reviews_and_votes_both_ways(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        $review = $this->review(['ratings' => ['value' => 3], 'title' => 'Moved']);
        app(StacheReviewRepository::class)->vote($review, 'voter-1');

        $this->artisan('reviews:transfer', ['--from' => 'file', '--to' => 'database', '--force' => true])
            ->expectsOutputToContain('Copied 1 reviews to the database driver.')
            ->assertSuccessful();

        $copied = app(EloquentReviewRepository::class)->find($review->id);
        $this->assertSame('Moved', $copied->title);
        $this->assertSame(['value' => 3], $copied->ratings);
        $this->assertSame(1, $copied->helpful);
        $this->assertFalse(app(EloquentReviewRepository::class)->vote($copied, 'voter-1'));
        $this->assertEquals($review->createdAt, $copied->createdAt);

        app(StacheReviewRepository::class)->delete($review);

        $this->artisan('reviews:transfer', ['--from' => 'database', '--to' => 'file', '--force' => true])->assertSuccessful();

        $back = app(StacheReviewRepository::class)->find($review->id);
        $this->assertSame(['voter-1'], $back->voters);
    }

    #[Test]
    public function export_lists_one_persons_reviews(): void
    {
        $this->review(['email' => 'jo@example.com', 'title' => 'Mine']);
        $this->review(['email' => 'ana@example.com', 'title' => 'Not mine']);

        $path = __DIR__.'/../__fixtures__/dev-null/export.json';

        $this->artisan('reviews:export', ['email' => 'JO@example.com', '--output' => $path])->assertSuccessful();

        $data = json_decode(file_get_contents($path), true);
        unlink($path);

        $this->assertSame('JO@example.com', $data['email']);
        $this->assertCount(1, $data['reviews']);
        $this->assertSame('Mine', $data['reviews'][0]['title']);
        $this->assertSame('The Long Field', $data['reviews'][0]['entry_title']);
        $this->assertArrayNotHasKey('voters', $data['reviews'][0]);
    }

    #[Test]
    public function forget_anonymises_or_deletes_and_stops_requests(): void
    {
        $kept = $this->review(['email' => 'jo@example.com', 'body' => 'Still useful']);

        $this->artisan('reviews:forget', ['email' => 'jo@example.com', '--force' => true])
            ->expectsOutputToContain('Anonymised 1 review.')
            ->assertSuccessful();

        $found = $this->reviews()->find($kept->id);
        $this->assertSame('A customer', $found->name);
        $this->assertNull($found->email);
        $this->assertSame('Still useful', $found->body);
        $this->assertTrue(app(RequestLog::class)->optedOut('jo@example.com'));

        $gone = $this->review(['email' => 'ana@example.com']);
        $this->artisan('reviews:forget', ['email' => 'ana@example.com', '--delete' => true, '--force' => true])->assertSuccessful();
        $this->assertNull($this->reviews()->find($gone->id));
    }

    #[Test]
    public function forget_asks_first(): void
    {
        $this->review();

        $this->artisan('reviews:forget', ['email' => 'jo@example.com'])
            ->expectsConfirmation('Anonymise every review by jo@example.com?', 'no')
            ->assertFailed();

        $this->assertSame('Jo Bloggs', $this->reviews()->repository()->get(ReviewQuery::make())->first()->name);
    }

    #[Test]
    public function deleting_a_user_anonymises_their_reviews(): void
    {
        $user = $this->user('sam@example.com');
        $review = $this->review(['user' => 'sam', 'email' => 'sam@example.com', 'name' => 'Sam Reader']);

        $user->delete();

        $found = $this->reviews()->find($review->id);
        $this->assertSame('A customer', $found->name);
        $this->assertNull($found->user);
        $this->assertNull($found->email);
    }

    #[Test]
    public function deleting_a_user_can_delete_their_reviews(): void
    {
        config(['reviews.deleted_users' => 'delete']);
        $user = $this->user('sam@example.com');
        $review = $this->review(['user' => 'sam']);

        User::find('sam')->delete();

        $this->assertNull($this->reviews()->find($review->id));
    }

    #[Test]
    public function send_requests_does_nothing_without_cargo(): void
    {
        $this->artisan('reviews:send-requests')->expectsOutputToContain('Cargo isn\'t installed')->assertSuccessful();
    }
}
