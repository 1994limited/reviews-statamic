<?php

namespace NineteenNinetyFour\Reviews\Widgets;

use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use Statamic\Facades\User;
use Statamic\Widgets\VueComponent;
use Statamic\Widgets\Widget;

/**
 * The dashboard's "Reviews waiting" widget: the oldest reviews waiting for
 * an editor. Add it to config/statamic/cp.php:
 *
 *     'widgets' => [
 *         ['type' => 'pending_reviews', 'width' => 50],
 *     ],
 *
 * Options: title, limit (5).
 */
class PendingReviews extends Widget
{
    protected static $handle = 'pending_reviews';

    public function component()
    {
        if (! User::current()?->can('view reviews')) {
            return;
        }

        $reviews = app(Reviews::class);
        $limit = max(1, min(20, (int) $this->config('limit', 5)));
        $query = ReviewQuery::make()->status('pending')->sort('oldest');

        return VueComponent::render('reviews-pending-widget', [
            'title' => $this->config('title', __('reviews::messages.cp.pending_widget')),
            'total' => $reviews->repository()->count($query),
            'reviews' => $reviews->repository()->get($query, $limit)->map(fn (Review $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'title' => $review->title,
                'name' => $review->name,
                'entry_title' => (string) ($reviews->entry($review->entry)?->get('title') ?? ''),
                'created_ago' => $review->createdAt?->diffForHumans(),
                'url' => cp_route('reviews.show', $review->id),
            ])->all(),
            'url' => cp_route('reviews.index', ['status' => 'pending']),
            'empty' => __('reviews::messages.cp.all_caught_up'),
        ]);
    }
}
