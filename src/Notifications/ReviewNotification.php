<?php

namespace NineteenNinetyFour\Reviews\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * An email about a review. It holds only plain values, worked out when it
 * was sent, so it can wait in a queue: on a site with a queue worker it's
 * sent in the background.
 */
abstract class ReviewNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public readonly array $details) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    abstract public function toMail(object $notifiable): MailMessage;

    /**
     * A line of the email, with people's words (names, titles, reviews) put
     * in as plain text: emails are written in Markdown, so "[a link](…)" in
     * a review would otherwise become a real link.
     */
    protected function say(string $line, array $replace = []): HtmlString
    {
        return new HtmlString(__($line, array_map(fn ($value) => is_scalar($value) ? static::plain((string) $value) : $value, $replace)));
    }

    /**
     * Text that reads the same in the HTML and plain-text parts of the
     * email, and that Markdown leaves alone. Line breaks are kept.
     */
    public static function plain(string $text): string
    {
        $text = strtr(e($text), [
            '[' => '&#91;', ']' => '&#93;', '(' => '&#40;', ')' => '&#41;',
            '*' => '&#42;', '_' => '&#95;', '`' => '&#96;', '#' => '&#35;', '!' => '&#33;',
            '\\' => '&#92;', '<' => '&lt;', '>' => '&gt;', '|' => '&#124;', '~' => '&#126;',
        ]);

        // Markdown reads a line starting "-", "+" or "1." as a list, and
        // four spaces as code; neither should come from a reviewer.
        return preg_replace(['/^(\s*)([-+])/m', '/^(\s*)(\d+)\./m', '/^ {4,}/m'], ['$1&#'.ord('-').';', '$1$2&#46;', ''], $text) ?? $text;
    }

    protected function plainLines(?string $text): ?HtmlString
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        return new HtmlString(nl2br(static::plain(trim($text)), false));
    }

    /**
     * Stars for a rating, as text: ★★★★☆.
     */
    protected static function stars(int $rating): string
    {
        return str_repeat('★', max(0, min(5, $rating))).str_repeat('☆', 5 - max(0, min(5, $rating)));
    }

    public function toArray(object $notifiable): array
    {
        return $this->details;
    }
}
