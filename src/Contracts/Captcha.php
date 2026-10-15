<?php

namespace NineteenNinetyFour\Reviews\Contracts;

use Illuminate\Http\Request;

/**
 * A captcha on the review form. Write your own by implementing this and
 * setting its class as reviews.spam.captcha.driver.
 */
interface Captcha
{
    /**
     * The HTML that goes in the form: the widget and its script. Include
     * data-reviews-captcha on an element, so the form tag knows a template
     * has placed it and doesn't add it again.
     */
    public function widget(): string;

    /**
     * Whether the visitor passed.
     */
    public function verify(Request $request): bool;

    /**
     * The form fields the captcha sends, kept out of old input.
     *
     * @return array<int, string>
     */
    public function fields(): array;
}
