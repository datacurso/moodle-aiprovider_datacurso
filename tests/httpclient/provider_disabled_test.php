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
require_once(__DIR__ . '/../fixtures/resolved_region.php');

use aiprovider_datacurso\local\provider_status;
use aiprovider_datacurso\local\tenant_resolver;
use aiprovider_datacurso\resolved_region;

/**
 * Tests that the AI clients respect the "Enabled" switch of the provider.
 *
 * With the provider disabled in Site administration > AI > AI providers, no AI request leaves the
 * site, while the shop client (balance, consumption history) keeps working. On Moodle Workplace a
 * tenant may also switch the provider off for itself, which stops the requests of its users only.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\datacurso_api_base
 * @covers     \aiprovider_datacurso\httpclient\ai_services_api
 * @covers     \aiprovider_datacurso\httpclient\ai_course_api
 * @covers     \aiprovider_datacurso\local\provider_status
 */
final class provider_disabled_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('licensekey', 'test-key', 'aiprovider_datacurso');
    }

    /**
     * Resolve the region of the licence beforehand, on the site and on every tenant, so building a client
     * does not ask the shop. Called once the tenants of the test exist.
     */
    private function keep_region(): void {
        resolved_region::keep('test-key');
    }

    /**
     * Build an AI services client whose transport is the given fake, counting the transports created.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_services_api
     */
    private function make_services_client(fake_curl $curl): ai_services_api {
        return new class ($curl) extends ai_services_api {
            /** @var int Number of transports created. */
            public int $curlscreated = 0;

            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct(null, 'https://ai.example.invalid', 'https://eu.ai.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                $this->curlscreated++;
                return $this->fakecurl;
            }
        };
    }

    /**
     * Build a course AI client whose transport is the given fake, counting the transports created.
     *
     * @param fake_curl $curl Transport double.
     * @return ai_course_api
     */
    private function make_course_client(fake_curl $curl): ai_course_api {
        return new class ($curl) extends ai_course_api {
            /** @var int Number of transports created. */
            public int $curlscreated = 0;

            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct(null, 'https://course.example.invalid', 'https://eu.course.example.invalid');
            }

            #[\Override]
            protected function create_curl(): \curl {
                $this->curlscreated++;
                return $this->fakecurl;
            }
        };
    }

    /**
     * Assert that the callback is refused with the provider disabled message.
     *
     * @param callable $callback Code that makes an AI request.
     * @param string $errorcode Expected error code: providerdisabled (site) or providerdisabled_tenant.
     */
    private function assert_refused_as_disabled(callable $callback, string $errorcode = 'providerdisabled'): void {
        try {
            $callback();
            $this->fail('Expected the ' . $errorcode . ' exception.');
        } catch (\moodle_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
            $this->assertSame('aiprovider_datacurso', $e->module);
            $this->assertSame(get_string($errorcode, 'aiprovider_datacurso'), $e->getMessage());
        }
    }

    /**
     * Create two tenants with one user each, or skip the test on a site without tenancy.
     *
     * @return array{0: int, 1: \stdClass, 2: int, 3: \stdClass} Tenant A, its user, tenant B, its user.
     */
    private function create_two_tenants(): array {
        $tenants = $this->getDataGenerator()->get_plugin_generator('tool_tenant');
        $tenanta = (int) $tenants->create_tenant()->id;
        $tenantb = (int) $tenants->create_tenant()->id;
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();
        $tenants->allocate_user((int) $usera->id, $tenanta);
        $tenants->allocate_user((int) $userb->id, $tenantb);
        return [$tenanta, $usera, $tenantb, $userb];
    }

    /**
     * I1: with the provider disabled, the AI services client refuses the request without opening a connection.
     */
    public function test_disabled_provider_refuses_ai_services_requests_without_a_connection(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $this->keep_region();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = $this->make_services_client($curl);

        $this->assert_refused_as_disabled(fn() => $client->request('POST', '/jedomi/v1/chat/completions', [
            'model' => 'any',
            'messages' => [],
        ]));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I1: with the provider disabled, the course AI client refuses requests and downloads without a connection.
     */
    public function test_disabled_provider_refuses_course_requests_and_downloads(): void {
        $this->setAdminUser();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $this->keep_region();
        $curl = new fake_curl();
        $curl->enqueue('{"ok": true}');
        $client = $this->make_course_client($curl);

        $this->assert_refused_as_disabled(fn() => $client->request('POST', '/course/execute', ['prompt' => 'hello']));
        $this->assert_refused_as_disabled(fn() => $client->download_file('/resources/package.zip', 'package.zip'));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I1: with the provider disabled, an upload is refused before reaching the service.
     */
    public function test_disabled_provider_refuses_uploads(): void {
        $this->setAdminUser();
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $this->keep_region();
        $curl = new fake_curl();
        $client = $this->make_course_client($curl);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_system::instance()->id,
            'component' => 'aiprovider_datacurso',
            'filearea' => 'test',
            'itemid' => 0,
            'filepath' => '/',
            'filename' => 'notes.txt',
        ], 'content');

        $this->assert_refused_as_disabled(fn() => $client->upload_file('/upload-file', $file));

        $this->assertSame(0, $client->curlscreated);
        $this->assertSame([], $curl->calls);
    }

    /**
     * I2: with the provider enabled, the AI clients send their requests as before.
     */
    public function test_enabled_provider_sends_requests(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        $this->keep_region();
        $curl = new fake_curl();
        $curl->enqueue('{"answer": "services"}');
        $curl->enqueue('{"answer": "course"}');

        $services = $this->make_services_client($curl);
        $course = $this->make_course_client($curl);

        $this->assertSame(['answer' => 'services'], $services->request('POST', '/jedomi/v1/chat/completions', []));
        $this->assertSame(['answer' => 'course'], $course->request('POST', '/course/execute', []));
        $this->assertCount(2, $curl->calls);
        $this->assertSame('https://ai.example.invalid/jedomi/v1/chat/completions', $curl->calls[0]['url']);
        $this->assertSame('https://course.example.invalid/course/execute', $curl->calls[1]['url']);
    }

    /**
     * I3: the shop client keeps working with the provider disabled, so balance and history still load.
     */
    public function test_shop_client_works_with_the_provider_disabled(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $this->keep_region();
        $curl = new fake_curl();
        $curl->enqueue('{"saldo": 10}');
        $shop = new class ($curl) extends datacurso_api {
            /**
             * Constructor.
             *
             * @param fake_curl $fakecurl Transport double.
             */
            public function __construct(
                /** @var fake_curl Transport double. */
                private fake_curl $fakecurl
            ) {
                parent::__construct();
            }

            #[\Override]
            protected function create_curl(): \curl {
                return $this->fakecurl;
            }
        };

        $this->assertSame(['saldo' => 10], $shop->get('tokens/saldo'));
        $this->assertCount(1, $curl->calls);
    }

    /**
     * I4: a tenant that switches the provider off stops the requests of its users, and only theirs.
     */
    public function test_disabled_tenant_refuses_its_requests_and_other_tenants_keep_sending(): void {
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        [$tenanta, $usera, $tenantb, $userb] = $this->create_two_tenants();
        $this->keep_region();
        provider_status::set_tenant_enabled($tenanta, false);
        $curl = new fake_curl();
        $curl->enqueue('{"answer": "b"}');

        $this->setUser($usera);
        $this->assertFalse(provider_status::is_enabled());
        $client = $this->make_services_client($curl);
        $this->assert_refused_as_disabled(
            fn() => $client->request('POST', '/forum/reply', []),
            'providerdisabled_tenant'
        );
        $this->assertSame(0, $client->curlscreated);

        $this->setUser($userb);
        $this->assertTrue(provider_status::is_enabled());
        $this->assertTrue(provider_status::is_enabled($tenantb));
        $this->assertSame(['answer' => 'b'], $this->make_services_client($curl)->request('POST', '/forum/reply', []));
        $this->assertCount(1, $curl->calls);
    }

    /**
     * I4: the site switch wins: a tenant cannot keep the provider on when the site switches it off.
     */
    public function test_site_switch_wins_over_an_enabled_tenant(): void {
        if (!tenant_resolver::is_tenancy_available()) {
            $this->markTestSkipped('Tenants exist only on Moodle Workplace.');
        }
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        [$tenanta, $usera] = $this->create_two_tenants();
        $this->keep_region();
        provider_status::set_tenant_enabled($tenanta, true);
        $curl = new fake_curl();

        $this->setUser($usera);
        $this->assertFalse(provider_status::is_enabled($tenanta));
        $this->assert_refused_as_disabled(fn() => $this->make_course_client($curl)->request('POST', '/course/execute', []));
        $this->assertSame([], $curl->calls);
    }

    /**
     * I5: without a saved tenant value the provider follows the site switch, so nothing changes on upgrade.
     */
    public function test_a_tenant_without_a_saved_value_follows_the_site_switch(): void {
        \core\plugininfo\aiprovider::enable_plugin('datacurso', 1);
        $this->assertTrue(provider_status::is_enabled(tenant_resolver::NO_TENANT));
        $this->assertTrue(provider_status::is_enabled(123456));

        \core\plugininfo\aiprovider::enable_plugin('datacurso', 0);
        $this->assertFalse(provider_status::is_enabled(tenant_resolver::NO_TENANT));
        $this->assertFalse(provider_status::is_enabled(123456));
    }

    /**
     * I5: switching a tenant on and off is written to the config log, naming the tenant.
     */
    public function test_tenant_switch_changes_are_written_to_the_config_log(): void {
        global $DB;
        $this->setAdminUser();

        provider_status::set_tenant_enabled(77, false);
        provider_status::set_tenant_enabled(77, false);
        provider_status::set_tenant_enabled(77, true);

        $rows = $DB->get_records('config_log', ['plugin' => 'aiprovider_datacurso'], 'id ASC');
        $rows = array_values(array_filter($rows, fn($row) => str_starts_with($row->name, provider_status::CONFIG_NAME)));
        $this->assertCount(2, $rows);
        $this->assertSame(provider_status::CONFIG_NAME . ' (tenant 77)', $rows[0]->name);
        $this->assertSame('0', $rows[0]->value);
        $this->assertSame('1', $rows[1]->value);
    }
}
