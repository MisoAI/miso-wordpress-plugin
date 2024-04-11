<?php

namespace Miso;

use Miso\DataBase;

class Operations {

    public static function recent_tasks() {
        return DataBase::recent_tasks();
    }

    public static function enqueue_sync_posts($source, $args = []) {

        // TODO: bounce if another task is running

        $task = self::create_task($source, $args, 'queued');
        as_enqueue_async_action('miso_sync_posts_hook', [$task]);
        spawn_cron();
    }

    public static function sync_posts($source, $args) {
        $task = self::create_task($source, $args, 'started');
        self::run_sync_posts($task);
    }

    public static function run_sync_posts($task) {

        // TODO: bounce if another task is running

        $args = $task['args'] ?? [];
        $query = $args['query'] ?? [
            'post_type' => 'post',
            'post_status' => 'publish',
        ];

        try {
            $miso = create_client();

            $total = (new \WP_Query($query))->found_posts;
            $task['status'] = 'running';
            $task['data']['phase'] = 'upload';
            $task['data']['total'] = $total;
            $task['data']['uploaded'] = 0;

            self::update_task($task);

            $page = 1;
            $wpIds = [];
            $records = [];

            do {
                // get paged posts
                $posts = new \WP_Query(array_merge($query, [
                    'posts_per_page' => 100,
                    'paged' => $page,
                ]));
                if (!$posts->have_posts()) {
                    break;
                }

                // transform posts to Miso records
                foreach ($posts->posts as $post) {
                    $record = post_to_record($post);
                    $records[] = $record;

                    // keep track of post IDs
                    $wpIds[] = $record['product_id'];

                    // send to Miso API
                    if (count($records) >= 20) {
                        $miso->products->upload($records);
                        $task['data']['uploaded'] += count($records);
                        self::update_task($task);
                        $records = [];
                    }
                }

                $page++;
            } while (true);

            // send to Miso API
            if (count($records) > 0) {
                $miso->products->upload($records);
                $task['data']['uploaded'] += count($records);
                self::update_task($task);
            }

            // compare ids and delete records that no longer exist
            $task['data']['phase'] = 'delete';
            self::update_task($task);

            $misoIds = [];
            try {
                // on first sync, the catalog index may not be ready in time, throwing 404 error
                $misoIds = $miso->products->ids();
            } catch (\Exception $e) {
                // ignore
            }
            $idsToDelete = array_diff($misoIds, $wpIds);
            $deleted = count($idsToDelete);
            if ($deleted > 0) {
                $miso->products->delete($idsToDelete);
            }

            $task['data']['deleted'] = $deleted;
            $task['data']['phase'] = 'done';
            $task['status'] = 'done';
            self::update_task($task);

        } catch (\Exception $e) {
            $task['status'] = 'failed';
            $task['data']['error'] = $e->getMessage();
            self::update_task($task);
            throw $e;
        }
    }

    protected static function create_task($source, $args, $status) {
        $current_user = wp_get_current_user();
        $task = DataBase::create_task([
            'type' => 'sync_posts',
            'created_by' => $current_user->ID,
            'created_via' => $source, // 'admin-page', 'wp-cli'
            'args' => $args,
            'status' => $status,
        ]);
        do_action('miso_task_progress', $task);
        return $task;
    }

    protected static function update_task($task) {
        DataBase::update_task($task);
        do_action('miso_task_progress', $task);
    }

}

add_action('miso_sync_posts_hook', [__NAMESPACE__ . '\Operations', 'run_sync_posts'], 10, 1);
