<?php

namespace NineteenNinetyFour\Reviews\Database;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReviewModel extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'ratings' => 'array',
        'photos' => 'array',
        'verified' => 'boolean',
        'has_photos' => 'boolean',
        'rating' => 'integer',
        'helpful' => 'integer',
        'replied_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function getTable()
    {
        return config('reviews.drivers.database.tables.reviews', 'reviews');
    }

    public function getConnectionName()
    {
        return config('reviews.drivers.database.connection') ?: parent::getConnectionName();
    }

    public function votes(): HasMany
    {
        return $this->hasMany(VoteModel::class, 'review_id');
    }
}
