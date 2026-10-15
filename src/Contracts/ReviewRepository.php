<?php

namespace NineteenNinetyFour\Reviews\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;

/**
 * Where reviews are kept. The file and database drivers implement it.
 */
interface ReviewRepository
{
    public function find(string $id): ?Review;

    /**
     * Store the review as it is, dates included.
     */
    public function save(Review $review): void;

    public function delete(Review $review): void;

    public function paginate(ReviewQuery $query, int $perPage = 10, int $page = 1): LengthAwarePaginator;

    /**
     * @return Collection<int, Review>
     */
    public function get(ReviewQuery $query, ?int $limit = null): Collection;

    public function count(ReviewQuery $query): int;

    /**
     * How many reviews are in each status.
     *
     * @return array<string, int>
     */
    public function statusCounts(): array;

    /**
     * The approved reviews' ratings for an entry: how many of each star,
     * and the criteria's totals.
     *
     * @return array{stars: array<int, int>, criteria: array<string, array{sum: int, count: int}>}
     */
    public function tally(string $entry, ?string $site = null): array;

    /**
     * Record a "helpful" vote. False if this voter has voted on it before.
     */
    public function vote(Review $review, string $voter): bool;

    /**
     * Every review, voters included, for moving between drivers and
     * exporting.
     *
     * @return LazyCollection<int, Review>
     */
    public function all(): LazyCollection;
}
