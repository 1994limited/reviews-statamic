<?php

namespace NineteenNinetyFour\Reviews\Http;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            $this->failed($url, $e);

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    public function getJson(string $url, array $query = [], array $headers = []): ?array
    {
        try {
            $response = Http::acceptJson()->withHeaders($headers)->timeout($this->timeout)->get($url, $query);
        } catch (Throwable $e) {
            $this->failed($url, $e);

            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    /**
     * Log a failed request without its keys: platforms take them in the
     * query string, which connection errors repeat in their messages.
     */
    protected function failed(string $url, Throwable $e): void
    {
        $where = (string) preg_replace('/[?#].*$/s', '', $url);
        $message = (string) preg_replace('~(https?://[^\s?#]+)[?#][^\s]*~i', '$1', $e->getMessage());

        Log::warning("Reviews: a request to {$where} failed: ".get_class($e).': '.$message);
    }
}
