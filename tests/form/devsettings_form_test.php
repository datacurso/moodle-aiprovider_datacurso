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

use aiprovider_datacurso\httpclient\ai_services_api;

/**
 * Tests for the unlinked development settings page and its form.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\form\devsettings_form
 * @covers     \aiprovider_datacurso\httpclient\ai_services_api::configured_base_url
 */
final class devsettings_form_test extends \advanced_testcase {
    /**
     * Values a URL field accepts.
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
     * Values a URL field refuses.
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
     * Empty, http and https URLs are accepted by the helper and by both form fields.
     *
     * @dataProvider valid_provider
     * @param string $value Submitted value.
     */
    public function test_valid_values_are_accepted(string $value): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertTrue(devsettings_form::is_valid_service_url($value));
        $errors = (new devsettings_form())->validation([
            ai_services_api::DEV_URL_SETTING => $value,
            ai_services_api::DEV_URL_SETTING_EU => $value,
        ], []);
        $this->assertSame([], $errors);
    }

    /**
     * Anything that is not an absolute http or https URL is refused on the field that holds it.
     *
     * @dataProvider invalid_provider
     * @param string $value Submitted value.
     */
    public function test_invalid_values_are_refused(string $value): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse(devsettings_form::is_valid_service_url($value));
        $errors = (new devsettings_form())->validation([
            ai_services_api::DEV_URL_SETTING => '',
            ai_services_api::DEV_URL_SETTING_EU => $value,
        ], []);
        $this->assertSame(
            [ai_services_api::DEV_URL_SETTING_EU => get_string('devservicesurl_invalid', 'aiprovider_datacurso')],
            $errors
        );
    }

    /**
     * Only site administrators get through; a manager with moodle/site:config does not.
     */
    public function test_only_site_admins_have_access(): void {
        global $DB;
        $this->resetAfterTest();

        $manager = $this->getDataGenerator()->create_user();
        $managerrole = $DB->get_field('role', 'id', ['shortname' => 'manager']);
        assign_capability('moodle/site:config', CAP_ALLOW, $managerrole, \context_system::instance()->id, true);
        role_assign($managerrole, $manager->id, \context_system::instance()->id);
        $this->setUser($manager);
        $this->assertTrue(has_capability('moodle/site:config', \context_system::instance()));

        try {
            devsettings_form::require_site_admin();
            $this->fail('A manager with moodle/site:config must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('accessdenied', $e->errorcode);
        }

        $this->setAdminUser();
        devsettings_form::require_site_admin();
    }

    /**
     * A visitor who is not logged in is refused (require_login redirects, which PHPUnit turns into an exception).
     */
    public function test_anonymous_user_is_refused(): void {
        $this->resetAfterTest();
        $this->setUser(0);

        $this->expectException(\moodle_exception::class);
        devsettings_form::require_site_admin();
    }

    /**
     * Saving writes the config and the config log; an empty value unsets the setting.
     */
    public function test_save_writes_config_and_config_log(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $logcount = fn(string $name): int => $DB->count_records(
            'config_log',
            ['plugin' => 'aiprovider_datacurso', 'name' => $name]
        );
        $before = $logcount('devservicesurl');
        $beforeeu = $logcount('devservicesurleu');

        devsettings_form::save((object) [
            ai_services_api::DEV_URL_SETTING => ' https://dev.example.com ',
            ai_services_api::DEV_URL_SETTING_EU => '',
        ]);

        $this->assertSame('https://dev.example.com', get_config('aiprovider_datacurso', 'devservicesurl'));
        $this->assertEmpty(get_config('aiprovider_datacurso', 'devservicesurleu'));
        $this->assertSame($before + 1, $logcount('devservicesurl'));
        $last = $DB->get_records(
            'config_log',
            ['plugin' => 'aiprovider_datacurso', 'name' => 'devservicesurl'],
            'id DESC',
            '*',
            0,
            1
        );
        $this->assertSame('https://dev.example.com', reset($last)->value);
        // Nothing changed for the European URL, so nothing is logged.
        $this->assertSame($beforeeu, $logcount('devservicesurleu'));

        devsettings_form::save((object) [
            ai_services_api::DEV_URL_SETTING => '',
            ai_services_api::DEV_URL_SETTING_EU => '',
        ]);

        $this->assertFalse(get_config('aiprovider_datacurso', 'devservicesurl'));
        $this->assertSame($before + 2, $logcount('devservicesurl'));
    }

    /**
     * The form prefills the saved values.
     */
    public function test_current_data_returns_saved_values(): void {
        $this->resetAfterTest();
        set_config('devservicesurleu', 'https://dev-eu.example.com', 'aiprovider_datacurso');

        $this->assertEquals([
            ai_services_api::DEV_URL_SETTING => '',
            ai_services_api::DEV_URL_SETTING_EU => 'https://dev-eu.example.com',
        ], devsettings_form::current_data());
    }

    /**
     * The URL in use is the development URL of the region when set, the production URL otherwise.
     */
    public function test_configured_base_url_follows_the_development_settings(): void {
        $this->resetAfterTest();

        $this->assertSame('https://plugins-ai.datacurso.com', ai_services_api::configured_base_url(false));
        $this->assertSame('https://eu.plugins-ai.datacurso.com', ai_services_api::configured_base_url(true));

        set_config('devservicesurl', 'https://dev.example.com', 'aiprovider_datacurso');
        $this->assertSame('https://dev.example.com', ai_services_api::configured_base_url(false));
        $this->assertSame('https://eu.plugins-ai.datacurso.com', ai_services_api::configured_base_url(true));
    }

    /**
     * The development settings are not in the admin tree: the provider page keeps none of them.
     *
     * No admin setting anywhere in the tree matches the development URLs, so the admin search does not
     * reveal the unlinked page either.
     */
    public function test_settings_page_does_not_define_the_development_settings(): void {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->setAdminUser();

        $root = admin_get_root(true, true);

        $this->assertNull($root->locate('aiprovider_datacurso_devsettings'));
        $this->assertSame([], $root->search('devservicesurl'));

        $page = $root->locate('aiprovider_datacurso');
        $this->assertInstanceOf(\admin_settingpage::class, $page);
        $this->assertTrue(property_exists($page->settings, 'aiprovider_datacursolicensekey'));
        $this->assertFalse(property_exists($page->settings, 'aiprovider_datacursodevservicesurl'));
        $this->assertFalse(property_exists($page->settings, 'aiprovider_datacursodevservicesurleu'));
        $this->assertFalse(property_exists($page->settings, 'aiprovider_datacursodevsettings'));
    }
}
