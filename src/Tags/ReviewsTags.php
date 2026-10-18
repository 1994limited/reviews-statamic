<?php

namespace NineteenNinetyFour\Reviews\Tags;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\MessageBag;
use NineteenNinetyFour\Reviews\Photos;
use NineteenNinetyFour\Reviews\Presenter;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\Schema;
use NineteenNinetyFour\Reviews\Settings;
use NineteenNinetyFour\Reviews\Spam;
use NineteenNinetyFour\Reviews\Submissions;
use NineteenNinetyFour\Reviews\Summary;
use Statamic\Contracts\Entries\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Tags\Concerns\RendersForms;
use Statamic\Tags\Tags;

/**
 * {{ reviews }} and friends. Each finds its entry from entry="…", or the
 * entry the template is showing. Visitors' text comes out escaped.
 */
class ReviewsTags extends Tags
{
    use RendersForms;

    protected static $handle = 'reviews';

    /**
     * {{ reviews }} ... {{ /reviews }}
     *
     * The entry's approved reviews. sort="newest|oldest|highest|lowest|helpful",
     * rating="5" (or "4|5"), verified="true", photos="true",
     * site="current", limit="3", paginate="10" with page_name="reviews_page".
     */
    public function index(): array|string
    {
        $entry = $this->entry();

        if (! $entry) {
            return $this->isPair ? $this->parseNoResults() : [];
        }

        $query = ReviewQuery::make()
            ->entry($this->reviews()->entryId($entry))
            ->approved()
            ->sort($this->param('sort'))
            ->rating($this->ratingsParam())
            ->verified($this->params->has('verified') ? $this->params->bool('verified') : null)
            ->withPhotos($this->params->has('photos') ? $this->params->bool('photos') : null)
            ->site($this->siteParam());

        $viewer = User::current();
        $presenter = app(Presenter::class);
        $perPage = $this->params->int('paginate');

        if ($perPage > 0) {
            $pageName = $this->param('page_name') ?? 'reviews_page';
            $page = max(1, (int) (is_numeric(request()->query($pageName)) ? request()->query($pageName) : 1));
            $paginator = $this->reviews()->repository()->paginate($query, min(100, $perPage), $page);
            $reviews = collect($paginator->items())->map(fn (Review $review) => $presenter->review($review, $viewer))->all();

            return $this->output($reviews, $this->pagination($paginator, $pageName));
        }

        $limit = $this->params->int('limit') ?: null;
        $reviews = $this->reviews()->repository()->get($query, $limit ? min(100, $limit) : null)
            ->map(fn (Review $review) => $presenter->review($review, $viewer))
            ->all();

        return $this->output($reviews);
    }

    /**
     * {{ reviews:summary }} {{ average }} {{ count }} {{ breakdown }} ... {{ /reviews:summary }}
     *
     * The entry's average rating, how many reviews, how many of each star,
     * and each criterion's average.
     */
    public function summary(): array
    {
        $entry = $this->entry();

        if (! $entry) {
            return app(Presenter::class)->summary(Summary::from([]));
        }

        $summary = $this->reviews()->summary($this->reviews()->entryId($entry), $this->siteParam());

        return app(Presenter::class)->summary($summary, $this->reviews()->criteria($entry));
    }

    /**
     * {{ reviews:count }}
     */
    public function count(): int
    {
        return $this->summary()['count'];
    }

    /**
     * {{ reviews:average }}, to one decimal place (empty with no reviews).
     */
    public function average(): ?float
    {
        return $this->summary()['average'];
    }

    /**
     * {{ reviews:stars :rating="average" }} {{ if full }}★{{ elseif half }}⯪{{ else }}☆{{ /if }} {{ /reviews:stars }}
     *
     * Five stars for any rating, each full, half or empty.
     */
    public function stars(): array
    {
        $rating = $this->params->get('rating');

        return Presenter::stars(is_numeric($rating) ? max(0, min(5, (float) $rating)) : 0);
    }

    /**
     * {{ reviews:form }} ... {{ /reviews:form }}
     *
     * The review form, for the entry. Your own fields go inside; the tag
     * adds the entry, the spam checks and CSRF. Works without JavaScript.
     */
    public function form(): string
    {
        $entry = $this->entry();

        if (! $entry) {
            return '';
        }

        $user = User::current();
        $submissions = app(Submissions::class);
        $refusal = $submissions->refusal($entry, $user, Submissions::sessionOrders(request()));
        $existing = $user ? $this->reviews()->existing($this->reviews()->entryId($entry), $user) : null;
        $photos = app(Photos::class);
        $spam = app(Spam::class);
        $customer = (array) session('reviews.customer', []);

        $data = [
            'entry_id' => $this->reviews()->entryId($entry),
            'can_review' => $refusal === null,
            'cannot_review' => $refusal !== null,
            'reason' => $refusal,
            'reason_message' => $refusal ? e(__('reviews::messages.refusals.'.$refusal)) : null,
            'signed_in' => $user !== null,
            'user_name' => $user ? e((string) $user->name()) : null,
            'needs_name' => $user === null,
            'needs_email' => $user === null,
            'buyers_only' => $submissions->buyersOnly($entry),
            'criteria' => $this->formCriteria($entry, $existing),
            'old' => $this->old($existing, $customer),
            'errors' => $this->errorMessages(),
            'error_list' => array_values($this->errorMessages()),
            'has_errors' => $this->errorMessages() !== [],
            'success' => $this->success(),
            'editing' => $existing !== null,
            'review' => $existing ? app(Presenter::class)->review($existing, $user) + ['pending' => $existing->isPending()] : null,
            'existing_photos' => $existing ? $this->existingPhotos($existing) : [],
            'photos_enabled' => $photos->enabled(),
            'max_photos' => $photos->max(),
            'max_photo_size' => $photos->maxSize(),
            'title_limit' => (int) config('reviews.form.title_limit', 120),
            'body_limit' => (int) config('reviews.form.body_limit', 5000),
            'name_limit' => (int) config('reviews.form.name_limit', 60),
            'captcha' => $spam->captchaWidget(),
        ];

        $attrs = $photos->enabled() ? ['enctype' => 'multipart/form-data'] : [];
        $html = $this->formOpen(url('!/reviews/submit'), 'POST', $this->knownParams(), $attrs);
        $html .= '<input type="hidden" name="entry" value="'.e($data['entry_id']).'">';
        $html .= $this->redirectFields();
        $html .= $spam->fields();

        $content = $this->isPair ? (string) $this->parse($data) : '';

        // The captcha, at the end of the form if the template didn't place it.
        if ($data['captcha'] !== '' && ! str_contains($content, 'data-reviews-captcha')) {
            $content .= $data['captcha'];
        }

        return $html.$content.$this->formClose().$spam->script();
    }

    /**
     * {{ reviews:helpful }} Helpful ({{ helpful }}) {{ /reviews:helpful }}
     *
     * A "Was this helpful?" button's form, inside {{ reviews }}.
     */
    public function helpful(): string
    {
        $id = $this->param('id') ?? $this->contextValue('id');

        if (! is_string($id) || ! preg_match('/^[a-z0-9]{1,64}$/', $id)) {
            return '';
        }

        $html = $this->formOpen(url('!/reviews/'.$id.'/helpful'), 'POST', $this->knownParams());
        $html .= $this->redirectFields();
        $html .= $this->isPair ? (string) $this->parse(['id' => $id]) : '<button type="submit">'.e(__('reviews::messages.helpful')).'</button>';

        return $html.$this->formClose();
    }

    /**
     * {{ reviews:delete }} <button>Delete my review</button> {{ /reviews:delete }}
     *
     * Deleting your own review, inside {{ reviews }} (where {{ is_mine }}).
     */
    public function delete(): string
    {
        $id = $this->param('id') ?? $this->contextValue('id');

        if (! is_string($id) || $id === '') {
            return '';
        }

        $html = $this->formOpen(url('!/reviews/delete'), 'POST', $this->knownParams());
        $html .= '<input type="hidden" name="id" value="'.e($id).'">';
        $html .= $this->redirectFields();
        $html .= $this->isPair ? (string) $this->parse(['id' => $id]) : '<button type="submit">'.e(__('reviews::messages.delete')).'</button>';

        return $html.$this->formClose();
    }

    /**
     * {{ reviews:success }}: the message after a review is sent or a vote
     * counted, escaped.
     */
    public function success(): ?string
    {
        $message = session('reviews_success');

        return is_string($message) ? e($message) : null;
    }

    /**
     * {{ reviews:errors }} {{ message }} {{ /reviews:errors }}, or just
     * {{ reviews:errors }} for the first.
     */
    public function errors(): array|string|null
    {
        $messages = $this->errorMessages();

        if ($this->isPair) {
            return collect($messages)->map(fn ($message, $field) => ['field' => $field, 'message' => $message])->values()->all();
        }

        return collect($messages)->first();
    }

    /**
     * {{ reviews:schema }}: the review schema (JSON-LD) for the entry, with
     * type="Book" (or any schema.org type), id="#product" to join your own
     * schema, and reviews="5".
     */
    public function schema(): string
    {
        $entry = $this->entry();

        if (! $entry) {
            return '';
        }

        return app(Schema::class)->script(
            $entry,
            $this->param('type'),
            $this->param('id'),
            $this->params->has('reviews') ? max(0, $this->params->int('reviews')) : null,
        );
    }

    /**
     * {{ reviews:schema_data }}: the review schema's rating and reviews as
     * JSON, without a script tag, to add to schema you print yourself or
     * another addon's: {"aggregateRating": {…}, "review": […]}.
     * only="aggregate" for the AggregateRating object alone; reviews="0"
     * to leave the reviews out; format="properties" for the properties
     * without braces, each ending in a comma, to put first in JSON-LD of
     * your own. Empty when there's no approved review.
     */
    public function schemaData(): string
    {
        $entry = $this->entry();
        $parts = $entry ? app(Schema::class)->parts($entry, $this->params->has('reviews') ? max(0, $this->params->int('reviews')) : null) : null;

        if ($parts === null) {
            return '';
        }

        if ($this->param('only') === 'aggregate') {
            $parts = ['aggregateRating' => $parts['aggregateRating']];

            if ($this->param('format') !== 'properties') {
                return Schema::encode($parts['aggregateRating']);
            }
        }

        // The properties alone, each followed by a comma, to go straight
        // after the "{" of JSON-LD written elsewhere (an SEO addon's schema
        // field): valid JSON with reviews or without.
        if ($this->param('format') === 'properties') {
            return implode('', array_map(fn ($key, $value) => Schema::encode([$key => $value]) === '' ? '' : substr(Schema::encode([$key => $value]), 1, -1).',', array_keys($parts), $parts));
        }

        return Schema::encode($parts);
    }

    /**
     * {{ reviews:can_review }}: whether the visitor may review the entry.
     */
    public function canReview(): bool
    {
        $entry = $this->entry();

        return $entry !== null && app(Submissions::class)->refusal($entry, User::current(), Submissions::sessionOrders(request())) === null;
    }

    /*
    |--------------------------------------------------------------------------
    | Ready-made views
    |--------------------------------------------------------------------------
    */

    /**
     * {{ reviews:display }}: the average, the reviews and the form, ready
     * made. heading="…", sort="helpful" (the order until a visitor
     * chooses), per_page="10", form="false" to leave the form out.
     */
    public function display(): string
    {
        $data = $this->displayData();

        if ($data === null) {
            return '';
        }

        $parts = ['wrap' => false] + $data;

        return view('reviews::display.section', $data + [
            'heading' => $this->param('heading') ?? __('reviews::messages.display.heading'),
            'summary' => view('reviews::display.summary', $parts)->render(),
            'list' => view('reviews::display.list', $parts)->render(),
            'form' => $data['show_form'] ? view('reviews::display.form', $parts)->render() : '',
        ])->render();
    }

    /**
     * {{ reviews:display_summary }}: the ready-made average and breakdown.
     */
    public function displaySummary(): string
    {
        return $this->displayPart('summary');
    }

    /**
     * {{ reviews:display_list }}: the ready-made list of reviews.
     */
    public function displayList(): string
    {
        return $this->displayPart('list');
    }

    /**
     * {{ reviews:display_form }}: the ready-made review form.
     */
    public function displayForm(): string
    {
        return $this->displayPart('form');
    }

    /**
     * {{ reviews:styles }}: the default styles for the ready-made views, as
     * a <link> for your <head>.
     */
    public function styles(): string
    {
        return '<link rel="stylesheet" href="'.e(static::stylesheetUrl()).'">';
    }

    /**
     * {{ reviews:styles_url }}: the default stylesheet's address, to link
     * to or load yourself.
     */
    public function stylesUrl(): string
    {
        return static::stylesheetUrl();
    }

    public static function stylesheetUrl(): string
    {
        return url('!/reviews/reviews.css').'?v='.substr((string) md5_file(static::stylesheetPath()), 0, 12);
    }

    public static function stylesheetPath(): string
    {
        return __DIR__.'/../../resources/css/reviews.css';
    }

    protected function displayPart(string $part): string
    {
        $data = $this->displayData();

        return $data === null ? '' : view('reviews::display.'.$part, ['wrap' => true] + $data)->render();
    }

    /**
     * What the ready-made views need, or null without an entry.
     *
     * @return array<string, mixed>|null
     */
    protected function displayData(): ?array
    {
        $entry = $this->entry();

        if (! $entry) {
            return null;
        }

        $sorts = ['helpful', 'newest', 'highest', 'lowest'];
        $requested = request()->query('sort');
        $default = $this->param('sort');
        $sort = is_string($requested) && in_array($requested, $sorts, true) ? $requested : (in_array($default, $sorts, true) ? $default : 'helpful');

        $rating = request()->query('rating');
        $rating = is_string($rating) ? Review::score($rating) : null;

        $current = url()->current();

        return [
            'entry' => $entry->id(),
            'sort' => $sort,
            'sorts' => collect($sorts)->map(fn ($value) => [
                'value' => $value,
                'label' => __('reviews::messages.display.sorts.'.$value),
                'selected' => $value === $sort,
            ])->all(),
            'rating' => $rating,
            'per_page' => max(1, min(50, $this->params->int('per_page', (int) config('reviews.per_page', 10)))),
            'show_form' => $this->params->bool('form', true),
            'redirect' => $current.'#reviews',
            'error_redirect' => $current.'#write-a-review',
            'title_required' => Settings::bool('require_title'),
            'body_required' => Settings::bool('require_body'),
            'site_name' => e(Site::current()->name()),
            // "Checked before they appear" is only true when nothing is published straight away.
            'moderated' => Settings::string('auto_approve', 'never') === 'never',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    protected function entry(): ?Entry
    {
        $id = $this->param('entry') ?? $this->contextValue('id');

        if (! is_string($id) || $id === '') {
            return null;
        }

        $entry = $this->reviews()->entry($id, Site::current()->handle());

        return $entry instanceof Entry ? $entry : null;
    }

    protected function param(string $key): ?string
    {
        $value = $this->params->get($key);
        $value = is_object($value) && method_exists($value, 'value') ? $value->value() : $value;

        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    protected function contextValue(string $key): mixed
    {
        $value = $this->context->get($key);

        return is_object($value) && method_exists($value, 'value') ? $value->value() : $value;
    }

    /**
     * @return array<int, int>|null
     */
    protected function ratingsParam(): ?array
    {
        $rating = $this->param('rating');

        return $rating === null ? null : array_map('intval', explode('|', $rating));
    }

    /**
     * The site to show reviews from: the site="" parameter, or else the
     * current site when each site has its own reviews (and every site's
     * when they're shared).
     */
    protected function siteParam(): ?string
    {
        $site = $this->param('site');

        if ($site === null) {
            return $this->reviews()->siteScope();
        }

        if ($site === 'all') {
            return null;
        }

        return $site === 'current' ? Site::current()->handle() : $site;
    }

    protected function output(array $reviews, array $pagination = []): array|string
    {
        if (! $this->isPair && ! $this->params->has('as')) {
            return $reviews;
        }

        if ($reviews === [] && $this->isPair && ! $this->params->has('as')) {
            return $this->parseNoResults();
        }

        $data = [
            'total_results' => $pagination['total_items'] ?? count($reviews),
            'no_results' => $reviews === [],
        ];

        if ($as = $this->param('as')) {
            return $this->parse([$as => $reviews, 'paginate' => $pagination ?: null] + $data);
        }

        return $this->parseLoop(array_map(fn ($review) => $review + ['paginate' => $pagination ?: null], $reviews));
    }

    protected function pagination(LengthAwarePaginator $paginator, string $pageName): array
    {
        $url = fn (int $page) => request()->fullUrlWithQuery([$pageName => $page]);

        return [
            'total_items' => $paginator->total(),
            'items_per_page' => $paginator->perPage(),
            'total_pages' => $paginator->lastPage(),
            'current_page' => $paginator->currentPage(),
            'prev_page' => $paginator->currentPage() > 1 ? $url($paginator->currentPage() - 1) : null,
            'next_page' => $paginator->hasMorePages() ? $url($paginator->currentPage() + 1) : null,
            'links' => collect(range(1, max(1, $paginator->lastPage())))->map(fn ($page) => [
                'page' => $page,
                'url' => $url($page),
                'current' => $page === $paginator->currentPage(),
            ])->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formCriteria(Entry $entry, ?Review $existing): array
    {
        $old = session()->getOldInput('ratings');

        return collect($this->reviews()->criteria($entry))->map(function ($criterion) use ($old, $existing) {
            $value = is_array($old) ? Review::score($old[$criterion['handle']] ?? null) : ($existing->ratings[$criterion['handle']] ?? null);

            return [
                'handle' => $criterion['handle'],
                'label' => e($criterion['label']),
                'name' => 'ratings['.$criterion['handle'].']',
                'old' => $value,
                'error' => $this->errorMessages()['ratings.'.$criterion['handle']] ?? null,
            ];
        })->all();
    }

    /**
     * What the form was last filled in with: from a failed submission, the
     * visitor's own review, or the order they came from.
     *
     * @return array<string, mixed>
     */
    protected function old(?Review $existing, array $customer): array
    {
        $old = fn (string $key) => session()->getOldInput($key);
        $hasOld = session()->hasOldInput();

        $value = function (string $key, ?string $fallback) use ($old, $hasOld) {
            $value = $hasOld ? $old($key) : $fallback;

            return is_scalar($value) && (string) $value !== '' ? e((string) $value) : null;
        };

        return [
            'rating' => Review::score($hasOld ? $old('rating') : $existing?->rating),
            'title' => $value('title', $existing?->title),
            'body' => $value('body', $existing?->body),
            'name' => $value('name', $existing?->name ?? ($customer['name'] ?? null)),
            'email' => $value('email', $existing?->email ?? ($customer['email'] ?? null)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function existingPhotos(Review $review): array
    {
        $assets = collect(app(Photos::class)->assets($review))->keyBy(fn ($asset) => $asset->id());

        return collect($review->photos)->map(fn ($photo, $index) => [
            'index' => $index,
            'url' => isset($photo['asset']) ? $assets->get($photo['asset'])?->url() : null,
            'pending' => isset($photo['disk']),
        ])->values()->all();
    }

    /**
     * @return array<string, string>
     */
    protected function errorMessages(): array
    {
        return collect($this->errorBag()?->messages() ?? [])
            ->map(fn (array $messages) => e((string) ($messages[0] ?? '')))
            ->all();
    }

    protected function errorBag(): ?MessageBag
    {
        $errors = session('errors');

        return $errors && method_exists($errors, 'getBag') ? $errors->getBag('reviews') : null;
    }

    protected function redirectFields(): string
    {
        $html = '';

        foreach (['redirect' => '_redirect', 'error_redirect' => '_error_redirect'] as $param => $field) {
            if ($value = $this->param($param)) {
                $html .= '<input type="hidden" name="'.$field.'" value="'.e($value).'">';
            }
        }

        return $html;
    }

    /**
     * @return array<int, string>
     */
    protected function knownParams(): array
    {
        return ['entry', 'id', 'redirect', 'error_redirect', 'sort', 'rating', 'verified', 'photos', 'site', 'limit', 'paginate', 'page_name', 'as', 'type', 'reviews', 'csrf', 'heading', 'per_page', 'form', 'format', 'only'];
    }

    protected function reviews(): Reviews
    {
        return app(Reviews::class);
    }
}
