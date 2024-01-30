<?php
/**
 * Plugin Name:       Miso AI
 * Plugin URI:        https://miso.ai/
 * Description:       The official WordPress plugin for Miso AI data integration. 
 * Version:           0.9.0
 * Author:            Simon Pai
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://github.com/MisoAI/miso-wordpress-plugin
 */

require_once __DIR__ . '/vendor/woocommerce/action-scheduler/action-scheduler.php';
require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/src/utils.php';
require_once __DIR__ . '/src/client.php';
require_once __DIR__ . '/src/database.php';
require_once __DIR__ . '/src/operations.php';

// filters: including function that transform WP post to Miso record
require_once __DIR__ . '/src/filters.php';

// actions: automatic cascade post updates to Miso catalog
require_once __DIR__ . '/src/actions.php';

// adds commands to WP CLI
require_once __DIR__ . '/src/wp-cli.php';

// adds admin pages
require_once __DIR__ . '/src/admin/index.php';

register_activation_hook(__FILE__, function() {
    Miso\DataBase::install();
});
register_deactivation_hook(__FILE__, function() {
    Miso\DataBase::uninstall();
});
