<?php

namespace NineteenNinetyFour\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Reviews\Review;

/**
 * The site's public reply was added to a review, or changed.
 */
class ReviewReplied
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
