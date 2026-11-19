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

/**
 * Tests for the development service URL setting.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\admin\setting_service_url
 */
final class setting_service_url_test extends \advanced_testcase {
    /**
     * Build the setting as settings.php declares it.
     *
     * @return setting_service_url
     */
    private function make_setting(): setting_service_url {
        return new setting_service_url('aiprovider_datacurso/devservicesurl', 'Services URL', '');
    }

    /**
     * Values the setting accepts.
     *
     * @return array<string, array{0: string}>
     */
    public static function valid_provider(): array {
        return [
            'empty' => [''],
            'https' => ['https://dev.example.com'],
            'https with path' => ['https://dev.example.com/api/'],
            'http with port' => ['http://plugins-ai-server.datacurso:8002'],
        ];
    }

    /**
     * Values the setting refuses.
     *
     * @return array<string, array{0: string}>
     */
    public static function invalid_provider(): array {
        return [
            'no scheme' => ['dev.example.com'],
            'ftp' => ['ftp://dev.example.com'],
            'javascript' => ['javascript:alert(1)'],
            'no host' => ['https://'],
            'plain text' => ['not a url'],
        ];
    }

    /**
     * Empty, http and https URLs are accepted.
     *
     * @dataProvider valid_provider
     * @param string $value Submitted value.
     */
    public function test_valid_values_are_accepted(string $value): void {
        $this->assertTrue($this->make_setting()->validate($value));
    }

    /**
     * Anything that is not an absolute http or https URL is refused with the setting's error.
     *
     * @dataProvider invalid_provider
     * @param string $value Submitted value.
     */
    public function test_invalid_values_are_refused(string $value): void {
        $result = $this->make_setting()->validate($value);

        $this->assertIsString($result);
    }

    /**
     * A refused value is not saved.
     */
    public function test_invalid_value_is_not_saved(): void {
        $this->resetAfterTest();
        $setting = $this->make_setting();

        $this->assertNotSame('', $setting->write_setting('ftp://dev.example.com'));
        $this->assertFalse(get_config('aiprovider_datacurso', 'devservicesurl'));

        $this->assertSame('', $setting->write_setting('https://dev.example.com'));
        $this->assertSame('https://dev.example.com', get_config('aiprovider_datacurso', 'devservicesurl'));
    }
}
