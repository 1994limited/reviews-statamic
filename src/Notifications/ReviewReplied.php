<?php

namespace NineteenNinetyFour\Reviews\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the reviewer: the site replied to their review.
 */
class ReviewReplied extends ReviewNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->details;

        $message = (new MailMessage)
            ->subject(__('reviews::messages.mail.replied_subject', ['site' => $d['site']]))
            ->greeting($this->say('reviews::messages.mail.greeting', ['name' => $d['first_name']]))
            ->line($this->say('reviews::messages.mail.replied_intro', ['entry' => $d['entry'], 'site' => $d['site']]));

        if ($line = $this->plainLines($d['reply'])) {
            $message->line($line);
        }

        if ($d['url']) {
            $message->action(__('reviews::messages.mail.replied_action'), $d['url']);
        }

        return $message;
    }
}
