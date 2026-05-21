<?php

namespace Miso;

function has_api_key() {
    return !!(get_option('miso_settings')['miso_api_key'] ?? false);
}

function has_product_id_prefix() {
    return !!(get_option('miso_settings')['miso_product_id_prefix'] ?? false);
}

function create_client() {
    // Allow tests (or advanced integrations) to substitute the client.
    // Returning anything other than null short-circuits the default path.
    $pre = apply_filters('miso_pre_create_client', null);
    if ($pre !== null) {
        return $pre;
    }
    $api_key = get_option('miso_settings')['miso_api_key'] ?? null;
    if (!$api_key) {
        throw new \Exception('API key is required');
    }
    return new Client([
        'api_key' => $api_key,
    ]);
}
