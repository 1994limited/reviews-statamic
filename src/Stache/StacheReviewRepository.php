<?php

namespace NineteenNinetyFour\Reviews\Stache;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\LazyCollection;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;
use Statamic\Facades\Stache;

class StacheReviewRepository implements ReviewRepository
{
    protected function store(): ReviewsStore
    {
        return Stache::store('reviews');
    }

    public function find(string $id): ?Review
    {
        if (! preg_match('/^[a-z0-9]{1,64}$/', $id)) {
            return null;
        }

        return $this->store()->getItem($id);
    }

    public function save(Review $review): void
    {
        $this->locked($review->id, function () use ($review) {
            // Votes are only ever added by vote(): keep the ones on disk, so a
            // review loaded before someone voted doesn't put the old count back.
            if ($onDisk = $this->fromDisk($review)) {
                $review->voters = array_values(array_unique([...$onDisk->voters, ...$review->voters]));
                $review->helpful = max(count($review->voters), $onDisk->helpful);
            }

            $this->store()->save($review);
        });
    }

    public function delete(Review $review): void
    {
        $this->store()->delete($review);
    }

    public function paginate(ReviewQuery $query, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $reviews = $this->matching($query);

        return new LengthAwarePaginator($reviews->forPage($page, $perPage)->values(), $reviews->count(), $perPage, $page);
    }

    public function get(ReviewQuery $query, ?int $limit = null): Collection
    {
        $reviews = $this->matching($query);

        return $limit ? $reviews->take($limit)->values() : $reviews;
    }

    public function count(ReviewQuery $query): int
    {
        return $this->candidates($query)->filter(fn (Review $review) => $query->matches($review))->count();
    }

    public function statusCounts(): array
    {
        $counts = $this->store()->index('status')->items()->countBy()->all();

        return collect(ReviewStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $counts[$status->value] ?? 0])->all();
    }

    public function tally(string $entry, ?string $site = null): array
    {
        $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $criteria = [];

        $reviews = $this->candidates(ReviewQuery::make()->entry($entry))
            ->filter(fn (Review $review) => $review->isApproved() && $review->counted && ($site === null || $review->site === $site));

        foreach ($reviews as $review) {
            if (isset($stars[$review->rating])) {
                $stars[$review->rating]++;
            }

            foreach ($review->ratings as $handle => $score) {
                $criteria[$handle]['sum'] = ($criteria[$handle]['sum'] ?? 0) + $score;
                $criteria[$handle]['count'] = ($criteria[$handle]['count'] ?? 0) + 1;
            }
        }

        return ['stars' => $stars, 'criteria' => $criteria];
    }

    public function vote(Review $review, string $voter): bool
    {
        return $this->locked($review->id, function () use ($review, $voter) {
            // Read from the file itself, so votes made by other requests count.
            $fresh = $this->fromDisk($review) ?? $this->find($review->id) ?? $review;

            if (in_array($voter, $fresh->voters, true)) {
                return false;
            }

            $fresh->voters[] = $voter;
            $fresh->helpful = count($fresh->voters);
            $this->store()->save($fresh);

            $review->voters = $fresh->voters;
            $review->helpful = $fresh->helpful;

            return true;
        });
    }

    /**
     * The review as its file has it now, which may be newer than the
     * Stache's copy when another request has just written it.
     */
    protected function fromDisk(Review $review): ?Review
    {
        $path = $review->path();

        if (! is_file($path)) {
            return null;
        }

        clearstatcache(true, $path);

        return $this->store()->makeItemFromFile($path, (string) file_get_contents($path));
    }

    /**
     * Run a change to one review with no other request changing it at the
     * same time.
     */
    protected function locked(string $id, callable $callback): mixed
    {
        return Cache::lock('reviews:review:'.$id, 10)->block(5, $callback);
    }

    public function all(): LazyCollection
    {
        $keys = $this->store()->paths()->keys();

        return LazyCollection::make(function () use ($keys) {
            foreach ($keys->chunk(200) as $chunk) {
                foreach ($this->store()->getItems($chunk) as $review) {
                    if ($review) {
                        yield $review;
                    }
                }
            }
        });
    }

    /**
     * The reviews worth reading for a query: narrowed by the indexes first,
     * so only an entry's own reviews (or a user's) are read from the cache.
     *
     * @return Collection<int, Review>
     */
    protected function candidates(ReviewQuery $query): Collection
    {
        $keys = null;

        $narrow = function (string $index, array $values) use (&$keys) {
            $found = $this->store()->index($index)->items()->filter(fn ($value) => in_array($value, $values, true))->keys();
            $keys = $keys === null ? $found : $keys->intersect($found);
        };

        if ($query->entries !== null) {
            $narrow('entry', $query->entries);
        }

        if ($query->statuses !== null) {
            $narrow('status', array_map(fn ($status) => $status->value, $query->statuses));
        }

        if ($query->user !== null) {
            $narrow('user', [$query->user]);
        }

        if ($query->email !== null) {
            $narrow('email', [$query->email]);
        }

        $keys ??= $this->store()->paths()->keys();

        return $this->store()->getItems($keys->values())->filter()->values();
    }

    /**
     * @return Collection<int, Review>
     */
    protected function matching(ReviewQuery $query): Collection
    {
        $reviews = $this->candidates($query)->filter(fn (Review $review) => $query->matches($review));

        return static::sort($reviews, $query->sort);
    }

    /**
     * Sort in PHP as the database driver sorts in SQL: the newest first
     * among equals, then by ID.
     *
     * @param  Collection<int, Review>  $reviews
     * @return Collection<int, Review>
     */
    public static function sort(Collection $reviews, string $sort): Collection
    {
        $time = fn (Review $review) => $review->createdAt?->getTimestamp() ?? 0;

        $order = match ($sort) {
            'oldest' => [fn ($a, $b) => $time($a) <=> $time($b), fn ($a, $b) => $a->id <=> $b->id],
            'highest' => [fn ($a, $b) => $b->rating <=> $a->rating],
            'lowest' => [fn ($a, $b) => $a->rating <=> $b->rating],
            'helpful' => [fn ($a, $b) => $b->helpful <=> $a->helpful],
            default => [],
        };

        if ($sort !== 'oldest') {
            $order[] = fn ($a, $b) => $time($b) <=> $time($a);
            $order[] = fn ($a, $b) => $b->id <=> $a->id;
        }

        return $reviews->sortBy($order)->values();
    }
}
