<?php

namespace NineteenNinetyFour\Reviews\Http;

use Illuminate\Support\Facades\Http;
use NineteenNinetyFour\Reviews\Core\Http\Client;
use Throwable;

/**
 * The core's HTTP client, through Laravel's (so Http::fake() works in tests).
 * Null for anything but a JSON answer.
 */
class LaravelClient implements Client
{
    public function __construct(protected int $timeout = 15) {}

    public function postForm(string $url, array $form): ?array
    {
        try {
            $response = Http::asForm()->timeout($this->timeout)->post($url, $form);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function getJson(string $url, array $query = [], array $headers = []): ?array
    {
        try {
            $response = Http::acceptJson()->withHeaders($headers)->timeout($this->timeout)->get($url, $query);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }
}
