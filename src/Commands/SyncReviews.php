<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Reviews\Import\ImportReport;
use NineteenNinetyFour\Reviews\Sync\Source;
use NineteenNinetyFour\Reviews\Sync\Sync;
use Statamic\Console\RunsInPlease;
use Throwable;

/**
 * Syncs reviews from other platforms. Laravel's scheduler runs it hourly;
 * each source syncs once its own interval has passed.
 */
class SyncReviews extends Command
{
    use RunsInPlease;

    protected $signature = 'reviews:sync
        {source? : One source\'s ID, to sync it now}
        {--force : Sync every enabled source now, due or not}
        {--all : Fetch everything again, not just what\'s changed}';

    protected $description = 'Sync reviews from other platforms';

    public function handle(Sync $sync): int
    {
        if ($id = $this->argument('source')) {
            $source = $sync->find((string) $id);

            if (! $source) {
                $this->components->error("There's no source {$id}.");

                return self::FAILURE;
            }

            try {
                $this->report($source, $sync->run($source, (bool) $this->option('all')));
            } catch (Throwable $e) {
                $this->report($source, $e);

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $failed = $sync->runDue((bool) $this->option('force'), fn (Source $source, $result) => $this->report($source, $result));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    protected function report(Source $source, ImportReport|Throwable $result): void
    {
        if ($result instanceof Throwable) {
            $this->components->error("{$source->name}: {$result->getMessage()}");

            return;
        }

        $this->components->info("{$source->name}: {$result->added} added, {$result->updated} updated, ".count(array_unique($result->unmatched)).' matched nothing.');
    }
}
