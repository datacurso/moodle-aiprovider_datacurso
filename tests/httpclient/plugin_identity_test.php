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

namespace aiprovider_datacurso\httpclient;

/**
 * Tests for the headers that say which plugin makes a request and its release.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \aiprovider_datacurso\httpclient\plugin_identity
 */
final class plugin_identity_test extends \advanced_testcase {
    /**
     * A client nobody named sends no header: the provider does not speak for the plugins that use it.
     */
    public function test_no_component_sends_no_headers(): void {
        $identity = new plugin_identity();

        $this->assertSame([], $identity->get_headers());
    }

    /**
     * A blank name counts as none.
     *
     * @dataProvider blank_components
     * @param string $component A name with nothing in it.
     */
    public function test_a_blank_component_counts_as_none(string $component): void {
        $identity = new plugin_identity($component);

        $this->assertSame([], $identity->get_headers());
    }

    /**
     * Names that are empty or only spaces.
     *
     * @return array
     */
    public static function blank_components(): array {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'tab and newline' => ["\t\n"],
        ];
    }

    /**
     * A plugin that names itself sends its name and the release of its version.php.
     */
    public function test_a_plugin_sends_its_name_and_release(): void {
        $manager = \core_plugin_manager::instance();
        $plugin = $manager->get_plugin_info('aiprovider_datacurso');
        $release = $plugin->release;
        $this->assertNotEmpty($release);

        $identity = new plugin_identity('aiprovider_datacurso');
        $headers = $identity->get_headers();

        $this->assertSame(['X-Plugin-Name: aiprovider_datacurso', 'X-Plugin-Version: ' . $release], $headers);
    }

    /**
     * The name is trimmed before it is sent.
     */
    public function test_the_name_is_trimmed(): void {
        $identity = new plugin_identity('  local_notinstalled ');

        $this->assertSame(['X-Plugin-Name: local_notinstalled'], $identity->get_headers());
    }

    /**
     * Without a release to report, only the name is sent and the service asks for an update.
     */
    public function test_a_plugin_that_is_not_installed_sends_only_its_name(): void {
        $identity = new plugin_identity('local_notinstalled');

        $this->assertSame(['X-Plugin-Name: local_notinstalled'], $identity->get_headers());
    }

    /**
     * A plugin that has no release in its version.php sends only its name.
     */
    public function test_a_plugin_without_a_release_sends_only_its_name(): void {
        $manager = \core_plugin_manager::instance();
        $plugin = $manager->get_plugin_info('mod_page');
        $release = $plugin->release;
        if (!empty($release)) {
            $this->markTestSkipped('mod_page has a release in this Moodle version.');
        }

        $identity = new plugin_identity('mod_page');

        $this->assertSame(['X-Plugin-Name: mod_page'], $identity->get_headers());
    }

    /**
     * The properties of a named plugin are its name, its release and its date-based version.
     */
    public function test_properties_of_an_installed_plugin(): void {
        $manager = \core_plugin_manager::instance();
        $plugin = $manager->get_plugin_info('aiprovider_datacurso');

        $identity = new plugin_identity(' aiprovider_datacurso ');

        $this->assertSame('aiprovider_datacurso', $identity->get_component());
        $this->assertSame(trim((string) $plugin->release), $identity->get_release());
        $this->assertSame((string) $plugin->versiondisk, $identity->get_version());
        $this->assertMatchesRegularExpression('/^\d{10}$/', $identity->get_version());
    }

    /**
     * Without a plugin all three properties are empty.
     */
    public function test_properties_without_a_component(): void {
        $identity = new plugin_identity('  ');

        $this->assertNull($identity->get_component());
        $this->assertSame('', $identity->get_release());
        $this->assertSame('', $identity->get_version());
    }

    /**
     * A plugin that is not installed keeps its name but has no release and no version.
     */
    public function test_properties_of_a_plugin_that_is_not_installed(): void {
        $identity = new plugin_identity('local_notinstalled');

        $this->assertSame('local_notinstalled', $identity->get_component());
        $this->assertSame('', $identity->get_release());
        $this->assertSame('', $identity->get_version());
    }
}
