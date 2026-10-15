<?php

namespace NineteenNinetyFour\Reviews\Events;

use Illuminate\Foundation\Events\Dispatchable;
use NineteenNinetyFour\Reviews\Review;

/**
 * A review has been written (or edited by its reviewer) and saved. It may be pending or, when auto-approved, already approved.
 */
class ReviewSubmitted
{
    use Dispatchable;

    public function __construct(public Review $review) {}
}
