<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use Statamic\Console\RunsInPlease;

/**
 * Everything kept about one person's reviews, as JSON: for answering a
 * request for their data.
 */
class ExportReviews extends Command
{
    use RunsInPlease;

    protected $signature = 'reviews:export
        {email : The reviewer\'s email address}
        {--output= : A file to write to, instead of the screen}';

    protected $description = 'Export one person\'s reviews as JSON';

    public function handle(Reviews $reviews): int
    {
        $email = (string) $this->argument('email');

        $found = $reviews->repository()->get(ReviewQuery::make()->email($email)->sort('oldest'))
            ->map(fn (Review $review) => collect($review->toArray())->except(['voters'])->put('entry_title', $reviews->entry($review->entry)?->get('title'))->all())
            ->values();

        $json = json_encode(['email' => $email, 'exported_at' => now()->toIso8601String(), 'reviews' => $found], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($path = $this->option('output')) {
            file_put_contents($path, $json);
            $this->components->info("Wrote {$found->count()} reviews to {$path}.");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
