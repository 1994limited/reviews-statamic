<?php

namespace NineteenNinetyFour\Reviews\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Cache;
use NineteenNinetyFour\Reviews\Reviews;

/**
 * Clear an entry's cached pages once, for every "helpful" vote on its
 * reviews in the last few minutes.
 */
class RefreshAfterVotes implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public string $entry, public string $token = '') {}

    public function handle(Reviews $reviews): void
    {
        // Votes from now on start a new wait: unless another job's already
        // waiting.
        if (Cache::get(Reviews::voteRefreshKey($this->entry)) === $this->token) {
            Cache::forget(Reviews::voteRefreshKey($this->entry));
        }

        $reviews->changed($this->entry, summary: false);
    }
}
