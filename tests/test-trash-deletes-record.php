<?php

/**
 * Trashing a published post should cascade a delete call to the Miso catalog.
 *
 * WordPress implements trashing as a post_status change to 'trash', which
 * fires wp_after_insert_post just like a normal update. The plugin reads the
 * non-publish status as "should be deleted" and calls products->delete().
 * This test pins that behavior so a future change to the hook or the
 * shall_be_deleted logic that breaks trash-cascade fails CI.
 */

class Miso_Mock_Products {
    public $delete_calls = [];
    public $upload_calls = [];

    public function delete($ids) {
        $this->delete_calls[] = $ids;
    }

    public function upload($records) {
        $this->upload_calls[] = $records;
    }

    public function ids($args = []) {
        return [];
    }
}

class Miso_Mock_Client {
    public $products;

    public function __construct() {
        $this->products = new Miso_Mock_Products();
    }
}

class Test_Trash_Deletes_Record extends WP_UnitTestCase {

    private $mock;

    public function set_up() {
        parent::set_up();

        update_option('miso_settings', ['miso_api_key' => 'test-key']);
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $this->mock = new Miso_Mock_Client();
        add_filter('miso_pre_create_client', [$this, 'inject_mock']);
    }

    public function tear_down() {
        remove_filter('miso_pre_create_client', [$this, 'inject_mock']);
        parent::tear_down();
    }

    public function inject_mock() {
        return $this->mock;
    }

    public function test_trashing_a_published_post_calls_products_delete() {
        $post_id = self::factory()->post->create([
            'post_status' => 'publish',
            'post_type'   => 'post',
        ]);

        // Only count what happens after the trash transition.
        $this->mock->products->delete_calls = [];
        $this->mock->products->upload_calls = [];

        wp_trash_post($post_id);

        $this->assertCount(1, $this->mock->products->delete_calls, 'Expected exactly one products->delete call after trashing.');
        $this->assertSame([strval($post_id)], $this->mock->products->delete_calls[0]);
        $this->assertCount(0, $this->mock->products->upload_calls, 'Trashing must not trigger an upload.');
    }
}
