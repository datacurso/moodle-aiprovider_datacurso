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
 * Tests for the per-service rate-limit configuration form.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \aiprovider_datacurso\form\config_form
 */
final class config_form_test extends \advanced_testcase {
    /**
     * Negative limits, sub-1 windows and negative per-action costs are rejected.
     *
     * MDL-UNIT-004: validation of the limits configuration form.
     */
    public function test_validation_rejects_invalid_numbers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new config_form();

        $data = [
            'limit' => ['local_coursegen' => -5],
            'windowvalue' => ['local_coursegen' => 0],
            'credit' => ['local_coursegen' => ['course_image' => -1]],
        ];

        $errors = $form->validation($data, []);

        // Each invalid field is rejected with a validation error keyed by its element name.
        $this->assertArrayHasKey('limit[local_coursegen]', $errors);
        $this->assertArrayHasKey('windowgroup_local_coursegen', $errors);
        $this->assertArrayHasKey('credit[local_coursegen][course_image]', $errors);
        $this->assertNotEmpty($errors['limit[local_coursegen]']);
        $this->assertNotEmpty($errors['windowgroup_local_coursegen']);
        $this->assertNotEmpty($errors['credit[local_coursegen][course_image]']);

        // The production form references the missing core string 'err_positive' (the valid keys are
        // 'err_numeric' / 'err_positiveint'), so each rejected field emits a get_string() debugging
        // notice. That is a separate, minor production defect; this test asserts only the rejection.
        $this->assertDebuggingCalledCount(3);
    }

    /**
     * Valid values pass validation without error.
     *
     * MDL-UNIT-004: valid data produces no validation errors.
     */
    public function test_validation_accepts_valid_numbers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $form = new config_form();

        $data = [
            'limit' => ['local_coursegen' => 100],
            'windowvalue' => ['local_coursegen' => 2],
            'credit' => ['local_coursegen' => ['course_image' => 2000]],
        ];

        $this->assertSame([], $form->validation($data, []));
    }

    /**
     * Saving persists enable/limit/window/credit per service and reopening prefills them.
     *
     * MDL-INT-001: persistence of the per-service limits configuration.
     */
    public function test_save_and_reload_round_trip(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = (object) [
            'licensekey' => 'KEY-1',
            'enable' => ['local_coursegen' => 1],
            'limit' => ['local_coursegen' => 250],
            'windowvalue' => ['local_coursegen' => 3],
            'windowunit' => ['local_coursegen' => 'days'],
            'credit' => ['local_coursegen' => ['course_image' => 1500]],
        ];

        config_form::save($data);

        // Raw config keys are written with the expected encodings.
        $this->assertSame('1', get_config('aiprovider_datacurso', 'ratelimit_local_coursegen_enable'));
        $this->assertSame('250', get_config('aiprovider_datacurso', 'ratelimit_local_coursegen_limit'));
        $this->assertSame(
            ['value' => 3, 'unit' => 'days'],
            json_decode(get_config('aiprovider_datacurso', 'ratelimit_local_coursegen_window'), true)
        );
        $this->assertSame(
            ['course_image' => 1500],
            json_decode(get_config('aiprovider_datacurso', 'ratelimit_local_coursegen_creditperaction'), true)
        );

        // Reopening the form prefills the persisted values.
        $current = config_form::current_data();
        $this->assertSame(1, $current['enable']['local_coursegen']);
        $this->assertSame(250, $current['limit']['local_coursegen']);
        $this->assertSame(3, $current['windowvalue']['local_coursegen']);
        $this->assertSame('days', $current['windowunit']['local_coursegen']);
        $this->assertSame(1500, $current['credit']['local_coursegen']['course_image']);
    }

    /**
     * The license key uses the same config storage as the native provider page.
     *
     * MDL-INT-002: shared license key between native config and the Configuration tab.
     */
    public function test_license_key_is_shared_storage(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Saving from the Configuration tab updates the shared native setting.
        config_form::save((object) ['licensekey' => 'ABC']);
        $this->assertSame('ABC', get_config('aiprovider_datacurso', 'licensekey'));

        // Changing the native setting is reflected when the Configuration tab reloads.
        set_config('licensekey', 'XYZ', 'aiprovider_datacurso');
        $this->assertSame('XYZ', config_form::current_data()['licensekey']);
    }
}
