<?php

namespace Miso;

use Miso\Utils;

function post_to_record(\WP_Post $post, $args = []) {

    $record = Utils\default_post_to_record($post, $args);

    return (array) apply_filters('miso_post_to_record', $record, $post);
}
