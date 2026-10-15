<?php

namespace NineteenNinetyFour\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Reviews\Review;

/**
 * A review was deleted, by an editor or its reviewer.
 */
class ReviewDeleted
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
