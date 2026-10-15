<?php

namespace NineteenNinetyFour\Reviews\Captcha;

/**
 * Google reCAPTCHA v3: nothing to tick. Google scores each visit from 0
 * (a bot) to 1 (a person); reviews scoring under the threshold
 * (reviews.spam.captcha.score, 0.5) are refused.
 */
class RecaptchaV3 extends Recaptcha
{
    public const ACTION = 'review';

    public function widget(): string
    {
        $key = e($this->siteKey);
        $json = json_encode($this->siteKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Asks Google for a token as the form is sent, then sends it.
        return '<input type="hidden" name="g-recaptcha-response" value="" data-reviews-captcha data-reviews-recaptcha>'
            .'<script src="https://www.google.com/recaptcha/api.js?render='.$key.'" async defer></script>'
            .'<script>(function(){document.querySelectorAll("[data-reviews-recaptcha]").forEach(function(input){var form=input.form;if(!form||form.dataset.reviewsRecaptcha)return;form.dataset.reviewsRecaptcha=1;form.addEventListener("submit",function(event){if(input.value||!window.grecaptcha)return;event.preventDefault();grecaptcha.ready(function(){grecaptcha.execute('.$json.',{action:"'.self::ACTION.'"}).then(function(token){input.value=token;form.requestSubmit?form.requestSubmit():form.submit()})})})})})();</script>';
    }

    protected function passed(array $result): bool
    {
        $threshold = (float) ($this->config['score'] ?? 0.5);

        return parent::passed($result)
            && ($result['action'] ?? null) === self::ACTION
            && is_numeric($result['score'] ?? null)
            && (float) $result['score'] >= $threshold;
    }
}
