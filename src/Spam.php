<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Telling people from bots, without asking people to do anything:
 *
 * - a honeypot, a field people can't see and bots fill in;
 * - a time trap: forms sent within seconds of the page loading are a
 *   bot's. The form carries the time it was drawn, encrypted. A page
 *   served from the static cache was drawn long ago, so the form's small
 *   script also notes when the page was opened in the browser;
 * - Cloudflare Turnstile, when its keys are set.
 */
class Spam
{
    public const TIME_FIELD = '_reviews_t';

    public const OPENED_FIELD = '_reviews_opened';

    public function honeypot(): string
    {
        $name = (string) config('reviews.spam.honeypot', 'website');

        return preg_match('/^[a-z_][a-z0-9_-]{0,40}$/i', $name) ? $name : 'website';
    }

    /**
     * The hidden fields every review form carries.
     */
    public function fields(): string
    {
        $honeypot = e($this->honeypot());
        $time = e(Crypt::encryptString((string) time()));

        return '<div style="position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
            .'<label>'.e(__('reviews::messages.honeypot')).' <input type="text" name="'.$honeypot.'" value="" tabindex="-1" autocomplete="off"></label>'
            .'</div>'
            .'<input type="hidden" name="'.self::TIME_FIELD.'" value="'.$time.'">'
            .'<input type="hidden" name="'.self::OPENED_FIELD.'" value="" data-reviews-opened>';
    }

    /**
     * Marks when the page was opened, for the time trap on cached pages.
     */
    public function script(): string
    {
        return '<script>(function(){var t=Date.now();document.querySelectorAll("[data-reviews-opened]").forEach(function(i){var f=i.form;if(f&&!f.dataset.reviewsTimed){f.dataset.reviewsTimed=1;f.addEventListener("submit",function(){f.querySelectorAll("[data-reviews-opened]").forEach(function(x){x.value=String(Math.round((Date.now()-t)/1000))})})}})})();</script>';
    }

    /**
     * Whether a form submission looks like a bot's.
     */
    public function isBot(Request $request): bool
    {
        $honeypot = $request->input($this->honeypot());

        if ($honeypot !== null && $honeypot !== '') {
            return true;
        }

        $minimum = max(0, (int) config('reviews.spam.minimum_seconds', 3));

        if ($minimum === 0) {
            return false;
        }

        // Seconds since the page was opened in the browser, from the script.
        $opened = $request->input(self::OPENED_FIELD);

        if (is_string($opened) && ctype_digit($opened) && (int) $opened < $minimum) {
            return true;
        }

        // Seconds since the form was drawn on the server. A form without
        // the field (or with one that's been tampered with) is a bot's.
        try {
            $drawn = Crypt::decryptString((string) $request->input(self::TIME_FIELD));
        } catch (DecryptException) {
            return true;
        }

        return ! ctype_digit($drawn) || time() - (int) $drawn < $minimum;
    }

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Turnstile
    |--------------------------------------------------------------------------
    */

    public function turnstileEnabled(): bool
    {
        return filled(config('reviews.spam.turnstile.site_key')) && filled(config('reviews.spam.turnstile.secret_key'));
    }

    public function turnstileWidget(): string
    {
        if (! $this->turnstileEnabled()) {
            return '';
        }

        return '<div class="cf-turnstile" data-sitekey="'.e((string) config('reviews.spam.turnstile.site_key')).'"></div>'
            .'<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }

    public function passesTurnstile(Request $request): bool
    {
        if (! $this->turnstileEnabled()) {
            return true;
        }

        $token = $request->input('cf-turnstile-response');

        if (! is_string($token) || $token === '' || strlen($token) > 4096) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret' => config('reviews.spam.turnstile.secret_key'),
                'response' => $token,
                'remoteip' => $request->ip(),
            ]);

            return $response->successful() && $response->json('success') === true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * How many links are in some text.
     */
    public static function links(?string $text): int
    {
        if ($text === null || $text === '') {
            return 0;
        }

        return preg_match_all('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|ru|xyz|top|info|io|co|biz)\b)~i', $text);
    }
}
