<?php

namespace NineteenNinetyFour\Reviews\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reading what a visitor sent. A field can arrive as anything (an array,
 * say, from name="title[]" or an edited URL), so only plain text counts;
 * anything else is read as missing.
 */
trait ReadsText
{
    protected function text(Request $request, string $key, int $limit = 2000): ?string
    {
        $value = $request->input($key);

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || mb_strlen($value) > $limit ? null : $value;
    }

    /**
     * Back to the page, or to the form's redirect when it's on this site.
     */
    protected function back(Request $request, string $field = '_redirect'): RedirectResponse
    {
        $to = $this->text($request, $field);

        return $to !== null && static::isLocal($to, $request) ? redirect()->to($to) : redirect()->back();
    }

    /**
     * A path on this site ("/thanks"), or a full URL with this site's host.
     * Backslashes and control characters are refused outright: browsers
     * read "/\evil.example" as another site.
     */
    public static function isLocal(string $to, Request $request): bool
    {
        if (preg_match('/[\\\\\x00-\x1F\x7F]/', $to)) {
            return false;
        }

        if (str_starts_with($to, '/')) {
            return ! str_starts_with($to, '//');
        }

        $parts = parse_url($to);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && isset($parts['host'])
            && strtolower($parts['host']) === strtolower($request->getHost());
    }
}
