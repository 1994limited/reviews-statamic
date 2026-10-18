<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Reviews\Core\Import\Csv;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\Reviews;
use Statamic\Console\RunsInPlease;

/**
 * Every review, as a CSV that Reviews (for Statamic or Craft) imports: for
 * moving between sites or platforms.
 */
class ExportAllReviews extends Command
{
    use RunsInPlease;

    public const COLUMNS = ['id', 'entry', 'entry_title', 'rating', 'title', 'body', 'name', 'email', 'status', 'verified', 'reply', 'replied_at', 'created_at', 'source', 'source_url'];

    protected $signature = 'reviews:export-all {file? : Where to write the CSV (the screen without one)}';

    protected $description = 'Export every review as CSV';

    public function handle(Reviews $reviews): int
    {
        $file = $this->argument('file') ?: 'php://stdout';
        $out = fopen($file, 'w');

        if ($out === false) {
            $this->components->error("Can't write {$file}.");

            return self::FAILURE;
        }

        fputcsv($out, self::COLUMNS, ',', '"', '');
        $count = 0;

        foreach ($reviews->repository()->all() as $review) {
            /** @var Review $review */
            // Every cell is safe to open in a spreadsheet: nothing a reviewer
            // wrote can run as a formula.
            fputcsv($out, array_map([Csv::class, 'cell'], [
                $review->id,
                $review->entry,
                $reviews->entry($review->entry)?->get('title'),
                $review->rating,
                $review->title,
                $review->body,
                $review->name,
                $review->email,
                $review->status->value,
                $review->verified ? '1' : '0',
                $review->reply,
                $review->repliedAt?->toIso8601String(),
                $review->createdAt?->toIso8601String(),
                $review->source,
                $review->sourceUrl,
            ]), ',', '"', '');
            $count++;
        }

        fclose($out);

        if ($file !== 'php://stdout') {
            $this->components->info("Exported {$count} reviews to {$file}.");
        }

        return self::SUCCESS;
    }
}
