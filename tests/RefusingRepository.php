<?php

namespace NineteenNinetyFour\Reviews\Tests;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use RuntimeException;

/**
 * A repository that reads as the real one does, but can't save.
 */
class RefusingRepository implements ReviewRepository
{
    public function __construct(protected ReviewRepository $inner) {}

    public function find(string $id): ?Review
    {
        return $this->inner->find($id);
    }

    public function save(Review $review): void
    {
        throw new RuntimeException('The database is having a moment.');
    }

    public function delete(Review $review): void
    {
        $this->inner->delete($review);
    }

    public function paginate(ReviewQuery $query, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return $this->inner->paginate($query, $perPage, $page);
    }

    public function get(ReviewQuery $query, ?int $limit = null): Collection
    {
        return $this->inner->get($query, $limit);
    }

    public function count(ReviewQuery $query): int
    {
        return $this->inner->count($query);
    }

    public function statusCounts(): array
    {
        return $this->inner->statusCounts();
    }

    public function tally(string $entry, ?string $site = null): array
    {
        return $this->inner->tally($entry, $site);
    }

    public function vote(Review $review, string $voter): bool
    {
        return $this->inner->vote($review, $voter);
    }

    public function all(): LazyCollection
    {
        return $this->inner->all();
    }
}
