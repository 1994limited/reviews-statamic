<?php

namespace NineteenNinetyFour\Reviews\Commands;

use Illuminate\Console\Command;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\Reviews;
use Statamic\Console\RunsInPlease;

/**
 * Take a person's name and email off their reviews (or delete them), for
 * a request to be forgotten. They're sent no more review requests either.
 */
class ForgetReviews extends Command
{
    use RunsInPlease;

    protected $signature = 'reviews:forget
        {email : The reviewer\'s email address}
        {--delete : Delete the reviews, rather than keeping them without the name and email}
        {--force : Don\'t ask first}';

    protected $description = 'Anonymise or delete one person\'s reviews';

    public function handle(Reviews $reviews, RequestLog $log): int
    {
        $email = (string) $this->argument('email');
        $delete = (bool) $this->option('delete');
        $verb = $delete ? 'Delete' : 'Anonymise';

        if (! $this->option('force') && ! $this->confirm("{$verb} every review by {$email}?", false)) {
            return self::FAILURE;
        }

        $count = $reviews->forget($email, null, $delete);
        $log->optOut($email);

        $this->components->info(($delete ? 'Deleted' : 'Anonymised')." {$count} ".($count === 1 ? 'review' : 'reviews').'.');

        return self::SUCCESS;
    }
}
