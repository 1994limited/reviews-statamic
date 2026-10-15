<?php

namespace NineteenNinetyFour\Reviews;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Spam = 'spam';

    public function label(): string
    {
        return __('reviews::messages.statuses.'.$this->value);
    }

    /**
     * Read leniently: anything that isn't a status is pending.
     */
    public static function read(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return is_string($value) ? (self::tryFrom($value) ?? self::Pending) : self::Pending;
    }
}
