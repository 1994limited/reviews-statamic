<?php

namespace NineteenNinetyFour\Reviews;

use NineteenNinetyFour\Reviews\Core\Schema\SchemaBuilder;
use NineteenNinetyFour\Reviews\Core\Schema\SchemaReview;
use NineteenNinetyFour\Reviews\Core\Schema\Subject;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use Statamic\Contracts\Entries\Entry;

/**
 * Review schema (schema.org JSON-LD) for an entry: its average rating and
 * its latest reviews, for search engines' star ratings.
 */
class Schema
{
    public function __construct(protected Reviews $reviews, protected Cargo $cargo) {}

    /**
     * The entry's schema type: set per collection in config, "Product" for
     * Cargo's products, otherwise "Thing".
     */
    public function type(Entry $entry): string
    {
        $types = (array) config('reviews.schema.types', []);
        $type = $types[$entry->collectionHandle()] ?? null;

        if (is_string($type) && preg_match('/^[A-Za-z]{2,60}$/', $type)) {
            return $type;
        }

        return $this->cargo->isProduct($entry) ? 'Product' : 'Thing';
    }

    /**
     * The JSON-LD for an entry, or null when it has no approved reviews
     * (an empty rating isn't allowed).
     *
     * @return array<string, mixed>|null
     */
    public function data(Entry $entry, ?string $type = null, ?string $id = null, ?int $reviews = null): ?array
    {
        $entryId = $this->reviews->entryId($entry);
        $site = $this->reviews->siteScope();
        $summary = $this->reviews->summary($entryId, $site);

        if ($summary->count() === 0) {
            return null;
        }

        $type = $type !== null && preg_match('/^[A-Za-z]{2,60}$/', $type) ? $type : $this->type($entry);
        $limit = $reviews ?? max(0, (int) config('reviews.schema.reviews', 5));

        $latest = $limit > 0
            ? $this->reviews->repository()
                ->get(ReviewQuery::make()->entry($entryId)->site($site)->approved()->counted()->sort('newest'), $limit)
                ->map(fn (Review $review) => new SchemaReview(
                    rating: $review->rating,
                    author: Reviews::displayName($review->name),
                    date: $review->createdAt?->toDateString(),
                    title: $review->title,
                    body: $review->body,
                ))
                ->values()
                ->all()
            : [];

        $subject = new Subject(
            name: (string) ($entry->get('title') ?? $entry->value('title') ?? ''),
            type: $type,
            url: $entry->absoluteUrl() ?: null,
            id: $id !== null && $id !== '' ? $id : null,
        );

        return SchemaBuilder::build($subject, $summary->core(), $latest);
    }

    /**
     * Just the rating and reviews, to add to an entry's schema printed by
     * something else (an SEO addon, say). Null with no approved review.
     *
     * @return array{aggregateRating: array<string, mixed>, review?: array<int, array<string, mixed>>}|null
     */
    public function parts(Entry $entry, ?int $reviews = null): ?array
    {
        $data = $this->data($entry, null, null, $reviews);

        return $data === null ? null : array_intersect_key($data, array_flip(['aggregateRating', 'review']));
    }

    /**
     * JSON safe to print anywhere in a page, including inside a script tag:
     * nothing in a review can close the tag.
     */
    public static function encode(array $data, bool $pretty = false): string
    {
        return SchemaBuilder::encode($data, $pretty);
    }

    /**
     * The JSON-LD in a script tag, safe to print in the page: nothing in a
     * review can close the tag.
     */
    public function script(Entry $entry, ?string $type = null, ?string $id = null, ?int $reviews = null): string
    {
        $data = $this->data($entry, $type, $id, $reviews);

        if ($data === null) {
            return '';
        }

        return '<script type="application/ld+json">'.static::encode($data, pretty: true).'</script>';
    }
}
