<?php

namespace NineteenNinetyFour\Reviews\Captcha;

use Illuminate\Http\Request;

/**
 * hCaptcha.
 *
 * @deprecated 1.2 Reviews now uses reviews-core's captchas. Kept for code that extends this class; it still works as it did.
 */
class HCaptcha extends SiteverifyCaptcha
{
    protected function field(): string
    {
        return 'h-captcha-response';
    }

    protected function endpoint(): string
    {
        return 'https://api.hcaptcha.com/siteverify';
    }

    protected function payload(string $token, Request $request): array
    {
        return parent::payload($token, $request) + ['sitekey' => $this->siteKey];
    }

    public function widget(): string
    {
        return '<div class="h-captcha" data-reviews-captcha data-sitekey="'.e($this->siteKey).'"></div>'
            .'<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
    }
}
