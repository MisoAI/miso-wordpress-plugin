# Customization

## Customize Miso record

You can add a WordPress filter to modify the Miso record you upload in the following steps:

1. Create a user plugin
2. Modify the filter function to your needs

### Create a user plugin skeleton

1. Locate your WordPress plugin directory at `{{ YOUR_SITE_DIR }}/app/public/wp-content/plugins`.
2. Add a directory `my-plugin` in it. You can use your own name.
3. Add the following files in your plugin directory.

`my-plugin.php` (MUST match your plugin directory name)

```php
<?php
/**
 * Plugin Name:       User Plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

require_once __DIR__ . '/filters.php';
```

`filters.php`

```php
function my_miso_post_to_record_0(array $record, WP_Post $post) {
    // modify $record here
    return $record;
}

add_filter('miso_post_to_record', 'my_miso_post_to_record_0', 10, 2);
```

Alternatively, you can also place it in `functions.php` of your theme.

### Modify the filter function

Here is an example to add a prefix to `product_id`:

```php
function my_miso_post_to_record_0(array $record, WP_Post $post) {
    $record['product_id'] = 'my_prefix_' . $record['product_id'];
    return $record;
}

add_filter('miso_post_to_record', 'my_miso_post_to_record_0', 10, 2);
```

Another example to add extra data to `custom_attributes`:

```php
function my_miso_post_to_record_0(array $record, WP_Post $post) {
    if (!array_key_exists('custom_attributes', $record)) {
        $record['custom_attributes'] = [];
    }
    // add the first two characters as "title initial" in custom_attributes
    $record['custom_attributes']['title_initial'] = substr($post->post_title, 0, 2);
    return $record;
}

add_filter('miso_post_to_record', 'my_miso_post_to_record_0', 10, 2);
```

You can have multiple filters like the following:

```php
function my_miso_post_to_record_0(array $record, WP_Post $post) {
    // ...
    return $record;
}

function my_miso_post_to_record_1(array $record, WP_Post $post) {
    // ...
    return $record;
}

add_filter('miso_post_to_record', 'my_miso_post_to_record_0', 10, 2);
add_filter('miso_post_to_record', 'my_miso_post_to_record_1', 10, 2);
```
