<?php

/**
 * Regression test for GitHub issue #12.
 *
 * The plugin previously hooked save_post, which fires before meta_input and
 * tax_input are persisted. The fix migrates to wp_after_insert_post; this
 * test pins that behavior so a future revert to save_post fails CI.
 */
class Test_Hook_Timing extends WP_UnitTestCase {

    public function test_meta_and_taxonomy_are_persisted_when_wp_after_insert_post_fires() {
        // wp_insert_post() drops tax_input when the current user lacks
        // assign_terms capability (edit_posts for post_tag).
        wp_set_current_user(self::factory()->user->create(['role' => 'editor']));

        $captured = ['meta' => null, 'tags' => null];

        add_action('wp_after_insert_post', function ($id) use (&$captured) {
            $captured['meta'] = get_post_meta($id, 'miso_test_key', true);
            $captured['tags'] = wp_get_post_terms($id, 'post_tag', ['fields' => 'names']);
        }, 5);

        wp_insert_post([
            'post_title'   => 'Test post',
            'post_status'  => 'publish',
            'post_type'    => 'post',
            'meta_input'   => ['miso_test_key' => 'expected_value'],
            'tax_input'    => ['post_tag' => ['alpha', 'beta']],
        ]);

        $this->assertSame('expected_value', $captured['meta']);
        $this->assertIsArray($captured['tags']);
        $this->assertContains('alpha', $captured['tags']);
        $this->assertContains('beta', $captured['tags']);
    }

    public function test_plugin_registers_update_post_on_wp_after_insert_post() {
        $this->assertNotFalse(
            has_action('wp_after_insert_post', 'Miso\\update_post'),
            'Miso\\update_post must be registered on wp_after_insert_post'
        );
        $this->assertFalse(
            has_action('save_post', 'Miso\\update_post'),
            'Miso\\update_post must not be registered on save_post (reverts cause incomplete syncs — see issue #12)'
        );
    }
}
