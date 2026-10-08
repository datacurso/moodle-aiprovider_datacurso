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
 * End-to-end system actions that call the live Datacurso AI service.
 *
 * These scenarios are marked "Automatizado: no" in the test definition: they require a live
 * Datacurso AI service plus a valid license with credits, which is not available in CI. Each is
 * represented as a documented skip so its intent stays visible in the suite.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class system_actions_test extends \advanced_testcase {
    /** @var string Shared reason for the live-service skips. */
    private const REASON = 'Requires a live Datacurso AI service and a valid license with credits; '
        . 'not runnable in CI (marked "Automatizado: no" in the test definition).';

    /**
     * SYS-E2E-001: generate text end to end.
     */
    public function test_generate_text_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-E2E-002: generate image end to end.
     */
    public function test_generate_image_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-E2E-003: summarise text end to end.
     */
    public function test_summarise_text_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-E2E-004: rate limit exceeded end to end.
     */
    public function test_rate_limit_exceeded_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-E2E-005: invalid or empty AI service response end to end.
     */
    public function test_invalid_response_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-E2E-006: automatic region selection from the license end to end.
     */
    public function test_region_autoselection_end_to_end(): void {
        $this->markTestSkipped(self::REASON);
    }

    /**
     * SYS-EVAL-001: model adherence to the configured system instruction.
     */
    public function test_system_instruction_adherence_eval(): void {
        $this->markTestSkipped(
            'Golden-dataset evaluation requiring the live Datacurso AI service and a valid license '
            . '(marked "Automatizado: no"). It also depends on the system-instruction defect: the '
            . 'automated red signal for that defect lives in the failing MDL-UNIT-012 '
            . '(process_generate_text_test / process_summarise_text_test) and API-CTR-002 tests.'
        );
    }
}
