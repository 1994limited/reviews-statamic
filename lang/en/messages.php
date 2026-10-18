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
    'captcha' => 'Please confirm you’re not a robot, then send your review again.',
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

    // The ready-made views ({{ reviews:display }}).
    'display' => [
        'heading' => 'Reviews',
        'out_of' => 'out of 5',
        'rated' => 'Rated :rating out of 5',
        'from_reviews' => 'From :count review|From :count reviews',
        'stars' => ':count star|:count stars',
        'none_yet' => 'No reviews yet. Be the first.',
        'none_with_rating' => 'No reviews with that rating yet.',
        'showing' => 'Showing :count-star reviews · show all|Showing :count-star reviews · show all',
        'write' => 'Write a review',
        'yours' => 'Your review',
        'sort' => 'Sort by',
        'sort_button' => 'Sort',
        'sorts' => [
            'helpful' => 'Most helpful',
            'newest' => 'Newest',
            'highest' => 'Highest rated',
            'lowest' => 'Lowest rated',
        ],
        'verified' => 'Verified purchase',
        'reply' => 'Reply from :name',
        'helpful' => 'Helpful',
        'pages' => 'Review pages',
        'previous' => 'Previous',
        'next' => 'Next',
        'page_of' => 'Page :page of :pages',
        'your_rating' => 'Your rating',
        'optional' => '(optional)',
        'title' => 'Headline',
        'body' => 'Your review',
        'name' => 'Your name',
        'email' => 'Your email',
        'never_shown' => '(never shown)',
        'photos' => 'Photos, up to :max',
        'photo_waiting' => 'Waiting to be checked',
        'remove' => 'Remove',
        'checked_first' => 'Reviews are checked before they appear.',
        'editing_pending' => 'Your review is waiting to be checked.',
        'editing_live' => 'Your review is live. Changing it sends it back to be checked.',
        'send' => 'Send your review',
        'update' => 'Update your review',
    ],

    'import' => [
        'business' => 'A review of the business (choose where those go)',
        'from' => 'From :platform',
        'see_it' => 'See it there',
        'permission' => 'Import and sync reviews',
    ],

    'cp' => [
        'reviews' => 'Reviews',
        'pending_widget' => 'Reviews waiting',
        'import' => 'Import',
        'sync' => 'Sync',
        'all_caught_up' => 'All caught up: no reviews are waiting.',
    ],

    'permissions' => [
        'view' => 'View reviews',
        'moderate' => 'Approve and reject reviews',
        'reply' => 'Reply to reviews',
        'delete' => 'Delete reviews',
    ],

];
