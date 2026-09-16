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
 * Resolves the tenant a user belongs to.
 *
 * The plugin targets Moodle Workplace, where tenancy is provided by tool_tenant.
 * On a site without that plugin (a plain Moodle install, or the CI environment
 * used to run the test suite) there is a single implicit tenant, represented here
 * by tenant id 0, so tenant configuration falls back to the site-wide values.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning <info@industriaelearning.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tenant_resolver {
    /** @var int Tenant id used when the site has no tenancy support. */
    public const NO_TENANT = 0;

    /**
     * Returns the tenant id for the given user, or for the current user when none is given.
     *
     * @param int|null $userid User to resolve the tenant for. Defaults to the current user.
     * @return int The tenant id, or self::NO_TENANT when tenancy is not available.
     */
    public static function get_tenant_id(?int $userid = null): int {
        global $USER;

        if (!self::is_tenancy_available()) {
            return self::NO_TENANT;
        }

        return (int) \tool_tenant\tenancy::get_tenant_id($userid ?? (int) $USER->id);
    }

    /**
     * Whether this site provides tenancy through tool_tenant.
     *
     * @return bool
     */
    public static function is_tenancy_available(): bool {
        return class_exists('\tool_tenant\tenancy');
    }
}
