<?php

namespace NineteenNinetyFour\Reviews;

/**
 * An entry's approved reviews in numbers: how many, the average rating, how
 * many of each star, and each criterion's average.
 */
class Summary
{
    /**
     * @param  array<int, int>  $stars  How many reviews gave each rating, 5 to 1.
     * @param  array<string, array{sum: int, count: int}>  $criteria
     */
    public function __construct(public array $stars, public array $criteria = []) {}

    public static function from(array $tally): static
    {
        return new static($tally['stars'] ?? [], $tally['criteria'] ?? []);
    }

    public function count(): int
    {
        return array_sum($this->stars);
    }

    /**
     * The exact average, or null with no reviews.
     */
    public function average(): ?float
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        $total = 0;

        foreach ($this->stars as $rating => $times) {
            $total += $rating * $times;
        }

        return $total / $count;
    }

    /**
     * The average of a criterion, or null when nobody rated it.
     */
    public function criterionAverage(string $handle): ?float
    {
        $count = $this->criteria[$handle]['count'] ?? 0;

        return $count > 0 ? $this->criteria[$handle]['sum'] / $count : null;
    }

    public function toArray(): array
    {
        return ['stars' => $this->stars, 'criteria' => $this->criteria];
    }
}
