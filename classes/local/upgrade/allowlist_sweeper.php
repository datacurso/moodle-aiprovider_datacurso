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

namespace aiprovider_datacurso\local\upgrade;

/**
 * Sweeps dead per-instance rate-limit allowlist keys out of ai_providers.config.
 *
 * Global-scope keys were already cleaned up by upgrade savepoint 2026042902
 * (unset_config()), but that call cannot reach the JSON config stored on each
 * aiprovider_datacurso provider *instance* row in ai_providers.config
 * (core_ai\provider::$config, see ai/classes/provider.php). This helper does that,
 * and is called from upgrade savepoint 2026082600 (db/upgrade.php).
 *
 * Extracted as a standalone class (rather than left inline in db/upgrade.php) so it
 * is directly unit-testable without executing the full upgrade path.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class allowlist_sweeper {
    /**
     * Regex patterns matching dead per-instance allowlist keys.
     *
     * Matches the old per-service user-allowlist toggles/user-id-lists (e.g.
     * ratelimit_local_coursegen_allowedusers, ratelimit_local_coursegen_allowedusers_enable)
     * and the old per-service creator/analyst role lists (e.g.
     * ratelimit_local_coursegen_coursecreators, ratelimit_report_lifestory_generalanalysts).
     *
     * @var string[]
     */
    private const DEAD_KEY_PATTERNS = [
        '/^ratelimit_[a-z0-9_]+_allowedusers(_enable)?$/',
        '/^ratelimit_[a-z0-9_]+_(coursecreators|activitycreators|courseanalysts|generalanalysts)$/',
    ];

    /**
     * Strip dead allowlist keys from every aiprovider_datacurso provider instance's
     * config. Idempotent: rows with no dead keys are left completely untouched
     * (no DB write, not counted).
     *
     * @param \moodle_database $db
     * @return int Number of instance rows actually updated.
     */
    public static function run(\moodle_database $db): int {
        $records = $db->get_records('ai_providers', ['provider' => \aiprovider_datacurso\provider::class]);

        $updated = 0;
        foreach ($records as $record) {
            $config = json_decode($record->config ?? '{}', true);
            if (!is_array($config)) {
                continue;
            }

            $before = $config;
            foreach (array_keys($config) as $key) {
                if (self::is_dead_key($key)) {
                    unset($config[$key]);
                }
            }

            if ($config === $before) {
                continue;
            }

            $record->config = json_encode($config);
            $db->update_record('ai_providers', $record);
            $updated++;
        }

        return $updated;
    }

    /**
     * Whether a config key is a dead per-instance allowlist key that must be stripped.
     *
     * @param string $key
     * @return bool
     */
    private static function is_dead_key(string $key): bool {
        foreach (self::DEAD_KEY_PATTERNS as $pattern) {
            if (preg_match($pattern, $key) === 1) {
                return true;
            }
        }

        return false;
    }
}
