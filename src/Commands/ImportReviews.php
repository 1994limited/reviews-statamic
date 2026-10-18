<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Reviews\Import\ImportOptions;
use NineteenNinetyFour\Reviews\Import\Imports;
use Statamic\Console\RunsInPlease;

/**
 * Imports reviews from a CSV or JSON file another platform exported.
 */
class ImportReviews extends Command
{
    use RunsInPlease;

    protected $signature = 'reviews:import
        {file : The CSV or JSON file}
        {--format= : yotpo, judgeme, reviewsio, shopify, trustpilot, woocommerce, reviews or generic, if it can\'t be worked out}
        {--counted : Count them as this site\'s own reviews (only for reviews your customers wrote for you)}
        {--pending : Hold every review for an editor}
        {--include-unpublished : Add reviews the platform wasn\'t showing, as rejected}
        {--business= : The ID of the entry reviews of the business go on}
        {--site= : The handle of the site they were written on}
        {--dry-run : Only say what would be imported}';

    protected $description = 'Import reviews from another platform\'s export';

    public function handle(Imports $imports): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->components->error("Can't read {$file}.");

            return self::FAILURE;
        }

        $result = $imports->read((string) file_get_contents($file), $this->option('format') ?: null);

        if (! $result->format) {
            $this->components->error($result->skipped[0]['reason'] ?? 'The file couldn’t be read.');

            return self::FAILURE;
        }

        $this->components->info("Read as {$result->format->label()}: ".count($result->reviews).' reviews, '.count($result->skipped).' rows skipped.');

        $options = new ImportOptions(
            counted: (bool) $this->option('counted'),
            status: $this->option('pending') ? 'pending' : 'keep',
            includeUnpublished: (bool) $this->option('include-unpublished'),
            businessEntry: $this->option('business') ?: null,
            site: $this->option('site') ?: null,
        );

        if ($this->option('dry-run')) {
            $preview = $imports->preview($result->reviews, $options);
            $this->line("Would add {$preview['matched']}; {$preview['existing']} here already; {$preview['unpublished']} not shown on the platform.");
            $this->unmatched($preview['unmatched']);

            return self::SUCCESS;
        }

        $report = $imports->import($result->reviews, $options);
        $this->components->info("Added {$report->added}; {$report->unchanged} here already; {$report->failed} failed.");
        $this->unmatched(array_values(array_unique($report->unmatched)));

        return $report->failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $unmatched
     */
    protected function unmatched(array $unmatched): void
    {
        if ($unmatched === []) {
            return;
        }

        $this->components->warn(count($unmatched).' matched nothing on this site:');

        foreach (array_slice($unmatched, 0, 30) as $item) {
            $this->line("  {$item}");
        }
    }
}
