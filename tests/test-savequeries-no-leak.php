<?php

/**
 * A full sync must not let $wpdb->queries grow with the catalog size when
 * SAVEQUERIES is enabled.
 *
 * SAVEQUERIES is turned on automatically whenever WP_DEBUG is enabled (common
 * on staging). With it on, WordPress appends the SQL, timing, and a caller
 * backtrace for every query to $wpdb->queries and never clears it. Across a
 * full reindex this array grows unbounded and OOMs the worker. run_sync_posts
 * resets it at the end of each page, so the residual log after a multi-page
 * sync is just the bounded tail of work that runs after the last page - it
 * does not scale with the number of pages. This test pins that behavior so a
 * regression (dropping the per-page reset) fails CI.
 */

class Miso_SaveQueries_Mock_Products {
    public function delete($ids) {}
    public function upload($records) {}
    public function ids($args = []) {
        return [];
    }
}

class Miso_SaveQueries_Mock_Client {
    public $products;

    public function __construct() {
        $this->products = new Miso_SaveQueries_Mock_Products();
    }
}

class Test_SaveQueries_No_Leak extends WP_UnitTestCase {

    private $mock;

    public function set_up() {
        parent::set_up();

        // Mirror a WP_DEBUG staging box: every query is logged to $wpdb->queries.
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }

        update_option('miso_settings', ['miso_api_key' => 'test-key']);
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        // The plugin creates the task table via dbDelta, which the SQLite test
        // drop-in can't execute (see test-auto-creates-task-table.php). Create
        // it directly so the sync's task writes succeed instead of erroring on
        // every update; the translator handles a plain CREATE TABLE fine.
        global $wpdb;
        $table = $wpdb->prefix . 'miso_task';
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$table} (
            id int NOT NULL AUTO_INCREMENT,
            type varchar(255) NOT NULL,
            status varchar(255) NOT NULL,
            created_via varchar(255) NOT NULL,
            created_by int,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            modified_at timestamp DEFAULT CURRENT_TIMESTAMP,
            args text,
            data text,
            PRIMARY KEY (id)
        )");

        $this->mock = new Miso_SaveQueries_Mock_Client();
        add_filter('miso_pre_create_client', [$this, 'inject_mock']);
    }

    public function tear_down() {
        remove_filter('miso_pre_create_client', [$this, 'inject_mock']);
        parent::tear_down();
    }

    public function inject_mock() {
        return $this->mock;
    }

    public function test_query_log_does_not_grow_with_pages() {
        global $wpdb;

        if (!SAVEQUERIES) {
            $this->markTestSkipped('SAVEQUERIES is forced off in this environment.');
        }

        // The sync pages at 100 posts/page; create enough to span two pages so
        // an unflushed log would carry a full page-1 plus a page-2 of queries.
        self::factory()->post->create_many(105, [
            'post_status' => 'publish',
            'post_type'   => 'post',
        ]);

        // Measure only the sync, not the factory inserts above.
        $wpdb->queries = [];

        Miso\Operations::sync_posts('test', Miso\Utils\normalize_post_to_record_args());

        // With the per-page reset, only the post-last-page tail survives, which
        // is far below even a single page's worth of post processing. Without
        // it, the two pages of per-post queries (hundreds) would remain.
        $this->assertLessThan(
            100,
            count($wpdb->queries),
            'Per-page $wpdb->queries reset is missing - the query log scales with catalog size and will OOM large syncs.'
        );
    }
}
