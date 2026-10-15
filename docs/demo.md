# The demo shop

**Dog-ear Books** is a made-up bookshop built with Reviews and Cargo, using only the tags in these docs. Each book has its average rating on the shelf, and on its page the rating breakdown, criteria (Story, Writing), reviews sorted by most helpful with pages, photos, replies and verified purchases, a star-rating form, and review schema.

Run it locally (no licence needed):

```bash
git clone https://github.com/1994limited/reviews-statamic-demo.git
cd reviews-statamic-demo
composer setup
php artisan demo:users
php artisan demo:seed
php artisan serve
```

Then open http://localhost:8000. `demo:users` makes two accounts, both with the password `password`, and only runs with `APP_ENV=local`:

- `admin@example.com`: the Control Panel, at `/cp`.
- `jo@example.com`: a reader who has bought two books, so their reviews of them are verified.

`demo:seed` writes about 70 reviews, some waiting for moderation, one rejected and one spam, and a few orders. Run it again with `--fresh` to start over.

The templates to copy are in `resources/views/products/show.antlers.html` and `resources/views/partials/reviews.antlers.html`.
