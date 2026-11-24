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

require_once($CFG->libdir . '/filelib.php');

/**
 * HTTP client for the Datacurso AI services API.
 *
 * @package    aiprovider_datacurso
 * @copyright  2025 Industria Elearning
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ai_services_api extends datacurso_api_base {
    /** Default base URL for the standard DataCurso AI service. */
    private const DEFAULT_BASE_URL = 'https://plugins-ai.datacurso.com';

    /** Default base URL for the EU-hosted DataCurso AI service. */
    private const DEFAULT_BASE_URL_EU = 'https://eu.plugins-ai.datacurso.com';

    /** Setting holding the development URL of the standard region. */
    public const DEV_URL_SETTING = 'devservicesurl';

    /** Setting holding the development URL of the European region. */
    public const DEV_URL_SETTING_EU = 'devservicesurleu';

    /**
     * Constructor.
     *
     * The base URL is, in this order: the URL passed for the region of the site, the development
     * URL configured for that region (unlinked development settings page of the provider), and the production URL
     * of that region. The European region never uses the standard development URL, so the data of a
     * European site does not leave the European deployment by accident. The transport policy still
     * applies to whichever URL is chosen.
     *
     * Workplace: the second argument may be a tenant id instead of a URL (integer or numeric string).
     * The region and the licence are then resolved for that tenant.
     *
     * @param string|null $licensekey The license key obtained from Datacurso SHOP.
     * @param string|int|null $baseurl Optional standard-region base URL override, or a tenant id.
     * @param string|null $baseurleu Optional EU-region base URL to override the default endpoint.
     */
    public function __construct(?string $licensekey = null, int|string|null $baseurl = null, ?string $baseurleu = null) {
        $tenantid = null;

        if (is_int($baseurl) || (is_string($baseurl) && ctype_digit($baseurl))) {
            $tenantid = (int)$baseurl;
            $baseurl = null;
        }

        if (datacurso_api_base::is_license_for_ue($licensekey, $tenantid)) {
            $finalbaseurl = $baseurleu ?? self::configured_base_url(true);
        } else {
            $finalbaseurl = $baseurl ?? self::configured_base_url(false);
        }

        parent::__construct($finalbaseurl, $licensekey, $tenantid);
    }

    /**
     * The base URL a region uses when no URL is passed to the constructor.
     *
     * It is the development URL configured for that region, or the production URL of that region.
     * The European region never uses the standard development URL.
     *
     * @param bool $eu True for the European region.
     * @return string The base URL.
     */
    public static function configured_base_url(bool $eu): string {
        if ($eu) {
            return self::development_url(self::DEV_URL_SETTING_EU) ?? self::DEFAULT_BASE_URL_EU;
        }
        return self::development_url(self::DEV_URL_SETTING) ?? self::DEFAULT_BASE_URL;
    }

    /**
     * The development URL configured in the given setting.
     *
     * @param string $setting Name of the setting.
     * @return string|null Null when the setting is empty.
     */
    private static function development_url(string $setting): ?string {
        $url = trim((string) get_config('aiprovider_datacurso', $setting));
        return $url === '' ? null : $url;
    }

    /**
     * Build the streaming URL for a given session ID, adjusting base URL.
     *
     * @param string $sessionid
     * @return string streaming URL
     */
    public function get_streaming_url_for_session(string $sessionid): string {
        // Build streaming URL with session ID.
        $baseurl = rtrim($this->baseurl, '/');

        return $baseurl . '/chat/stream?session_id=' . urlencode($sessionid);
    }
}
