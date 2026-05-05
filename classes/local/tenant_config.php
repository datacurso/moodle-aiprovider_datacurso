<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace aiprovider_datacurso\local;

/**
 * Tenant configuration storage.
 *
 * Persists configuration values in mdl_aiprovider_datacurso_tenant_config
 * instead of mdl_config_plugins.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_config {
    /** @var string Database table name */
    private const TABLE = 'aiprovider_datacurso_tenant_config';

    /**
     * Save all configuration values coming from the tenant settings form.
     *
     * @param string    $plugin   Plugin name (e.g. aiprovider_datacurso)
     * @param int       $tenantid Tenant ID
     * @param \stdClass $data     Raw form data
     */
    public static function save_from_form(
        string $plugin,
        int $tenantid,
        \stdClass $data
    ): void {
        global $DB;

        foreach ((array)$data as $name => $value) {
            if (in_array($name, ['submitbutton', 'cancel'], true)) {
                continue;
            }

            if (is_array($value)) {
                $items = array_map('strval', $value);
                $items = array_filter($items, static fn($item): bool => $item !== '');
                $value = implode(',', $items);
            } else if (is_object($value)) {
                $value = json_encode($value);
            }

            $conditions = [
                'plugin'    => $plugin,
                'tenant_id' => $tenantid,
                'name'      => $name,
            ];

            $existingid = $DB->get_field(
                self::TABLE,
                'id',
                $conditions,
                IGNORE_MISSING
            );

            if ($existingid) {
                $record = (object)$conditions;
                $record->id    = $existingid;
                $record->value = (string)$value;

                $DB->update_record(self::TABLE, $record);
            } else {
                $record = (object)$conditions;
                $record->value = (string)$value;

                $DB->insert_record(self::TABLE, $record);
            }
        }
    }


    /**
     * Get a configuration value for a tenant.
     *
     * Falls back to Moodle global config if not found.
     *
     * @param string $plugin
     * @param int    $tenantid
     * @param string $name
     * @param mixed  $default
     * @return mixed
     */
    public static function get(
        string $plugin,
        int $tenantid,
        string $name,
        $default = null
    ) {
        global $DB;

        $value = $DB->get_field(
            self::TABLE,
            'value',
            [
                'plugin'    => $plugin,
                'tenant_id' => $tenantid,
                'name'      => $name,
            ]
        );

        if ($value !== false) {
            // Attempt JSON decode.
            $decoded = json_decode($value);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        // Fallback to global plugin config.
        $global = get_config($plugin, $name);
        return $global !== false ? $global : $default;
    }

    /**
     * Get all configuration values for a tenant.
     *
     * @param string $plugin
     * @param int    $tenantid
     * @return array
     */
    public static function get_all(string $plugin, int $tenantid): array {
        global $DB;

        $records = $DB->get_records(
            self::TABLE,
            [
                'plugin'    => $plugin,
                'tenant_id' => $tenantid,
            ],
            '',
            'name, value'
        );

        $config = [];

        foreach ($records as $record) {
            $decoded = json_decode($record->value);
            $config[$record->name] =
                json_last_error() === JSON_ERROR_NONE
                    ? $decoded
                    : $record->value;
        }

        return $config;
    }

    /**
     * Delete all configuration for a tenant.
     *
     * @param string $plugin
     * @param int    $tenantid
     */
    public static function delete_all(string $plugin, int $tenantid): void {
        global $DB;

        $DB->delete_records(self::TABLE, [
            'plugin'    => $plugin,
            'tenant_id' => $tenantid,
        ]);
    }

    /**
     * Delete specific configuration names across all tenants for a plugin.
     *
     * @param string $plugin
     * @param string[] $names
     */
    public static function delete_names(string $plugin, array $names): void {
        global $DB;

        $names = array_values(array_filter(array_map('strval', $names), static function (string $name): bool {
            return $name !== '';
        }));

        if (empty($names)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($names, SQL_PARAMS_NAMED);
        $params['plugin'] = $plugin;

        $DB->delete_records_select(
            self::TABLE,
            "plugin = :plugin AND name {$insql}",
            $params
        );
    }
}
