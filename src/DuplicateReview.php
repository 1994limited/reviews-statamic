<?php

namespace NineteenNinetyFour\Reviews;

/**
 * A guest's second review of an entry from the same email address. They're
 * thanked as for a new review, so the form can't be used to find out who
 * has reviewed what; nothing is kept.
 */
class DuplicateReview extends ReviewException {}
