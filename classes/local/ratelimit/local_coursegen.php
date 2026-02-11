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
 * Class local_coursegen
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_coursegen extends ratelimit_settings {
    /** @var string Plugin component name. */
    private const PLUGIN = 'aiprovider_datacurso';

    /**
     * Add the rate limit settings related to course generation.
     *
     * @param admin_settingpage $settings Settings page to append controls to.
     * @param string $component Component name used to namespace config keys.
     */
    public function add_settings(admin_settingpage $settings, string $component): void {
        $configprefix = self::PLUGIN . "/ratelimit_{$component}";

        // Checkbox to enable limiting by allowed users list.
        $allowedusersenable = new \admin_setting_configcheckbox(
            "{$configprefix}_allowedusers_enable",
            new \lang_string('ratelimit_local_coursegen_allowedusers_enable', self::PLUGIN),
            new \lang_string('ratelimit_local_coursegen_allowedusers_enable_desc', self::PLUGIN),
            0
        );
        $settings->add($allowedusersenable);

        $attributes = $this->get_autocomplete_attributes();
        $choices = $this->get_user_choices([
            'moodle/course:create',
            'local/coursegen:createcoursewithai',
        ]);

        $coursecreators = new autocomplete(
            "{$configprefix}_coursecreators",
            new \lang_string('ratelimit_local_coursegen_coursecreators', self::PLUGIN),
            new \lang_string('ratelimit_local_coursegen_coursecreators_desc', self::PLUGIN),
            [],
            $choices,
            $attributes
        );
        $settings->add($coursecreators);
        $settings->hide_if("{$configprefix}_coursecreators", "{$configprefix}_allowedusers_enable", 'eq', 0);

        $choices = ratelimit_settings::get_user_choices([
            'moodle/course:manageactivities',
            'local/coursegen:createactivitywithai',
        ]);
        $activitycreators = new autocomplete(
            "{$configprefix}_activitycreators",
            new \lang_string('ratelimit_local_coursegen_activitycreators', self::PLUGIN),
            new \lang_string('ratelimit_local_coursegen_activitycreators_desc', self::PLUGIN),
            [],
            $choices,
            $attributes
        );
        $settings->add($activitycreators);
        $settings->hide_if("{$configprefix}_activitycreators", "{$configprefix}_allowedusers_enable", 'eq', 0);
    }

    /**
     * Get the allowed user ids for local_coursegen, separated by action path.
     *
     * - Course creation actions ("/course/") use the "coursecreators" field.
     * - Activity creation actions ("/resources/create-mod") use
     *   the "activitycreators" field.
     * - Other actions for this service have no specific restriction.
     *
     * @param string $serviceid Service id, expected "local_coursegen".
     * @param string|null $actionpath HTTP path for the remote call.
     * @return int[]
     */
    public static function get_allowed_service_user_ids(string $serviceid, ?string $actionpath): array {
        if ($serviceid !== 'local_coursegen') {
            return [];
        }

        if (empty($actionpath)) {
            return [];
        }

        $mapping = [
            '/course/v2/start' => 'ratelimit_local_coursegen_coursecreators',
            '/course/start' => 'ratelimit_local_coursegen_coursecreators',
            '/resources/create-mod' => 'ratelimit_local_coursegen_activitycreators',
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
