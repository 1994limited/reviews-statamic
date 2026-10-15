# Moderation

Reviews wait for an editor before they're shown, unless the settings publish some straight away (reviews by signed-in users, or by verified buyers). Editors moderate them in **Content → Reviews**.

## The list

Tabs for each status, **Pending** first while anything waits, with how many are in each. Search the reviews, names and emails; filter by rating, or by entry (from a review, **All its reviews**). Tick several to approve, reject, mark as spam or delete them at once.

## A review

Everything the reviewer sent: the ratings, title and text exactly as written, their photos (still private until it's approved), their name and email, their user account if they were signed in, and the order that verified it (Cargo).

- **Approve** publishes it, and its photos.
- **Reject** hides it, with a reason for other editors (optional). It's kept, and can be approved later.
- **Spam** hides it as spam.
- **Back to pending** returns it to the queue.
- **Delete** removes it and its photos for good.

Editors can't change what a reviewer wrote: a review is the reviewer's own words. To answer it, reply in public.

**Your public reply** shows under the review on the site (`{{ reply }}`), and the reviewer can be emailed about it ([Emails](emails.md)). Leave it empty and save to remove it.

**Note** is for editors only, such as why it was rejected.

## Permissions

In **Users → Roles**, under Reviews:

| Permission | |
| --- | --- |
| View reviews | The list and each review. |
| Approve and reject reviews | Approve, reject, spam, back to pending, and notes. |
| Reply to reviews | Public replies. |
| Delete reviews | |

Super users can do everything.

## The dashboard widget

**Reviews waiting** lists the oldest reviews waiting for an editor. Add it to `config/statamic/cp.php`:

```php
'widgets' => [
    ['type' => 'pending_reviews', 'width' => 50],
],
```

Options: `title`, `limit` (5). It's only shown to people who can view reviews.

## The entry field

Add a **Reviews** field (in the Special section of the fieldtypes) to a blueprint, and each entry shows its average rating, how many reviews it has, and how many are waiting, with links to them. It saves nothing.
