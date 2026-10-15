<?php

namespace NineteenNinetyFour\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Reviews\Review;

/**
 * An editor approved a review, or it was approved as it was submitted.
 */
class ReviewApproved
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
