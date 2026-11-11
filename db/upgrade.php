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

    if ($oldversion < 2025121900) {
        // Settings of the provider kept per tenant.
        aiprovider_datacurso_create_tenant_config_table($dbman);

        // Datacurso savepoint reached.
        upgrade_plugin_savepoint(true, 2025121900, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026050501) {
        $table = new xmldb_table('aiprovider_datacurso_userlimit');

        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }

        upgrade_plugin_savepoint(true, 2026050501, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026050502) {
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

        upgrade_plugin_savepoint(true, 2026050502, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026050503) {
        // Workplace adaptation: clean legacy per-user/allowlist values saved per tenant.
        \aiprovider_datacurso\local\tenant_config::delete_names('aiprovider_datacurso', [
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
        ]);

        upgrade_plugin_savepoint(true, 2026050503, 'aiprovider', 'datacurso');
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

    if ($oldversion < 2026090100) {
        // Local mirror of the external consumption history, synced on demand for Report Builder.
        aiprovider_datacurso_create_consumption_table($dbman);

        upgrade_plugin_savepoint(true, 2026090100, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026091601) {
        // The tenant_config table is already created by the 2025121900 step above;
        // db/install.xml now declares it too, so no further action is needed here.
        upgrade_plugin_savepoint(true, 2026091601, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026100701) {
        // The 2026090100 step above comes from MOODLE_405_STABLE, whose versions are lower than the
        // 2026091601 that WORKPLACE_405_STABLE had already reached: a Workplace site upgrading from
        // 1.4.3-wp skips it and never gets the consumption table. Create it here for those sites.
        aiprovider_datacurso_create_consumption_table($dbman);

        upgrade_plugin_savepoint(true, 2026100701, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026100800) {
        // The consumption mirror is read per tenant: each record keeps the tenant of the user who
        // consumed and the fingerprint of the licence it was synced with, so each licence keeps its
        // own sync watermark. The rows already mirrored carry neither and cannot be attributed, so
        // the mirror is emptied; the history is pulled again the next time the page is opened.
        aiprovider_datacurso_add_consumption_tenancy($dbman);
        $DB->delete_records('aiprovider_datacurso_consumption');

        upgrade_plugin_savepoint(true, 2026100800, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026102900) {
        // The suite plugins drop their own "Enabled" setting and ask the provider instead. Copy the current
        // value of each one into the provider's site default first, so that no site changes state when it
        // upgrades. Plugins without a setting stay enabled. The per-tenant values are copied by each suite
        // plugin in its own upgrade.
        \aiprovider_datacurso\local\plugin_settings::migrate_legacy_flags();

        upgrade_plugin_savepoint(true, 2026102900, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026111001) {
        // A site that ran the provider of MOODLE_405_STABLE (1.7.0 is 2026110400, 1.7.1 is 2026111000)
        // and then moved to this branch skips the Workplace steps numbered below its version: 2025121900
        // (the table of the settings per tenant) and 2026100800 (the tenant and the licence of each
        // consumption record). The consumption history then fails with "Unknown column tenant_id".
        // Create what is missing; a site that already has it is left as it is.
        aiprovider_datacurso_create_tenant_config_table($dbman);
        aiprovider_datacurso_create_consumption_table($dbman);
        // When the fields are missing, the rows mirrored without them are emptied and pulled again.
        aiprovider_datacurso_add_consumption_tenancy($dbman);

        upgrade_plugin_savepoint(true, 2026111001, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026111002) {
        // Generate the per-site secret behind the user pseudonyms sent to the Datacurso
        // services, so it exists before the first request instead of being written lazily.
        // A site that comes from MOODLE_405_STABLE already has it and keeps it.
        \aiprovider_datacurso\local\outbound_privacy::get_pseudonym_secret();

        upgrade_plugin_savepoint(true, 2026111002, 'aiprovider', 'datacurso');
    }

    if ($oldversion < 2026111101) {
        // Until 1.7.1-wp the AI clients did not read the provider switch of Site administration > AI >
        // AI providers, so a site whose switch nobody ever touched sent its requests with it off. From now
        // on the switch stops them: switch it on for those sites, so nothing stops working on upgrade. A
        // switch that someone turned off (it has a config_log row) stays off.
        $touched = $DB->record_exists('config_log', ['plugin' => 'aiprovider_datacurso', 'name' => 'enabled']);
        if (!$touched && !\core\plugininfo\aiprovider::is_plugin_enabled('datacurso')) {
            \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        }

        upgrade_plugin_savepoint(true, 2026111101, 'aiprovider', 'datacurso');
    }

    return true;
}

/**
 * Create the local mirror of the consumption history when it does not exist yet.
 *
 * @param database_manager $dbman
 */
function aiprovider_datacurso_create_consumption_table(database_manager $dbman): void {
    // Local mirror of the external consumption history, synced on demand for Report Builder.
    $table = new xmldb_table('aiprovider_datacurso_consumption');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('externalid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
    $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('service', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, null);
    $table->add_field('action', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
    $table->add_field('credits', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('balance', XMLDB_TYPE_NUMBER, '12, 2', null, XMLDB_NOTNULL, null, '0');
    $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
    $table->add_key('externalid', XMLDB_KEY_UNIQUE, ['externalid']);

    $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
    $table->add_index('service', XMLDB_INDEX_NOTUNIQUE, ['service']);
    $table->add_index('action', XMLDB_INDEX_NOTUNIQUE, ['action']);

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

/**
 * Create the table of the settings kept per tenant when it does not exist yet.
 *
 * @param database_manager $dbman
 */
function aiprovider_datacurso_create_tenant_config_table(database_manager $dbman): void {
    $table = new xmldb_table('aiprovider_datacurso_tenant_config');

    $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
    $table->add_field('plugin', XMLDB_TYPE_CHAR, '100', null, null, null, null);
    $table->add_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
    $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, null, null, null);
    $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

    $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
    $table->add_key('plugin_tenant_name_uk', XMLDB_KEY_UNIQUE, ['plugin', 'tenant_id', 'name']);

    if (!$dbman->table_exists($table)) {
        $dbman->create_table($table);
    }
}

/**
 * Add the tenant and the licence of each record to the consumption mirror when they are missing.
 *
 * The consumption mirror is read per tenant: each record keeps the tenant of the user who consumed
 * and the fingerprint of the licence it was synced with, so each licence keeps its own sync watermark.
 *
 * @param database_manager $dbman
 * When a field is missing, the rows already mirrored are deleted before it is added.
 *
 * @return bool Whether a field was added.
 */
function aiprovider_datacurso_add_consumption_tenancy(database_manager $dbman): bool {
    global $DB;

    $table = new xmldb_table('aiprovider_datacurso_consumption');

    $fields = [
        new xmldb_field('tenant_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'userid'),
        new xmldb_field('licence', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '', 'tenant_id'),
    ];
    $missing = array_filter($fields, static fn(xmldb_field $field): bool => !$dbman->field_exists($table, $field));
    if ($missing !== []) {
        // The rows mirrored without them cannot be attributed: the mirror is emptied first and
        // pulled again the next time it is opened.
        $DB->delete_records('aiprovider_datacurso_consumption');
    }
    foreach ($missing as $field) {
        $dbman->add_field($table, $field);
    }

    $indexes = [
        new xmldb_index('tenant_id', XMLDB_INDEX_NOTUNIQUE, ['tenant_id']),
        new xmldb_index('licence', XMLDB_INDEX_NOTUNIQUE, ['licence']),
    ];
    foreach ($indexes as $index) {
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    return $missing !== [];
}
