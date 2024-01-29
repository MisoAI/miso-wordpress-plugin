<?php

namespace Miso\Admin;

use Miso\Operations;

function admin_menu() {
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
            echo '<input type="text" name="miso_settings[api_key]" value="' . $api_key . '" style="min-width: 400px;" />';
        },
        'miso',
        'miso_settings',
    );
    add_menu_page(
        'Miso AI',
        'Miso AI',
        'manage_options',
        'miso',
        __NAMESPACE__ . '\admin_page',
        //'dashicons-admin-site'
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
}

function admin_page() {
    $view = get_request_var('view');
    switch ($view) {
        case 'posts':
            posts_page();
            break;
        default:
            settings_page();
    }
}

function get_request_var($name) {
    return isset($_REQUEST[$name]) ? $_REQUEST[$name] : null;
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

function posts_page() {
    $has_api_key = \Miso\has_api_key();
    $recent_tasks = Operations::recent_tasks();
    ?>
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
        <table id="recent-tasks" class="widefat fixed" cellspacing="0">
            <thead>
                <th class="manage-column column-columnname" scope="col">Status</th>
                <th class="manage-column column-columnname" scope="col">Uploaded</th>
                <th class="manage-column column-columnname" scope="col">Deleted</th>
                <th class="manage-column column-columnname" scope="col">Created At</th>
                <th class="manage-column column-columnname" scope="col">Updated At</th>
            </thead>
            <tbody>
                <?php foreach ($recent_tasks as $task):
                    $data = $task['data'] ?? [];
                    $uploaded = $data['uploaded'] ?? 0;
                    $total = $data['total'] ?? 0;
                    $deleted = $data['deleted'] ?? 0;
                ?>
                    <tr data-task-id="<?php echo $task['id']; ?>">
                        <td class="column-columnname"><?php echo $task['status'] ?? ''; ?></td>
                        <td class="column-columnname"><?php echo $uploaded; ?> / <?php echo $total; ?></td>
                        <td class="column-columnname"><?php echo $deleted; ?></td>
                        <td class="column-columnname"><?php echo $task['created_at'] ?? ''; ?></td>
                        <td class="column-columnname"><?php echo $task['modified_at'] ?? ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script>
        const ajax_url = '<?php echo admin_url( "admin-ajax.php" ); ?>';
        const ajax_nonce = '<?php echo wp_create_nonce( "secure_nonce_name" ); ?>';
        function updateProgress({ miso_recent_tasks }) {
            for (const task of miso_recent_tasks) {
                const { uploaded = 0, total = 0, deleted = 0 } = task.data || {};
                const $tr = jQuery('#recent-tasks tr[data-task-id="' + task.id + '"]');
                if ($tr.length === 0) {
                    jQuery('#recent-tasks tbody').prepend(`<tr data-task-id="${task.id}"><td class="column-columnname">${task.status}</td><td class="column-columnname">${uploaded} / ${total}</td><td class="column-columnname">${deleted}</td><td class="column-columnname">${task.created_at}</td><td class="column-columnname">${task.modified_at}</td></tr>`);
                } else {
                    $tr.find('td:nth-child(1)').text(task.status);
                    $tr.find('td:nth-child(2)').text(`${uploaded}/${total}`);
                    $tr.find('td:nth-child(3)').text(deleted);
                    $tr.find('td:nth-child(4)').text(task.created_at);
                    $tr.find('td:nth-child(5)').text(task.modified_at);
                }
            }
        }
        jQuery(document).ready(($) => {
            $('[name="sync-posts"]').on('submit', (event) => {
                event.preventDefault();
                const $form = $(event.target);
                const $button = $form.find('input[type="submit"]');
                const formData = $form.serializeArray();
                formData.push({ name: '_nonce', value: ajax_nonce });
                $button.prop('disabled', true);
                $.ajax({
                    url: ajax_url,
                    method: 'POST',
                    data: formData,
                    success: (response) => {
                        $button.prop('disabled', false);
                        wp.heartbeat.connectNow();
                        const intervalId = setInterval(() => wp.heartbeat.connectNow(), 10000);
                        setTimeout(() => clearInterval(intervalId), 120000);
                    },
                    error: (response) => {
                        $button.prop('disabled', false);
                        const data = response.responseJSON.data;
                        console.error(data);
                        alert('[Failed] ' + data);
                    },
                });
            });
            $(document).on('heartbeat-tick', (event, data) => {
                updateProgress(data);
            });
        });
    </script>
    <?php
}

function send_form() {
    // validate source
    check_ajax_referer('secure_nonce_name', '_nonce');

    $operation = $_POST['operation'] ?? null;
    if (empty($operation)) {
        wp_send_json_error('Operation not found', 400);
    }
    switch ($operation) {
        case 'sync-posts':
            sync_posts($_POST);
            break;
        default:
            wp_send_json_error('Unrecognized operation: ' . $operation, 400);
    }
}

function sync_posts(array $args) {
    Operations::enqueue_sync_posts([]);
    wp_send_json_success();
}

function heartbeat_send($response, $screen_id) {
    if ($screen_id !== 'toplevel_page_miso') {
        return $response;
    }
    $response['miso_recent_tasks'] = Operations::recent_tasks();
    return $response;
}

add_action('admin_menu', __NAMESPACE__ . '\admin_menu');
add_action('wp_ajax_miso_send_form', __NAMESPACE__ . '\send_form');
add_filter('heartbeat_send', __NAMESPACE__ . '\heartbeat_send', 10, 2);
