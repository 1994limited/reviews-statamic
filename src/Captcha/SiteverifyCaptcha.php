<?php

namespace NineteenNinetyFour\Reviews\Captcha;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use NineteenNinetyFour\Reviews\Contracts\Captcha;
use Throwable;

/**
 * A captcha checked by posting its token to the provider's "siteverify"
 * address, as Turnstile, hCaptcha and reCAPTCHA all work.
 */
abstract class SiteverifyCaptcha implements Captcha
{
    public function __construct(protected string $siteKey, protected string $secretKey, protected array $config = []) {}

    /** The form field the widget fills with its token. */
    abstract protected function field(): string;

    abstract protected function endpoint(): string;

    public function fields(): array
    {
        return [$this->field()];
    }

    public function verify(Request $request): bool
    {
        $token = $request->input($this->field());

        if (! is_string($token) || $token === '' || strlen($token) > 8192) {
            return false;
        }

        try {
            $response = Http::asForm()->timeout(10)->post($this->endpoint(), $this->payload($token, $request));
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return $response->successful() && $this->passed((array) $response->json());
    }

    protected function payload(string $token, Request $request): array
    {
        return ['secret' => $this->secretKey, 'response' => $token, 'remoteip' => $request->ip()];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function passed(array $result): bool
    {
        return ($result['success'] ?? false) === true;
    }
}
