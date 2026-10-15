<?php

namespace NineteenNinetyFour\Reviews\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use NineteenNinetyFour\Reviews\Contracts\ReviewRepository;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\ReviewStatus;

class EloquentReviewRepository implements ReviewRepository
{
    public function find(string $id): ?Review
    {
        $model = ReviewModel::query()->find($id);

        return $model ? $this->fromModel($model) : null;
    }

    public function save(Review $review): void
    {
        $this->connection()->transaction(function () use ($review) {
            ReviewModel::query()->updateOrCreate(['id' => $review->id], $this->attributes($review));

            // Voters come along when a review is copied from the file driver.
            foreach ($review->voters as $voter) {
                VoteModel::query()->firstOrCreate(['review_id' => $review->id, 'voter' => $voter], ['created_at' => now()]);
            }
        });
    }

    public function delete(Review $review): void
    {
        $this->connection()->transaction(function () use ($review) {
            VoteModel::query()->where('review_id', $review->id)->delete();
            ReviewModel::query()->whereKey($review->id)->delete();
        });
    }

    public function paginate(ReviewQuery $query, int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        $paginator = $this->builder($query)->paginate($perPage, ['*'], 'page', $page);

        return $paginator->setCollection($paginator->getCollection()->map(fn (ReviewModel $model) => $this->fromModel($model)));
    }

    public function get(ReviewQuery $query, ?int $limit = null): Collection
    {
        return $this->builder($query)
            ->when($limit, fn ($builder) => $builder->limit($limit))
            ->get()
            ->map(fn (ReviewModel $model) => $this->fromModel($model))
            ->values();
    }

    public function count(ReviewQuery $query): int
    {
        return $this->filtered($query)->count();
    }

    public function statusCounts(): array
    {
        $counts = ReviewModel::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        return collect(ReviewStatus::cases())->mapWithKeys(fn ($status) => [$status->value => (int) ($counts[$status->value] ?? 0)])->all();
    }

    public function tally(string $entry, ?string $site = null): array
    {
        $base = ReviewModel::query()
            ->where('entry_id', $entry)
            ->where('status', ReviewStatus::Approved->value)
            ->when($site !== null, fn ($query) => $query->where('site', $site));

        $counts = (clone $base)->selectRaw('rating, count(*) as total')->groupBy('rating')->pluck('total', 'rating')->all();

        $stars = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($counts as $rating => $total) {
            if (isset($stars[(int) $rating])) {
                $stars[(int) $rating] = (int) $total;
            }
        }

        $criteria = [];

        (clone $base)->whereNotNull('ratings')->select(['id', 'ratings'])->orderBy('id')->chunk(1000, function ($models) use (&$criteria) {
            foreach ($models as $model) {
                foreach (Review::scores($model->ratings) as $handle => $score) {
                    $criteria[$handle]['sum'] = ($criteria[$handle]['sum'] ?? 0) + $score;
                    $criteria[$handle]['count'] = ($criteria[$handle]['count'] ?? 0) + 1;
                }
            }
        });

        return ['stars' => $stars, 'criteria' => $criteria];
    }

    public function vote(Review $review, string $voter): bool
    {
        try {
            $this->connection()->transaction(function () use ($review, $voter) {
                VoteModel::query()->create(['review_id' => $review->id, 'voter' => $voter, 'created_at' => now()]);
                ReviewModel::query()->whereKey($review->id)->increment('helpful');
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $review->helpful = (int) ReviewModel::query()->whereKey($review->id)->value('helpful');

        return true;
    }

    public function all(): LazyCollection
    {
        return ReviewModel::query()->with('votes')->orderBy('id')->lazyById(200)
            ->map(function (ReviewModel $model) {
                $review = $this->fromModel($model);
                $review->voters = $model->votes->pluck('voter')->all();

                return $review;
            });
    }

    protected function filtered(ReviewQuery $query): Builder
    {
        return ReviewModel::query()
            ->when($query->entries !== null, fn ($builder) => $builder->whereIn('entry_id', $query->entries))
            ->when($query->site !== null, fn ($builder) => $builder->where('site', $query->site))
            ->when($query->statuses !== null, fn ($builder) => $builder->whereIn('status', array_map(fn ($s) => $s->value, $query->statuses)))
            ->when($query->ratings !== null, fn ($builder) => $builder->whereIn('rating', $query->ratings))
            ->when($query->verified !== null, fn ($builder) => $builder->where('verified', $query->verified))
            ->when($query->withPhotos !== null, fn ($builder) => $builder->where('has_photos', $query->withPhotos))
            ->when($query->user !== null, fn ($builder) => $builder->where('user_id', $query->user))
            ->when($query->email !== null, fn ($builder) => $builder->whereRaw('lower(email) = ?', [$query->email]))
            ->when($query->except !== null, fn ($builder) => $builder->whereKeyNot($query->except))
            ->when($query->search !== null, function ($builder) use ($query) {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query->search)).'%';

                $builder->where(fn ($builder) => $builder
                    ->whereRaw("lower(title) like ? escape '!'", [$like])
                    ->orWhereRaw("lower(body) like ? escape '!'", [$like])
                    ->orWhereRaw("lower(name) like ? escape '!'", [$like])
                    ->orWhereRaw("lower(email) like ? escape '!'", [$like]));
            });
    }

    protected function builder(ReviewQuery $query): Builder
    {
        $builder = $this->filtered($query);

        match ($query->sort) {
            'oldest' => $builder->orderBy('created_at')->orderBy('id'),
            'highest' => $builder->orderByDesc('rating'),
            'lowest' => $builder->orderBy('rating'),
            'helpful' => $builder->orderByDesc('helpful'),
            default => null,
        };

        if ($query->sort !== 'oldest') {
            $builder->orderByDesc('created_at')->orderByDesc('id');
        }

        return $builder;
    }

    protected function attributes(Review $review): array
    {
        return [
            'entry_id' => $review->entry,
            'site' => $review->site,
            'rating' => $review->rating,
            'ratings' => $review->ratings ?: null,
            'title' => $review->title,
            'body' => $review->body,
            'name' => $review->name,
            'email' => $review->email,
            'user_id' => $review->user,
            'status' => $review->status->value,
            'verified' => $review->verified,
            'order_id' => $review->order,
            'reply' => $review->reply,
            'reply_by' => $review->replyBy,
            'replied_at' => $review->repliedAt,
            'note' => $review->note,
            'photos' => $review->photos ?: null,
            'has_photos' => $review->photos !== [],
            'helpful' => max($review->helpful, count($review->voters)),
            'created_at' => $review->createdAt,
            'updated_at' => $review->updatedAt,
            'approved_at' => $review->approvedAt,
        ];
    }

    protected function fromModel(ReviewModel $model): Review
    {
        return Review::fromArray($model->id, [
            'entry' => $model->entry_id,
            'site' => $model->site,
            'rating' => $model->rating,
            'ratings' => $model->ratings,
            'title' => $model->title,
            'body' => $model->body,
            'name' => $model->name,
            'email' => $model->email,
            'user' => $model->user_id,
            'status' => $model->status,
            'verified' => $model->verified,
            'order' => $model->order_id,
            'reply' => $model->reply,
            'reply_by' => $model->reply_by,
            'replied_at' => $model->replied_at,
            'note' => $model->note,
            'photos' => $model->photos,
            'helpful' => $model->helpful,
            'created_at' => $model->created_at,
            'updated_at' => $model->updated_at,
            'approved_at' => $model->approved_at,
        ]);
    }

    protected function connection()
    {
        return DB::connection((new ReviewModel)->getConnectionName());
    }
}
