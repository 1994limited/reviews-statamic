<?php

namespace NineteenNinetyFour\Reviews\Contracts;

/**
 * Which orders have been sent a review request, and which email addresses
 * asked not to be sent any more. Addresses are kept only as hashes.
 */
interface RequestLog
{
    public function sent(string $order): bool;

    public function markSent(string $order): void;

    public function optedOut(string $email): bool;

    public function optOut(string $email): void;
}
