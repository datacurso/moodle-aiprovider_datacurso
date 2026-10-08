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

namespace aiprovider_datacurso\form;

/**
 * Tests for the tenant-aware configuration form.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\settings_tenant_form
 */
final class settings_tenant_form_test extends \advanced_testcase {
    /**
     * A negative per-action credit cost is rejected.
     *
     * MDL-UNIT-004: validation of the per-action credit costs.
     */
    public function test_validation_rejects_negative_credit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new settings_tenant_form();

        $errors = $form->validation(
            ['credit' => ['local_coursegen' => ['course_image' => -1]]],
            []
        );

        $this->assertArrayHasKey('credit[local_coursegen][course_image]', $errors);
        $this->assertNotEmpty($errors['credit[local_coursegen][course_image]']);
    }

    /**
     * A valid (non-negative) per-action credit cost passes validation without error.
     *
     * MDL-UNIT-004: valid per-action credit costs produce no validation errors.
     */
    public function test_validation_accepts_non_negative_credit(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new settings_tenant_form();

        $errors = $form->validation(
            ['credit' => ['local_coursegen' => ['course_image' => 2000]]],
            []
        );

        $this->assertSame([], $errors);
    }
}
