# Photos

Reviewers can add photos to their reviews. It's off until you turn it on in **Tools → Addons → Reviews → Settings → Photos**, where you also choose how many a review may have (4) and the asset container approved photos go in.

Then add a file field to the form, as in [the form example](templates.md#the-form):

```antlers
{{ if photos_enabled }}
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
{{ /if }}
```

<details>
<summary>Blade</summary>

```blade
@if ($photos_enabled)
    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple>
@endif
```

</details>

The form tag adds `enctype="multipart/form-data"` itself when photos are on.

## What happens to them

1. **They're checked.** JPEG, PNG and WebP, up to 8MB each (`photos.max_size`, in kilobytes). Anything that isn't really an image is refused.
2. **They're re-saved**, as JPEGs no bigger than 2000 pixels on their longest side (`photos.max_dimension`). This leaves the camera's metadata behind, including where the photo was taken.
3. **They wait privately** on the `local` disk, in `storage/app/private/reviews/pending` (`photos.pending_disk`, `photos.pending_directory`), not anywhere on the web. Editors see them on the review's page in the Control Panel.
4. **When the review is approved**, they move into the asset container, in a `reviews` folder (`photos.folder`), with alt text ("Photo from Jo B.'s review"), and appear in `{{ photos }}`.
5. **If an approved review is rejected later**, they move back off the site. When a review is deleted, so are its photos.

A signed-in reviewer changing their review can remove photos (a `remove_photos[]` checkbox with each one's `index`, from `{{ existing_photos }}`) and add more, up to the limit.

Photos need PHP's GD extension, which most hosts have. Without it, photos stay off.
