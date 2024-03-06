<?php

namespace Miso;

class DataBase {

    public static function install() {
        self::create_task_table();
    }

    public static function uninstall() {
        self::drop_task_table();
    }

    public static function recent_tasks() {
        global $wpdb;
        $table_name = self::table_name('task');
        $tasks = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 10"), ARRAY_A);
        return array_map(function($task) {
            $task['args'] = json_decode($task['args'], true);
            $task['data'] = json_decode($task['data'], true);
            return $task;
        }, $tasks);
    }

    public static function create_task($task) {
        try {
            self::truncate_task_table();
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        global $wpdb;
        $table_name = self::table_name('task');
        $current_time = current_time('mysql', 1);
        $wpdb->insert($table_name, array_merge($task, [
            'created_at' => $current_time,
            'modified_at' => $current_time,
            'args' => wp_json_encode($task['args'] ?? []),
            'data' => wp_json_encode($task['data'] ?? []),
        ]));
        $task['id'] = $wpdb->insert_id;
        return $task;
    }

    public static function update_task($task) {
        global $wpdb;
        $table_name = self::table_name('task');
        $current_time = current_time('mysql', 1);
        $wpdb->update(
            $table_name, 
            [
                'status' => $task['status'],
                'modified_at' => $current_time,
                'data' => wp_json_encode($task['data'] ?? []),
            ],
            [
                'id' => $task['id'],
            ],
        );
    }

    protected static function truncate_task_table() {
        global $wpdb;
        $table_name = self::table_name('task');
        $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} ORDER BY id DESC LIMIT 1 OFFSET 30"));
        if ($id !== null) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$table_name} WHERE id <= %d", $id));
        }
    }

    protected static function create_task_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = self::table_name('task');
        $sql = "
            CREATE TABLE IF NOT EXISTS {$table_name} (
                id int NOT NULL AUTO_INCREMENT,
                type varchar(255) NOT NULL,
                status varchar(255) NOT NULL,
                created_via varchar(255) NOT NULL,
                created_by int,
                created_at timestamp DEFAULT CURRENT_TIMESTAMP,
                modified_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                args json,
                data json,
                PRIMARY KEY (id),
                INDEX (created_at)
            ) $charset_collate;
        ";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    protected static function drop_task_table() {
        global $wpdb;
        $table_name = self::table_name('task');
        $wpdb->query($wpdb->prepare("DROP TABLE IF EXISTS {$table_name}"));
    }

    protected static function table_name($name) {
        global $wpdb;
        return $wpdb->prefix . 'miso_' . $name;
    }

}
