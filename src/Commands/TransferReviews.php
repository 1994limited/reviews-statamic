<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\ServiceProvider;
use Statamic\Console\RunsInPlease;

/**
 * Copy every review from one driver to the other, as when moving a site
 * from flat files to the database (or back). Reviews keep their IDs and
 * their "helpful" votes.
 */
class TransferReviews extends Command
{
    use RunsInPlease;

    protected $signature = 'reviews:transfer
        {--from= : file or database}
        {--to= : file or database}
        {--force : Don\'t ask before copying}';

    protected $description = 'Copy reviews from one storage driver to another';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        foreach ([$from, $to] as $driver) {
            if (! in_array($driver, ['file', 'database'], true)) {
                $this->components->error('Pass --from and --to as "file" or "database".');

                return self::FAILURE;
            }
        }

        if ($from === $to) {
            $this->components->error('--from and --to are the same driver.');

            return self::FAILURE;
        }

        if (! $this->tablesExist()) {
            $this->components->error('The reviews tables don\'t exist yet. Publish the migration and run it first:');
            $this->line('  php please vendor:publish --tag=reviews-migrations');
            $this->line('  php artisan migrate');

            return self::FAILURE;
        }

        $source = app(ServiceProvider::repositoryFor($from));
        $target = app(ServiceProvider::repositoryFor($to));
        $count = $source->all()->count();

        if ($count === 0) {
            $this->components->info("There are no reviews in the {$from} driver.");

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm("Copy {$count} reviews from {$from} to {$to}? Reviews with the same ID there are replaced.", true)) {
            return self::FAILURE;
        }

        $entries = [];
        $bar = $this->output->createProgressBar($count);

        foreach ($source->all() as $review) {
            $target->save($review);
            $entries[$review->entry] = true;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        foreach (array_keys($entries) as $entry) {
            app(Reviews::class)->forgetSummary($entry);
        }

        $this->components->info("Copied {$count} reviews to the {$to} driver. Set REVIEWS_DRIVER={$to} to use them.");

        return self::SUCCESS;
    }

    /**
     * The database driver's tables are only migrated with the site's own
     * migrations once the driver is database, or the migration has been
     * published. Before that, there's nowhere to copy to or from.
     */
    private function tablesExist(): bool
    {
        $schema = Schema::connection(config('reviews.drivers.database.connection') ?: null);
        $tables = config('reviews.drivers.database.tables', []);

        return $schema->hasTable($tables['reviews'] ?? 'reviews')
            && $schema->hasTable($tables['votes'] ?? 'review_votes')
            && $schema->hasTable($tables['requests'] ?? 'review_requests');
    }
}
