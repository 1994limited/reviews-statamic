# Events and PHP API

## Events

All in `NineteenNinetyFour\Reviews\Events`, each with the `$review`:

| Event | When |
| --- | --- |
| `ReviewSubmitted` | A review is written, or changed by its reviewer. It may be pending or already approved. |
| `ReviewApproved` | An editor approves it, or it's approved as it's written. |
| `ReviewRejected` | An editor rejects it or marks it as spam (`$review->status`). |
| `ReviewReplied` | The public reply is added or changed. |
| `ReviewDeleted` | Deleted, by an editor or its reviewer. |

```php
use Illuminate\Support\Facades\Event;
use NineteenNinetyFour\Reviews\Events\ReviewApproved;

Event::listen(ReviewApproved::class, function (ReviewApproved $event) {
    // Post it to Slack, give the reviewer loyalty points…
});
```

## A review

`NineteenNinetyFour\Reviews\Review` has public properties: `id`, `entry` (the original entry's ID), `site`, `rating`, `ratings` (criteria, by handle), `title`, `body`, `name`, `email`, `user`, `status` (a `ReviewStatus`: `Pending`, `Approved`, `Rejected`, `Spam`), `verified`, `order`, `reply`, `replyBy`, `repliedAt`, `note`, `photos`, `helpful`, `createdAt`, `updatedAt`, `approvedAt`.

## The `Reviews` service

```php
use NineteenNinetyFour\Reviews\Reviews;

$reviews = app(Reviews::class);

$review = $reviews->find($id);
$reviews->approve($review);
$reviews->reject($review, 'Not about the book');
$reviews->spam($review);
$reviews->reply($review, 'Thank you!', $user);
$reviews->delete($review);

$summary = $reviews->summary($entryId);   // ->count(), ->average(), ->stars, ->criterionAverage('value')
$reviews->forget('someone@example.com');  // anonymise; forget(email, delete: true) deletes
```

Always go through the service to change reviews: it keeps averages and cached pages up to date, and fires the events.

## Querying

```php
use NineteenNinetyFour\Reviews\ReviewQuery;

$query = ReviewQuery::make()
    ->entry($entryId)          // or an array of IDs
    ->approved()               // or ->status('pending'), ->status(['approved', 'rejected'])
    ->rating([4, 5])
    ->verified()
    ->withPhotos()
    ->search('lovely')
    ->sort('helpful');         // newest, oldest, highest, lowest, helpful

$repository = $reviews->repository();

$repository->get($query, limit: 10);
$repository->paginate($query, perPage: 10, page: 1);
$repository->count($query);
$repository->statusCounts();   // ['pending' => 3, 'approved' => 120, …]
```

An entry's ID here is the original's: for a translated entry, use `$reviews->entryId($entry)`.

## Commands

| Command | |
| --- | --- |
| `reviews:transfer --from=file --to=database` | [Storage drivers](drivers.md#moving-between-them) |
| `reviews:export {email} --output=file.json` | [Privacy](privacy.md) |
| `reviews:forget {email} [--delete]` | [Privacy](privacy.md) |
| `reviews:send-requests [--dry-run]` | [Cargo](cargo.md#review-requests) |

## HTTP endpoints

The form tags post to these; send `Accept: application/json` to get JSON back instead of a redirect.

| | |
| --- | --- |
| `POST /!/reviews/submit` | A review. 200 `{message, review: {id, status}}`, or 422 `{message, errors}`. |
| `POST /!/reviews/delete` | `id`: delete the signed-in visitor's own review. |
| `POST /!/reviews/{id}/helpful` | A vote. `{message, helpful}`. |

They need a CSRF token, like any form.
