<?php

namespace NineteenNinetyFour\Reviews;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Events\ReviewApproved;
use NineteenNinetyFour\Reviews\Events\ReviewDeleted;
use NineteenNinetyFour\Reviews\Events\ReviewRejected;
use NineteenNinetyFour\Reviews\Events\ReviewReplied;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\StaticCaching\Invalidator;
use Throwable;

/**
 * Reviews, from finding them to moderating them. Every change goes through
 * here, so the entry's numbers and its cached pages stay up to date.
 */
class Reviews
{
    public function __construct(protected ReviewRepository $repository) {}

    public function repository(): ReviewRepository
    {
        return $this->repository;
    }

    public function find(string $id): ?Review
    {
        return $this->repository->find($id);
    }

    public function query(): ReviewQuery
    {
        return ReviewQuery::make();
    }

    /*
    |--------------------------------------------------------------------------
    | What can be reviewed
    |--------------------------------------------------------------------------
    */

    /**
     * Whether visitors may review an entry: one in a collection that takes
     * reviews, published and live (not scheduled or expired), with a page
     * on the site.
     */
    public function reviewable(mixed $entry): bool
    {
        if (! $entry instanceof EntryContract) {
            return false;
        }

        $collections = Settings::list('collections');

        if ($collections !== [] && ! in_array($entry->collectionHandle(), $collections, true)) {
            return false;
        }

        if ($entry->status() !== 'published' || $entry->private()) {
            return false;
        }

        return $entry->url() !== null;
    }

    /**
     * The ID reviews are kept under: the origin entry's, so a translated
     * copy of a page shows the same reviews.
     */
    public function entryId(EntryContract $entry): string
    {
        try {
            return (string) ($entry->hasOrigin() ? $entry->root()->id() : $entry->id());
        } catch (Throwable) {
            return (string) $entry->id();
        }
    }

    /**
     * The entry with an ID, as the current site has it if it's localised.
     */
    public function entry(?string $id, ?string $site = null): ?EntryContract
    {
        if ($id === null || $id === '') {
            return null;
        }

        $entry = Entry::find($id);

        if ($entry && $site && $entry->locale() !== $site) {
            return $entry->in($site) ?? $entry;
        }

        return $entry;
    }

    /**
     * The criteria reviews of an entry are rated on, besides the overall
     * rating: each a handle and a label.
     *
     * @return array<int, array{handle: string, label: string}>
     */
    public function criteria(?EntryContract $entry = null): array
    {
        $criteria = [];

        foreach ((array) Settings::get('criteria', []) as $criterion) {
            if (! is_array($criterion)) {
                continue;
            }

            $handle = is_string($criterion['handle'] ?? null) ? trim($criterion['handle']) : '';
            $label = is_string($criterion['label'] ?? null) ? trim($criterion['label']) : '';
            $collections = array_filter((array) ($criterion['collections'] ?? []), 'is_string');

            if (! preg_match('/^[a-z][a-z0-9_]{0,40}$/', $handle) || isset($criteria[$handle])) {
                continue;
            }

            if ($entry && $collections && ! in_array($entry->collectionHandle(), $collections, true)) {
                continue;
            }

            $criteria[$handle] = ['handle' => $handle, 'label' => $label !== '' ? $label : ucfirst(str_replace('_', ' ', $handle))];
        }

        return array_values($criteria);
    }

    /*
    |--------------------------------------------------------------------------
    | Numbers
    |--------------------------------------------------------------------------
    */

    /**
     * An entry's approved reviews in numbers. Cached until one of its
     * reviews changes, so a busy page reads it once.
     */
    public function summary(string $entry, ?string $site = null): Summary
    {
        $key = $this->summaryKey($entry);
        $part = $site ?? '*';

        $cached = Cache::get($key);
        $cached = is_array($cached) ? $cached : [];

        if (! isset($cached[$part]) || ! is_array($cached[$part])) {
            $cached[$part] = $this->repository->tally($entry, $site);
            Cache::forever($key, $cached);
        }

        return Summary::from($cached[$part]);
    }

    public function forgetSummary(string $entry): void
    {
        Cache::forget($this->summaryKey($entry));
    }

    protected function summaryKey(string $entry): string
    {
        return 'reviews:summary:'.sha1($entry);
    }

    /*
    |--------------------------------------------------------------------------
    | People
    |--------------------------------------------------------------------------
    */

    /**
     * The review someone already wrote of an entry, if any: by their user
     * account when signed in, or else their email.
     */
    public function existing(string $entry, ?User $user, ?string $email = null): ?Review
    {
        $query = ReviewQuery::make()->entry($entry);

        if ($user) {
            $query->user((string) $user->id());
        } elseif ($email) {
            $query->email($email);
        } else {
            return null;
        }

        return $this->repository->get($query, 1)->first();
    }

    /**
     * How a reviewer's name is shown on the site.
     */
    public static function displayName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '' || Settings::string('name_display', 'short') !== 'short') {
            return $name;
        }

        $parts = explode(' ', $name);

        if (count($parts) === 1) {
            return $name;
        }

        $last = mb_substr((string) end($parts), 0, 1);

        return $parts[0].' '.mb_strtoupper($last).'.';
    }

    /*
    |--------------------------------------------------------------------------
    | Saving and moderating
    |--------------------------------------------------------------------------
    */

    /**
     * Save a review and bring everything that shows it up to date.
     */
    public function save(Review $review, ?string $previousEntry = null): void
    {
        $review->touch();
        $this->repository->save($review);
        $this->changed($review->entry);

        if ($previousEntry !== null && $previousEntry !== $review->entry) {
            $this->changed($previousEntry);
        }
    }

    public function approve(Review $review): void
    {
        if ($review->isApproved()) {
            return;
        }

        app(Photos::class)->publish($review);

        $review->status = ReviewStatus::Approved;
        $review->approvedAt = CarbonImmutable::now()->startOfSecond();
        $this->save($review);

        ReviewApproved::dispatch($review);
    }

    public function reject(Review $review, ?string $note = null, ReviewStatus $status = ReviewStatus::Rejected): void
    {
        $status = $status === ReviewStatus::Spam ? ReviewStatus::Spam : ReviewStatus::Rejected;

        app(Photos::class)->unpublish($review);

        $review->status = $status;
        $review->approvedAt = null;

        if ($note !== null) {
            $review->note = trim($note) === '' ? null : trim($note);
        }

        $this->save($review);

        ReviewRejected::dispatch($review);
    }

    public function spam(Review $review): void
    {
        $this->reject($review, null, ReviewStatus::Spam);
    }

    /**
     * Back to waiting for an editor, as when a reviewer changes their review.
     */
    public function hold(Review $review): void
    {
        app(Photos::class)->unpublish($review);

        $review->status = ReviewStatus::Pending;
        $review->approvedAt = null;
        $this->save($review);
    }

    /**
     * Set (or, when empty, remove) the site's public reply.
     */
    public function reply(Review $review, ?string $reply, ?User $by = null): void
    {
        $reply = $reply !== null ? trim($reply) : null;
        $reply = $reply === '' ? null : $reply;

        $replied = $reply !== null && $reply !== $review->reply;

        $review->reply = $reply;
        $review->replyBy = $reply !== null ? ($by ? (string) $by->id() : $review->replyBy) : null;
        $review->repliedAt = $reply !== null ? ($replied ? CarbonImmutable::now()->startOfSecond() : $review->repliedAt) : null;

        $this->save($review);

        if ($replied) {
            ReviewReplied::dispatch($review);
        }
    }

    public function delete(Review $review): void
    {
        app(Photos::class)->delete($review);

        $this->repository->delete($review);
        $this->changed($review->entry);

        ReviewDeleted::dispatch($review);
    }

    /**
     * Record a "helpful" vote. False when the voter has voted already.
     */
    public function vote(Review $review, string $voter): bool
    {
        if (! $review->isApproved()) {
            return false;
        }

        $voted = $this->repository->vote($review, $voter);

        if ($voted) {
            $this->changed($review->entry, summary: false);
        }

        return $voted;
    }

    /**
     * Take a person's name and email off their reviews, keeping what they
     * wrote, or delete the reviews. Returns how many.
     */
    public function forget(?string $email, ?string $user = null, bool $delete = false): int
    {
        $reviews = collect();

        if ($email) {
            $reviews = $reviews->merge($this->repository->get(ReviewQuery::make()->email($email)));
        }

        if ($user) {
            $reviews = $reviews->merge($this->repository->get(ReviewQuery::make()->user($user)));
        }

        $reviews = $reviews->unique(fn (Review $review) => $review->id);

        foreach ($reviews as $review) {
            if ($delete) {
                $this->delete($review);

                continue;
            }

            $review->name = (string) __('reviews::messages.anonymous');
            $review->email = null;
            $review->user = null;
            $this->save($review);
        }

        return $reviews->count();
    }

    /**
     * An entry's reviews changed: its numbers and its cached pages are
     * out of date.
     */
    public function changed(string $entry, bool $summary = true): void
    {
        if ($summary) {
            $this->forgetSummary($entry);
        }

        $item = Entry::find($entry);

        if (! $item) {
            return;
        }

        try {
            $invalidator = app(Invalidator::class);

            // A new collection: descendants() is cached by Statamic, and
            // adding to it would make the entry its own descendant.
            foreach (collect([$item])->merge($item->descendants()->values())->unique(fn ($e) => $e->id()) as $localised) {
                $invalidator->invalidate($localised);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
