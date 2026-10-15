<?php

namespace NineteenNinetyFour\Reviews;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use NineteenNinetyFour\Reviews\Events\ReviewSubmitted;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use Statamic\Contracts\Auth\User;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;

/**
 * Turning what a visitor sent from the review form into a review: who may
 * write one, checking what they wrote, and whether it's published straight
 * away or waits for an editor.
 */
class Submissions
{
    /** Session key of the orders opened from review request emails. */
    public const ORDERS = 'reviews.orders';

    public function __construct(protected Reviews $reviews, protected Photos $photos, protected Cargo $cargo) {}

    /**
     * Why someone can't review an entry, or null when they can:
     * "unavailable", "sign_in" or "buyers_only".
     */
    public function refusal(mixed $entry, ?User $user, array $orders = []): ?string
    {
        if (! $this->reviews->reviewable($entry)) {
            return 'unavailable';
        }

        if (Settings::string('who', 'anyone') === 'users' && ! $user) {
            return 'sign_in';
        }

        if ($this->buyersOnly($entry) && ! $this->cargo->purchase($this->reviews->entryId($entry), $user, $orders)) {
            return 'buyers_only';
        }

        return null;
    }

    public function buyersOnly(mixed $entry): bool
    {
        return Settings::bool('buyers_only') && $this->cargo->isProduct($entry);
    }

    /**
     * The orders the visitor opened review request emails for.
     *
     * @return array<int, string>
     */
    public static function sessionOrders(Request $request): array
    {
        if (! $request->hasSession()) {
            return [];
        }

        return array_values(array_filter((array) $request->session()->get(self::ORDERS, []), 'is_string'));
    }

    /**
     * Save a review from the form. Throws a ValidationException for what to
     * put right, and a ReviewException when they can't review at all.
     */
    public function submit(Request $request, ?User $user): Review
    {
        $orders = static::sessionOrders($request);
        $entry = $this->reviews->entry($this->input($request, 'entry', 64));

        if ($reason = $this->refusal($entry, $user, $orders)) {
            throw new ReviewException(__('reviews::messages.refusals.'.$reason));
        }

        $entryId = $this->reviews->entryId($entry);
        $existing = $this->reviews->existing($entryId, $user, $user ? null : $this->input($request, 'email', 254));

        if ($existing && ! $user) {
            throw new ReviewException(__('reviews::messages.already_reviewed'));
        }

        $data = $this->validate($request, $entry, $user, $existing);

        $review = $existing ?? Review::make();
        $review->entry = $entryId;
        $review->site ??= Site::current()->handle();
        $review->rating = (int) $data['rating'];
        $review->ratings = $data['ratings'];
        $review->title = $data['title'];
        $review->body = $data['body'];
        $review->name = $data['name'];
        $review->email = $data['email'];
        $review->user = $user ? (string) $user->id() : null;

        $order = $this->cargo->purchase($entryId, $user, $orders);
        $review->verified = $order !== null;
        $review->order = $order?->id();

        if ($existing) {
            foreach ($data['remove_photos'] as $index) {
                if (isset($review->photos[$index])) {
                    $this->photos->delete($single = Review::fromArray($review->id, ['photos' => [$review->photos[$index]]]));
                    unset($review->photos[$index]);
                }
            }
            $review->photos = array_values($review->photos);
        }

        if ($data['photos'] !== []) {
            $this->photos->store($review, $data['photos']);
        }

        // A changed review goes back to an editor, unless it'd be published
        // straight away anyway.
        $publish = $this->autoApproves($review);

        $review->submitting = true;

        if ($publish) {
            $review->status = ReviewStatus::Pending;
            $this->reviews->save($review);
            $this->reviews->approve($review);
        } elseif ($existing && ! $review->isPending()) {
            $this->reviews->hold($review);
        } else {
            $review->status = ReviewStatus::Pending;
            $this->reviews->save($review);
        }

        ReviewSubmitted::dispatch($review);
        $review->submitting = false;

        return $review;
    }

    /**
     * Whether a review is published without waiting for an editor. Never
     * decided by its rating.
     */
    public function autoApproves(Review $review): bool
    {
        return match (Settings::string('auto_approve', 'never')) {
            'users' => $review->user !== null,
            'verified' => $review->verified,
            default => false,
        };
    }

    /**
     * @return array{rating: int, ratings: array<string, int>, title: ?string, body: ?string, name: string, email: ?string, photos: array<int, UploadedFile>, remove_photos: array<int, int>}
     */
    protected function validate(Request $request, Entry $entry, ?User $user, ?Review $existing): array
    {
        $limits = [
            'title' => max(1, (int) config('reviews.form.title_limit', 120)),
            'body' => max(1, (int) config('reviews.form.body_limit', 5000)),
            'name' => max(1, (int) config('reviews.form.name_limit', 60)),
        ];

        $criteria = $this->reviews->criteria($entry);
        $photos = $this->photos->enabled();
        $kept = $existing ? count($existing->photos) - count((array) $request->input('remove_photos', [])) : 0;

        $rules = [
            'rating' => ['required', 'integer', 'between:1,5'],
            'ratings' => ['nullable', 'array'],
            'title' => [Settings::bool('require_title') ? 'required' : 'nullable', 'string', 'max:'.$limits['title']],
            'body' => [Settings::bool('require_body') ? 'required' : 'nullable', 'string', 'max:'.$limits['body'], 'min:'.max(0, (int) config('reviews.form.body_minimum', 0))],
            'name' => [$user ? 'nullable' : 'required', 'string', 'max:'.$limits['name']],
            'email' => [$user ? 'nullable' : 'required', 'string', 'email:filter', 'max:254'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer', 'min:0'],
        ];

        foreach ($criteria as $criterion) {
            $rules['ratings.'.$criterion['handle']] = ['nullable', 'integer', 'between:1,5'];
        }

        if ($photos) {
            $rules['photos'] = ['nullable', 'array', 'max:'.max(0, $this->photos->max() - max(0, $kept))];
            $rules['photos.*'] = ['file', 'image', 'mimes:'.implode(',', Photos::TYPES), 'max:'.$this->photos->maxSize()];
        }

        $attributes = ['rating' => __('reviews::messages.fields.rating'), 'title' => __('reviews::messages.fields.title'), 'body' => __('reviews::messages.fields.body'), 'name' => __('reviews::messages.fields.name'), 'email' => __('reviews::messages.fields.email'), 'photos' => __('reviews::messages.fields.photos'), 'photos.*' => __('reviews::messages.fields.photo')];

        foreach ($criteria as $criterion) {
            $attributes['ratings.'.$criterion['handle']] = $criterion['label'];
        }

        $input = [
            'rating' => $request->input('rating'),
            'ratings' => $request->input('ratings'),
            'title' => $this->input($request, 'title', null),
            'body' => $this->input($request, 'body', null),
            'name' => $this->input($request, 'name', null),
            'email' => $this->input($request, 'email', null),
            'remove_photos' => $request->input('remove_photos'),
        ];

        if ($photos) {
            $input['photos'] = array_values(array_filter((array) $request->file('photos', []), fn ($file) => $file instanceof UploadedFile));
        }

        $validator = Validator::make($input, $rules, ['rating.required' => __('reviews::messages.choose_rating')], $attributes);

        $validator->after(function ($validator) use ($input) {
            $max = max(0, (int) config('reviews.form.max_links', 2));

            if (Spam::links($input['title']) + Spam::links($input['body']) > $max) {
                $validator->errors()->add('body', __('reviews::messages.too_many_links'));
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $ratings = [];

        foreach ($criteria as $criterion) {
            $score = Review::score($input['ratings'][$criterion['handle']] ?? null);

            if ($score !== null) {
                $ratings[$criterion['handle']] = $score;
            }
        }

        $name = $input['name'] ?? ($user ? $this->userName($user) : '');

        return [
            'rating' => (int) $input['rating'],
            'ratings' => $ratings,
            'title' => $input['title'],
            'body' => $input['body'],
            'name' => $name,
            'email' => $user ? ($user->email() ?: $input['email']) : $input['email'],
            'photos' => $photos ? $input['photos'] : [],
            'remove_photos' => collect((array) ($input['remove_photos'] ?? []))->filter(fn ($i) => is_numeric($i))->map(fn ($i) => (int) $i)->unique()->values()->all(),
        ];
    }

    /**
     * A signed-in reviewer's name, when the form doesn't give one.
     */
    protected function userName(User $user): string
    {
        $name = trim((string) ($user->name() ?? ''));

        if ($name === '' || $name === $user->email()) {
            return (string) __('reviews::messages.anonymous');
        }

        return mb_substr($name, 0, max(1, (int) config('reviews.form.name_limit', 60)));
    }

    /**
     * Text from the form: a string, trimmed, or null. Too long is left for
     * validation to report.
     */
    protected function input(Request $request, string $key, ?int $limit): ?string
    {
        $value = $request->input($key);

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim(str_replace("\0", '', (string) $value));

        if ($value === '' || ($limit !== null && mb_strlen($value) > $limit)) {
            return null;
        }

        return $value;
    }
}
