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

    /**
     * Fetch the config_log rows written by this plugin for a setting name, newest first.
     *
     * @param string $name
     * @return \stdClass[]
     */
    private function config_log_rows(string $name): array {
        global $DB;
        return array_values($DB->get_records('config_log', ['plugin' => 'aiprovider_datacurso', 'name' => $name], 'id DESC'));
    }

    /**
     * Every rate-limit setting saved from the Configuration tab is written to the config log,
     * so changes are attributable like any other admin setting.
     *
     * AIP-SEC-005: configuration change audit.
     */
    public function test_rate_limit_changes_are_written_to_config_log(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        config_form::save((object) [
            'enable' => ['local_coursegen' => 1],
            'limit' => ['local_coursegen' => 250],
            'windowvalue' => ['local_coursegen' => 3],
            'windowunit' => ['local_coursegen' => 'days'],
            'credit' => ['local_coursegen' => ['course_image' => 1500]],
        ]);

        $this->assertSame('1', $this->config_log_rows('ratelimit_local_coursegen_enable')[0]->value ?? null);
        $this->assertSame('250', $this->config_log_rows('ratelimit_local_coursegen_limit')[0]->value ?? null);
        $this->assertSame(
            json_encode(['value' => 3, 'unit' => 'days']),
            $this->config_log_rows('ratelimit_local_coursegen_window')[0]->value ?? null
        );
        $this->assertSame(
            json_encode(['course_image' => 1500]),
            $this->config_log_rows('ratelimit_local_coursegen_creditperaction')[0]->value ?? null
        );
    }

    /**
     * A license key change is logged, but the secret itself never reaches the log; an unchanged
     * key adds no row.
     *
     * AIP-SEC-005: configuration change audit without leaking the secret.
     */
    public function test_license_key_change_is_logged_without_the_secret(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // The install already logs the default (empty) value of the setting.
        $before = count($this->config_log_rows('licensekey'));

        config_form::save((object) ['licensekey' => 'SECRET-KEY-123']);

        $rows = $this->config_log_rows('licensekey');
        $this->assertCount($before + 1, $rows);
        $this->assertSame('********', $rows[0]->value);
        $this->assertSame('********', $rows[0]->oldvalue);
        foreach ($DB->get_records('config_log') as $row) {
            $this->assertStringNotContainsString('SECRET-KEY-123', (string) $row->value);
            $this->assertStringNotContainsString('SECRET-KEY-123', (string) $row->oldvalue);
        }

        // Saving the same key again is not a change.
        config_form::save((object) ['licensekey' => 'SECRET-KEY-123']);
        $this->assertCount($before + 1, $this->config_log_rows('licensekey'));

        // A different key is.
        config_form::save((object) ['licensekey' => 'OTHER-KEY']);
        $this->assertCount($before + 2, $this->config_log_rows('licensekey'));
    }

    /**
     * Saving the same rate-limit values again adds no config_log row: only changes are audited.
     *
     * REL-006: idempotent save leaves the audit trail untouched.
     */
    public function test_unchanged_rate_limits_add_no_config_log_rows(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $data = (object) [
            'enable' => ['local_coursegen' => 1],
            'limit' => ['local_coursegen' => 250],
            'windowvalue' => ['local_coursegen' => 3],
            'windowunit' => ['local_coursegen' => 'days'],
            'credit' => ['local_coursegen' => ['course_image' => 1500]],
        ];

        config_form::save($data);
        $count = $DB->count_records('config_log', ['plugin' => 'aiprovider_datacurso']);
        $newestid = (int) $DB->get_field_sql('SELECT MAX(id) FROM {config_log}');

        config_form::save($data);

        $this->assertSame($count, $DB->count_records('config_log', ['plugin' => 'aiprovider_datacurso']));
        $this->assertSame($newestid, (int) $DB->get_field_sql('SELECT MAX(id) FROM {config_log}'));

        // A real change is still audited, and it is the newest row.
        $data->limit['local_coursegen'] = 300;
        config_form::save($data);
        $this->assertSame('300', $this->config_log_rows('ratelimit_local_coursegen_limit')[0]->value);
        $this->assertSame($count + 1, $DB->count_records('config_log', ['plugin' => 'aiprovider_datacurso']));
    }
}
