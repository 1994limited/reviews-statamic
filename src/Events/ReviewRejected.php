<?php

namespace NineteenNinetyFour\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Reviews\Review;

/**
 * An editor rejected a review, or marked it as spam (check $review->status).
 */
class ReviewRejected
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
