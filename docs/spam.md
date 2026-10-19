# Spam

Every review form has three checks that ask nothing of people, and you can add a captcha.

## The honeypot

A field people can't see (it's off screen, and hidden from screen readers), named `website`. Bots fill in every field; a form with it filled in is thanked, and nothing is kept, so the bot doesn't learn to leave it alone. Rename it with `spam.honeypot` in the config.

## The time trap

People take more than a few seconds to write a review. Forms sent within three seconds of the page loading are a bot's (`spam.minimum_seconds`; 0 turns it off). The form carries the time it was drawn, encrypted, so it can't be made up. On a statically cached page the form was drawn long ago, so a tiny script in the form also notes when the page was opened in the browser.

## Links

Reviews with more than two links are refused (`form.max_links`).

## Rate limits

Five reviews per visitor per ten minutes, and 30 "helpful" votes a minute (`throttle.submit`, `throttle.votes`). Visitors are told apart by IP address, which is kept only in your cache, for as long as the limit lasts, and never with the review.

## Captchas

When bots get past all that, add a captcha. Four come with Reviews:

| `REVIEWS_CAPTCHA` | |
| --- | --- |
| `turnstile` | [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/). Usually invisible; free. |
| `hcaptcha` | [hCaptcha](https://www.hcaptcha.com). |
| `recaptcha` | [Google reCAPTCHA v2](https://developers.google.com/recaptcha/docs/display), the "I'm not a robot" checkbox. |
| `recaptcha_v3` | [Google reCAPTCHA v3](https://developers.google.com/recaptcha/docs/v3): nothing to tick. Google scores each visitor from 0 to 1, and reviews scoring under 0.5 are refused (`spam.captcha.score`). |

Get a site key and secret key from the provider, for your domain, and add them to `.env`:

```env
REVIEWS_CAPTCHA=turnstile
REVIEWS_CAPTCHA_SITE_KEY=…
REVIEWS_CAPTCHA_SECRET_KEY=…
```

The captcha appears at the end of every review form. To put it somewhere else, print `{{ captcha }}` inside the form where you want it:

```antlers
{{ reviews:form }}
    …
    {{ captcha }}
    <button type="submit">Send your review</button>
{{ /reviews:form }}
```

<details>
<summary>Blade</summary>

```blade
<s:reviews:form>
    …
    {!! $captcha !!}
    <button type="submit">Send your review</button>
</s:reviews:form>
```

</details>

Captchas need JavaScript, so with one, the form doesn't work with JavaScript turned off. A captcha that can't be checked (the provider is down, or the keys are wrong) refuses the review rather than letting it through, with the message "Please confirm you're not a robot". Without both keys, there's no captcha.

Captchas set their own cookies and send visitors' details to their provider; mention yours in your privacy notice.

### Your own captcha

Any class that implements `NineteenNinetyFour\Reviews\Contracts\Captcha`:

```php
use Illuminate\Http\Request;
use NineteenNinetyFour\Reviews\Contracts\Captcha;

class FriendlyCaptcha implements Captcha
{
    public function widget(): string
    {
        // What goes in the form. data-reviews-captcha tells the form tag it's there.
        return '<div class="frc-captcha" data-reviews-captcha data-sitekey="…"></div><script …></script>';
    }

    public function verify(Request $request): bool
    {
        // Check $request->input('frc-captcha-solution') with the provider.
    }

    public function fields(): array
    {
        // The fields it sends, so they aren't put back in the form after an error.
        return ['frc-captcha-solution'];
    }
}
```

Then set `REVIEWS_CAPTCHA` (or `spam.captcha.driver`) to its class name. It's made by Laravel's container, with `siteKey`, `secretKey` and `config` (the `spam.captcha` config) if its constructor asks for them.

## Content-Security-Policy

Reviews writes no inline scripts: the time trap, and reCAPTCHA v3's token, are in one file, `/!/reviews/reviews.js`, from your own site, so `script-src 'self'` allows them. A captcha also needs its provider's addresses allowed: `challenges.cloudflare.com` for Turnstile, `js.hcaptcha.com` and `hcaptcha.com` for hCaptcha, and `www.google.com` and `www.gstatic.com` for reCAPTCHA (each in `script-src` and `frame-src`).
