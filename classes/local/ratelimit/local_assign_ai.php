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
 * Class local_assign_ai
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Wilber Narvaez <https://datacurso.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_assign_ai extends ratelimit_settings {
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
            new \lang_string('ratelimit_local_assign_ai_allowedusers_enable', self::PLUGIN),
            new \lang_string('ratelimit_local_assign_ai_allowedusers_enable_desc', self::PLUGIN),
            0
        );
        $settings->add($allowedusersenable);

        $attributes = $this->get_autocomplete_attributes();
        $choices = $this->get_user_choices([
            'local/assign_ai:review',
            'local/assign_ai:changestatus',
            'local/assign_ai:viewdetails',
            'mod/assign:submit',
        ]);

        $settings->add(
            new autocomplete(
                "{$configprefix}_allowedusers",
                new \lang_string('ratelimit_local_assign_ai_allowedusers', self::PLUGIN),
                new \lang_string('ratelimit_local_assign_ai_allowedusers_desc', self::PLUGIN),
                [],
                $choices,
                $attributes
            )
        );
    }
}
