<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
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
     * Stops review requests to an address. Doesn't expire. The address is
     * encrypted, so it stays out of access logs and analytics.
     */
    public static function optOut(string $email): string
    {
        return URL::signedRoute('statamic.reviews.opt-out', ['to' => Crypt::encryptString(mb_strtolower(trim($email)))]);
    }

    /**
     * The address an opt-out link is for: encrypted from 1.2.2, or plain in
     * links sent before.
     */
    public static function optOutEmail(Request $request): string
    {
        $to = $request->query('to');

        if (is_string($to) && $to !== '') {
            try {
                return Crypt::decryptString($to);
            } catch (DecryptException) {
                return '';
            }
        }

        $email = $request->query('email');

        return is_string($email) ? mb_substr(trim($email), 0, 254) : '';
    }
}
