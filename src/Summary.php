<?php

namespace NineteenNinetyFour\Reviews;

use NineteenNinetyFour\Reviews\Core\Rating\Summary as Core;

/**
 * An entry's approved reviews in numbers: how many, the average rating, how
 * many of each star, and each criterion's average. The sums are reviews-core's,
 * shared with Reviews for Craft.
 */
class Summary
{
    /** @var array<int, int> How many reviews gave each rating, 5 to 1. */
    public array $stars;

    /** @var array<string, array{sum: int, count: int}> */
    public array $criteria;

    protected Core $core;

    /**
     * @param  array<int, int>  $stars  How many reviews gave each rating, 5 to 1.
     * @param  array<string, array{sum: int, count: int}>  $criteria
     */
    public function __construct(array $stars, array $criteria = [])
    {
        $this->core = new Core($stars, $criteria);
        $this->stars = $this->core->stars;
        $this->criteria = $this->core->criteria;
    }

    public static function from(array $tally): static
    {
        return new static($tally['stars'] ?? [], $tally['criteria'] ?? []);
    }

    public function count(): int
    {
        return $this->core->count();
    }

    /**
     * The exact average, or null with no reviews.
     */
    public function average(): ?float
    {
        return $this->core->average();
    }

    /**
     * The average of a criterion, or null when nobody rated it.
     */
    public function criterionAverage(string $handle): ?float
    {
        return $this->core->criterionAverage($handle);
    }

    /**
     * The same numbers as reviews-core has them.
     */
    public function core(): Core
    {
        return $this->core;
    }

    public function toArray(): array
    {
        return ['stars' => $this->stars, 'criteria' => $this->criteria];
    }
}
