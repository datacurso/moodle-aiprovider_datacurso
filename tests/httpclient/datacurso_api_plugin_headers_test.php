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

namespace aiprovider_datacurso\httpclient;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../fixtures/fake_curl.php');
require_once(__DIR__ . '/../fixtures/fake_transport_client.php');

/**
 * Tests that a request carries the plugin headers and that a refusal of the service is reported.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 */
final class datacurso_api_plugin_headers_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        // These tests exercise the transport, which only runs with the provider enabled.
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
    }

    /**
     * Send a GET that the service refuses with HTTP 426 and return the exception it raises.
     *
     * @param string $body Body of the answer.
     * @return \moodle_exception
     */
    private function get_refused_by_the_service(string $body): \moodle_exception {
        $curl = new fake_curl();
        $curl->enqueue($body, 426);
        $client = new fake_transport_client($curl, 'local_coursegen');

        try {
            $client->request('GET', '/status');
        } catch (\moodle_exception $exception) {
            return $exception;
        }

        $this->fail('Expected a moodle_exception.');
    }

    /**
     * The header lines that identify a plugin.
     *
     * @param string[] $headers All the header lines of a request.
     * @return string[] Those that start with X-Plugin-.
     */
    private function plugin_headers_in(array $headers): array {
        $found = [];
        foreach ($headers as $header) {
            if (str_starts_with($header, 'X-Plugin-')) {
                $found[] = $header;
            }
        }

        return $found;
    }

    /**
     * A client nobody named sends no plugin header with its request.
     */
    public function test_a_client_without_a_component_sends_no_plugin_headers(): void {
        $this->resetAfterTest();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = new fake_transport_client($curl);

        $client->request('GET', '/status');

        $headers = $curl->calls[0]['options']['CURLOPT_HTTPHEADER'];
        $pluginheaders = $this->plugin_headers_in($headers);
        $this->assertSame([], $pluginheaders);
        $this->assertSame([], $client->get_plugin_headers());
    }

    /**
     * A plugin that names itself sends its name and release with the request, next to the license.
     */
    public function test_a_named_client_sends_the_plugin_headers_with_the_request(): void {
        $this->resetAfterTest();
        $manager = \core_plugin_manager::instance();
        $plugin = $manager->get_plugin_info('aiprovider_datacurso');
        $release = $plugin->release;
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = new fake_transport_client($curl, 'aiprovider_datacurso');

        $client->request('GET', '/status');

        $headers = $curl->calls[0]['options']['CURLOPT_HTTPHEADER'];
        $this->assertContains('License-Key: test-key', $headers);
        $this->assertContains('X-Plugin-Name: aiprovider_datacurso', $headers);
        $this->assertContains('X-Plugin-Version: ' . $release, $headers);
        $this->assertContains('X-Plugin-Build: ' . $plugin->versiondisk, $headers);
    }

    /**
     * The person is told which release the service no longer accepts, and the minimum.
     */
    public function test_an_outdated_plugin_is_told_the_minimum_version(): void {
        $this->resetAfterTest();
        $body = '{"detail": {"error_code": "PLUGIN_UPDATE_REQUIRED", "plugin": "local_coursegen",'
            . ' "version": "1.0.1", "minimum": "1.2.0"}}';

        $exception = $this->get_refused_by_the_service($body);
        $message = $exception->getMessage();

        $this->assertSame('plugin_update_required', $exception->errorcode);
        $this->assertStringContainsString('1.2.0', $message);
        $this->assertDebuggingCalled();
    }

    /**
     * A refusal that does not say which release is needed asks for the latest version.
     */
    public function test_any_other_426_asks_for_the_latest_version(): void {
        $this->resetAfterTest();
        $exception = $this->get_refused_by_the_service('{"detail": {"error_code": "PLUGIN_VERSION_REQUIRED"}}');

        $this->assertSame('plugin_version_required', $exception->errorcode);
        $this->assertDebuggingCalled();
    }

    /**
     * A 426 is reported as the update message, not as the generic HTTP error.
     */
    public function test_a_426_is_not_reported_as_a_generic_http_error(): void {
        $this->resetAfterTest();
        $exception = $this->get_refused_by_the_service('<html>Upgrade Required</html>');

        $this->assertNotSame('httperror', $exception->errorcode);
        $this->assertDebuggingCalled();
    }
}
