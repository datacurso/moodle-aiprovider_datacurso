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

/**
 * Says which plugin makes the requests to a Datacurso service, and its release.
 *
 * The services that refuse plugin releases that are too old need the name and the release of the
 * caller. This class builds the two headers, X-Plugin-Name and X-Plugin-Version. The release is read
 * from the version.php of the named plugin. A client that is given no plugin sends no header: the
 * provider does not speak for the plugins that use it.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class plugin_identity {
    /** @var string|null Frankenstyle name of the calling plugin, or null when none was given. */
    private ?string $component;

    /**
     * Constructor.
     *
     * @param string|null $component Frankenstyle name of the calling plugin, for example "local_coursegen".
     */
    public function __construct(?string $component = null) {
        $this->component = $this->clean_component($component);
    }

    /**
     * Build the header lines that identify the plugin.
     *
     * The version header is left out when the plugin is not installed or has no release, and the
     * service then asks for an update.
     *
     * @return string[] Header lines; empty when no plugin was given.
     */
    public function get_headers(): array {
        if ($this->component === null) {
            return [];
        }

        $headers = ['X-Plugin-Name: ' . $this->component];
        $release = $this->get_release();
        if ($release === '') {
            return $headers;
        }

        $headers[] = 'X-Plugin-Version: ' . $release;
        return $headers;
    }

    /**
     * Treat a missing or blank name as no plugin at all.
     *
     * @param string|null $component Name as it was given.
     * @return string|null The trimmed name, or null.
     */
    private function clean_component(?string $component): ?string {
        if ($component === null) {
            return null;
        }

        $component = trim($component);
        if ($component === '') {
            return null;
        }

        return $component;
    }

    /**
     * Read the release of the plugin from its version.php.
     *
     * @return string The release, or an empty string when the plugin is not installed or has none.
     */
    private function get_release(): string {
        $manager = \core_plugin_manager::instance();
        $plugin = $manager->get_plugin_info($this->component);
        if ($plugin === null) {
            return '';
        }

        $release = $plugin->release;
        if ($release === null) {
            return '';
        }

        return trim((string) $release);
    }
}
