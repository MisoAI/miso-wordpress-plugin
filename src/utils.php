<?php

namespace Miso\Utils;

function format_date($date) {
    return $date ? date_create_immutable($date, timezone_open('UTC'))->format('Y-m-d\TH:i:s\Z') : null;
}

function default_post_to_record(\WP_Post $post) {

    $id = $post->ID;

    $tags = array_map(function (\WP_Term $term) {
        return $term->name;
    }, wp_get_post_terms($id, 'post_tag'));
    $categories = array_map(function (\WP_Term $term) {
        return [$term->name];
    }, wp_get_post_terms($id, 'category'));
    $author = get_user_by('ID', $post->post_author);
    $cover_image = get_the_post_thumbnail_url($id, 'medium_large');

    return [
        'product_id' => strval($id),
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
