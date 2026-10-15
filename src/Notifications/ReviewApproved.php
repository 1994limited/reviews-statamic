<?php

namespace NineteenNinetyFour\Reviews\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the reviewer: their review is on the site.
 */
class ReviewApproved extends ReviewNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->details;

        $message = (new MailMessage)
            ->subject(__('reviews::messages.mail.approved_subject', ['site' => $d['site']]))
            ->greeting($this->say('reviews::messages.mail.greeting', ['name' => $d['first_name']]))
            ->line($this->say('reviews::messages.mail.approved_intro', ['entry' => $d['entry'], 'site' => $d['site']]));

        if ($d['url']) {
            $message->action(__('reviews::messages.mail.approved_action'), $d['url']);
        }

        return $message;
    }
}
