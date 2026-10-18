<?php

namespace NineteenNinetyFour\Reviews\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use NineteenNinetyFour\Reviews\Contracts\RequestLog;
use NineteenNinetyFour\Reviews\DuplicateReview;
use NineteenNinetyFour\Reviews\Http\ReadsText;
use NineteenNinetyFour\Reviews\Integrations\Cargo;
use NineteenNinetyFour\Reviews\Presenter;
use NineteenNinetyFour\Reviews\ReviewException;
use NineteenNinetyFour\Reviews\Reviews;
use NineteenNinetyFour\Reviews\Spam;
use NineteenNinetyFour\Reviews\Submissions;
use NineteenNinetyFour\Reviews\Tags\ReviewsTags;
use Statamic\Facades\User;

/**
 * The front-end actions, at /!/reviews/... Each answers JSON to fetch()
 * (Accept: application/json) and redirects back to a plain form post, with
 * a message flashed as "reviews_success" or errors in the "reviews" error
 * bag.
 */
class ActionsController
{
    use ReadsText;

    /** The cookie that tells one guest's votes from another's. */
    public const VOTER_COOKIE = 'reviews_voter';

    public function __construct(
        protected Reviews $reviews,
        protected Submissions $submissions,
        protected Spam $spam,
        protected Presenter $presenter,
    ) {}

    public function submit(Request $request): JsonResponse|RedirectResponse
    {
        // A bot is told it worked, and nothing is kept.
        if ($this->spam->isBot($request)) {
            return $this->success($request, __('reviews::messages.thanks_pending'));
        }

        if (! $this->spam->passesCaptcha($request)) {
            return $this->failure($request, ['captcha' => __('reviews::messages.captcha')]);
        }

        try {
            $review = $this->submissions->submit($request, User::current());
        } catch (ValidationException $e) {
            return $this->failure($request, $e->errors());
        } catch (DuplicateReview) {
            return $this->success($request, __('reviews::messages.thanks_pending'));
        } catch (ReviewException $e) {
            return $this->failure($request, ['review' => $e->getMessage()]);
        }

        $message = $review->isApproved() ? __('reviews::messages.thanks_published') : __('reviews::messages.thanks_pending');

        return $this->success($request, $message, ['review' => ['id' => $review->id, 'status' => $review->status->value]]);
    }

    /**
     * The default stylesheet. Cached for a year when asked for with its
     * version (?v=…), which changes whenever the file does.
     */
    public function styles(Request $request): Response
    {
        $path = ReviewsTags::stylesheetPath();
        $versioned = $request->query('v') === substr((string) md5_file($path), 0, 12);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => $versioned ? 'public, max-age=31536000, immutable' : 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A signed-in reviewer deleting their own review.
     */
    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        $user = User::current();
        $review = $this->reviews->find((string) $this->text($request, 'id', 64));

        if (! $user || ! $review || $review->user !== (string) $user->id()) {
            return $this->failure($request, ['review' => __('reviews::messages.not_yours')]);
        }

        $this->reviews->delete($review);

        return $this->success($request, __('reviews::messages.deleted'));
    }

    /**
     * "Was this helpful?" One vote per visitor: their user account, or a
     * cookie for guests.
     */
    public function helpful(Request $request, string $review): JsonResponse|RedirectResponse
    {
        $found = $this->reviews->find($review);

        // Votes are for this site's own reviews, not ones from elsewhere.
        if (! $found || ! $found->isApproved() || ! $found->isOwn()) {
            return $this->failure($request, ['review' => __('reviews::messages.not_found')]);
        }

        [$voter, $cookie] = $this->voter($request);
        $voted = $this->reviews->vote($found, $voter);

        $response = $voted
            ? $this->success($request, __('reviews::messages.voted'), ['helpful' => $found->helpful])
            : $this->success($request, __('reviews::messages.voted_already'), ['helpful' => $found->helpful]);

        return $cookie ? $response->withCookie($cookie) : $response;
    }

    /**
     * Where a review request email's links lead: the order is remembered for
     * this visit, so the review counts as a verified purchase, and they're
     * sent on to the product's page. The link is signed.
     */
    public function fromOrder(Request $request, Cargo $cargo, string $order, string $product): RedirectResponse
    {
        $found = $cargo->order($order);
        $entry = $this->reviews->entry($product);

        if (! $found || ! $this->reviews->reviewable($entry) || ! in_array($this->reviews->entryId($entry), $cargo->products($found), true)) {
            abort(404);
        }

        $orders = Submissions::sessionOrders($request);
        $request->session()->put(Submissions::ORDERS, array_values(array_unique([...array_slice($orders, -9), (string) $found->id()])));

        $customer = $cargo->customer($found);
        $request->session()->put('reviews.customer', array_filter($customer));

        return redirect()->to(($entry->url() ?? '/').'#review');
    }

    /**
     * Asking for no more review request emails: a page with a button, so a
     * mail scanner following the link doesn't unsubscribe anyone.
     */
    public function optOut(Request $request): Response
    {
        $email = (string) $this->text($request, 'email', 254);

        return response()->view('reviews::opt-out', [
            'email' => $email,
            'action' => $request->fullUrl(),
            'done' => false,
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function confirmOptOut(Request $request, RequestLog $log): Response
    {
        $email = (string) $this->text($request, 'email', 254);

        if ($email !== '') {
            $log->optOut($email);
        }

        return response()->view('reviews::opt-out', ['email' => $email, 'action' => null, 'done' => true])->header('X-Robots-Tag', 'noindex');
    }

    /**
     * The visitor's hashed voter key, and a cookie to set for a new guest.
     *
     * @return array{0: string, 1: ?\Symfony\Component\HttpFoundation\Cookie}
     */
    protected function voter(Request $request): array
    {
        if ($user = User::current()) {
            return [$this->hash('u:'.$user->id()), null];
        }

        $token = $request->cookie(self::VOTER_COOKIE);
        $cookie = null;

        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9]{32}$/', $token)) {
            $token = Str::random(32);
            $cookie = Cookie::make(self::VOTER_COOKIE, $token, 60 * 24 * 365, httpOnly: true, sameSite: 'lax');
        }

        return [$this->hash('g:'.$token), $cookie];
    }

    protected function hash(string $value): string
    {
        return substr(hash_hmac('sha256', $value, (string) config('app.key')), 0, 40);
    }

    protected function success(Request $request, string $message, array $data = []): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => $message] + $data);
        }

        return $this->back($request)->with('reviews_success', $message);
    }

    /**
     * @param  array<string, string|array<int, string>>  $errors
     */
    protected function failure(Request $request, array $errors): JsonResponse|RedirectResponse
    {
        if ($request->wantsJson()) {
            return response()->json(['message' => collect($errors)->flatten()->first(), 'errors' => $errors], 422);
        }

        return $this->back($request, $request->filled('_error_redirect') ? '_error_redirect' : '_redirect')
            ->withErrors($errors, 'reviews')
            ->withInput($request->except(['photos', $this->spam->honeypot(), Spam::TIME_FIELD, Spam::OPENED_FIELD, '_token', ...$this->spam->captchaFields()]));
    }
}
