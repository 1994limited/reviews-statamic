<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Support\Facades\URL;

/**
 * The signed links in review request emails.
 */
class Links
{
    /** How long a review request's links work, in days. */
    public const DAYS = 90;

    /**
     * Opens the product's page as the customer of an order, so their review
     * counts as a verified purchase.
     */
    public static function order(string $order, string $product): string
    {
        return URL::temporarySignedRoute('statamic.reviews.order', now()->addDays(self::DAYS), ['order' => $order, 'product' => $product]);
    }

    /**
     * Stops review requests to an address. Doesn't expire.
     */
    public static function optOut(string $email): string
    {
        return URL::signedRoute('statamic.reviews.opt-out', ['email' => $email]);
    }
}
