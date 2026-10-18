<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\Reviews\Events\ReviewApproved;
use NineteenNinetyFour\Reviews\Events\ReviewSubmitted;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Spam;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class SubmitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    protected function only(): ?Review
    {
        return $this->reviews()->repository()->get(ReviewQuery::make())->first();
    }

    protected function total(): int
    {
        return $this->reviews()->repository()->count(ReviewQuery::make());
    }

    #[Test]
    public function a_guest_review_waits_for_an_editor(): void
    {
        Event::fake([ReviewSubmitted::class]);

        $this->from('/products/the-long-field')
            ->post('/!/reviews/submit', $this->submission())
            ->assertRedirect('/products/the-long-field')
            ->assertSessionHas('reviews_success', 'Thank you. Your review will appear once we’ve checked it.');

        $review = $this->only();

        $this->assertSame('book', $review->entry);
        $this->assertSame('default', $review->site);
        $this->assertSame(4, $review->rating);
        $this->assertSame('Worth it', $review->title);
        $this->assertSame('I enjoyed this a lot.', $review->body);
        $this->assertSame('Sam Reader', $review->name);
        $this->assertSame('sam@example.com', $review->email);
        $this->assertNull($review->user);
        $this->assertSame(ReviewStatus::Pending, $review->status);
        $this->assertFalse($review->verified);

        Event::assertDispatched(ReviewSubmitted::class);
    }

    #[Test]
    public function it_answers_json_to_scripts(): void
    {
        $this->postJson('/!/reviews/submit', $this->submission())
            ->assertOk()
            ->assertJsonPath('review.status', 'pending')
            ->assertJsonPath('message', 'Thank you. Your review will appear once we’ve checked it.');

        $this->postJson('/!/reviews/submit', $this->submission(['rating' => 9, 'email' => 'other@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('rating');
    }

    #[Test]
    public function it_stores_no_ip_address_or_browser(): void
    {
        $this->post('/!/reviews/submit', $this->submission(), ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_USER_AGENT' => 'TestBrowser/1.0']);

        $stored = json_encode($this->only()->fileData());

        $this->assertStringNotContainsString('203.0.113.9', $stored);
        $this->assertStringNotContainsString('TestBrowser', $stored);
    }

    #[Test]
    public function mistakes_come_back_in_the_reviews_error_bag_with_the_input(): void
    {
        $this->from('/products/the-long-field')
            ->post('/!/reviews/submit', $this->submission(['rating' => '', 'email' => 'not-an-email', 'body' => '']))
            ->assertRedirect('/products/the-long-field')
            ->assertSessionHasErrors(['rating', 'email', 'body'], null, 'reviews')
            ->assertSessionHasInput('title', 'Worth it');

        $this->assertSame(0, $this->total());
    }

    #[Test]
    public function the_rating_must_be_a_whole_number_from_one_to_five(): void
    {
        foreach (['0', '6', '4.5', 'five', ['5']] as $i => $rating) {
            $this->post('/!/reviews/submit', $this->submission(['rating' => $rating, 'email' => "r{$i}@example.com"]))
                ->assertSessionHasErrors('rating', null, 'reviews');
        }

        $this->assertSame(0, $this->total());
    }

    #[Test]
    public function text_is_limited_in_length(): void
    {
        config(['reviews.form.body_limit' => 20, 'reviews.form.title_limit' => 5]);

        $this->post('/!/reviews/submit', $this->submission(['title' => 'Too long a title', 'body' => str_repeat('a', 21)]))
            ->assertSessionHasErrors(['title', 'body'], null, 'reviews');
    }

    #[Test]
    public function fields_sent_as_arrays_are_read_as_missing(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['name' => ['Sam'], 'body' => ['x' => 'y']]))
            ->assertSessionHasErrors(['name', 'body'], null, 'reviews');
    }

    #[Test]
    public function a_rating_alone_is_enough_when_text_is_optional(): void
    {
        config(['reviews.form.require_body' => false]);

        $this->post('/!/reviews/submit', $this->submission(['title' => '', 'body' => '']))->assertSessionHasNoErrors();

        $this->assertNull($this->only()->body);
    }

    #[Test]
    public function a_title_can_be_required(): void
    {
        config(['reviews.form.require_title' => true]);

        $this->post('/!/reviews/submit', $this->submission(['title' => '']))->assertSessionHasErrors('title', null, 'reviews');
    }

    #[Test]
    public function reviews_with_too_many_links_are_refused(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['body' => 'Buy at https://a.example and www.b.example and c.com']))
            ->assertSessionHasErrors('body', null, 'reviews');

        $this->post('/!/reviews/submit', $this->submission(['body' => 'See https://a.example for more.']))
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function criteria_ratings_are_kept_and_unknown_ones_ignored(): void
    {
        config(['reviews.criteria' => [
            ['handle' => 'quality', 'label' => 'Quality'],
            ['handle' => 'value', 'label' => 'Value', 'collections' => ['products']],
            ['handle' => 'taste', 'label' => 'Taste', 'collections' => ['recipes']],
        ]]);

        $this->post('/!/reviews/submit', $this->submission(['ratings' => ['quality' => '5', 'value' => '3', 'taste' => '4', 'nope' => '5']]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['quality' => 5, 'value' => 3], $this->only()->ratings);

        $this->post('/!/reviews/submit', $this->submission(['email' => 'b@example.com', 'ratings' => ['quality' => '7']]))
            ->assertSessionHasErrors('ratings.quality', null, 'reviews');
    }

    #[Test]
    public function only_live_routed_entries_in_chosen_collections_can_be_reviewed(): void
    {
        $this->product('draft', 'Draft', published: false);
        $this->event('future', 'Next year', now()->addYear()->toDateString());
        $this->unrouted('snippet', 'Snippet');
        Entry::make()->collection('pages')->id('about')->slug('about')->data(['title' => 'About'])->save();

        foreach (['draft', 'future', 'snippet', 'missing', '../etc'] as $i => $id) {
            $response = $this->post('/!/reviews/submit', $this->submission(['entry' => $id, 'email' => "u{$i}@example.com"]));
            $this->assertNotNull(session('errors')?->getBag('reviews')->first('review'), "Entry [{$id}] was accepted.");
        }

        config(['reviews.collections' => ['products']]);

        $this->post('/!/reviews/submit', $this->submission(['entry' => 'about', 'email' => 'p@example.com']))
            ->assertSessionHasErrors('review', null, 'reviews');

        $this->assertSame(0, $this->total());
    }

    #[Test]
    public function reviews_of_a_translation_are_kept_against_the_original(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'default' => ['name' => 'English', 'url' => '/', 'locale' => 'en_GB'],
            'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ])->save();
        Collection::find('products')->sites(['default', 'french'])->routes('/products/{slug}')->save();

        $original = Entry::find('book');
        $french = $original->makeLocalization('french')->id('livre')->slug('le-long-champ');
        $french->save();

        $this->post('/!/reviews/submit', $this->submission(['entry' => 'livre']))->assertSessionHasNoErrors();

        $this->assertSame('book', $this->only()->entry);
    }

    #[Test]
    public function separate_sites_each_have_their_own_reviews(): void
    {
        config(['statamic.system.multisite' => true, 'reviews.sites' => 'separate']);
        Site::setSites([
            'default' => ['name' => 'English', 'url' => '/', 'locale' => 'en_GB'],
            'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ])->save();
        Collection::find('products')->sites(['default', 'french'])->routes('/products/{slug}')->save();

        $original = Entry::find('book');
        $original->makeLocalization('french')->id('livre')->slug('le-long-champ')->save();

        $this->post('/!/reviews/submit', $this->submission(['rating' => 5]))->assertSessionHasNoErrors();
        $this->post('/!/reviews/submit', $this->submission(['entry' => 'livre', 'rating' => 1]))->assertSessionHasNoErrors();

        // The same email reviewed it on each site: two reviews.
        $reviews = $this->reviews();
        $this->assertSame(2, $reviews->repository()->count(ReviewQuery::make()));
        $reviews->repository()->get(ReviewQuery::make())->each(fn (Review $review) => $reviews->approve($review));

        Site::setCurrent('default');
        $this->assertSame(5.0, $reviews->summary('book', $reviews->siteScope())->average());
        $this->assertSame('5', trim((string) $this->antlers('{{ reviews:average entry="book" }}')));

        Site::setCurrent('french');
        $this->assertSame(1.0, $reviews->summary('book', $reviews->siteScope())->average());
        $this->assertSame('2', trim((string) $this->antlers('{{ reviews:count entry="book" site="all" }}')));
    }

    #[Test]
    public function shared_sites_show_every_site_s_reviews(): void
    {
        config(['statamic.system.multisite' => true]);
        Site::setSites([
            'default' => ['name' => 'English', 'url' => '/', 'locale' => 'en_GB'],
            'french' => ['name' => 'French', 'url' => '/fr/', 'locale' => 'fr_FR'],
        ])->save();
        Collection::find('products')->sites(['default', 'french'])->routes('/products/{slug}')->save();
        Entry::find('book')->makeLocalization('french')->id('livre')->slug('le-long-champ')->save();

        $this->post('/!/reviews/submit', $this->submission(['rating' => 5]))->assertSessionHasNoErrors();
        $this->post('/!/reviews/submit', $this->submission(['entry' => 'livre', 'rating' => 1]))->assertSessionHasNoErrors();

        // Shared: the second is the same person's repeat, and not kept.
        $this->assertSame(1, $this->reviews()->repository()->count(ReviewQuery::make()));
        $this->reviews()->approve($this->only());
        Site::setCurrent('french');
        $this->assertSame('1', trim((string) $this->antlers('{{ reviews:count entry="book" }}')));
    }

    #[Test]
    public function guests_can_be_turned_away(): void
    {
        config(['reviews.form.who' => 'users']);

        $this->post('/!/reviews/submit', $this->submission())
            ->assertSessionHasErrors(['review' => 'Sign in to write a review.'], null, 'reviews');

        $this->actingAs($this->user('sam@example.com', name: 'Sam Reader'))
            ->post('/!/reviews/submit', $this->submission(['name' => '', 'email' => '']))
            ->assertSessionHasNoErrors();

        $review = $this->only();
        $this->assertSame('sam', $review->user);
        $this->assertSame('sam@example.com', $review->email);
        $this->assertSame('Sam Reader', $review->name);
    }

    #[Test]
    public function a_guest_can_review_an_entry_once_per_email_address(): void
    {
        $first = $this->post('/!/reviews/submit', $this->submission());
        $again = $this->post('/!/reviews/submit', $this->submission(['email' => 'SAM@example.com', 'body' => 'Again']));

        // The same answer as a first review, so nobody can find out from the
        // form whether an address has reviewed something.
        $again->assertSessionHasNoErrors()->assertSessionHas('reviews_success', 'Thank you. Your review will appear once we’ve checked it.');
        $this->assertSame($first->getStatusCode(), $again->getStatusCode());

        $this->assertSame(1, $this->total());
        $this->assertSame('I enjoyed this a lot.', $this->only()->body);
    }

    #[Test]
    public function mistakes_are_reported_the_same_whether_or_not_the_address_has_reviewed(): void
    {
        $this->post('/!/reviews/submit', $this->submission());

        $this->post('/!/reviews/submit', $this->submission(['rating' => '']))->assertSessionHasErrors('rating', null, 'reviews');
        $this->post('/!/reviews/submit', $this->submission(['rating' => '', 'email' => 'new@example.com']))->assertSessionHasErrors('rating', null, 'reviews');
    }

    #[Test]
    public function signed_in_reviewers_are_shown_by_their_accounts_name_whatever_the_form_sends(): void
    {
        $this->actingAs($this->user('sam@example.com', name: 'Sam Reader'))
            ->post('/!/reviews/submit', $this->submission(['name' => 'The Shop Owner']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Sam Reader', $this->only()->name);
    }

    #[Test]
    public function a_signed_in_reviewer_editing_their_review_sends_it_back_to_an_editor(): void
    {
        $user = $this->user('sam@example.com', name: 'Sam Reader');
        $this->review(['user' => 'sam', 'email' => 'sam@example.com', 'rating' => 5, 'body' => 'First']);

        $this->actingAs($user)->post('/!/reviews/submit', $this->submission(['rating' => 2, 'body' => 'Changed my mind']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $this->total());
        $review = $this->only();
        $this->assertSame(2, $review->rating);
        $this->assertSame('Changed my mind', $review->body);
        $this->assertSame(ReviewStatus::Pending, $review->status);
    }

    #[Test]
    public function signed_in_reviews_can_be_published_straight_away(): void
    {
        config(['reviews.auto_approve' => 'users']);
        Event::fake([ReviewApproved::class, ReviewSubmitted::class]);

        $this->post('/!/reviews/submit', $this->submission())->assertSessionHasNoErrors();
        $this->assertSame(ReviewStatus::Pending, $this->only()->status);

        $this->actingAs($this->user('ana@example.com', name: 'Ana'))
            ->post('/!/reviews/submit', $this->submission(['rating' => 1]))
            ->assertSessionHas('reviews_success', 'Thank you. Your review is now live.');

        $published = $this->reviews()->repository()->get(ReviewQuery::make()->user('ana'))->first();
        $this->assertSame(ReviewStatus::Approved, $published->status);
        $this->assertNotNull($published->approvedAt);

        Event::assertDispatched(ReviewApproved::class);
    }

    #[Test]
    public function the_star_rating_never_decides_whether_a_review_is_published(): void
    {
        foreach (['never', 'users', 'verified'] as $i => $setting) {
            config(['reviews.auto_approve' => $setting]);

            foreach ([1, 5] as $rating) {
                $this->post('/!/reviews/submit', $this->submission(['rating' => $rating, 'email' => "{$setting}{$rating}@example.com"]));
            }
        }

        $this->assertSame(6, $this->reviews()->repository()->count(ReviewQuery::make()->status('pending')));
    }

    #[Test]
    public function a_filled_honeypot_is_thanked_and_nothing_kept(): void
    {
        $this->post('/!/reviews/submit', $this->submission(['website' => 'https://spam.example']))
            ->assertSessionHas('reviews_success')
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->total());
    }

    #[Test]
    public function forms_sent_too_quickly_are_a_bots(): void
    {
        config(['reviews.spam.minimum_seconds' => 3]);

        // Drawn a second ago.
        $this->post('/!/reviews/submit', $this->submission([Spam::TIME_FIELD => Crypt::encryptString((string) (time() - 1))]));
        // No time at all, or one that's been made up.
        $this->post('/!/reviews/submit', $this->submission([Spam::TIME_FIELD => null, 'email' => 'b@example.com']));
        $this->post('/!/reviews/submit', $this->submission([Spam::TIME_FIELD => (string) (time() - 100), 'email' => 'c@example.com']));
        // A page from the static cache, drawn long ago but opened a second ago.
        $this->post('/!/reviews/submit', $this->submission([Spam::OPENED_FIELD => '1', 'email' => 'd@example.com']));

        $this->assertSame(0, $this->total());

        $this->post('/!/reviews/submit', $this->submission([Spam::OPENED_FIELD => '12', 'email' => 'e@example.com']));
        $this->assertSame(1, $this->total());
    }

    #[Test]
    public function it_only_redirects_within_the_site(): void
    {
        $from = '/products/the-long-field';

        $this->from($from)->post('/!/reviews/submit', $this->submission(['_redirect' => '/thanks']))->assertRedirect('/thanks');

        foreach (['https://evil.example/x', '//evil.example', '/\\evil.example', "/\t/evil.example", 'javascript:alert(1)'] as $i => $to) {
            $this->from($from)
                ->post('/!/reviews/submit', $this->submission(['_redirect' => $to, 'email' => "x{$i}@example.com"]))
                ->assertRedirect($from);
        }
    }

    #[Test]
    public function errors_can_go_to_their_own_page(): void
    {
        $this->from('/products/the-long-field')
            ->post('/!/reviews/submit', $this->submission(['rating' => '', '_redirect' => '/thanks', '_error_redirect' => '/sorry']))
            ->assertRedirect('/sorry');
    }

    #[Test]
    public function submitting_is_rate_limited(): void
    {
        config(['reviews.throttle.submit' => 2]);

        $this->post('/!/reviews/submit', $this->submission(['email' => 'a@example.com']))->assertRedirect();
        $this->post('/!/reviews/submit', $this->submission(['email' => 'b@example.com']))->assertRedirect();
        $this->post('/!/reviews/submit', $this->submission(['email' => 'c@example.com']))->assertStatus(429);
    }

    #[Test]
    public function it_needs_a_csrf_token(): void
    {
        $this->withMiddleware()->app['config']->set('app.env', 'production');

        $this->assertTrue(
            collect(app('router')->getRoutes()->getByName('statamic.reviews.submit')->gatherMiddleware())
                ->contains(fn ($m) => in_array($m, ['web', 'statamic.web'], true))
        );
    }

    #[Test]
    public function a_signed_in_reviewer_can_delete_their_own_review_only(): void
    {
        $mine = $this->review(['user' => 'sam']);
        $theirs = $this->review(['user' => 'ana', 'email' => 'ana@example.com']);

        $this->actingAs($this->user('sam@example.com'));

        $this->post('/!/reviews/delete', ['id' => $theirs->id])->assertSessionHasErrors('review', null, 'reviews');
        $this->post('/!/reviews/delete', ['id' => $mine->id])->assertSessionHas('reviews_success');

        $this->assertNull($this->reviews()->find($mine->id));
        $this->assertNotNull($this->reviews()->find($theirs->id));
    }
}
