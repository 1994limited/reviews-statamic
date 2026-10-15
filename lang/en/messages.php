<?php

return [

    'reviews' => 'Reviews',

    'statuses' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'spam' => 'Spam',
    ],

    'fields' => [
        'rating' => 'rating',
        'title' => 'title',
        'body' => 'review',
        'name' => 'name',
        'email' => 'email address',
        'photos' => 'photos',
        'photo' => 'photo',
    ],

    'anonymous' => 'A customer',
    'honeypot' => 'Leave this empty',
    'photo_alt' => 'Photo from :name’s review',
    'helpful' => 'Helpful',
    'delete' => 'Delete my review',

    'choose_rating' => 'Choose a rating from 1 to 5 stars.',
    'too_many_links' => 'Your review has too many links in it.',
    'turnstile' => 'Please confirm you’re not a robot, then send your review again.',
    'already_reviewed' => 'You’ve already reviewed this.',
    'not_yours' => 'That isn’t your review.',
    'not_found' => 'That review can’t be found.',

    'refusals' => [
        'unavailable' => 'This can’t be reviewed.',
        'sign_in' => 'Sign in to write a review.',
        'buyers_only' => 'Only customers who bought this can review it.',
    ],

    'thanks_pending' => 'Thank you. Your review will appear once we’ve checked it.',
    'thanks_published' => 'Thank you. Your review is now live.',
    'deleted' => 'Your review has been deleted.',
    'voted' => 'Thanks for letting us know.',
    'voted_already' => 'You’ve already said this was helpful.',

    'opt_out' => [
        'title' => 'Review requests',
        'ask' => 'Stop sending review requests to :email?',
        'button' => 'Stop sending them',
        'done' => 'Done. We won’t ask you for reviews again.',
    ],

    'mail' => [
        'new_subject' => 'New :rating-star review of :entry',
        'new_intro' => ':name reviewed :entry on :site.',
        'new_pending' => 'It’s waiting for you to approve it.',
        'new_published' => 'It was published straight away.',
        'new_action' => 'Moderate the review',
        'approved_subject' => 'Your review is live',
        'approved_intro' => 'Thanks for reviewing :entry. Your review is now on :site.',
        'approved_action' => 'See your review',
        'replied_subject' => ':site replied to your review',
        'replied_intro' => ':site replied to your review of :entry:',
        'replied_action' => 'See the reply',
        'request_subject' => 'How was your order from :site?',
        'request_intro' => 'Thanks for your order. We’d love to know what you think of it: it takes a minute, and helps other customers choose.',
        'request_action' => 'Review :product',
        'request_opt_out' => 'Don’t want these emails? [Stop review requests](:url).',
        'greeting' => 'Hello :name,',
        'greeting_anonymous' => 'Hello,',
        'verified' => 'Verified purchase',
        'rating' => ':rating out of 5',
    ],

    'cp' => [
        'reviews' => 'Reviews',
        'pending_widget' => 'Reviews waiting',
        'all_caught_up' => 'All caught up: no reviews are waiting.',
    ],

    'permissions' => [
        'view' => 'View reviews',
        'moderate' => 'Approve and reject reviews',
        'reply' => 'Reply to reviews',
        'delete' => 'Delete reviews',
    ],

];
