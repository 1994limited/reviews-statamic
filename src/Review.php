<?php

namespace NineteenNinetyFour\Reviews;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Query\ContainsQueryableValues;
use Statamic\Facades\Entry;
use Statamic\Facades\File;
use Statamic\Facades\Stache;
use Statamic\Facades\YAML;
use Throwable;

/**
 * One review of one entry: a rating out of five, optional ratings for each
 * criterion, a title and text, who wrote it, where it is in moderation, and
 * the site's public reply. What the reviewer wrote is kept exactly as they
 * wrote it, and escaped wherever it's shown.
 */
class Review implements ContainsQueryableValues
{
    public string $id;

    /** The entry reviewed: its origin's ID, so every site's copy shares reviews. */
    public string $entry = '';

    /** The handle of the site it was written on. */
    public ?string $site = null;

    public int $rating = 0;

    /** @var array<string, int> Ratings for each criterion, by handle. */
    public array $ratings = [];

    public ?string $title = null;

    public ?string $body = null;

    public string $name = '';

    public ?string $email = null;

    /** The reviewer's user ID, when they were signed in. */
    public ?string $user = null;

    public ReviewStatus $status = ReviewStatus::Pending;

    /** Bought from this site (with Cargo). */
    public bool $verified = false;

    /** The Cargo order that verified it. */
    public ?string $order = null;

    public ?string $reply = null;

    /** The ID of the user who wrote the reply. */
    public ?string $replyBy = null;

    public ?CarbonImmutable $repliedAt = null;

    /** An editor's note, never shown on the site (why it was rejected, say). */
    public ?string $note = null;

    /**
     * Photos: while waiting for moderation, on the private disk
     * ['disk' => ..., 'path' => ...]; once approved, assets
     * ['asset' => 'container::path'].
     *
     * @var array<int, array<string, string>>
     */
    public array $photos = [];

    public int $helpful = 0;

    /**
     * Who has voted it helpful, as hashed keys. Kept on the review by the
     * file driver; the database driver keeps them in a table of their own.
     *
     * @var array<int, string>
     */
    public array $voters = [];

    public ?CarbonImmutable $createdAt = null;

    public ?CarbonImmutable $updatedAt = null;

    public ?CarbonImmutable $approvedAt = null;

    /** Set while a review is being submitted, so nobody's emailed that their own new review was approved. Not stored. */
    public bool $submitting = false;

    /** The file it was read from (the file driver). */
    protected ?string $initialPath = null;

    public static function make(): static
    {
        $review = new static;
        $review->id = strtolower((string) Str::ulid());

        return $review;
    }

    /**
     * Build a review from what a driver stored, in the shape of
     * fileData(). A file can be edited by hand, so anything malformed is
     * read as empty rather than breaking every review.
     */
    public static function fromArray(string $id, array $data): static
    {
        $text = fn ($value) => is_scalar($value) && (string) $value !== '' ? (string) $value : null;

        $review = new static;
        $review->id = $id;
        $review->entry = (string) $text($data['entry'] ?? null);
        $review->site = $text($data['site'] ?? null);
        $review->rating = static::score($data['rating'] ?? null) ?? 0;
        $review->ratings = static::scores($data['ratings'] ?? []);
        $review->title = $text($data['title'] ?? null);
        $review->body = $text($data['body'] ?? null);
        $review->name = (string) $text($data['name'] ?? null);
        $review->email = $text($data['email'] ?? null);
        $review->user = $text($data['user'] ?? null);
        $review->status = ReviewStatus::read($data['status'] ?? null);
        $review->verified = filter_var($data['verified'] ?? false, FILTER_VALIDATE_BOOL);
        $review->order = $text($data['order'] ?? null);
        $review->reply = $text($data['reply'] ?? null);
        $review->replyBy = $text($data['reply_by'] ?? null);
        $review->repliedAt = static::date($data['replied_at'] ?? null);
        $review->note = $text($data['note'] ?? null);
        $review->photos = static::readPhotos($data['photos'] ?? []);
        $review->helpful = max(0, (int) (is_numeric($data['helpful'] ?? null) ? $data['helpful'] : 0));
        $review->voters = array_values(array_filter((array) ($data['voters'] ?? []), 'is_string'));
        $review->createdAt = static::date($data['created_at'] ?? null);
        $review->updatedAt = static::date($data['updated_at'] ?? null);
        $review->approvedAt = static::date($data['approved_at'] ?? null);

        return $review;
    }

    /**
     * A rating from 1 to 5, or null.
     */
    public static function score(mixed $value): ?int
    {
        if (! is_numeric($value) || (int) $value != $value) {
            return null;
        }

        $value = (int) $value;

        return $value >= 1 && $value <= 5 ? $value : null;
    }

    /**
     * @return array<string, int>
     */
    public static function scores(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $scores = [];

        foreach ($value as $handle => $score) {
            if (is_string($handle) && ($score = static::score($score)) !== null) {
                $scores[$handle] = $score;
            }
        }

        return $scores;
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected static function readPhotos(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($photo) => is_array($photo) && (is_string($photo['asset'] ?? null) || (is_string($photo['disk'] ?? null) && is_string($photo['path'] ?? null))))
            ->map(fn ($photo) => array_filter(array_intersect_key($photo, array_flip(['asset', 'disk', 'path'])), 'is_string'))
            ->values()
            ->all();
    }

    public static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (is_int($value)) {
            return CarbonImmutable::createFromTimestamp($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function id(): string
    {
        return $this->id;
    }

    public function entry(): ?EntryContract
    {
        return $this->entry === '' ? null : Entry::find($this->entry);
    }

    public function isApproved(): bool
    {
        return $this->status === ReviewStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === ReviewStatus::Pending;
    }

    /**
     * Whether the review's photos are still waiting on the private disk.
     */
    public function hasPendingPhotos(): bool
    {
        return collect($this->photos)->contains(fn ($photo) => isset($photo['disk']));
    }

    /**
     * Drivers call this as they save.
     */
    public function touch(): static
    {
        $now = CarbonImmutable::now()->startOfSecond();
        $this->createdAt ??= $now;
        $this->updatedAt = $now;

        return $this;
    }

    public function initialPath(?string $path = null): static|string|null
    {
        if (func_num_args() === 0) {
            return $this->initialPath;
        }

        $this->initialPath = $path;

        return $this;
    }

    /**
     * What the file driver stores, and the shape fromArray() reads.
     */
    public function fileData(): array
    {
        return array_filter([
            'entry' => $this->entry,
            'site' => $this->site,
            'rating' => $this->rating,
            'ratings' => $this->ratings ?: null,
            'title' => $this->title,
            'body' => $this->body,
            'name' => $this->name,
            'email' => $this->email,
            'user' => $this->user,
            'status' => $this->status->value,
            'verified' => $this->verified ?: null,
            'order' => $this->order,
            'reply' => $this->reply,
            'reply_by' => $this->replyBy,
            'replied_at' => $this->repliedAt?->toIso8601String(),
            'note' => $this->note,
            'photos' => $this->photos ?: null,
            'helpful' => $this->helpful ?: null,
            'voters' => $this->voters ?: null,
            'created_at' => $this->createdAt?->toIso8601String(),
            'updated_at' => $this->updatedAt?->toIso8601String(),
            'approved_at' => $this->approvedAt?->toIso8601String(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Everything, for exports and the JSON API.
     */
    public function toArray(): array
    {
        $data = ['id' => $this->id] + $this->fileData();
        unset($data['voters']);

        return $data + ['status' => $this->status->value, 'verified' => $this->verified, 'helpful' => $this->helpful];
    }

    /*
     * The file driver's Stache store reads and writes these.
     */

    public function path(): string
    {
        return $this->initialPath ?? rtrim(Stache::store('reviews')->directory(), '/').'/'.$this->id.'.yaml';
    }

    public function writeFile(): void
    {
        File::put($this->path(), YAML::dump($this->fileData()));
    }

    public function deleteFile(): void
    {
        File::delete($this->path());
    }

    /**
     * What the Stache indexes by.
     */
    public function getQueryableValue(string $field)
    {
        return match ($field) {
            'id' => $this->id,
            // The Stache's path index, which it compares with the files on
            // disk to notice reviews added, changed or deleted by hand.
            'path' => $this->path(),
            'entry' => $this->entry,
            'site' => $this->site,
            'status' => $this->status->value,
            'rating' => $this->rating,
            'user' => $this->user,
            'email' => $this->email !== null ? mb_strtolower($this->email) : null,
            'created_at' => $this->createdAt?->getTimestamp(),
            'verified' => $this->verified,
            'helpful' => $this->helpful,
            'order' => $this->order,
            default => null,
        };
    }
}
