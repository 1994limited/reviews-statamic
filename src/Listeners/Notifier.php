<?php

namespace NineteenNinetyFour\Reviews\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Notification;
use NineteenNinetyFour\Reviews\Events\ReviewApproved;
use NineteenNinetyFour\Reviews\Events\ReviewReplied;
use NineteenNinetyFour\Reviews\Events\ReviewSubmitted;
use NineteenNinetyFour\Reviews\Notifications\NewReview;
use NineteenNinetyFour\Reviews\Notifications\ReviewApproved as ApprovedEmail;
use NineteenNinetyFour\Reviews\Notifications\ReviewReplied as RepliedEmail;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\Settings;
use Statamic\Facades\Site;
use Throwable;

/**
 * The emails: new reviews to editors, and approvals and replies to
 * reviewers, as the settings say.
 */
class Notifier
{
    public function subscribe(Dispatcher $events): array
    {
        return [
            ReviewSubmitted::class => 'submitted',
            ReviewApproved::class => 'approved',
            ReviewReplied::class => 'replied',
        ];
    }

    public function submitted(ReviewSubmitted $event): void
    {
        $to = collect(Settings::list('notify_to'))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($to->isEmpty()) {
            return;
        }

        $review = $event->review;

        $this->send(fn () => Notification::route('mail', $to->all())->notify(new NewReview($this->details($review) + [
            'title' => $review->title,
            'body' => $review->body,
            'name' => $review->name,
            'rating' => $review->rating,
            'verified' => $review->verified,
            'approved' => $review->isApproved(),
            'cp_url' => $this->cpUrl($review),
        ])));
    }

    public function approved(ReviewApproved $event): void
    {
        $review = $event->review;

        if ($review->submitting || ! Settings::bool('notify_approved') || ! $this->reachable($review)) {
            return;
        }

        $this->send(fn () => Notification::route('mail', $review->email)->notify(new ApprovedEmail($this->details($review))));
    }

    public function replied(ReviewReplied $event): void
    {
        $review = $event->review;

        if (! Settings::bool('notify_replied') || ! $review->isApproved() || ! $this->reachable($review)) {
            return;
        }

        $this->send(fn () => Notification::route('mail', $review->email)->notify(new RepliedEmail($this->details($review) + [
            'reply' => $review->reply,
        ])));
    }

    protected function reachable(Review $review): bool
    {
        return $review->email !== null && filter_var($review->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function details(Review $review): array
    {
        $entry = app(Reviews::class)->entry($review->entry, $review->site);
        $url = $entry?->absoluteUrl();

        return [
            'review' => $review->id,
            'entry' => (string) ($entry?->get('title') ?? __('reviews::messages.reviews')),
            'url' => $url ? $url.'#review-'.$review->id : null,
            'site' => (Site::get((string) $review->site) ?? Site::default())->name(),
            'first_name' => explode(' ', trim($review->name))[0] ?: null,
        ];
    }

    protected function cpUrl(Review $review): ?string
    {
        try {
            return cp_route('reviews.show', $review->id);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * A failed email shouldn't lose the review it's about.
     */
    protected function send(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            report($e);
        }
    }
}
