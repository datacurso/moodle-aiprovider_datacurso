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

namespace aiprovider_datacurso\local\ratelimit;

use admin_settingpage;
use core_admin\local\settings\autocomplete;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Class local_datacurso_ratings
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_datacurso_ratings extends ratelimit_settings {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add the rate limit settings related to ratings analysis with AI.
     *
     * @param admin_settingpage $settings Settings page to append controls to.
     * @param string $component Component name used to namespace config keys.
     */
    public function add_settings(admin_settingpage $settings, string $component): void {
        $configprefix = self::PLUGIN . "/ratelimit_{$component}";

        // Checkbox to enable limiting by allowed users list.
        $allowedusersenable = new \admin_setting_configcheckbox(
            "{$configprefix}_allowedusers_enable",
            new \lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable', self::PLUGIN),
            new \lang_string('ratelimit_local_datacurso_ratings_allowedusers_enable_desc', self::PLUGIN),
            0
        );
        $settings->add($allowedusersenable);

        $attributes = $this->get_autocomplete_attributes();

        // Course analysis generators.
        $coursechoices = $this->get_user_choices([
            'local/datacurso_ratings:generateanalysiscourse',
            'local/datacurso_ratings:generateanalysisactivity',
        ]);
        $courseanalysts = new autocomplete(
            "{$configprefix}_courseanalysts",
            new \lang_string('ratelimit_local_datacurso_ratings_courseanalysts', self::PLUGIN),
            new \lang_string('ratelimit_local_datacurso_ratings_courseanalysts_desc', self::PLUGIN),
            [],
            $coursechoices,
            $attributes
        );
        $settings->add($courseanalysts);
        $settings->hide_if("{$configprefix}_courseanalysts", "{$configprefix}_allowedusers_enable", 'eq', 0);

        // General analysis generators.
        $generalchoices = $this->get_user_choices([
            'local/datacurso_ratings:generateanalysisgeneral',
        ]);
        $generalanalysts = new autocomplete(
            "{$configprefix}_generalanalysts",
            new \lang_string('ratelimit_local_datacurso_ratings_generalanalysts', self::PLUGIN),
            new \lang_string('ratelimit_local_datacurso_ratings_generalanalysts_desc', self::PLUGIN),
            [],
            $generalchoices,
            $attributes
        );
        $settings->add($generalanalysts);
        $settings->hide_if("{$configprefix}_generalanalysts", "{$configprefix}_allowedusers_enable", 'eq', 0);
    }

    /**
     * Get the allowed user ids for local_datacurso_ratings, by action.
     *
     * - "/rating/course" and "/rating/query" use the "courseanalysts" field.
     * - "/rating/general" uses the "generalanalysts" field.
     *
     * @param string $serviceid Service id, expected "local_datacurso_ratings".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'local_datacurso_ratings') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/rating/course' => 'ratelimit_local_datacurso_ratings_courseanalysts',
            '/rating/query' => 'ratelimit_local_datacurso_ratings_courseanalysts',
            '/rating/general' => 'ratelimit_local_datacurso_ratings_generalanalysts',
        ];

        $configkey = self::resolve_config_key_for_action($actionpath, $mapping);
        if ($configkey === null) {
            return [];
        }

        $config = get_config(self::PLUGIN);
        $raw = $config->{$configkey} ?? '';

        return self::extract_user_ids($raw);
    }
}
