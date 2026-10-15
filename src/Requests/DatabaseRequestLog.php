<?php

namespace NineteenNinetyFour\Reviews\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;

class DatabaseRequestLog implements RequestLog
{
    public function sent(string $order): bool
    {
        return $this->table()->where('type', 'sent')->where('order_id', $order)->exists();
    }

    public function markSent(string $order): void
    {
        try {
            $this->table()->insert(['type' => 'sent', 'order_id' => $order, 'created_at' => now()]);
        } catch (UniqueConstraintViolationException) {
            // Already recorded.
        }
    }

    public function optedOut(string $email): bool
    {
        return $this->table()->where('type', 'opt_out')->where('email_hash', FileRequestLog::hash($email))->exists();
    }

    public function optOut(string $email): void
    {
        if (! $this->optedOut($email)) {
            $this->table()->insert(['type' => 'opt_out', 'email_hash' => FileRequestLog::hash($email), 'created_at' => now()]);
        }
    }

    protected function table(): Builder
    {
        return DB::connection(config('reviews.drivers.database.connection') ?: null)
            ->table(config('reviews.drivers.database.tables.requests', 'review_requests'));
    }
}
