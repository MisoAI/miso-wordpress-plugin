<?php

/**
 * The task table must be ensured on every site that loads the plugin, not just
 * the one register_activation_hook ran on. On multisite the activation hook
 * never fires for subsites created after network activation, and it never fires
 * on auto-update for any site (auto-updates skip activation hooks). Such sites
 * would call recent_tasks() against a missing <prefix>miso_task table and hit
 * MySQL error 1146 ("Table doesn't exist").
 *
 * The fix gates a per-request install() behind the per-site miso_db_version
 * option (DataBase::maybe_upgrade(), hooked to init). These tests pin the
 * gating contract — install runs when the version is absent (new subsite) or
 * stale (auto-update), and is skipped when current (the hot path) — and the
 * init wiring that makes it run on every request.
 *
 * Note: this suite runs against the SQLite drop-in, which can't execute the
 * dbDelta CREATE TABLE, so we assert install()'s observable control flow rather
 * than the physical table. On real MySQL the same install() creates the table.
 */

use Miso\DataBase;

class Test_Auto_Creates_Task_Table extends WP_UnitTestCase {

    /** Counts install() invocations: install() always calls update_option('miso_db_version'),
     *  and pre_update_option_* fires on every update_option call, before the value-equality
     *  short-circuit — so it registers even when the version is rewritten to the same value. */
    private $install_runs = 0;

    public function set_up() {
        parent::set_up();
        add_filter('pre_update_option_miso_db_version', [$this, 'count_install']);
    }

    public function tear_down() {
        remove_filter('pre_update_option_miso_db_version', [$this, 'count_install']);
        parent::tear_down();
    }

    public function count_install($value) {
        $this->install_runs++;
        return $value;
    }

    public function test_installs_when_version_absent() {
        // A fresh multisite subsite: activation hook never ran here.
        delete_option('miso_db_version');
        $this->install_runs = 0;

        DataBase::maybe_upgrade();

        $this->assertSame(1, $this->install_runs, 'maybe_upgrade() must install when no db version is recorded.');
        $this->assertSame(DataBase::DB_VERSION, get_option('miso_db_version'));
    }

    public function test_installs_when_version_stale() {
        // An auto-update bumped the plugin code but never fired the activation hook.
        update_option('miso_db_version', '0');
        $this->install_runs = 0;

        DataBase::maybe_upgrade();

        $this->assertSame(1, $this->install_runs, 'maybe_upgrade() must re-install when the recorded db version is older than the code.');
        $this->assertSame(DataBase::DB_VERSION, get_option('miso_db_version'));
    }

    public function test_noop_when_version_current() {
        // The hot path: every normal request must not touch the schema.
        update_option('miso_db_version', DataBase::DB_VERSION);
        $this->install_runs = 0;

        DataBase::maybe_upgrade();

        $this->assertSame(0, $this->install_runs, 'maybe_upgrade() must be a no-op once the recorded version is current.');
    }

    public function test_init_hook_runs_upgrade() {
        // Pin the wiring: the plugin hooks maybe_upgrade() to init so it runs on
        // every request, covering sites the activation hook never reached.
        delete_option('miso_db_version');

        do_action('init');

        $this->assertSame(DataBase::DB_VERSION, get_option('miso_db_version'), 'The init hook must ensure the schema on every request.');
    }
}
