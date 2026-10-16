<?php

namespace NineteenNinetyFour\Reviews\Requests;

use Illuminate\Support\Facades\File;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;

/**
 * The file driver's request log: one JSON file, written with a lock.
 */
class FileRequestLog implements RequestLog
{
    public function sent(string $order): bool
    {
        return isset($this->read()['sent'][$order]);
    }

    public function markSent(string $order): void
    {
        $this->update(function (array $data) use ($order) {
            $data['sent'][$order] = now()->toIso8601String();

            return $data;
        });
    }

    public function optedOut(string $email): bool
    {
        return isset($this->read()['opted_out'][static::hash($email)]);
    }

    public function optOut(string $email): void
    {
        $this->update(function (array $data) use ($email) {
            $data['opted_out'][static::hash($email)] = now()->toIso8601String();

            return $data;
        });
    }

    public static function hash(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower(trim($email)), (string) config('app.key'));
    }

    protected function path(): string
    {
        return (string) config('reviews.drivers.file.requests', storage_path('reviews-requests.json'));
    }

    protected function read(): array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return ['sent' => [], 'opted_out' => []];
        }

        // Shared lock: wait while update() is rewriting the file, rather than
        // read it half written and send a request twice, or to someone who
        // opted out.
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ['sent' => [], 'opted_out' => []];
        }

        try {
            flock($handle, LOCK_SH);
            $data = json_decode((string) stream_get_contents($handle), true);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return is_array($data) ? $data + ['sent' => [], 'opted_out' => []] : ['sent' => [], 'opted_out' => []];
    }

    protected function update(callable $change): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'c+');

        if ($handle === false) {
            return;
        }

        try {
            flock($handle, LOCK_EX);
            $data = json_decode((string) stream_get_contents($handle), true);
            $data = $change(is_array($data) ? $data + ['sent' => [], 'opted_out' => []] : ['sent' => [], 'opted_out' => []]);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string) json_encode($data, JSON_PRETTY_PRINT));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
