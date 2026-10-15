<?php

namespace NineteenNinetyFour\Reviews\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\HtmlString;

/**
 * To a customer (with Cargo): would they review what they bought? One
 * button per product, each a signed link that marks their review as a
 * verified purchase.
 */
class ReviewRequest extends ReviewNotification
{
    public function toMail(object $notifiable): MailMessage
    {
        $d = $this->details;

        $message = (new MailMessage)
            ->subject($d['subject'])
            ->greeting($d['first_name'] ? $this->say('reviews::messages.mail.greeting', ['name' => $d['first_name']]) : __('reviews::messages.mail.greeting_anonymous'))
            ->line($this->plainLines($d['intro']) ?? '');

        $products = $d['products'];

        // MailMessage has one button, so the first product gets it and the
        // others are links.
        if ($first = array_shift($products)) {
            $message->action(__('reviews::messages.mail.request_action', ['product' => $first['title']]), $first['url']);
        }

        foreach ($products as $product) {
            $message->line(new HtmlString('['.static::plain(__('reviews::messages.mail.request_action', ['product' => $product['title']])).']('.$product['url'].')'));
        }

        $message->salutation(new HtmlString(__('reviews::messages.mail.request_opt_out', ['url' => $d['opt_out_url']])));

        return $message;
    }
}
