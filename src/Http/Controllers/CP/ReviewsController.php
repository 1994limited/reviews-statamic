<?php

namespace NineteenNinetyFour\Reviews\Http\Controllers\CP;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use NineteenNinetyFour\Reviews\Photos;
use NineteenNinetyFour\Reviews\Review;
use NineteenNinetyFour\Reviews\ReviewQuery;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\ReviewStatus;
use Statamic\Facades\CP\Toast;
use Statamic\Facades\Site;
use Statamic\Facades\User;
use Statamic\Http\Controllers\CP\CpController;

/**
 * Content → Reviews: the moderation queue, each review, and approving,
 * rejecting, replying and deleting.
 */
class ReviewsController extends CpController
{
    public function __construct(Request $request, protected Reviews $reviews, protected Photos $photos)
    {
        parent::__construct($request);
    }

    public function index(Request $request): Response
    {
        $this->authorize('view reviews');

        $counts = $this->reviews->repository()->statusCounts();
        $status = $this->statusFilter($request, $counts);
        $search = is_string($request->query('search')) ? mb_substr(trim($request->query('search')), 0, 100) : null;
        $rating = is_numeric($request->query('rating')) ? Review::score($request->query('rating')) : null;
        $entry = is_string($request->query('entry')) && preg_match('/^[\w-]{1,64}$/', $request->query('entry')) ? $request->query('entry') : null;
        $site = is_string($request->query('site')) ? Site::get($request->query('site')) : null;
        $page = max(1, (int) $request->query('page', 1));

        $query = ReviewQuery::make()
            ->status($status === 'all' ? null : ReviewStatus::from($status))
            ->search($search)
            ->rating($rating)
            ->entry($entry)
            ->site($site?->handle())
            ->sort($status === 'pending' ? 'oldest' : 'newest');

        $paginator = $this->reviews->repository()->paginate($query, 25, $page);
        $user = User::current();

        return Inertia::render('reviews::Index', [
            'reviews' => collect($paginator->items())->map(fn (Review $review) => $this->row($review))->all(),
            'counts' => $counts,
            'status' => $status,
            'statuses' => collect(ReviewStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'search' => $search,
            'rating' => $rating,
            'site' => $site ? ['handle' => $site->handle(), 'name' => $site->name()] : null,
            'entry' => $entry ? ['id' => $entry, 'title' => (string) ($this->reviews->entry($entry)?->get('title') ?? $entry)] : null,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
            'can' => $this->can($user),
            'urls' => [
                'index' => cp_route('reviews.index'),
                'bulk' => cp_route('reviews.bulk'),
            ],
        ]);
    }

    public function show(string $review): Response
    {
        $this->authorize('view reviews');

        $review = $this->find($review);
        $entry = $this->reviews->entry($review->entry, $review->site);
        $user = User::current();

        $reviewer = $review->user ? User::find($review->user) : null;
        $replier = $review->replyBy ? User::find($review->replyBy) : null;

        return Inertia::render('reviews::Show', [
            'review' => $this->row($review) + [
                'email' => $review->email,
                'user' => $reviewer ? ['name' => $reviewer->name() ?: $reviewer->email(), 'url' => $reviewer->editUrl()] : null,
                'order' => $review->order,
                'order_url' => $review->order && Route::has('statamic.cp.cargo.orders.edit') ? cp_route('cargo.orders.edit', $review->order) : null,
                'body' => $review->body,
                'ratings' => collect($this->reviews->criteria($entry))
                    ->filter(fn ($c) => isset($review->ratings[$c['handle']]))
                    ->map(fn ($c) => ['label' => $c['label'], 'rating' => $review->ratings[$c['handle']]])
                    ->values()->all(),
                'reply' => $review->reply,
                'replied_by' => $replier ? ($replier->name() ?: $replier->email()) : null,
                'replied_at' => $review->repliedAt?->toIso8601String(),
                'note' => $review->note,
                'photos' => $this->photoUrls($review),
                'site_name' => Site::multiEnabled() ? Site::get((string) $review->site)?->name() : null,
                'approved_at' => $review->approvedAt?->toIso8601String(),
                'updated_at' => $review->updatedAt?->toIso8601String(),
                'entry_edit_url' => $entry?->editUrl(),
                'entry_reviews_url' => cp_route('reviews.index', ['entry' => $review->entry, 'status' => 'all']),
            ],
            'statuses' => collect(ReviewStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->all(),
            'can' => $this->can($user),
            'urls' => [
                'index' => cp_route('reviews.index'),
                'status' => cp_route('reviews.status', $review->id),
                'reply' => cp_route('reviews.reply', $review->id),
                'note' => cp_route('reviews.note', $review->id),
                'destroy' => cp_route('reviews.destroy', $review->id),
            ],
        ]);
    }

    /**
     * Approve, reject, mark as spam, or put back to pending.
     */
    public function status(Request $request, string $review): RedirectResponse
    {
        $this->authorize('moderate reviews');

        $request->validate([
            'status' => ['required', 'in:'.implode(',', array_map(fn ($s) => $s->value, ReviewStatus::cases()))],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $this->find($review);
        $this->setStatus($review, ReviewStatus::from($request->input('status')), $request->input('note'));

        Toast::success(__('Review :status.', ['status' => mb_strtolower($review->status->label())]));

        return back();
    }

    public function reply(Request $request, string $review): RedirectResponse
    {
        $this->authorize('reply to reviews');

        $request->validate(['reply' => ['nullable', 'string', 'max:5000']]);

        $review = $this->find($review);
        $this->reviews->reply($review, $request->input('reply'), User::current());

        Toast::success($review->reply === null ? __('Reply removed.') : __('Reply saved.'));

        return back();
    }

    /**
     * An editor's note, never shown on the site.
     */
    public function note(Request $request, string $review): RedirectResponse
    {
        $this->authorize('moderate reviews');

        $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $review = $this->find($review);
        $note = trim((string) $request->input('note'));
        $review->note = $note === '' ? null : $note;
        $this->reviews->save($review);

        Toast::success(__('Note saved.'));

        return back();
    }

    public function destroy(string $review): RedirectResponse
    {
        $this->authorize('delete reviews');

        $this->reviews->delete($this->find($review));

        Toast::success(__('Review deleted.'));

        return redirect(cp_route('reviews.index'));
    }

    /**
     * Several reviews at once, from the list.
     */
    public function bulk(Request $request): RedirectResponse
    {
        $request->validate([
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['string', 'max:64'],
            'action' => ['required', 'in:approved,rejected,spam,pending,delete'],
        ]);

        $action = $request->input('action');
        $this->authorize($action === 'delete' ? 'delete reviews' : 'moderate reviews');

        $done = 0;

        foreach ($request->input('ids') as $id) {
            $review = $this->reviews->find((string) $id);

            if (! $review) {
                continue;
            }

            $action === 'delete' ? $this->reviews->delete($review) : $this->setStatus($review, ReviewStatus::from($action));
            $done++;
        }

        Toast::success(trans_choice(':count review updated.|:count reviews updated.', $done, ['count' => $done]));

        return back();
    }

    /**
     * A photo still waiting on the private disk, for editors only.
     */
    public function photo(string $review, int $index): HttpResponse
    {
        $this->authorize('view reviews');

        $contents = $this->photos->pendingContents($this->find($review), $index);

        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function setStatus(Review $review, ReviewStatus $status, ?string $note = null): void
    {
        match ($status) {
            ReviewStatus::Approved => $this->reviews->approve($review),
            ReviewStatus::Rejected => $this->reviews->reject($review, $note),
            ReviewStatus::Spam => $this->reviews->reject($review, $note, ReviewStatus::Spam),
            ReviewStatus::Pending => $review->isPending() ? null : $this->reviews->hold($review),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(Review $review): array
    {
        $entry = $this->reviews->entry($review->entry, $review->site);

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'title' => $review->title,
            'excerpt' => $review->body !== null ? mb_strimwidth(preg_replace('/\s+/u', ' ', $review->body) ?? '', 0, 160, '…') : null,
            'name' => $review->name,
            'verified' => $review->verified,
            'status' => $review->status->value,
            'status_label' => $review->status->label(),
            'has_reply' => $review->reply !== null,
            'photos' => count($review->photos),
            'helpful' => $review->helpful,
            'entry_id' => $review->entry,
            'entry_title' => (string) ($entry?->get('title') ?? __('(deleted entry)')),
            'entry_url' => $entry?->absoluteUrl(),
            'created_at' => $review->createdAt?->toIso8601String(),
            'created_ago' => $review->createdAt?->diffForHumans(),
            'url' => cp_route('reviews.show', $review->id),
        ];
    }

    /**
     * @return array<int, array{url: string, pending: bool}>
     */
    protected function photoUrls(Review $review): array
    {
        $assets = collect($this->photos->assets($review))->keyBy(fn ($asset) => $asset->id());

        return collect($review->photos)->map(function ($photo, $index) use ($assets, $review) {
            if (isset($photo['asset'])) {
                $asset = $assets->get($photo['asset']);

                return $asset ? ['url' => $asset->url(), 'pending' => false, 'edit_url' => $asset->editUrl()] : null;
            }

            return ['url' => cp_route('reviews.photo', [$review->id, $index]), 'pending' => true, 'edit_url' => null];
        })->filter()->values()->all();
    }

    /**
     * The tab to show: the one asked for, or Pending while anything waits.
     *
     * @param  array<string, int>  $counts
     */
    protected function statusFilter(Request $request, array $counts): string
    {
        $status = $request->query('status');

        if (is_string($status) && ($status === 'all' || ReviewStatus::tryFrom($status))) {
            return $status;
        }

        return ($counts['pending'] ?? 0) > 0 ? 'pending' : 'all';
    }

    /**
     * @return array<string, bool>
     */
    protected function can($user): array
    {
        return [
            'moderate' => (bool) $user?->can('moderate reviews'),
            'reply' => (bool) $user?->can('reply to reviews'),
            'delete' => (bool) $user?->can('delete reviews'),
        ];
    }

    protected function find(string $id): Review
    {
        $review = $this->reviews->find($id);

        abort_unless($review, 404);

        return $review;
    }
}
