<?php

namespace Miso;

use Miso\Utils;

// cascade save_post
function update_post($id, \WP_Post $post, $update) {
    if (wp_is_post_revision($id) || wp_is_post_autosave($id)) {
        return $post;
    }
    if (!has_api_key()) {
        return $post;
    }

    $client = create_client();

    // transform to Miso record
    $args = Utils\normalize_post_to_record_args();
    $record = post_to_record($post, $args);
    
    if (Utils\shall_be_deleted($record)) {
        // shall delete from Miso catalog
        $client->products->delete([$record['product_id']]);
    } else {
        // shall update the record
        $client->products->upload([$record]);
    }

    return $post;
}

add_action('save_post', __NAMESPACE__ . '\update_post', 10, 3);

// cascade update_post_meta
// TODO
