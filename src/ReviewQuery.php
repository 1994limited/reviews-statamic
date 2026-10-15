<?php

namespace NineteenNinetyFour\Reviews;

/**
 * Which reviews to fetch, and in what order. Both drivers read it.
 */
class ReviewQuery
{
    /** @var array<int, string>|null */
    public ?array $entries = null;

    public ?string $site = null;

    /** @var array<int, ReviewStatus>|null */
    public ?array $statuses = null;

    /** @var array<int, int>|null */
    public ?array $ratings = null;

    public ?bool $verified = null;

    public ?bool $withPhotos = null;

    public ?string $user = null;

    public ?string $email = null;

    public ?string $search = null;

    /** The review to leave out (the visitor's own, say). */
    public ?string $except = null;

    public string $sort = 'newest';

    public const SORTS = ['newest', 'oldest', 'highest', 'lowest', 'helpful'];

    public static function make(): static
    {
        return new static;
    }

    public function entry(string|array|null $entries): static
    {
        $this->entries = $entries === null ? null : array_values(array_filter((array) $entries, fn ($id) => is_string($id) && $id !== ''));

        return $this;
    }

    public function site(?string $site): static
    {
        $this->site = $site;

        return $this;
    }

    public function status(ReviewStatus|string|array|null $statuses): static
    {
        $statuses = $statuses === null || is_array($statuses) ? $statuses : [$statuses];
        $this->statuses = $statuses === null ? null : array_values(array_map(fn ($status) => ReviewStatus::read($status), $statuses));

        return $this;
    }

    public function approved(): static
    {
        return $this->status(ReviewStatus::Approved);
    }

    public function rating(int|array|null $ratings): static
    {
        $this->ratings = $ratings === null ? null : array_values(array_filter(array_map(fn ($r) => Review::score($r), (array) $ratings)));

        return $this;
    }

    public function verified(?bool $verified = true): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function withPhotos(?bool $withPhotos = true): static
    {
        $this->withPhotos = $withPhotos;

        return $this;
    }

    public function user(?string $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function email(?string $email): static
    {
        $this->email = $email !== null ? mb_strtolower(trim($email)) : null;

        return $this;
    }

    public function search(?string $search): static
    {
        $search = $search !== null ? trim($search) : null;
        $this->search = $search === '' ? null : $search;

        return $this;
    }

    public function except(?string $id): static
    {
        $this->except = $id;

        return $this;
    }

    public function sort(?string $sort): static
    {
        $this->sort = in_array($sort, self::SORTS, true) ? $sort : 'newest';

        return $this;
    }

    /**
     * Whether a review matches, for drivers that filter in PHP.
     */
    public function matches(Review $review): bool
    {
        if ($this->entries !== null && ! in_array($review->entry, $this->entries, true)) {
            return false;
        }

        if ($this->site !== null && $review->site !== $this->site) {
            return false;
        }

        if ($this->statuses !== null && ! in_array($review->status, $this->statuses, true)) {
            return false;
        }

        if ($this->ratings !== null && ! in_array($review->rating, $this->ratings, true)) {
            return false;
        }

        if ($this->verified !== null && $review->verified !== $this->verified) {
            return false;
        }

        if ($this->withPhotos !== null && ($review->photos !== []) !== $this->withPhotos) {
            return false;
        }

        if ($this->user !== null && $review->user !== $this->user) {
            return false;
        }

        if ($this->email !== null && mb_strtolower((string) $review->email) !== $this->email) {
            return false;
        }

        if ($this->except !== null && $review->id === $this->except) {
            return false;
        }

        if ($this->search !== null) {
            $haystack = mb_strtolower(implode(' ', [$review->title, $review->body, $review->name, $review->email]));

            if (! str_contains($haystack, mb_strtolower($this->search))) {
                return false;
            }
        }

        return true;
    }
}
