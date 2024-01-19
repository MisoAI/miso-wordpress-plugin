<?php

namespace Miso;

function has_api_key() {
    return !!get_option('miso_settings')['api_key'];
}

function create_client() {
    $api_key = get_option('miso_settings')['api_key'] ?? null;
    if (!$api_key) {
        throw new \Exception('API key is required');
    }
    return new Client([
        'api_key' => $api_key,
    ]);
}
