<?php

namespace NineteenNinetyFour\Reviews\Captcha;

/**
 * Cloudflare Turnstile. Usually invisible to people.
 *
 * @deprecated 1.2 Reviews now uses reviews-core's captchas. Kept for code that extends this class; it still works as it did.
 */
class Turnstile extends SiteverifyCaptcha
{
    protected function field(): string
    {
        return 'cf-turnstile-response';
    }

    protected function endpoint(): string
    {
        return 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    }

    public function widget(): string
    {
        return '<div class="cf-turnstile" data-reviews-captcha data-sitekey="'.e($this->siteKey).'"></div>'
            .'<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
}
