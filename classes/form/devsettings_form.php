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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Development URLs of the DataCurso AI services (unlinked page, site administrators only).
 *
 * Each field accepts nothing or an absolute http:// or https:// URL with a host. Whether plain
 * HTTP may actually be used is decided when the request is sent, by the transport policy.
 *
 * @package    aiprovider_datacurso
 * @copyright  2026 Datacurso
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class devsettings_form extends \moodleform {
    /** @var array<string, bool> Settings of the form, mapped to whether they belong to the European region. */
    private const SETTINGS = [
        ai_services_api::DEV_URL_SETTING => false,
        ai_services_api::DEV_URL_SETTING_EU => true,
    ];

    /**
     * Refuse anyone who is not a site administrator, even with moodle/site:config.
     *
     * @throws \require_login_exception When the user is not logged in.
     * @throws \moodle_exception When the user is not a site administrator.
     */
    public static function require_site_admin(): void {
        require_login(null, false);
        if (!is_siteadmin()) {
            throw new \moodle_exception('accessdenied', 'admin');
        }
    }

    /**
     * Whether a value is empty or an absolute http:// or https:// URL with a host.
     *
     * @param string $value Submitted value.
     * @return bool
     */
    public static function is_valid_service_url(string $value): bool {
        $url = trim($value);
        if ($url === '') {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        return in_array($scheme, ['http', 'https'], true)
            && $host !== ''
            && clean_param($url, PARAM_URL) === $url;
    }

    /**
     * The saved values, to prefill the form.
     *
     * @return array<string, string>
     */
    public static function current_data(): array {
        $data = [];
        foreach (array_keys(self::SETTINGS) as $name) {
            $data[$name] = (string) get_config('aiprovider_datacurso', $name);
        }
        return $data;
    }

    /**
     * Save the submitted URLs; an empty value unsets the setting. Every change goes to the config log.
     *
     * @param \stdClass $data Validated form data.
     */
    public static function save(\stdClass $data): void {
        foreach (array_keys(self::SETTINGS) as $name) {
            $value = trim((string) ($data->{$name} ?? ''));
            $previous = (string) get_config('aiprovider_datacurso', $name);
            if ($value === $previous) {
                continue;
            }
            set_config($name, $value === '' ? null : $value, 'aiprovider_datacurso', true);
        }
    }

    #[\Override]
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('static', 'devsettings_intro', '', get_string('devsettings_desc', 'aiprovider_datacurso'));

        foreach (self::SETTINGS as $name => $eu) {
            $mform->addElement('text', $name, get_string($name, 'aiprovider_datacurso'), ['size' => 60]);
            $mform->setType($name, PARAM_RAW_TRIMMED);
            $mform->addElement('static', $name . '_desc', '', get_string($name . '_desc', 'aiprovider_datacurso'));
            $mform->addElement(
                'static',
                $name . '_effective',
                '',
                get_string('devsettings_effectiveurl', 'aiprovider_datacurso', s(ai_services_api::configured_base_url($eu)))
            );
        }

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        foreach (array_keys(self::SETTINGS) as $name) {
            if (!self::is_valid_service_url((string) ($data[$name] ?? ''))) {
                $errors[$name] = get_string('devservicesurl_invalid', 'aiprovider_datacurso');
            }
        }
        return $errors;
    }
}
