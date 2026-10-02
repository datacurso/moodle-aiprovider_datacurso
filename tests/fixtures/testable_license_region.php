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
 * Test double for the resolution of the region of a licence.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace aiprovider_datacurso\local;

/**
 * Answers for the shop instead of reaching it, and counts how often it was asked.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_license_region extends license_region {
    /** @var int Times the shop was asked. */
    public static int $asked = 0;

    /** @var bool What the shop answers. */
    public static bool $european = false;

    /** @var \moodle_exception|null Thrown instead of answering, to play a shop that is down. */
    public static ?\moodle_exception $failure = null;

    /**
     * Forget everything this double remembers between tests.
     */
    public static function reset(): void {
        self::$asked = 0;
        self::$european = false;
        self::$failure = null;
    }

    /**
     * Answer for the shop.
     *
     * @return bool
     * @throws \moodle_exception When a failure was queued.
     */
    protected static function ask_the_shop(): bool {
        self::$asked++;
        if (self::$failure !== null) {
            throw self::$failure;
        }

        return self::$european;
    }
}
