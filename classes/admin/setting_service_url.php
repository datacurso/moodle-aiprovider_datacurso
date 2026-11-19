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

namespace aiprovider_datacurso\admin;

use admin_setting_configtext;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Optional development URL of a DataCurso service.
 *
 * Accepts nothing or an absolute http:// or https:// URL with a host. Whether plain HTTP may
 * actually be used is decided when the request is sent, by the transport policy.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_service_url extends admin_setting_configtext {
    /**
     * Constructor.
     *
     * @param string $name Setting name, as 'aiprovider_datacurso/<name>'.
     * @param string $visiblename Localised name.
     * @param string $description Localised description.
     */
    public function __construct($name, $visiblename, $description) {
        parent::__construct($name, $visiblename, $description, '', PARAM_URL);
    }

    /**
     * Validate the submitted URL.
     *
     * @param string $data The submitted value.
     * @return bool|string True when valid, the localised error otherwise.
     */
    public function validate($data) {
        $url = trim((string) $data);
        if ($url === '') {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        $valid = in_array($scheme, ['http', 'https'], true)
            && $host !== ''
            && clean_param($url, PARAM_URL) === $url;

        return $valid ? true : get_string('devservicesurl_invalid', 'aiprovider_datacurso');
    }
}
