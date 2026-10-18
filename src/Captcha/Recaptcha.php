<?php

namespace NineteenNinetyFour\Reviews\Captcha;

/**
 * Google reCAPTCHA v2: the "I'm not a robot" checkbox.
 *
 * @deprecated 1.2 Reviews now uses reviews-core's captchas. Kept for code that extends this class; it still works as it did.
 */
class Recaptcha extends SiteverifyCaptcha
{
    protected function field(): string
    {
        return 'g-recaptcha-response';
    }

    protected function endpoint(): string
    {
        return 'https://www.google.com/recaptcha/api/siteverify';
    }

    public function widget(): string
    {
        return '<div class="g-recaptcha" data-reviews-captcha data-sitekey="'.e($this->siteKey).'"></div>'
            .'<script src="https://www.google.com/recaptcha/api.js" async defer></script>';
    }
}
