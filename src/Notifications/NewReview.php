<?php

namespace NineteenNinetyFour\Reviews\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * To editors: someone has written a review.
 */
class NewReview extends ReviewNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->details;

        $message = (new MailMessage)
            ->subject(__('reviews::messages.mail.new_subject', ['rating' => $d['rating'], 'entry' => $d['entry']]))
            ->greeting(__('reviews::messages.mail.greeting_anonymous'))
            ->line($this->say('reviews::messages.mail.new_intro', ['name' => $d['name'], 'entry' => $d['entry'], 'site' => $d['site']]))
            ->line(static::stars((int) $d['rating']).($d['verified'] ? ' · '.__('reviews::messages.mail.verified') : ''));

        if ($d['title']) {
            $message->line(new HtmlString('**'.static::plain($d['title']).'**'));
        }

        if ($line = $this->plainLines($d['body'])) {
            $message->line($line);
        }

        $message->line(__($d['approved'] ? 'reviews::messages.mail.new_published' : 'reviews::messages.mail.new_pending'));

        if ($d['cp_url']) {
            $message->action(__('reviews::messages.mail.new_action'), $d['cp_url']);
        }

        return $message;
    }
}
