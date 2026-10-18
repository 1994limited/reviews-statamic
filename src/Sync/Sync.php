<?php

namespace NineteenNinetyFour\Reviews\Sync;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use NineteenNinetyFour\Reviews\Core\Http\Client;
use NineteenNinetyFour\Reviews\Core\Sync\SyncException;
use NineteenNinetyFour\Reviews\Http\LaravelClient;
use NineteenNinetyFour\Reviews\Import\ImportOptions;
use NineteenNinetyFour\Reviews\Import\ImportReport;
use NineteenNinetyFour\Reviews\Import\Imports;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use Throwable;

/**
 * Fetching reviews from other platforms on a schedule.
 */
class Sync
{
    /** How far back each sync looks again, for reviews changed late. */
    public const OVERLAP_DAYS = 2;

    /** The HTTP client fetches go through: Laravel's, unless set (for tests). */
    public ?Client $client = null;

    public function __construct(protected SourceStore $store, protected Imports $imports, protected Reviews $reviews) {}

    public function store(): SourceStore
    {
        return $this->store;
    }

    /**
     * @return array<string, Source>
     */
    public function all(): array
    {
        return $this->store->all();
    }

    public function find(string $id): ?Source
    {
        return $this->store->find($id);
    }

    /**
     * Save a source, after checking it. Returns its errors, by field.
     *
     * @return array<string, string>
     */
    public function save(Source $source): array
    {
        $errors = $this->validate($source);

        if ($errors !== []) {
            return $errors;
        }

        $before = $source->id !== '' ? $this->find($source->id) : null;
        $source->id = $source->id !== '' ? $source->id : strtolower((string) Str::ulid());
        $this->store->save($source);

        // Its reviews follow the source's choice on counting.
        if ($before && $before->counts() !== $source->counts()) {
            foreach ($this->reviews->repository()->get(ReviewQuery::make()->sourceId($source->id)) as $review) {
                $review->counted = $source->counts();
                $this->reviews->repository()->save($review);
                $this->reviews->changed($review->entry);
            }
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function validate(Source $source): array
    {
        $platform = $source->platform();
        $errors = [];

        if (! $platform) {
            return ['type' => __('reviews::messages.sync.unknown_platform')];
        }

        if (trim($source->name) === '') {
            $errors['name'] = __('validation.required', ['attribute' => __('reviews::messages.sync.name')]);
        }

        foreach ($platform->fields() as $key => $field) {
            if ($field['required'] && trim((string) ($source->settings[$key] ?? '')) === '') {
                $errors['settings.'.$key] = __('validation.required', ['attribute' => $field['label']]);
            }
        }

        if ($source->countInSummary && ! $platform->schemaAllowed()) {
            $errors['count_in_summary'] = __('reviews::messages.sync.cant_count', ['platform' => $platform->label()]);
        }

        return $errors;
    }

    /**
     * Remove a source. Its reviews stay, or go too.
     */
    public function delete(Source $source, bool $withReviews = false): void
    {
        if ($withReviews) {
            foreach ($this->reviews->repository()->get(ReviewQuery::make()->sourceId($source->id)) as $review) {
                $this->reviews->delete($review);
            }
        }

        $this->store->delete($source);
    }

    /**
     * Fetch a source's reviews now: everything changed since its last sync
     * (and a little before), or everything on the first.
     */
    public function run(Source $source, bool $all = false): ImportReport
    {
        $platform = $source->platform();

        if (! $platform) {
            throw new SyncException(__('reviews::messages.sync.unknown_platform'));
        }

        $since = ! $all && $source->lastSynced ? $source->lastSynced->subDays(self::OVERLAP_DAYS)->toDateTimeImmutable() : null;
        $started = CarbonImmutable::now();
        $report = new ImportReport;

        $options = new ImportOptions(
            counted: $source->counts(),
            businessEntry: $source->businessEntry,
            sourceId: $source->id,
            update: true,
        );

        try {
            $this->imports->import($platform->fetch($this->client ?? new LaravelClient(30), $source->resolvedSettings(), $since), $options, $report);
        } catch (Throwable $e) {
            $source->lastError = mb_substr($e->getMessage(), 0, 2000);
            $this->store->save($source);

            throw $e instanceof SyncException ? $e : new SyncException($e->getMessage(), 0, $e);
        }

        $source->lastSynced = $started;
        $source->lastError = null;
        $this->store->save($source);

        return $report;
    }

    /**
     * Every source that's due, synced. Returns how many failed.
     *
     * @param  callable(Source, ImportReport|Throwable): void|null  $each
     */
    public function runDue(bool $force = false, ?callable $each = null): int
    {
        $failed = 0;

        foreach ($this->all() as $source) {
            if (! $source->enabled || (! $force && ! $source->isDue())) {
                continue;
            }

            // One sync of a source at a time.
            $lock = Cache::lock('reviews:sync:'.$source->id, 3600);

            if (! $lock->get()) {
                continue;
            }

            try {
                $result = $this->run($source);
            } catch (Throwable $e) {
                $failed++;
                $result = $e;
            } finally {
                $lock->release();
            }

            $each && $each($source, $result);
        }

        return $failed;
    }
}
