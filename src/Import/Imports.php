<?php

namespace NineteenNinetyFour\Reviews\Import;

use Carbon\CarbonImmutable;
use NineteenNinetyFour\Reviews\Core\Import\Formats;
use NineteenNinetyFour\Reviews\Core\Import\ImportedReview;
use NineteenNinetyFour\Reviews\Core\Import\Importer;
use NineteenNinetyFour\Reviews\Core\Import\ImportResult;
use NineteenNinetyFour\Reviews\Core\Import\Values;
use NineteenNinetyFour\Reviews\Core\Sync\Sources;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\ReviewStatus;
use NineteenNinetyFour\Reviews\Settings;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Throwable;

/**
 * Adding reviews from another platform, from a file or a sync: matching
 * each to an entry, and adding it once.
 */
class Imports
{
    /** @var array<string, EntryContract|false> */
    protected array $matches = [];

    public function __construct(protected Reviews $reviews) {}

    public function read(string $contents, ?string $format = null): ImportResult
    {
        return (new Importer)->read($contents, $format);
    }

    /**
     * The formats a file can be read as, by key.
     *
     * @return array<string, string>
     */
    public static function formats(): array
    {
        $formats = [];

        foreach (Formats::all() as $format) {
            $formats[$format->key()] = $format->label();
        }

        return $formats;
    }

    /**
     * A platform's name, for where a review came from.
     */
    public static function sourceLabel(string $key): string
    {
        return Sources::find($key)?->label() ?? static::formats()[$key] ?? ucfirst($key);
    }

    /**
     * What an import would do, without adding anything.
     *
     * @param  iterable<ImportedReview>  $reviews
     * @return array{matched: int, existing: int, unmatched: array<int, string>, unpublished: int}
     */
    public function preview(iterable $reviews, ImportOptions $options): array
    {
        $preview = ['matched' => 0, 'existing' => 0, 'unmatched' => [], 'unpublished' => 0];

        foreach ($reviews as $imported) {
            if (! $imported->published && ! $options->includeUnpublished) {
                $preview['unpublished']++;
            } elseif ($this->existing($imported, $options)) {
                $preview['existing']++;
            } elseif ($this->match($imported, $options)) {
                $preview['matched']++;
            } else {
                $preview['unmatched'][] = $this->describe($imported);
            }
        }

        $preview['unmatched'] = array_values(array_unique($preview['unmatched']));

        return $preview;
    }

    /**
     * @param  iterable<ImportedReview>  $reviews
     */
    public function import(iterable $reviews, ImportOptions $options, ?ImportReport $report = null): ImportReport
    {
        $report ??= new ImportReport;
        $changed = [];

        foreach ($reviews as $imported) {
            try {
                $result = $this->add($imported, $options);
            } catch (Throwable $e) {
                report($e);
                $report->failed++;

                continue;
            }

            if ($result === null) {
                $report->unmatched[] = $this->describe($imported);

                continue;
            }

            [$state, $entry] = $result;

            match ($state) {
                'added' => $report->added++,
                'updated' => $report->updated++,
                'unchanged' => $report->unchanged++,
                default => null,
            };

            if ($state === 'added' || $state === 'updated') {
                $changed[$entry] = true;
            }
        }

        // Each entry's numbers and pages once, rather than per review.
        foreach (array_keys($changed) as $entry) {
            $this->reviews->changed($entry);
        }

        return $report;
    }

    /**
     * @return array{string, string}|null The state and the entry's ID, or null when nothing matched.
     */
    protected function add(ImportedReview $imported, ImportOptions $options): ?array
    {
        $existing = $this->existing($imported, $options);

        if ($existing) {
            if (! $options->update) {
                return ['unchanged', $existing->entry];
            }

            return [$this->update($existing, $imported, $options) ? 'updated' : 'unchanged', $existing->entry];
        }

        if (! $imported->published && ! $options->includeUnpublished) {
            return ['skipped', ''];
        }

        $entry = $this->match($imported, $options);

        if (! $entry) {
            return null;
        }

        $review = Review::make();
        $review->entry = $this->reviews->entryId($entry);
        $review->site = $options->site ?? $entry->locale();
        $review->fingerprint = $this->fingerprint($imported, $options);
        $review->source = $imported->source;
        $review->sourceId = $options->sourceId;
        $review->counted = $options->counted;
        $review->email = $imported->email;
        $review->createdAt = $imported->date ? CarbonImmutable::instance($imported->date) : CarbonImmutable::now();
        $this->fillPhotos($review, $imported);
        $this->fill($review, $imported, $options);

        $this->reviews->repository()->save($review);

        return ['added', $review->entry];
    }

    protected function existing(ImportedReview $imported, ImportOptions $options): ?Review
    {
        return $this->reviews->repository()->get(ReviewQuery::make()->fingerprint($this->fingerprint($imported, $options)), 1)->first();
    }

    /**
     * What tells a review apart: the platform's own, and for a sync, which
     * source it came from too, so two shops on the same platform are never
     * mixed up.
     */
    protected function fingerprint(ImportedReview $imported, ImportOptions $options): string
    {
        $fingerprint = $imported->fingerprint();

        return $options->sourceId ? hash('sha256', $fingerprint.'|source:'.$options->sourceId) : $fingerprint;
    }

    protected function update(Review $review, ImportedReview $imported, ImportOptions $options): bool
    {
        $snapshot = fn () => [
            $review->rating, $review->title, $review->body, $review->name, $review->email, $review->verified,
            $review->reply, $review->repliedAt?->toIso8601String(), $review->sourceUrl, $review->photos,
            $review->status, $review->counted,
        ];

        $before = $snapshot();
        $review->counted = $options->counted;
        $review->email = $imported->email;
        $this->fillPhotos($review, $imported);
        $this->fill($review, $imported, $options);

        if ($before === $snapshot()) {
            return false;
        }

        $review->touch();
        $this->reviews->repository()->save($review);

        return true;
    }

    protected function fill(Review $review, ImportedReview $imported, ImportOptions $options): void
    {
        $review->rating = $imported->rating;
        $review->title = $imported->title !== null ? mb_substr($imported->title, 0, 255) : null;
        $review->body = $imported->body;
        $review->name = mb_substr($imported->name, 0, 255);
        $review->verified = $imported->verified;
        $review->reply = $imported->reply;
        $review->repliedAt = $imported->reply !== null && $imported->replyDate ? CarbonImmutable::instance($imported->replyDate) : null;
        $url = Values::url($imported->url);
        $review->sourceUrl = $url !== null && mb_strlen($url) <= 500 ? $url : null;

        // A new review follows the platform. Later, a sync only takes down
        // what the platform took down: an editor's decision stands.
        if ($review->updatedAt === null) {
            $review->status = match (true) {
                ! $imported->published => ReviewStatus::Rejected,
                $options->status === 'pending' => ReviewStatus::Pending,
                default => ReviewStatus::Approved,
            };
            $review->approvedAt = $review->isApproved() ? $review->createdAt : null;
            $review->touch();
        } elseif (! $imported->published) {
            $review->status = ReviewStatus::Rejected;
            $review->approvedAt = null;
        }
    }

    /**
     * An imported review's photos are the platform's addresses.
     */
    protected function fillPhotos(Review $review, ImportedReview $imported): void
    {
        $own = array_values(array_filter($review->photos, fn ($photo) => ! isset($photo['url'])));
        $review->photos = [...$own, ...array_map(fn (string $url) => ['url' => $url], array_slice(Values::urls($imported->photos), 0, 10))];
    }

    /**
     * The entry an imported review is of: by ID (a Reviews export), SKU (an
     * entry's "sku" field), handle (its slug), page address, or exact title.
     * Reviews of the business go on the entry chosen for them.
     */
    public function match(ImportedReview $imported, ImportOptions $options): ?EntryContract
    {
        if ($imported->isAboutTheBusiness()) {
            return $options->businessEntry ? Entry::find($options->businessEntry) : null;
        }

        $key = json_encode($imported->product).'|'.$options->site;

        if (! array_key_exists($key, $this->matches)) {
            $this->matches[$key] = $this->find($imported->product, $options->site) ?? false;
        }

        return $this->matches[$key] ?: null;
    }

    /**
     * @param  array<string, string>  $product
     */
    protected function find(array $product, ?string $site): ?EntryContract
    {
        $collections = Settings::list('collections');
        $query = function () use ($collections, $site) {
            $query = Entry::query();

            if ($collections !== []) {
                $query->whereIn('collection', $collections);
            }

            if ($site !== null) {
                $query->where('site', $site);
            }

            return $query;
        };

        if (isset($product['id']) && ($entry = Entry::find($product['id']))) {
            return $entry;
        }

        if (isset($product['sku']) && ($entry = $query()->where('sku', $product['sku'])->first())) {
            return $entry;
        }

        if (isset($product['handle']) && ($entry = $query()->where('slug', $product['handle'])->first())) {
            return $entry;
        }

        if (isset($product['url'])) {
            $path = '/'.trim((string) parse_url($product['url'], PHP_URL_PATH), '/');
            $entry = Entry::findByUri($path, $site ?? Site::default()->handle())
                ?? $query()->where('slug', basename($path))->first();

            if ($entry) {
                return $entry;
            }
        }

        if (isset($product['name'])) {
            return $query()->where('title', $product['name'])->first();
        }

        return null;
    }

    protected function describe(ImportedReview $imported): string
    {
        if ($imported->product === []) {
            return __('reviews::messages.import.business');
        }

        $parts = [];

        foreach ($imported->product as $key => $value) {
            $parts[] = $key.' '.$value;
        }

        return implode(', ', $parts);
    }
}
