<?php

namespace Miso\Admin;

use Miso\Operations;

function admin_menu() {
    // resources
    wp_register_script('miso-posts', plugins_url('../../js/posts.js', __FILE__));
    wp_register_style('miso-admin', plugins_url('../../css/admin.css', __FILE__));
    wp_enqueue_style('miso-admin');
    // settings
    register_setting(
        'miso',
        'miso_settings',
        [
            'type' => 'array',
            'description' => 'Miso Settings',
            'sanitize_callback' => function ($value) {
                return $value;
            },
            'show_in_rest' => false,
        ],
    );
    add_settings_section(
        'miso_settings',
        '',
        function () {},
        'miso',
    );
    add_settings_field(
        'api_key',
        'Secret API Key',
        function () {
            $options = get_option('miso_settings', []);
            $api_key = array_key_exists('api_key', $options) ? $options['api_key'] : '';
            echo '<input type="text" name="miso_settings[api_key]" value="' . esc_attr($api_key) . '" style="min-width: 400px;" />';
        },
        'miso',
        'miso_settings',
    );
    // menu pages
    add_menu_page(
        'Miso AI',
        'Miso AI',
        'manage_options',
        'miso',
        __NAMESPACE__ . '\admin_page',
        '',
    );
    add_submenu_page(
        'miso',
        'Posts',
        'Posts',
        'manage_options',
        'miso&view=posts',
        __NAMESPACE__ . '\posts_page',
    );
    // change submenu name inside the second layer of menu items
    if (!empty( $GLOBALS['submenu']['miso'])) {
        $GLOBALS['submenu']['miso'][0][0] = esc_attr__('Settings', 'miso');
    }
    // fix the highlight of the submenu item
    add_filter('submenu_file', __NAMESPACE__ . '\submenu_file', 10, 2);
}

function get_request_var($name) {
    return isset($_REQUEST[$name]) ? sanitize_text_field($_REQUEST[$name]) : null;
}

function submenu_file($submenu_file, $parent_file) {
    $page = get_request_var('page');
    $view = get_request_var('view');
    if ($page !== 'miso' || !$view) {
        return $submenu_file;
    }
    return 'miso&view=' . $view;
}

function admin_page() {
    $view = get_request_var('view');
    switch ($view) {
        case 'posts':
            wp_enqueue_script('miso-posts');
            posts_page();
            break;
        default:
            settings_page();
    }
}

function settings_page() {
    ?>
    <div class="wrap">
        <h1>Settings</h1>
        <p>An API key is required for Miso data integration. You can get your secret API key from <a href="https://dojo.askmiso.com/" target="_blank">Miso dashboard</a>.</p>
        <form method="post" action="options.php">
            <?php
                settings_fields('miso');
                do_settings_sections('miso');
                submit_button();
            ?>
        </form>
    </div>
    <?php
}

class RecentTasks {

    static $COLUMNS = [
        [
            'key' => 'status',
            'label' => 'Status',
        ],
        [
            'key' => 'uploaded',
            'label' => 'Uploaded',
            'value' => [__CLASS__, 'get_uploaded'],
        ],
        [
            'key' => 'deleted',
            'label' => 'Deleted',
            'value' => [__CLASS__, 'get_deleted'],
        ],
        [
            'key' => 'created_by',
            'label' => 'Created By',
            'value' => [__CLASS__, 'get_created_by'],
        ],
        [
            'key' => 'created_at',
            'label' => 'Created At',
        ],
        [
            'key' => 'modified_at',
            'label' => 'Updated At',
        ],
    ];

    static function get_uploaded($task) {
        $data = $task['data'] ?? [];
        $uploaded = $data['uploaded'] ?? 0;
        $total = $data['total'] ?? 0;
        return $uploaded . ' / ' . $total;
    }

    static function get_deleted($task) {
        $data = $task['data'] ?? [];
        return $data['deleted'] ?? '0';
    }

    static function get_created_by($task) {
        if ($task['created_via'] == 'wp-cli') {
            return 'WP CLI';
        }
        $user_id = $task['created_by'] ?? 0;
        return $user_id > 0 ? get_user_by('id', $user_id)->user_login : 'Unknown';
    }

    static function get_value($task, $column) {
        return array_key_exists('value', $column) && is_callable($column['value']) ? call_user_func($column['value'], $task) : $task[$column['key']] ?? '';
    }
}

function posts_page() {
    $has_api_key = \Miso\has_api_key();
    $recent_tasks = Operations::recent_tasks();
    ?>
    <script>
        window.ajax_url = '<?php echo admin_url( "admin-ajax.php" ); ?>';
        window.ajax_nonce = '<?php echo wp_create_nonce( "secure_nonce_name" ); ?>';
    </script>
    <div class="wrap">
        <h1>Posts</h1>
        <p>Upload all posts to Miso catalog and delete extra records from Miso catalog.</p>
        <?php if (!$has_api_key): ?>
            <p><strong>API key is required to perform operations.</strong></p>
        <?php endif; ?>
        <form name="sync-posts">
            <div>
                <?php submit_button('Sync data', 'primary', 'submit', \true, $has_api_key ? [] : ['disabled' => '']); ?>
            </div>
            <input type="hidden" name="action" value="miso_send_form">
            <input type="hidden" name="operation" value="sync-posts">
        </form>
        <h1>Recent sync tasks</h1>
        <table id="recent-tasks" class="widefat fixed striped" cellspacing="0">
            <thead>
                <?php foreach (RecentTasks::$COLUMNS as $column): ?>
                    <th class="manage-column column-columnname" scope="col" data-column="<?php echo esc_attr($column['key']); ?>"><?php echo esc_html($column['label']); ?></th>
                <? endforeach; ?>
            </thead>
            <tbody>
                <?php foreach ($recent_tasks as $task): ?>
                    <tr data-task-id="<?php echo $task['id']; ?>">
                        <?php foreach (RecentTasks::$COLUMNS as $column): ?>
                            <td class="column-columnname" data-column="<?php echo esc_attr($column['key']); ?>"><?php echo esc_html(RecentTasks::get_value($task, $column)); ?></td>
                        <? endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

function send_form() {
    // validate source
    check_ajax_referer('secure_nonce_name', '_nonce');

    $operation = isset($_POST['operation']) ? sanitize_text_field($_POST['operation']) : null;
    if (empty($operation)) {
        wp_send_json_error('Operation not found', 400);
    }
    switch ($operation) {
        case 'sync-posts':
            sync_posts();
            break;
        default:
            wp_send_json_error('Unrecognized operation: ' . $operation, 400);
    }
}

function sync_posts() {
    Operations::enqueue_sync_posts('admin-page', []);
    wp_send_json_success();
}

function heartbeat_send($response, $screen_id) {
    if ($screen_id !== 'toplevel_page_miso') {
        return $response;
    }
    $response['miso_recent_tasks'] = array_map(function($task) {
        $entry = [
            'id' => $task['id'],
        ];
        foreach (RecentTasks::$COLUMNS as $column) {
            $entry[$column['key']] = RecentTasks::get_value($task, $column);
        }
        return $entry;
    } , Operations::recent_tasks());
    return $response;
}

add_action('admin_menu', __NAMESPACE__ . '\admin_menu');
add_action('wp_ajax_miso_send_form', __NAMESPACE__ . '\send_form');
add_filter('heartbeat_send', __NAMESPACE__ . '\heartbeat_send', 10, 2);
