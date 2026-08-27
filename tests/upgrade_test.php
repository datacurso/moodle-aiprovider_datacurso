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

/**
 * Static/textual guards over db/upgrade.php and version.php.
 *
 * Reads both files as text (no execution, no DB), so these tests are pure and fast.
 * Comments are stripped from db/upgrade.php before scanning for banned symbols, because
 * the neutered savepoint 2025112706 intentionally keeps an explanatory comment that
 * mentions the removed class name for historical context (design obs 216/217) — that
 * comment is not a code reference.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class upgrade_test extends \basic_testcase {
    /** @var string Absolute path to db/upgrade.php. */
    private const UPGRADE_FILE = __DIR__ . '/../db/upgrade.php';

    /** @var string Absolute path to version.php. */
    private const VERSION_FILE = __DIR__ . '/../version.php';

    /**
     * Read db/upgrade.php and strip every PHP comment (// and /* *\/ / doc blocks),
     * so only executable code remains for symbol scanning.
     *
     * @return string
     */
    private function upgrade_code_without_comments(): string {
        $source = file_get_contents(self::UPGRADE_FILE);
        $tokens = token_get_all($source);

        $code = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }

        return $code;
    }

    /**
     * Extract every upgrade_plugin_savepoint(true, NNNNNNNNNN, ...) number, in file order.
     *
     * @return int[]
     */
    private function extract_savepoints(): array {
        $source = file_get_contents(self::UPGRADE_FILE);
        preg_match_all('/upgrade_plugin_savepoint\(\s*true\s*,\s*(\d{10})\s*,/', $source, $matches);

        return array_map('intval', $matches[1]);
    }

    /**
     * Extract the body of a single `if ($oldversion < NNNNNNNNNN) { ... }` block
     * (comments already stripped), using balanced-brace matching.
     *
     * @param int $savepoint
     * @return string
     */
    private function extract_block_body(int $savepoint): string {
        $code = $this->upgrade_code_without_comments();
        $needle = "if (\$oldversion < {$savepoint})";
        $start = strpos($code, $needle);
        $this->assertNotFalse($start, "Guard for savepoint {$savepoint} not found");

        $braceopen = strpos($code, '{', $start);
        $depth = 0;
        $end = $braceopen;
        for ($i = $braceopen; $i < strlen($code); $i++) {
            if ($code[$i] === '{') {
                $depth++;
            } else if ($code[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        return substr($code, $braceopen, $end - $braceopen + 1);
    }

    /**
     * The upgrade file must not contain live code references to classes removed
     * during the port (comments explaining history are allowed).
     */
    public function test_upgrade_file_has_no_reference_to_removed_classes(): void {
        $code = $this->upgrade_code_without_comments();

        $this->assertStringNotContainsString('webservice_config', $code);
        $this->assertStringNotContainsString('user_token_limit_manager', $code);
        $this->assertStringNotContainsString('local\\ratelimit\\', $code);
    }

    /**
     * Every savepoint number appears exactly once and the sequence is strictly ascending.
     */
    public function test_savepoints_are_unique_and_ascending(): void {
        $savepoints = $this->extract_savepoints();

        $this->assertNotEmpty($savepoints);
        $this->assertSame(array_unique($savepoints), array_values($savepoints));

        $sorted = $savepoints;
        sort($sorted);
        $this->assertSame($sorted, $savepoints, 'Savepoints must appear in strictly ascending order');
    }

    /**
     * The highest savepoint in db/upgrade.php must equal $plugin->version from version.php.
     * This is the gap left open by U1 deviation 3 — closing it is U5's whole purpose.
     */
    public function test_highest_savepoint_matches_plugin_version(): void {
        $savepoints = $this->extract_savepoints();
        $highest = max($savepoints);

        $plugin = new \stdClass();
        include(self::VERSION_FILE);

        $this->assertSame($plugin->version, $highest);
    }

    /**
     * Savepoints 2025110600 and 2025112706 (both neutered during the U1 merge) are
     * still present, and neither body calls into webservice_config.
     */
    public function test_savepoints_2025110600_and_2025112706_are_retained_and_neutered(): void {
        $savepoints = $this->extract_savepoints();

        $this->assertContains(2025110600, $savepoints);
        $this->assertContains(2025112706, $savepoints);

        $body1 = $this->extract_block_body(2025110600);
        $body2 = $this->extract_block_body(2025112706);

        $this->assertStringNotContainsString('webservice_config', $body1);
        $this->assertStringNotContainsString('webservice_config', $body2);
    }
}
