<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NineteenNinetyFour\Reviews\Http\LaravelClient;
use NineteenNinetyFour\Reviews\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LaravelClientTest extends TestCase
{
    #[Test]
    public function a_failed_request_is_logged_without_its_keys(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6: Could not resolve host for https://api.judge.me/api/v1/reviews?api_token=SENTINEL-SECRET&page=1'));
        Log::spy();

        $this->assertNull((new LaravelClient)->getJson('https://api.judge.me/api/v1/reviews', ['api_token' => 'SENTINEL-SECRET']));

        Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'api.judge.me') && ! str_contains($message, 'SENTINEL'))->once();
    }
}
