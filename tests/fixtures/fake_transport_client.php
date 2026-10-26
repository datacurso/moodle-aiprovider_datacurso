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

require_once(__DIR__ . '/fake_curl.php');

/**
 * Datacurso API client whose transport is a fake cURL wrapper, so no request leaves the test.
 *
 * @package    aiprovider_datacurso
 * @category   test
 * @copyright  2026 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fake_transport_client extends datacurso_api_base {
    /** @var fake_curl Transport double. */
    private fake_curl $fakecurl;

    /**
     * Constructor.
     *
     * @param fake_curl $fakecurl Transport double.
     * @param string|null $callercomponent Plugin that makes the requests, if any.
     */
    public function __construct(fake_curl $fakecurl, ?string $callercomponent = null) {
        // The parent constructor needs an enabled provider instance; these tests only exercise the
        // transport, so the state it would build is set directly.
        $this->baseurl = 'https://example.invalid';
        $this->licensekey = 'test-key';
        $this->pluginidentity = new plugin_identity($callercomponent);
        $this->instanceprovider = new \aiprovider_datacurso\provider(
            enabled: true,
            name: 'test',
            config: json_encode(['licensekey' => 'test-key']),
            actionconfig: '{}',
        );
        $this->fakecurl = $fakecurl;
    }

    #[\Override]
    protected function create_curl(): \curl {
        return $this->fakecurl;
    }
}
