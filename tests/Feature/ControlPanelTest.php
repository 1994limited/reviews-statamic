<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Inertia\Testing\AssertableInertia;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use NineteenNinetyFour\Reviews\Widgets\PendingReviews;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;
use Statamic\Facades\Permission;
use Statamic\Fields\Field;

class ControlPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    protected function inertia(string $url)
    {
        return $this->get($url);
    }

    #[Test]
    public function the_list_needs_permission(): void
    {
        $this->get(cp_route('reviews.index'))->assertRedirect();

        $response = $this->actingAs($this->user('nobody@example.com', ['access cp']))->get(cp_route('reviews.index'));
        $this->assertContains($response->getStatusCode(), [302, 403]);
    }

    #[Test]
    public function the_list_opens_on_pending_while_anything_waits(): void
    {
        $this->review(['title' => 'Live']);
        $waiting = $this->review(['title' => 'Waiting', 'status' => ReviewStatus::Pending]);

        $this->actingAs($this->editor(['view reviews']))
            ->inertia(cp_route('reviews.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reviews::Index')
                ->where('status', 'pending')
                ->where('counts.pending', 1)
                ->where('counts.approved', 1)
                ->has('reviews', 1)
                ->where('reviews.0.id', $waiting->id)
                ->where('reviews.0.entry_title', 'The Long Field')
                ->where('can.moderate', false)
                ->etc());
    }

    #[Test]
    public function the_list_filters_and_searches(): void
    {
        $this->review(['title' => 'Five stars', 'rating' => 5]);
        $this->review(['title' => 'Two stars', 'rating' => 2]);

        $this->actingAs($this->editor())
            ->inertia(cp_route('reviews.index', ['status' => 'all', 'rating' => 2]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('reviews', 1)->where('reviews.0.title', 'Two stars')->etc());

        $this->actingAs($this->editor())
            ->inertia(cp_route('reviews.index', ['status' => 'approved', 'search' => 'five']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('reviews', 1)->where('reviews.0.title', 'Five stars')->etc());

        $this->actingAs($this->editor())
            ->inertia(cp_route('reviews.index', ['entry' => 'book', 'status' => 'all']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('reviews', 2)->where('entry.title', 'The Long Field')->etc());
    }

    #[Test]
    public function one_review_shows_everything(): void
    {
        config(['reviews.criteria' => [['handle' => 'value', 'label' => 'Value']]]);
        $review = $this->review(['ratings' => ['value' => 2], 'body' => 'Full text', 'email' => 'jo@example.com']);

        $this->actingAs($this->editor())
            ->inertia(cp_route('reviews.show', $review->id))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('reviews::Show')
                ->where('review.body', 'Full text')
                ->where('review.email', 'jo@example.com')
                ->where('review.ratings.0.label', 'Value')
                ->where('review.ratings.0.rating', 2)
                ->where('can.moderate', true)
                ->etc());

        $this->actingAs($this->editor())->get(cp_route('reviews.show', 'missing'))->assertNotFound();
    }

    #[Test]
    public function editors_approve_reject_and_mark_spam(): void
    {
        $review = $this->review(['status' => ReviewStatus::Pending]);
        $url = cp_route('reviews.status', $review->id);

        $this->actingAs($this->editor())->post($url, ['status' => 'approved'])->assertRedirect();
        $this->assertSame(ReviewStatus::Approved, $this->reviews()->find($review->id)->status);

        $this->actingAs($this->editor())->post($url, ['status' => 'rejected', 'note' => 'Not about the book'])->assertRedirect();
        $found = $this->reviews()->find($review->id);
        $this->assertSame(ReviewStatus::Rejected, $found->status);
        $this->assertSame('Not about the book', $found->note);

        $this->actingAs($this->editor())->post($url, ['status' => 'spam'])->assertRedirect();
        $this->assertSame(ReviewStatus::Spam, $this->reviews()->find($review->id)->status);

        $this->actingAs($this->editor())->post($url, ['status' => 'deleted'])->assertSessionHasErrors('status');
    }

    #[Test]
    public function viewing_alone_doesnt_allow_changes(): void
    {
        $review = $this->review(['status' => ReviewStatus::Pending]);
        $viewer = $this->editor(['view reviews']);

        foreach ([
            fn () => $this->post(cp_route('reviews.status', $review->id), ['status' => 'approved']),
            fn () => $this->post(cp_route('reviews.reply', $review->id), ['reply' => 'Hi']),
            fn () => $this->post(cp_route('reviews.note', $review->id), ['note' => 'Hi']),
            fn () => $this->delete(cp_route('reviews.destroy', $review->id)),
            fn () => $this->post(cp_route('reviews.bulk'), ['ids' => [$review->id], 'action' => 'approved']),
        ] as $attempt) {
            $this->actingAs($viewer);
            $this->assertContains($attempt()->getStatusCode(), [302, 403]);
        }

        $found = $this->reviews()->find($review->id);
        $this->assertSame(ReviewStatus::Pending, $found->status);
        $this->assertNull($found->reply);
        $this->assertNull($found->note);
    }

    #[Test]
    public function editors_reply_and_note(): void
    {
        $review = $this->review();

        $this->actingAs($this->editor())->post(cp_route('reviews.reply', $review->id), ['reply' => 'Thanks, Jo!'])->assertRedirect();
        $this->actingAs($this->editor())->post(cp_route('reviews.note', $review->id), ['note' => 'Regular customer'])->assertRedirect();

        $found = $this->reviews()->find($review->id);
        $this->assertSame('Thanks, Jo!', $found->reply);
        $this->assertSame('editor', $found->replyBy);
        $this->assertSame('Regular customer', $found->note);

        $this->actingAs($this->editor())->post(cp_route('reviews.reply', $review->id), ['reply' => str_repeat('x', 5001)])->assertSessionHasErrors('reply');
    }

    #[Test]
    public function editors_delete(): void
    {
        $review = $this->review();

        $this->actingAs($this->editor())->delete(cp_route('reviews.destroy', $review->id))->assertRedirect(cp_route('reviews.index'));

        $this->assertNull($this->reviews()->find($review->id));
    }

    #[Test]
    public function editors_act_on_several_at_once(): void
    {
        $a = $this->review(['status' => ReviewStatus::Pending]);
        $b = $this->review(['status' => ReviewStatus::Pending]);
        $c = $this->review(['status' => ReviewStatus::Pending]);

        $this->actingAs($this->editor())->post(cp_route('reviews.bulk'), ['ids' => [$a->id, $b->id, 'missing'], 'action' => 'approved'])->assertRedirect();

        $this->assertSame(ReviewStatus::Approved, $this->reviews()->find($a->id)->status);
        $this->assertSame(ReviewStatus::Approved, $this->reviews()->find($b->id)->status);
        $this->assertSame(ReviewStatus::Pending, $this->reviews()->find($c->id)->status);

        $this->actingAs($this->editor())->post(cp_route('reviews.bulk'), ['ids' => [$c->id], 'action' => 'delete'])->assertRedirect();
        $this->assertNull($this->reviews()->find($c->id));
    }

    #[Test]
    public function the_nav_item_and_permissions_are_registered(): void
    {
        $this->actingAs($this->editor())->get(cp_route('reviews.index'))->assertOk();

        foreach (['view reviews', 'moderate reviews', 'reply to reviews', 'delete reviews'] as $permission) {
            $this->assertNotNull(Permission::get($permission), "No [{$permission}] permission.");
        }
    }

    #[Test]
    public function the_widget_lists_waiting_reviews(): void
    {
        $this->review(['status' => ReviewStatus::Pending, 'title' => 'Waiting']);

        $this->actingAs($this->editor());
        $widget = (new PendingReviews)->component()->toArray();

        $this->assertSame('reviews-pending-widget', $widget['name']);
        $this->assertSame(1, $widget['props']['total']);
        $this->assertSame('Waiting', $widget['props']['reviews'][0]['title']);

        $this->actingAs($this->user('nobody@example.com', ['access cp']));
        $this->assertNull((new PendingReviews)->component());
    }

    #[Test]
    public function the_fieldtype_shows_the_entrys_numbers(): void
    {
        $this->review(['rating' => 4]);
        $this->review(['rating' => 2, 'status' => ReviewStatus::Pending]);

        $field = new Field('reviews', ['type' => 'reviews']);
        $field->setParent(Entry::find('book'));

        $preload = $field->fieldtype()->preload();

        $this->assertSame(1, $preload['count']);
        $this->assertEquals(4, $preload['average']);
        $this->assertSame(1, $preload['pending']);
        $this->assertNull($field->fieldtype()->process('anything'));
    }

    #[Test]
    public function a_review_s_page_shows_its_photos(): void
    {
        $review = $this->review(['photos' => [['disk' => 'local', 'path' => 'reviews/pending/x/a.jpg']], 'status' => ReviewStatus::Pending]);

        $this->actingAs($this->editor())
            ->get(cp_route('reviews.show', $review->id))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('review.photos', 1)
                ->where('review.photos.0.pending', true)
                ->etc());
    }
}
