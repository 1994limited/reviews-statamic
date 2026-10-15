<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use NineteenNinetyFour\Reviews\Events\ReviewApproved;
use NineteenNinetyFour\Reviews\Events\ReviewDeleted;
use NineteenNinetyFour\Reviews\Events\ReviewRejected;
use NineteenNinetyFour\Reviews\Events\ReviewReplied;
use NineteenNinetyFour\Reviews\Notifications\NewReview;
use NineteenNinetyFour\Reviews\Notifications\ReviewApproved as ApprovedEmail;
use NineteenNinetyFour\Reviews\Notifications\ReviewNotification;
use NineteenNinetyFour\Reviews\Notifications\ReviewReplied as RepliedEmail;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Facades\Entry;
use Statamic\StaticCaching\Invalidator;

class ModerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->product();
    }

    #[Test]
    public function approving_rejecting_and_marking_spam(): void
    {
        Event::fake([ReviewApproved::class, ReviewRejected::class]);
        $review = $this->review(['status' => ReviewStatus::Pending]);

        $this->reviews()->approve($review);
        $this->assertSame(ReviewStatus::Approved, $this->reviews()->find($review->id)->status);
        $this->assertNotNull($this->reviews()->find($review->id)->approvedAt);

        $this->reviews()->reject($review, 'Off topic');
        $found = $this->reviews()->find($review->id);
        $this->assertSame(ReviewStatus::Rejected, $found->status);
        $this->assertSame('Off topic', $found->note);
        $this->assertNull($found->approvedAt);

        $this->reviews()->spam($review);
        $this->assertSame(ReviewStatus::Spam, $this->reviews()->find($review->id)->status);

        $this->reviews()->hold($review);
        $this->assertSame(ReviewStatus::Pending, $this->reviews()->find($review->id)->status);

        Event::assertDispatchedTimes(ReviewApproved::class, 1);
        Event::assertDispatchedTimes(ReviewRejected::class, 2);
    }

    #[Test]
    public function approving_twice_does_nothing_the_second_time(): void
    {
        Event::fake([ReviewApproved::class]);
        $review = $this->review(['status' => ReviewStatus::Pending]);

        $this->reviews()->approve($review);
        $this->reviews()->approve($review);

        Event::assertDispatchedTimes(ReviewApproved::class, 1);
    }

    #[Test]
    public function replies_are_saved_changed_and_removed(): void
    {
        Event::fake([ReviewReplied::class]);
        $review = $this->review();
        $editor = $this->editor();

        $this->reviews()->reply($review, '  Thank you!  ', $editor);
        $found = $this->reviews()->find($review->id);
        $this->assertSame('Thank you!', $found->reply);
        $this->assertSame((string) $editor->id(), $found->replyBy);
        $this->assertNotNull($found->repliedAt);

        // Saving the same reply again isn't a new reply.
        $this->reviews()->reply($found, 'Thank you!', $editor);

        $this->reviews()->reply($found, '');
        $this->assertNull($this->reviews()->find($review->id)->reply);

        Event::assertDispatchedTimes(ReviewReplied::class, 1);
    }

    #[Test]
    public function deleting(): void
    {
        Event::fake([ReviewDeleted::class]);
        $review = $this->review();

        $this->reviews()->delete($review);

        $this->assertNull($this->reviews()->find($review->id));
        Event::assertDispatched(ReviewDeleted::class);
    }

    #[Test]
    public function changes_clear_the_entrys_cached_pages(): void
    {
        $invalidated = [];
        $this->app->instance(Invalidator::class, new class($invalidated) implements Invalidator
        {
            public function __construct(public array &$items) {}

            public function invalidate($item)
            {
                $this->items[] = $item->id();
            }

            public function refresh($item) {}
        });

        $review = $this->review(['status' => ReviewStatus::Pending]);
        $invalidated = [];

        $this->reviews()->approve($review);

        $this->assertSame(['book'], $invalidated);
    }

    #[Test]
    public function an_entry_can_be_saved_after_its_reviews_change(): void
    {
        $review = $this->review(['status' => ReviewStatus::Pending]);
        $this->reviews()->approve($review);

        $entry = Entry::find('book');

        // Statamic saves an entry's descendants with it: the entry mustn't be one.
        $this->assertSame([], $entry->descendants()->map->id()->values()->all());
        $this->assertSame([], $entry->directDescendants()->map->id()->values()->all());

        $entry->set('title', 'The Long Field, revised')->saveQuietly();
        $this->assertSame('The Long Field, revised', Entry::find('book')->get('title'));
    }

    #[Test]
    public function editors_are_emailed_about_new_reviews(): void
    {
        Notification::fake();
        config(['reviews.notifications.to' => ['editor@example.com', 'not-an-email']]);

        $this->post('/!/reviews/submit', $this->submission(['title' => '[Click](https://evil.example)', 'body' => "**bold**\n- list"]));

        Notification::assertSentOnDemand(NewReview::class, function (NewReview $notification, array $channels, AnonymousNotifiable $notifiable) {
            $this->assertSame(['editor@example.com'], $notifiable->routes['mail']);

            $mail = $notification->toMail($notifiable);
            $text = implode("\n", array_map('strval', $mail->introLines));

            $this->assertSame('New 4-star review of The Long Field', $mail->subject);
            $this->assertStringContainsString('Sam Reader reviewed The Long Field', $text);
            $this->assertStringNotContainsString('[Click](https://evil.example)', $text);
            $this->assertStringContainsString('&#91;Click&#93;&#40;https://evil.example&#41;', $text);
            $this->assertStringNotContainsString('**bold**', $text);
            $this->assertSame('Moderate the review', $mail->actionText);
            $this->assertStringContainsString('/reviews/', $mail->actionUrl);

            return true;
        });
    }

    #[Test]
    public function nobody_is_emailed_when_no_address_is_set(): void
    {
        Notification::fake();

        $this->post('/!/reviews/submit', $this->submission());

        Notification::assertNothingSent();
    }

    #[Test]
    public function reviewers_can_be_told_their_review_is_live(): void
    {
        Notification::fake();
        $review = $this->review(['status' => ReviewStatus::Pending]);

        $this->reviews()->approve($review);
        Notification::assertNothingSent();

        config(['reviews.notifications.approved' => true]);
        $other = $this->review(['status' => ReviewStatus::Pending, 'email' => 'ana@example.com', 'name' => 'Ana Lee']);
        $this->reviews()->approve($other);

        Notification::assertSentOnDemand(ApprovedEmail::class, function ($notification, $channels, $notifiable) {
            $mail = $notification->toMail($notifiable);

            return $notifiable->routes['mail'] === 'ana@example.com'
                && str_contains((string) $mail->greeting, 'Ana')
                && str_contains($mail->actionUrl, '/products/the-long-field#review-');
        });
    }

    #[Test]
    public function reviews_published_as_they_are_written_send_no_approval_email(): void
    {
        Notification::fake();
        config(['reviews.notifications.approved' => true, 'reviews.auto_approve' => 'users']);

        $this->actingAs($this->user('sam@example.com', name: 'Sam'))->post('/!/reviews/submit', $this->submission());

        Notification::assertNotSentTo(new AnonymousNotifiable, ApprovedEmail::class);
        Notification::assertCount(0);
    }

    #[Test]
    public function reviewers_are_told_about_replies_to_approved_reviews(): void
    {
        Notification::fake();
        $pending = $this->review(['status' => ReviewStatus::Pending]);
        $this->reviews()->reply($pending, 'Thanks');
        Notification::assertNothingSent();

        $approved = $this->review(['email' => 'ana@example.com']);
        $this->reviews()->reply($approved, 'Thanks *so* much');

        Notification::assertSentOnDemand(RepliedEmail::class, function ($notification, $channels, $notifiable) {
            $text = implode("\n", array_map('strval', $notification->toMail($notifiable)->introLines));

            return $notifiable->routes['mail'] === 'ana@example.com' && str_contains($text, 'Thanks &#42;so&#42; much');
        });
    }

    #[Test]
    public function emails_are_queued(): void
    {
        $this->assertContains(ShouldQueue::class, class_implements(ReviewNotification::class));
    }

    #[Test]
    public function markdown_in_visitor_text_stays_text(): void
    {
        $plain = ReviewNotification::plain("# Heading\n- item\n1. first\n    code\n<b>x</b> | ~y~");

        $this->assertStringNotContainsString('# ', $plain);
        $this->assertStringNotContainsString("\n- ", $plain);
        $this->assertStringNotContainsString("\n1. ", $plain);
        $this->assertStringNotContainsString('    code', $plain);
        $this->assertStringNotContainsString('<b>', $plain);
    }
}
