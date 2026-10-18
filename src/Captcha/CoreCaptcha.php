<?php

namespace NineteenNinetyFour\Reviews\Captcha;

use Illuminate\Http\Request;
use NineteenNinetyFour\Reviews\Contracts\Captcha;
use NineteenNinetyFour\Reviews\Core\Captcha\Captcha as Core;

/**
 * One of the captchas that come with Reviews (from reviews-core), on the
 * addon's own captcha interface.
 */
class CoreCaptcha implements Captcha
{
    public function __construct(protected Core $captcha) {}

    public function widget(): string
    {
        return $this->captcha->widget();
    }

    public function verify(Request $request): bool
    {
        $token = $request->input($this->captcha->field());

        return $this->captcha->verify(is_string($token) ? $token : null, $request->ip());
    }

    public function fields(): array
    {
        return [$this->captcha->field()];
    }
}
