<?php

namespace Miso\Utils;

function format_date($date) {
    return $date ? date_create_immutable($date, timezone_open('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
}

function normalize_post_to_record_args($args = []) {
    $product_id_prefix = get_option('miso_settings')['miso_product_id_prefix'] ?? null;
    return array_merge([
        'product_id_prefix' => $product_id_prefix,
    ], $args);
}

function default_post_to_record(\WP_Post $post, $args = []) {

    $product_id_prefix = $args['product_id_prefix'] ?? '';

    $id = $post->ID;
    $product_id = $product_id_prefix . strval($id);

    if ($post->post_status !== 'publish') {
        return [
            'product_id' => $product_id,
            '_delete' => true,
        ];
    }

    $tags = array_map(function (\WP_Term $term) {
        return $term->name;
    }, wp_get_post_terms($id, 'post_tag'));
    $categories = array_map(function (\WP_Term $term) {
        return [$term->name];
    }, wp_get_post_terms($id, 'category'));
    $author = get_user_by('ID', $post->post_author);
    $cover_image = get_the_post_thumbnail_url($id, 'medium_large');

    return [
        'product_id' => $product_id,
        'published_at' => format_date($post->post_date_gmt),
        'updated_at' => format_date($post->post_modified_gmt),
        'type' => 'post',
        'title' => $post->post_title,
        'html' => $post->post_content,
        'cover_image' => $cover_image ? $cover_image : null,
        'authors' => $author ? [$author->display_name] : [],
        'tags' => $tags,
        'categories' => $categories,
        'url' => get_permalink($id),
    ];
}

function shall_be_deleted($record) {
    return array_key_exists('_delete', $record) && !!$record['_delete'];
}

function log($value) {
    error_log(print_r($value, true));
}
