<?php

namespace NineteenNinetyFour\Reviews\Database;

use Illuminate\Database\Eloquent\Model;

class VoteModel extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    public function getTable()
    {
        return config('reviews.drivers.database.tables.votes', 'review_votes');
    }

    public function getConnectionName()
    {
        return config('reviews.drivers.database.connection') ?: parent::getConnectionName();
    }
}
