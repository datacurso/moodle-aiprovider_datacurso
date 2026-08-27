<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     aiprovider_datacurso
 * @category    upgrade
 * @copyright   Josue <josue@datacurso.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute aiprovider_datacurso upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_aiprovider_datacurso_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    // For further information please read {@link https://docs.moodle.org/dev/Upgrade_API}.
    //
    // You will also have to create the db/install.xml file by using the XMLDB Editor.
    // Documentation for the XMLDB Editor can be found at {@link https://docs.moodle.org/dev/XMLDB_editor}.
    if ($oldversion < 2025110500) {
        // Define table aiprovider_datacurso_rlimit to be created.
        $table = new xmldb_table('aiprovider_datacurso_rlimit');

        // Adding fields to table aiprovider_datacurso_rlimit.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('serviceid', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
        $table->add_field('windowstart', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('tokensused', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('lastsync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table aiprovider_datacurso_rlimit.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Conditionally launch create table for aiprovider_datacurso_rlimit.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Datacurso savepoint reached.
        upgrade_plugin_savepoint(true, 2025110500, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025110600) {
        upgrade_plugin_savepoint(true, 2025110600, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025112705) {
        upgrade_plugin_savepoint(true, 2025112705, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025112706) {
        // The Datacurso webservice self-configuration feature was removed in 2.1.0 together with
        // \aiprovider_datacurso\webservice_config. The original body called
        // webservice_config::upgrade_sync_ws_and_capabilities() inside a try/catch that only caught
        // \Exception, so a missing class would raise an uncatchable \Error and abort the upgrade.
        // The savepoint number is retained because released checkpoint history is immutable; the
        // artifacts this step used to create are cleaned up by step 2026071601 below.
        upgrade_plugin_savepoint(true, 2025112706, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025120201) {
        upgrade_plugin_savepoint(true, 2025120201, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025120301) {
        // Define table aiprovider_datacurso_userlimit to be created.
        $table = new xmldb_table('aiprovider_datacurso_userlimit');

        // Adding fields to table aiprovider_datacurso_userlimit.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tokenlimit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tokensused', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('countfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastsync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table aiprovider_datacurso_userlimit.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for aiprovider_datacurso_userlimit.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Datacurso savepoint reached.
        upgrade_plugin_savepoint(true, 2025120301, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2025121002) {
        // Define table aiprovider_datacurso_userlimit to be created.
        $table = new xmldb_table('aiprovider_datacurso_userlimit');

        // Adding fields to table aiprovider_datacurso_userlimit.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tokenlimit', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tokensused', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('countfrom', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastsync', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table aiprovider_datacurso_userlimit.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid_fk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('usermodified_fk', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for aiprovider_datacurso_userlimit.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Datacurso savepoint reached.
        upgrade_plugin_savepoint(true, 2025121002, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026030200) {
        $table = new xmldb_table('aiprovider_datacurso_userlimit');

        $field = new xmldb_field('nextresetat', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'lastsync');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('recurringintervalenabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'lastsync');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'recurringintervalunit',
            XMLDB_TYPE_CHAR,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            'day',
            'recurringintervalenabled'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field(
            'recurringintervalvalue',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'recurringintervalunit'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026030200, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026042200) {
        $table = new xmldb_table('aiprovider_datacurso_userlimit');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026042200, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026042902) {
        $obsoletekeys = [
            'ratelimit_local_assign_ai_allowedusers_enable',
            'ratelimit_local_assign_ai_allowedusers',
            'ratelimit_local_coursegen_allowedusers_enable',
            'ratelimit_local_coursegen_coursecreators',
            'ratelimit_local_coursegen_activitycreators',
            'ratelimit_local_datacurso_ratings_allowedusers_enable',
            'ratelimit_local_datacurso_ratings_courseanalysts',
            'ratelimit_local_datacurso_ratings_generalanalysts',
            'ratelimit_report_lifestory_allowedusers_enable',
            'ratelimit_report_lifestory_allowedusers',
        ];

        foreach ($obsoletekeys as $key) {
            unset_config($key, 'aiprovider_datacurso');
        }

        upgrade_plugin_savepoint(true, 2026042902, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026062601) {
        // Rate limit enforcement moved to the Datacurso service (token-manager), which now
        // accumulates the per-plugin credit consumption. The local per-window usage table is
        // no longer used.
        $table = new xmldb_table('aiprovider_datacurso_rlimit');
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026062601, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026071601) {
        // Clean up the artifacts created by the removed Datacurso webservice setup
        // (role, external service, tokens, registration flags).
        // Global settings (enablewebservices, webservice auth plugin, REST protocol)
        // are intentionally left untouched: other integrations may rely on them.
        // The 'datacursows' account is also left untouched on purpose. Deleting a user
        // account is a site decision, not a plugin one, and delete_user() cannot run from
        // an upgrade step: it calls \core\session\manager::destroy_user_sessions(), which
        // makes lazily initialised session handlers (Redis) call session_set_save_handler()
        // after output has already been sent, aborting the upgrade. Site administrators can
        // delete the account from the users management page once the upgrade is complete.
        require_once($CFG->dirroot . '/webservice/lib.php');

        if ($service = $DB->get_record('external_services', ['shortname' => 'datacursows'])) {
            (new webservice())->delete_service($service->id);
        }

        if ($roleid = $DB->get_field('role', 'id', ['shortname' => 'datacursows'])) {
            delete_role($roleid);
        }

        foreach (['registration_verified', 'registration_lastsent', 'registration_laststatus'] as $key) {
            unset_config($key, 'aiprovider_datacurso');
        }

        upgrade_plugin_savepoint(true, 2026071601, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026082600) {
        // Per-service user allowlists were removed in 2.1.0. Their global-scope keys were
        // already cleaned up by step 2026042902 (unset_config()), but that call cannot reach
        // the per-instance config JSON stored on each aiprovider_datacurso row in
        // ai_providers.config. Strip them instance by instance.
        \aiprovider_datacurso\local\upgrade\allowlist_sweeper::run($DB);

        upgrade_plugin_savepoint(true, 2026082600, 'aiprovider', 'datacurso');
    }

    return true;
}
