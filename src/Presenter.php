<?php

namespace NineteenNinetyFour\Reviews;

use Statamic\Contracts\Assets\Asset;
use Statamic\Contracts\Auth\User;

/**
 * Reviews and their numbers as templates use them. Everything a visitor
 * wrote comes out escaped (line breaks as <br>), so Antlers can print it
 * as it is; in Blade, print it with {!! !!}.
 */
class Presenter
{
    public function __construct(protected Reviews $reviews, protected Photos $photos) {}

    /**
     * @return array<string, mixed>
     */
    public function review(Review $review, ?User $viewer = null): array
    {
        $criteria = collect($this->reviews->criteria($this->reviews->entry($review->entry)))->keyBy('handle');

        return [
            'id' => $review->id,
            'entry_id' => $review->entry,
            'rating' => $review->rating,
            'stars' => static::stars($review->rating),
            'ratings' => collect($review->ratings)
                ->filter(fn ($score, $handle) => $criteria->has($handle))
                ->map(fn ($score, $handle) => [
                    'handle' => $handle,
                    'label' => e($criteria[$handle]['label']),
                    'rating' => $score,
                    'stars' => static::stars($score),
                ])
                ->values()
                ->all(),
            'title' => static::text($review->title),
            'body' => static::paragraphs($review->body),
            'name' => e(Reviews::displayName($review->name)),
            'verified' => $review->verified,
            'date' => $review->createdAt,
            'updated' => $review->updatedAt,
            'reply' => static::paragraphs($review->reply),
            'has_reply' => $review->reply !== null,
            'reply_date' => $review->repliedAt,
            'helpful' => $review->helpful,
            'photos' => collect($this->photos->assets($review))->map(fn (Asset $asset) => [
                'url' => $asset->url(),
                'id' => $asset->id(),
                'alt' => e((string) ($asset->get('alt') ?? '')),
                'width' => $asset->width(),
                'height' => $asset->height(),
            ])->all(),
            'status' => $review->status->value,
            'is_mine' => $viewer !== null && $review->user !== null && $review->user === (string) $viewer->id(),
            'site' => $review->site,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Summary $summary, array $criteria = []): array
    {
        $count = $summary->count();
        $average = $summary->average();

        return [
            'count' => $count,
            'has_reviews' => $count > 0,
            'no_reviews' => $count === 0,
            'average' => $average !== null ? round($average, 1) : null,
            'average_exact' => $average,
            // For a bar or the width of filled stars: 4.5 out of 5 is 90.
            'percent' => $average !== null ? round($average / 5 * 100, 1) : 0,
            'stars' => static::stars($average ?? 0),
            'breakdown' => collect([5, 4, 3, 2, 1])->map(fn ($rating) => [
                'rating' => $rating,
                'count' => $summary->stars[$rating] ?? 0,
                'percent' => $count > 0 ? (int) round(($summary->stars[$rating] ?? 0) / $count * 100) : 0,
            ])->all(),
            'criteria' => collect($criteria)->map(function ($criterion) use ($summary) {
                $average = $summary->criterionAverage($criterion['handle']);

                return [
                    'handle' => $criterion['handle'],
                    'label' => e($criterion['label']),
                    'average' => $average !== null ? round($average, 1) : null,
                    'percent' => $average !== null ? round($average / 5 * 100, 1) : 0,
                    'stars' => static::stars($average ?? 0),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Five stars, each "full", "half" or "empty", for a rating. Halves are
     * shown from a quarter up, and a full star from three quarters.
     *
     * @return array<int, array{type: string, full: bool, half: bool, empty: bool}>
     */
    public static function stars(float|int $rating): array
    {
        $stars = [];

        for ($i = 1; $i <= 5; $i++) {
            $part = $rating - ($i - 1);
            $type = $part >= 0.75 ? 'full' : ($part >= 0.25 ? 'half' : 'empty');

            $stars[] = ['type' => $type, 'full' => $type === 'full', 'half' => $type === 'half', 'empty' => $type === 'empty', 'number' => $i];
        }

        return $stars;
    }

    public static function text(?string $text): ?string
    {
        return $text === null ? null : e($text);
    }

    /**
     * Visitor text, escaped, with its line breaks kept.
     */
    public static function paragraphs(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = trim(preg_replace("/\r\n?/", "\n", $text) ?? '');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return nl2br(e($text), false);
    }
}
