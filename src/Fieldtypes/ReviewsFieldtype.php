<?php

namespace NineteenNinetyFour\Reviews\Fieldtypes;

use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use Statamic\Contracts\Entries\Entry;
use Statamic\Fields\Fieldtype;

/**
 * A read-only field for entry blueprints: the entry's average rating and
 * how many reviews it has, with links to them in the Control Panel. It
 * stores nothing.
 */
class ReviewsFieldtype extends Fieldtype
{
    protected static $handle = 'reviews';

    protected $categories = ['special'];

    protected $icon = 'rating-star';

    protected $selectable = true;

    public function preload()
    {
        $entry = $this->field()?->parent();

        if (! $entry instanceof Entry || ! $entry->id()) {
            return ['exists' => false];
        }

        $reviews = app(Reviews::class);
        $id = $reviews->entryId($entry);
        $summary = $reviews->summary($id);
        $pending = $reviews->repository()->count(ReviewQuery::make()->entry($id)->status('pending'));

        return [
            'exists' => true,
            'count' => $summary->count(),
            'average' => $summary->average() !== null ? round($summary->average(), 1) : null,
            'breakdown' => $summary->stars,
            'pending' => $pending,
            'url' => cp_route('reviews.index', ['entry' => $id, 'status' => 'all']),
            'pending_url' => cp_route('reviews.index', ['entry' => $id, 'status' => 'pending']),
        ];
    }

    public function process($data)
    {
        return null;
    }

    public function preProcess($data)
    {
        return null;
    }

    public function augment($value)
    {
        return null;
    }
}
