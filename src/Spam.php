<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use NineteenNinetyFour\Reviews\Captcha\CoreCaptcha;
use NineteenNinetyFour\Reviews\Captcha\HCaptcha;
use NineteenNinetyFour\Reviews\Captcha\Recaptcha;
use NineteenNinetyFour\Reviews\Captcha\RecaptchaV3;
use NineteenNinetyFour\Reviews\Captcha\Turnstile;
use NineteenNinetyFour\Reviews\Contracts\Captcha;
use NineteenNinetyFour\Reviews\Core\Assets;
use NineteenNinetyFour\Reviews\Core\Captcha\Captchas;
use NineteenNinetyFour\Reviews\Core\Spam\Links;
use NineteenNinetyFour\Reviews\Core\Spam\TimeToken;
use NineteenNinetyFour\Reviews\Http\LaravelClient;

/**
 * Telling people from bots, without asking people to do anything:
 *
 * - a honeypot, a field people can't see and bots fill in;
 * - a time trap: forms sent within seconds of the page loading are a
 *   bot's. The form carries the time it was drawn, encrypted. A page
 *   served from the static cache was drawn long ago, so the form's small
 *   script also notes when the page was opened in the browser;
 * - a captcha, when one is set up: Cloudflare Turnstile, hCaptcha,
 *   Google reCAPTCHA (v2 or v3), or your own.
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
        $time = e($this->timeToken()->issue());

        return '<div style="position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden" aria-hidden="true">'
            .'<label>'.e(__('reviews::messages.honeypot')).' <input type="text" name="'.$honeypot.'" value="" tabindex="-1" autocomplete="off"></label>'
            .'</div>'
            .'<input type="hidden" name="'.self::TIME_FIELD.'" value="'.$time.'">'
            .'<input type="hidden" name="'.self::OPENED_FIELD.'" value="" data-reviews-opened>';
    }

    /**
     * What review forms need in the browser (the time check, and
     * reCAPTCHA v3's token), from reviews-core: a file, not inline, so it
     * runs under a strict Content-Security-Policy.
     */
    public function script(): string
    {
        return '<script src="'.e(url('!/reviews/reviews.js').'?v='.substr((string) md5_file(static::scriptPath()), 0, 12)).'"></script>';
    }

    public static function scriptPath(): string
    {
        return Assets::scriptPath();
    }

    protected function timeToken(): TimeToken
    {
        return new TimeToken((string) config('app.key'));
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

        // Seconds since the form was drawn on the server, signed. A form
        // without the field (or with one that's been tampered with) is a
        // bot's.
        $token = $request->input(self::TIME_FIELD);

        if (is_string($token) && str_contains($token, '.') && ctype_digit(strstr($token, '.', true))) {
            return $this->timeToken()->tooQuick($token, null, $minimum);
        }

        // A form drawn before 1.2 carries the time encrypted.
        try {
            $drawn = Crypt::decryptString((string) $token);
        } catch (DecryptException) {
            return true;
        }

        return ! ctype_digit($drawn) || time() - (int) $drawn < $minimum;
    }

    /*
    |--------------------------------------------------------------------------
    | Captchas
    |--------------------------------------------------------------------------
    */

    /**
     * The drivers that come with the addon. The form uses reviews-core's;
     * these classes are kept, as they were, for code that extends them.
     */
    public const CAPTCHAS = [
        'turnstile' => Turnstile::class,
        'hcaptcha' => HCaptcha::class,
        'recaptcha' => Recaptcha::class,
        'recaptcha_v3' => RecaptchaV3::class,
    ];

    /**
     * The captcha the form uses, or null for none.
     */
    public function captcha(): ?Captcha
    {
        $config = (array) config('reviews.spam.captcha', []);
        $driver = $config['driver'] ?? null;

        if (! is_string($driver) || $driver === '') {
            return null;
        }

        // The addon's own drivers need both keys; without them, there's no captcha.
        if (Captchas::builtIn($driver)) {
            $captcha = Captchas::make($driver, (string) ($config['site_key'] ?? ''), (string) ($config['secret_key'] ?? ''), new LaravelClient(10), ['inline_script' => false] + $config);

            return $captcha ? new CoreCaptcha($captcha) : null;
        }

        $class = $driver;

        if (! class_exists($class) || ! is_subclass_of($class, Captcha::class)) {
            throw new InvalidArgumentException("Unknown reviews captcha [{$driver}]. Use turnstile, hcaptcha, recaptcha, recaptcha_v3, or a class implementing ".Captcha::class.'.');
        }

        return app()->makeWith($class, [
            'siteKey' => (string) ($config['site_key'] ?? ''),
            'secretKey' => (string) ($config['secret_key'] ?? ''),
            'config' => $config,
        ]);
    }

    public function captchaWidget(): string
    {
        return $this->captcha()?->widget() ?? '';
    }

    public function passesCaptcha(Request $request): bool
    {
        return $this->captcha()?->verify($request) ?? true;
    }

    /**
     * The captcha's own form fields, kept out of old input.
     *
     * @return array<int, string>
     */
    public function captchaFields(): array
    {
        return $this->captcha()?->fields() ?? [];
    }

    /**
     * How many links are in some text.
     */
    public static function links(?string $text): int
    {
        return Links::count($text);
    }
}
