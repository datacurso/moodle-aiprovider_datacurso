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

/**
 * Testable subclass of the consumption synchronisation.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\local\sync;

use aiprovider_datacurso\httpclient\datacurso_api;

/**
 * Substitutes the API client so the synchronisation runs without network access.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_consumption_sync extends consumption_sync {
    /** @var datacurso_api|null Client handed to the synchronisation. */
    public static ?datacurso_api $client = null;

    /**
     * Return the injected client instead of building a real one.
     *
     * @return datacurso_api
     */
    protected static function get_api_client(): datacurso_api {
        return static::$client;
    }
}
