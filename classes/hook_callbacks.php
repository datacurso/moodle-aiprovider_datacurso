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

namespace aiprovider_datacurso;

use core\hook\navigation\primary_extend;
use navigation_node;
use moodle_url;

/**
 * Hook callbacks for the Datacurso AI provider.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {

    /**
     * Add a "Datacurso AI Provider" item to the primary navigation (top bar), next to
     * Site administration. Visible only to users who can view the provider reports/config.
     *
     * @param primary_extend $hook The primary navigation hook.
     */
    public static function extend_primary_navigation(primary_extend $hook): void {
        // Only for logged-in, non-guest users with the provider reports capability.
        if (!isloggedin() || isguestuser()) {
            return;
        }
        $context = \context_system::instance();
        if (!has_capability('aiprovider/datacurso:viewreports', $context)) {
            return;
        }

        $url = new moodle_url('/ai/provider/datacurso/admin/report_sections.php');
        $hook->get_primaryview()->add(
            get_string('nav_datacurso', 'aiprovider_datacurso'),
            $url,
            navigation_node::TYPE_CUSTOM,
            null,
            'aiprovider_datacurso'
        );
    }
}
