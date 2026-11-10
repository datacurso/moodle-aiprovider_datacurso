## [2.2.1] - 2026-11-10

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.3**.

### Changed
- **The AI services no longer receive the user's timezone where they do not use it (AIP-PRIV-001-R1)**
  Requests of the AI services client (`ai_services_api`) carried the user's `timezone` on every route, although the AI services read it only on their CV and job matching routes. It is now sent only on paths starting with `/cv/` or `/jobmatch/`; every other request and upload of that client leaves it out. The course service client (`ai_course_api`), which uses it, keeps receiving it, and `site_id`, `site_url`, `userid` and `lang` are sent as before. Backed by `timezone_minimisation_test::test_ai_services_request_omits_timezone`, `test_ai_services_upload_omits_timezone`, `test_cv_and_jobmatch_routes_keep_timezone` and `test_course_client_keeps_timezone`
- **The README documents the privacy of the requests**
  New "Privacy and audit" section: the audit event, the transport rules, what the services receive (the `userid` is the Moodle user id: this version does not pseudonymise it), how deletion of a user is handled, what the token manager service keeps per user, the retention of the data at the services, where the European services are hosted, and the erase operation of the course service

### Removed
- **GitHub Actions workflows**
  The `.github` folder (the plugin CI and release workflows) is removed. CI runs on Jenkins, which publishes the required status check and blocks merges until every test and validation passes.

### Security
- **Every external request is recorded in the Moodle log (AIP-SEC-005-R1)**
  The new event `\aiprovider_datacurso\event\external_request_completed` is logged once per request to a Datacurso service: requests, uploads and downloads of the AI services and course service clients, requests of the shop client, and the requests of the core AI actions, including the download of a generated image. It records the user, the context, the client, the service, the HTTP method, the endpoint path without its query string, the result (`success`, `failure`, or `refused` when nothing left the site, such as a service URL that is not HTTPS or a request without a license key), the HTTP status and, when the request did not succeed, an error code. Its data is validated against an allowlist, so it never carries the license key, headers, payload, prompt, file names, response body, site URL, the user id sent to the service or transport error text. The event is logged in the system context unless the plugin making the request passes its own context, and optionally the user the request is for, with the new `set_audit_context()` of the client; the user id in the payload is never read for the log. The core AI actions use the context of the action. A problem while logging is reported to developers and never breaks the request. Path segments that identify a record or a session (numbers, UUIDs, long hexadecimal strings and long tokens mixing letters and digits) are logged as `{id}`. The refusals the clients already made when they are built (`instance_disabled`, `licensekey_missing`) are unchanged and happen before there is a request to log. New string `event_external_request_completed` in all the plugin's languages. Backed by `external_request_completed_test`, `request_audit_test::test_normalise_endpoint` and `test_event_records_the_normalised_endpoint`
- **Requests to the Datacurso services only travel over verified HTTPS and never follow a redirect**
  The AI services and course service clients (`datacurso_api_base`, including file downloads), the shop client (`datacurso_api`) and the core AI actions now verify the certificate chain and the host name of the service, and refuse a redirect answer instead of following it, so the license key and the content of the users never reach another host or travel in clear. A redirect answer fails with `httperror` and its status. A service URL that is not `https` is refused before any connection with the new `insecuretransport` exception (in all the plugin's languages) and logged as `refused`; developers who run the services locally over plain HTTP can allow it with `$CFG->aiprovider_datacurso_allow_insecure_transport = true;` in `config.php`. The rules live in the new `\aiprovider_datacurso\local\transport_policy` class. Downloads now use the same transport as the other requests instead of `create_file_from_url()`, which followed redirects. Backed by `transport_hardening_test::test_ai_request_sends_hardened_curl_options`, `test_shop_request_sends_hardened_curl_options`, `test_ai_redirect_answer_is_not_followed`, `test_shop_redirect_answer_is_not_followed`, `test_http_base_url_is_refused_without_a_connection`, `test_http_base_url_is_allowed_with_the_developer_flag`, `test_require_secure_url_accepts_only_https_by_default`, `test_download_sends_hardened_curl_options`, `test_download_redirect_answer_is_not_followed`, `test_download_from_http_base_url_is_refused`, `test_core_ai_request_sends_hardened_guzzle_options`, `test_core_ai_redirect_answer_is_not_followed` and `test_core_ai_http_endpoint_is_refused`
- **Requests to the Datacurso services give up after a total timeout**
  Besides the 30 seconds allowed to connect, a request to the AI services or a core AI action now gives up after 300 seconds, a file download after 300 seconds and a shop request after 30 seconds, so a service that stops answering no longer holds a web server process until PHP's own limit. Backed by the `transport_hardening_test` option tests
- **The consumption report escapes the action and the service it shows**
  The action and service columns of the consumption history printed unknown values from the shop as HTML. They are now escaped, and the synchronisation strips from each record anything that is not part of a component name (service) or a route path (action) before storing it. Backed by `consumption_history_test::test_markup_in_action_and_service_is_escaped` and `consumption_sync_test::test_markup_in_service_and_action_is_stripped`
- **Deleting a user unlinks the consumption history already copied to the site**
  When a user is deleted, the records of that user in the local consumption history are now attributed to nobody (userid 0) and kept in the site totals, so the report no longer shows the name of the deleted person with the consumption. The new observer `\aiprovider_datacurso\local\user_deletion::user_deleted` is declared in `db/events.php`. Backed by `user_deletion_test::test_deleted_user_consumption_rows_are_unlinked`
- **Viewing the reports is flagged as a personal data risk**
  The capability `aiprovider/datacurso:viewreports` now declares `RISK_PERSONAL`, since the reports show the consumption of every user by name. It takes effect on the next upgrade of the plugin. The reports page title is now the language string `reportspagetitle`, in all the plugin's languages. Backed by `capabilities_test::test_viewreports_capability_is_flagged_as_personal_risk` and `test_installed_viewreports_capability_is_flagged_as_personal_risk`

## [2.2.0] - 2026-11-09

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.3**.

### Added
- **Moodle 5.3 support**
  `supported` is raised to `[500, 503]` and the Jenkins CI runs on Moodle 5.0, 5.2 and 5.3 (MariaDB).
- **Jedomi is a provider service with its own consumption limits**
  `local_jedomi` is registered as a service of the provider, so the provider instance settings offer it a per-window credit limit and a credits-per-action value like the other services. It has a single `default` action that costs 2 credits unless an administrator saves another value in `ratelimit_local_jedomi_credit_default`. Requests to `/jedomi/` resolve to `local_jedomi`, so the `X-RateLimit-*` headers carry its limit and its look-ahead cost; while the limit is disabled no header is sent. The consumption report and history show the service as "Jedomi" and the `/jedomi/v1/chat/completions` action as "Generate Jedomi chat message", with the new strings `pluginname_jedomi` and `generate_jedomi_chat` in all the plugin's languages. Backed by `provider_test::test_get_services_includes_jedomi`, `test_jedomi_has_a_single_default_action_of_two_credits`, `test_get_actions_includes_jedomi_chat` and `test_existing_services_and_catalogs_are_unchanged`, `ratelimiter_test::test_jedomi_path_resolves_to_jedomi_service`, `test_jedomi_headers_follow_its_limit_configuration` and `test_existing_path_mappings_are_unchanged`, and `consumption_service_test::test_jedomi_consumption_is_labelled`
- **Each suite plugin is switched on and off from the provider**
  The Datacurso plugins tab now lists the suite plugins from a single list, `\aiprovider_datacurso\local\suite_plugins`, which `provider::get_services()` also reads without changing any service id, so rate limits and consumption keep working. The state of each plugin is stored in `plugin_enabled_<component>` and every change is written to the config log; a plugin with no saved state is enabled. Plugins read it with `\aiprovider_datacurso\local\plugin_settings::is_enabled('<component>')` and drop their own "Enabled" setting.
- **Suite plugins stop their pages and web services while disabled**
  `plugin_settings::require_enabled('<component>')` returns when the plugin is enabled and otherwise throws a `moodle_exception`, so a suite plugin can guard its pages and web services with one call. A user without `moodle/site:config` gets `plugindisabled` ("{plugin} is not enabled on this site. Please contact your site administrator."); a site administrator gets `plugindisabled_admin`, which names the plugin and links to the Datacurso plugins list, and the same URL is the exception link. The plugin is named by its suite name string, or by its own `pluginname` outside the suite (`suite_plugins::get_display_name()`). New strings `plugindisabled` and `plugindisabled_admin` in all the plugin's languages. Suite plugins that use it on Moodle 5.0 to 5.3 require version 2026110900. Backed by `plugin_settings_test::test_require_enabled_passes_for_enabled_and_unsaved_plugins`, `test_require_enabled_tells_a_user_to_contact_the_administrator`, `test_require_enabled_links_an_administrator_to_the_plugins_list` and `test_require_enabled_names_other_components_by_their_pluginname`
- **The plugins tab switches each plugin on and off in place, like the core AI tables**
  The table now shows Plugin, Description, Installed, Enabled, Settings and Link. The Enabled column has the same switch as Site administration > AI > provider "Actions": flipping it calls the new `aiprovider_datacurso_set_plugin_enabled` web service (`classes/external/set_plugin_enabled.php`, AJAX, `aiprovider/datacurso:manageconfig` at system context), which accepts only installed suite plugins (`pluginnotinstalled` otherwise), saves through `plugin_settings::set_enabled()` and confirms with the core "{plugin} enabled/disabled" notification; the row is updated by `amd/src/plugin_list.js` without reloading. Plugins that are not installed show no switch. The Link column shows the external-link icon with "{plugin} link" as its accessible name (`pluginlink_title`) instead of the full URL. New strings `pluginlink_title` and `pluginnotinstalled` in all languages. Backed by `set_plugin_enabled_test`, `plugin_list_page_test`, `suite_plugins_test::test_is_installed_follows_config_plugins` and `plugin_settings_test::test_has_settings_only_for_plugins_with_an_extension`
- **Each suite plugin has a settings page in the provider**
  `admin/plugin_settings.php?component=<component>` requires `aiprovider/datacurso:manageconfig`, accepts only suite plugins and has an "Enabled" field for every plugin. It shows the breadcrumb Datacurso AI Provider > Datacurso plugins list > "{plugin} settings", each step linking back, with the site name as page heading. The Settings column of the plugins tab links to it only for installed plugins that add their own fields (`plugin_settings::has_settings()`); the page itself works for every suite plugin.
- **Plugins can add their own fields to their settings page in the provider**
  A suite plugin that ships `\<component>\aiprovider\settings_extension` implementing `\aiprovider_datacurso\local\plugin_settings_extension` adds fields, validation and saving to its settings page; the provider finds the class by name and never references the plugin.
- **New capability to manage the suite plugins**
  `aiprovider/datacurso:manageconfig` (system context, `RISK_CONFIG`, granted to no archetype) guards the plugin switches and the plugin settings pages, so viewing the reports does not imply being able to change the site configuration. New string `datacurso:manageconfig` in all the plugin's languages.
- **Upgrade keeps the state of every plugin**
  The upgrade to 2026102900 copies the current "Enabled" value of each plugin that had one (`enableforumai`, `enableassignai`, `enabled` of Tutor AI, Ranking Activities AI and Jedomi, where for Jedomi only `0` disabled it, and `enableai` of Share Certificate AI) into the provider, so no site changes state. Plugins that had no such setting stay enabled, and a state already saved in the provider is not overwritten. New strings `invalidsuiteplugin`, `plugindesc_jedomi`, `pluginsettings`, `pluginsettings_enabled`, `pluginsettings_enabled_desc`, `pluginsettings_saved` and `pluginsettings_title` in all the plugin's languages. Backed by `suite_plugins_test`, `plugin_settings_test`, `plugin_settings_form_test`, `plugin_list_page_test` and `upgrade_plugin_settings_test`; the Behat scenario `plugins_list.feature` was updated to the new table.

### Fixed
- **The provider's error messages reach the user on Moodle 5.1 to 5.3**
  From Moodle 5.1, core_ai builds the response of a failed action from its `errorcode`, `error` and `errormessage`. Every failure path of the provider left one of them out, so core threw a `TypeError`, the action was not recorded and the user only saw "Something went wrong". The rate limit, credits exhausted, licence not allowed, HTTP error, service unavailable and invalid response messages are now shown, under the title "The AI request could not be completed" (new string `errorrequestfailed`). On Moodle 5.0, which does not read `error`, only the invalid text response was affected. All failure paths go through the new `abstract_processor::error_response()`. Backed by `error_response_test`

## [2.1.10] - 2026-10-27

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Changed
- **Unique build number for the Moodle plugins directory**
  The release `2.1.9` carried the same build number (`2026102602`) as the Moodle 4.5 release `1.6.4`, which the plugins directory rejects because every version of a listing must have a higher build number than the earlier ones. The build is raised to `2026102603`; the behaviour is the one of `2.1.9` (and `2.1.8`), which never reached the directory for the same reason.

## [2.1.9] - 2026-10-26

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Changed
- **Every request also carries the build number of the plugin**
  Besides `X-Plugin-Name` and `X-Plugin-Version` (the release), the requests now send `X-Plugin-Build`, the number in `$plugin->version` of the calling plugin (for example `2026102602`). The services compare that whole number with the minimum they accept, so they do not have to read release names such as `1.6.3-wp`. `plugin_identity` builds the header from its `version` property.

## [2.1.8] - 2026-10-26

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Added
- **Every request says which plugin makes it and its release**
  The Datacurso services need to know which plugin release is calling so that one that is too old can be told to update instead of failing in a confusing way. `datacurso_api_base` now sends `X-Plugin-Name` and `X-Plugin-Version` (the release in the plugin's `version.php`) with every request and file download, next to `License-Key`. A plugin that uses the client names itself with the new `$callercomponent` constructor argument (`local_coursegen` and `local_coursedynamicrules` do); a client that is not told sends no `X-Plugin-*` header, because the provider does not speak for the plugins that use it. The headers are built by the new `plugin_identity` class and `get_plugin_headers()` returns them.

### Changed
- **A plugin that is too old gets a clear message**
  When a service answers HTTP 426 the person now sees "update the plugin to version X" (`plugin_update_required`, naming the plugin, its release and the minimum) or "update the plugin to the latest version" (`plugin_version_required`, when no release was reported) instead of a generic HTTP error. The texts are in all the plugin's languages. What happened is read from the service's `error_code` (`PLUGIN_UPDATE_REQUIRED`), never from its text, by the new `upgrade_required_response` class.

## [2.1.7] - 2026-10-20

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Changed
- **Unique build number for the Moodle plugins directory**
  The previous release carried the same build number as the Moodle 4.5 release of this plugin, which the plugins directory rejects because every version of a listing must have a higher build number than the earlier ones. The build number is raised; the behaviour is the one of `2.1.6`.

## [2.1.6] - 2026-10-19

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Fixed
- **A new licence no longer keeps showing the consumption of the previous one**
  The local mirror of the consumption history kept a single watermark, the highest external id stored, whatever licence the rows came from. After changing the licence the report kept listing the consumption of the old one, and the history of the new one was never pulled when its ids were lower. The mirror now remembers the licence it was filled for and is emptied the next time the history is synchronised with a different licence, so the new history is pulled in full. Removing the licence empties it as well, so nothing is reported while no licence is configured.

## [2.1.5] - 2026-10-19

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Changed
- **Course creator API endpoint for the European Union**
  Sites licensed in the European Union now reach the course generation service at `https://eu.course-ai-v2.datacurso.com/api/v1` instead of the standard endpoint, so their course creation requests are served by the EU deployment. Sites of other regions keep using `https://course-ai-v2.datacurso.com/api/v1`.

## [2.1.4] - 2026-10-05

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Changed
- **The region of the licence is resolved once and kept**
  Which region serves a site was asked of the shop while building every API client, so each call to an AI service paid a round trip to `shop.datacurso.com` first, and a shop that did not answer took every service down with it although the services themselves were up. The region is a property of the licence, not of the request: it is now resolved once, kept with a fingerprint of the licence it belongs to, and asked for again only when that licence changes or after a week.
  When the region is already known and the shop cannot be reached, the known region is used and the service keeps working. When it is not known —a licence that has never reached the shop— the call still fails rather than guess: choosing a region wrongly would send the data of a site to a deployment it may not be allowed to reach.
  The licence key lives in the configuration of the AI provider instance, which core saves without notifying the plugin, so a change of licence is detected by its fingerprint on the next request instead of by a save callback. The week is there for the other case: a site whose region is moved between deployments without its key changing.
- **The privacy declaration names the whole chain**
  The declaration of the AI services said the request data reaches Datacurso and stopped there, although those services do not generate the answers themselves: they hand the request on to a large language model of a third party, today Google Gemini or OpenAI depending on the model, which may sit outside the European Union. The declaration now says so, in every shipped language, and says what that provider does not receive: the identifier of the user, the identifier of the site and the timezone are stripped before the request leaves. It also stops claiming that every erasure request goes through support, which is no longer true of the AI tutor: the platform now asks the service to delete the conversations of a person or of a course on its own.

## [2.1.3] - 2026-09-08

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Security
- **Upstream error responses are no longer shown to end users**
  When the AI service failed (HTTP error or transport failure), the processors returned the raw upstream body or the transport message as `errormessage`, which Moodle core displays in the editor and persists in `ai_action_register`. They now return the localized `httperror` / `serviceunavailable` messages; the raw detail is only written to developer debugging output.
- **Hardened image download fallback**
  When the AI service returns an image URL instead of inline base64, the plugin now only fetches it over `https`, from the same host as the AI service endpoint, with an allowed image extension (`png`, `jpg`, `jpeg`, `webp`), without following redirects, within 30 seconds and up to 10 MiB (declared length and streamed length). The downloaded bytes, like inline base64 images, must sniff as PNG, JPEG or WEBP before being stored, and the stored name is always `datacurso_image_<timestamp>.<ext>`. Contract note: images hosted on a third-party CDN are rejected by design; the service must return `b64_json` (already the preferred format). The public `download_file()` method of the image processor was removed and the request body (including the prompt) is no longer written to debugging output.
- **Shop client, API client and user lookup no longer leak internals**
  The shop API client raises localized exceptions (`instance_disabled`, `licensekey_missing`, `curlerror`, `httperror`, `jsondecodeerror`) carrying only the cURL error number or the HTTP status, never the request URL or the response body; the base API client stops writing response bodies to debugging output; and the `get_users` web service returns a localized message instead of the database error text.
- **AI service endpoint resolved once per request**
  The processors resolve the AI service endpoint (which performs a license check against the shop) a single time per request instead of two or three times, and a failure while pinning the image host now degrades to the localized invalid-image error instead of raising an exception after a successful AI response.
- **Base API client no longer exposes cURL error text**
  `datacurso_api_base` reports transport failures with the cURL error number only; the raw libcurl message (which may contain hostnames) is confined to developer debugging output.
- **Removed a request-payload debug log**
  `datacurso_api_base` no longer writes the outgoing request payload (`PAYLOAD: ...`) to debugging output on every 4xx/5xx response; only the response size and status are logged.
- **Pixel-area cap on generated images**
  Generated images are rejected when their declared dimensions exceed 25 megapixels, so a small file cannot expand into a huge bitmap on decode.
- **Actionable 403 messages in the processors**
  Credit exhaustion (`tokens_not_sufficient`) and license rejections (`license_not_allowed`) from the AI service now surface the same localized, actionable messages used by the other Datacurso plugins instead of the generic HTTP 403 error.
- **Course service rate-limit headers declared**
  The privacy metadata for the course creation service now declares the rate-limit headers that are forwarded when the `local_coursegen` limit is enabled.
- **Complete privacy metadata**
  The privacy metadata now declares the three external Datacurso systems separately (`datacurso_ai_services`, `datacurso_course_service`, `datacurso_shop`) with every field actually transferred (messages, model, image size and count, site identifier and URL, timezone, language, uploaded files, license key header and rate-limit headers), declares the `externalid` column of the local consumption table, and the privacy statement no longer claims that no personal data is stored locally. The future remote erasure/export hook is documented in the privacy provider; it is not implemented because the services expose no privacy endpoints yet, so remote erasure requests are handled through Datacurso support.

### Changed
- **Privacy metadata language strings restructured**
  Strings for the single generic external location (`privacy:metadata:aiprovider_datacurso:*`) were removed in every language; `privacy:metadata:aiprovider_datacurso` is kept as the export root label. The dead rate-limit and per-user quota strings (`privacy:metadata:aiprovider_datacurso_rlimit*` and `privacy:metadata:aiprovider_datacurso_userlimit*`, left over from tables removed in earlier releases) were also removed in every language.
- **Testable transport in the HTTP clients**
  `datacurso_api` and `datacurso_api_base` build their cURL wrapper through a `create_curl()` substitution point, and `user_service::get_users()` accepts an optional database instance, so error handling is covered by automated tests without network access.

## [2.1.2] - 2026-09-04

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Added
- **Jenkins CI onboarding**: new `Jenkinsfile.moodleplugin` pins `moodlePluginCI` to
  `MOODLE_500_STABLE`, so the plugin can run through the shared Jenkins pipeline in addition to
  GitHub Actions.
- **Expanded automated test coverage**: the sync contract (duplicate external id rejection, the
  `/tokens/historial-consumos` request shape, and empty/garbage date parsing), the consumption
  history report's active columns, action/service id-to-name resolution (with unknown ids passing
  through verbatim), and privacy export with seeded data.

### Fixed
- **Admin-configured system instruction is now honored**: `generate_text` and `summarise_text`
  requests previously ignored the instance form's system instruction and always sent the
  hardcoded per-action default; the resolved value is now used.
- **Privacy export no longer drops rows**: exporting a user's consumption history returned only
  the last database row instead of the full history.
- **POST payloads no longer escape non-ASCII characters**: accented and multibyte text now
  reaches the API as raw UTF-8 instead of `\uXXXX` sequences.
- **Rate-limit form defaults corrected**: the instance form's credit-budget default was wired to
  the window-length field instead of the limit field, and the window unit had no default at all.
- **Connection failures are handled gracefully**: network-level errors (DNS, timeout, refused) now
  return the same graceful error response as an API-level failure, instead of an uncaught
  exception.
- **Removed a dead per-request debug log file** written to moodledata on every POST call with no
  rotation.

## [2.1.1] - 2026-09-01

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Added
- **Local consumption mirror table and Report Builder integration**
  A new `aiprovider_datacurso_consumption` table locally mirrors the external Datacurso credit
  consumption history (`externalid`, `userid`, `service`, `action`, `credits`, `balance`,
  `timecreated`). `consumption_sync` keeps it up to date on demand (page-load-driven, lock-guarded,
  watermarked on `MAX(externalid)`); the consumption history tab now renders it through a Report
  Builder system report (`consumption_history`), which provides filtering, sorting, pagination and
  download (CSV/Excel/ODS) for free.
- **`aiprovider_datacurso_get_consumption_summary` web service**
  New AJAX-callable external function returning server-side aggregated credit totals (grouped by
  month, day, action or service) for the general report charts, backed by
  `consumption_service::get_summary()` reading from the local mirror table.
- **Privacy metadata for the consumption mirror table**
  `classes/privacy/provider.php` now declares `aiprovider_datacurso_consumption` and maps it to
  the owning user, so it participates in data export/deletion requests.

### Changed
- **Consumption report download now honors active filters and columns**
  Downloading the consumption history now goes through Report Builder, so the exported file
  reflects whatever filters, sorting and visible columns are currently applied — replacing the
  previous single "export full history" CSV button, which always ignored active filters.
- **General report charts read from the local mirror, not the external API**
  `report_charts.js` now fetches pre-aggregated totals from
  `aiprovider_datacurso_get_consumption_summary` instead of downloading and grouping raw
  consumption rows client-side, reducing payload size and client-side processing.

### Removed
- **Legacy consumption history table UI**: `amd/src/consumption.js`, the
  `get_consumption_history` external function and its backing service, the
  `output/consumption_page` renderable, and the `consumption_page`/`consumption_row` Mustache
  templates — all superseded by the Report Builder system report above.
- **`get_all_consumption` external function**: superseded by the aggregated
  `get_consumption_summary` endpoint; the report charts no longer need the full raw history.

## [2.1.0] - 2026-08-26

**Compatibility note:** This version is compatible from **Moodle 5.0** to **Moodle 5.2**.

### Added
- **Per-action credit fields on the provider-instance form**
  The provider-instance configuration form now exposes a credits-per-action field
  (`ratelimit_{service}_credit_{action}`) for every action of every service, next to the
  existing enable/limit/window fields, replacing the abandoned global credit-per-action setting.
  Each field carries a client-side positive-integer validation rule, matching the 4.5 form's
  check (there is no server-side `validation()` seam on the core provider-instance form).
- **`execute_request()` test seam in the HTTP client**
  `datacurso_api_base::send_request()` now delegates the actual cURL dispatch to a protected
  `execute_request()` method, so tests can capture the outgoing request (including rate-limit
  headers) without a network call.

### Changed
- **Rate limiting moved from local enforcement to forwarded headers**
  The plugin no longer tracks per-user, per-service usage in local tables. It computes
  `X-RateLimit-*` headers from the provider-instance configuration and forwards them; the
  remote Datacurso token-manager accumulates consumption and enforces the limit centrally.
- **`ratelimiter` requires its owning provider instance**
  `new ratelimiter($instanceprovider)` is now mandatory (no default); all rate-limit reads come
  from `$instanceprovider->config`, never from global plugin config.
- **`provider::get_credit_for_action()` is now instance-aware**
  Changed from a static method reading a dead global config key to a public instance method
  reading `$this->config["ratelimit_{service}_credit_{action}"]`, with the existing
  catalogue-default fallback chain.
- **`upload_file()` takes a `\stored_file` again**
  Callers hold Moodle file storage objects, not filesystem paths; the temporary file needed by
  cURL is created and removed internally. The richer 403 detail handling (`rate_limit_exceeded`,
  `tokens_not_sufficient`, `license_not_allowed`) is restored.
- **Reports tab structure keeps the 5.0 provider-instance layout**
  `consumption`, `generalreport`, `pluginslist` and the "Provider configuration" link (pointing
  at the core AI provider-instance edit form) are unchanged; the CSV export and year filter are
  now available on the consumption and general report tabs.
- **Reports access capability widened to the manager archetype**
  The consumption/report page and its primary-navigation entry now gate on
  `aiprovider/datacurso:viewreports` instead of `moodle/site:config`, so any user with the
  manager role (not only admins) can reach AI usage reports.

### Removed
- **Webservice self-configuration**: the admin page, the `aiprovider/datacurso:configurews`
  capability, and the backend class that generated and managed a dedicated Moodle webservice
  token for the plugin.
- **Per-user AI credit token limits**: the `aiprovider_datacurso_userlimit` table, its admin
  page, and the associated external functions.
- **Per-service, per-action user allowlists**: `local_assign_ai`, `local_coursegen`,
  `local_datacurso_ratings`, `ratelimit_settings` and `report_lifestory` rate-limit classes, and
  their configuration fields and language strings.
- **Local per-window rate-limit usage tracking**: the `aiprovider_datacurso_rlimit` table and
  the DB-backed precheck/sync methods that read and wrote it.
- **Global admin license key and rate-limit settings**: removed from `settings.php`; both now
  live exclusively on the provider-instance configuration form.

### Fixed
- **Upgrade checkpoint `2025112706` no longer calls a removed class**
  The body called `webservice_config::upgrade_sync_ws_and_capabilities()` inside a try/catch
  that only caught `\Exception`, so a missing class raised an uncatchable `\Error` and aborted
  the upgrade. The savepoint number is retained; the body is now a no-op.
- **Upgrade step `2026082600` sweeps dead per-instance allowlist keys**
  The 4.5-line global config cleanup (`unset_config()`) could not reach per-instance settings,
  which live as JSON in `ai_providers.config`. This step strips the leftover
  `ratelimit_*_allowedusers*` / `*_coursecreators` / `*_activitycreators` / `*_courseanalysts` /
  `*_generalanalysts` keys from every Datacurso provider instance's stored configuration.
- **`ratelimiter` constructor arity mismatch**
  Merging the header-forwarding rate-limit model against the 5.0 instance-scoped constructor
  left two call sites passing zero arguments to a constructor that requires one
  (`ArgumentCountError`); both now pass the owning provider instance.

## 2.0.5

**Released on:** 2026-02-10

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Fixed
- **Fatal error when only course generation allowlist was considered**  
  Corrected the rate limit user check that previously only evaluated the
  `local_coursegen` course creator list, which could cause incorrect
  access validation or fatal errors when other services or actions were
  configured.

## Added
- **Service/action-specific allowlist handling**  
  Extended the rate limiter so each AI-enabled service can declare its own
  user allowlist per HTTP action path (for example, `/course/v2/start` vs
  `/resources/create-mod`), keeping the access rules for different actions
  completely independent.

## Changed
- **Centralised helpers and internal clean-up**  
  Introduced small internal helpers to map paths to configuration keys and
  to extract user ids from configuration, reducing duplication and making
  future changes easier to maintain.
- **Version bump**  
  Release version bumped to **2.0.5**.

## 2.0.4

**Released on:** 2026-01-29

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Fixed
- **Suppress developer debug warning when listing rate-limited users**  
  Updated the rate-limit user selector query to load all required name fields (`firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`) so that `fullname()` no longer triggers the developer `debugging()` warning when building the allowed users lists.


## 2.0.3

**Released on:** 2026-01-26

**Compatibility note:** This version is compatible **from Moodle 5.0 to Moodle 5.1**.

## Added
- **Configurable base URLs for DataCurso AI services**  
  Added support for configurable base URLs for both the **standard** and **EU-hosted** DataCurso AI services, allowing greater flexibility across environments.
- **Optional base URL parameters in constructors**  
  Updated service constructors to accept optional base URL parameters, enabling explicit overrides when needed.
- **CHANGE.md file for change history**  
  Added a new **CHANGE.md** file to maintain a clear, versioned history of changes and releases.


## Changed
- **Centralized base URL resolution via instance method**  
  Refactored base URL access to ensure the correct instance method is used when resolving the active base URL, improving consistency and maintainability.
- **Service initialization flow updated**  
  Adjusted internal initialization logic so all API requests correctly respect the configured base URL (standard or EU-hosted).
- **Version bump**  
  Release version bumped to **2.0.3**.


## 2.0.2

**Released on:** 2026-01-19

## Added

- **Enhanced webservice setup error logging.**  
Improved error reporting during webservice registration by including the original exception message, providing clearer diagnostics when the setup process fails.

## Changed

- **Improved boolean evaluation logic.**  
Adjusted the `is_for_ue` method to ensure proper and safe boolean comparison, preventing unintended conditional behavior.

## Fixed

- **Webservice setup debugging limitations.**  
Resolved an issue where webservice registration failures did not expose sufficient context, making troubleshooting difficult.

## Changed

- **Release bump to 2.0.2**  
Updated the plugin version and release metadata to **2.0.2** to reflect the included improvements and fixes.

## 2.0.1

**Released on:** 2025-12-15

**Compatibility note:** This version is compatible **only with Moodle 5.0 and 5.1**.

## Added

- **User-level AI credit usage limits.** 
Introduced functionality to control and restrict AI credit consumption on a per-user basis within the Datacurso provider.

## Changed

- **Support updated to Moodle 5.0.** 
The plugin now targets the **MOODLE_500_STABLE** branch and is aligned with Moodle 5.0 and 5.1

- **Updated core API and rate limiter logic.** 
Improved the `datacurso_api_base` class and rate limiter implementation to support user-specific credit limits.

- **Updated provider language strings.** 
Revised and improved provider strings for better clarity and consistency.

- **Code quality and linting improvements.** 
Fixed linting issues and applied coding standard adjustments in provider and form-related files.

## Fixed

- **String and character issues.** 
Resolved issues related to string definitions that could cause compilation or display problems.

- **General linting errors.** 
Addressed additional linter warnings and errors across the codebase.

## Changed

- **Release bump to 2.0.1** 
Updated the plugin release number to **2.0.1**.

---

_History below this point predates the 5.0 provider-instance / 4.5 rate-limiting port (2.1.0). It
is the 4.5 line's own changelog, preserved verbatim for audit purposes; its version numbers
(1.x.x) are a separate, now-retired numbering scheme._

## [1.4.3] - 2026-08-14

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Changed
- **Course creator API endpoint**
  The course generation service now points to `https://course-ai-v2.datacurso.com/api/v1` for both the standard and EU regions.
- **Default per-window rate limit**
  The rate limit configuration form now prefills the credit limit per service from `provider::get_default_window_limit()` instead of a flat `10` for every service, so each service starts with a sensible default based on its most expensive action.
- **Site URL in outgoing payloads**
  Requests to the Datacurso API now include `site_url` so the service can identify the originating site.

### Fixed
- **Service user cleanup on upgrade**
  The upgrade step that removes artifacts from the deprecated Datacurso webservice setup now also deletes the `datacursows` service account, instead of leaving it for a site administrator to remove manually.

## [1.4.2] - 2026-08-10

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Fixed
- **File upload from the Moodle file storage API**
  `upload_file()` declared a string path, but callers hold a `stored_file` and
  Moodle keeps files in the file storage API, so uploading a syllabus threw a
  `TypeError` before any request was made. It now takes the `stored_file`,
  copies its content to a temporary file for the request, and removes that copy
  even when the request fails. No caller changes were needed.

  This behaviour was added in 1.2.x and was lost when `MOODLE_405_STABLE` was
  merged into `dev`: that side was ahead everywhere else in the file, so its
  older `upload_file` was kept along with the rest.

### Added
- **Tests for the upload contract**
  Cover the signature and the behaviour that depends on it: the file argument
  type, the position of the extra parameters, the name and MIME type sent with
  the request, and the removal of the temporary copy on both success and
  failure.

## [1.4.2] - 2026-08-03

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Fixed
- **Upgrade no longer fails with "redissessionhandlerproblem"**
  The `2026071601` upgrade step called `delete_user()` on the legacy `datacursows` service account. `delete_user()` ends by calling `\core\session\manager::destroy_user_sessions()`, which makes lazily initialised session handlers such as Redis run `session_set_save_handler()` from within the upgrade; under CLI the output has already been sent at that point, so the handler could not be registered and the upgrade aborted before reaching its savepoint

### Changed
- **The upgrade step no longer removes the service user account**
  Cleanup is now limited to the artifacts the plugin owns (external service, role and registration config flags). The `datacursows` account is left in place for administrators to delete from the users management page

- **Version bump**
  Internal version bumped to **2026080300** and release version bumped to **1.4.2**

## [1.4.1] - 2025-07-25

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Changed
- **Image/no-image variants for all action identifiers** (PR #14)
  Replaced bare `create_activity_*` entries with `_image`/`_noimage` pairs in `get_actions()` so consumption reports distinguish between image-generating and non-image requests per activity type. Added `/course/execute_image` and `/course/execute_noimage` variants for course creation
- **40 new action entries with translations in all 7 supported languages**
  Added language strings for all image/noimage action variants in en, es, fr, de, id, pt_br, and ru
- **Version bump**
  Internal version bumped to **2026072500** and release version bumped to **1.4.1**

## [1.4.0] - 2025-07-25

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Added
- **19 Moodle activity type action identifiers** (PR #12)
  Registered `create_activity_*` identifiers in the provider's action catalog so consumption reports show specific activity types (assign, quiz, lesson, workshop, h5pactivity, scorm, feedback, choice, data, book, page, resource, url, folder, label, imscp, forum, glossary, wiki) instead of the generic "Generate activity or resource with AI"
- **Translations for activity type identifiers in all 7 supported languages**
  Added language strings for all 19 activity type action names in en, es, fr, de, id, pt_br, and ru

### Changed
- **Version bump**
  Internal version bumped to **2026072400** and release version bumped to **1.4.0**

### Fixed
- **Removed unnecessary ratelimiter routing for activity type identifiers**
  Cleaned up prefix routing in `ratelimiter.php` that was no longer needed for `create_activity_*` identifiers

## 1.3.0

**Released on:** 2026-07-23

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Removed
- **Removed the Datacurso webservice setup feature** (PR #11)  
  Deleted the "Datacurso webservice setup" admin page, its `aiprovider/datacurso:configurews` capability, the external service functions (`aiprovider_datacurso_webservice_setup`, `aiprovider_datacurso_webservice_regenerate_token`, `aiprovider_datacurso_webservice_get_status`), the `webservice_config` backend class and its AMD modules, and the related documentation and images.
- **Added an upgrade step to clean up legacy webservice artifacts**  
  On existing sites the upgrade removes the previously created service user, role, external service, token and stored registration config keys left by the old setup.

## 1.1.6

**Released on:** 2026-04-29

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Changed
- **Optimized admin settings loading path**  
  Added an `ADMIN->fulltree` guard in provider settings so heavy per-service user capability lookups are executed only when the Datacurso settings page is actually rendered.
- **Removed per-user allowlist controls from Datacurso rate limiting**  
  Deleted service-specific allowlist checks and settings so Moodle permissions are the only access control, while preserving existing per-service rate-limit enforcement.
- **Cleaned obsolete allowlist configuration and code paths**  
  Removed allowlist-only classes/strings and added an upgrade step to delete legacy allowlist config keys.

## 1.1.5

**Released on:** 2026-04-29

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Changed
- **Optimized ratelimit settings class lookup in admin settings**  
  Replaced dynamic class discovery per service with an explicit service-to-class map in the provider, reducing unnecessary autoload checks when loading plugin settings.
- **Added tests for ratelimit settings mapping**  
  Added PHPUnit coverage for known and unknown service ids to ensure class resolution remains predictable and maintainable.

## 1.0.10

**Released on:** 2026-02-10

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Fixed
- **Abstract ratelimit_settings caused fatal error**  
  Resolved a fatal error triggered when `ratelimit_settings` was treated as
  an abstract class while service-specific rate limit classes no longer
  extended it.

## Changed
- **Relaxed service binding for allowlist resolution**  
  Updated `ratelimit_settings::get_allowed_users_for_service()` to call the
  static `get_allowed_service_user_ids()` method only when it exists on the
  target service class, removing the hard requirement for inheritance and
  keeping services decoupled from the base helper.
- **Version bump**  
  Release version bumped to **1.0.10**.

## 1.0.9

**Released on:** 2026-02-10

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Fixed
- **Fatal error when only course generation allowlist was considered**  
  Corrected the rate limit user check that previously only evaluated the
  `local_coursegen` course creator list, which could cause incorrect
  access validation or fatal errors when other services or actions were
  configured.

## Added
- **Service/action-specific allowlist handling**  
  Extended the rate limiter so each AI-enabled service can declare its own
  user allowlist per HTTP action path (for example, `/course/v2/start` vs
  `/resources/create-mod`), keeping the access rules for different actions
  completely independent.

## Changed
- **Centralised helpers and internal clean-up**  
  Introduced small internal helpers to map paths to configuration keys and
  to extract user ids from configuration, reducing duplication and making
  future changes easier to maintain.
- **Version bump**  
  Release version bumped to **1.0.9**.

## 1.0.8

**Released on:** 2026-01-29

**Compatibility note:** This version is compatible **with Moodle 4.5 only**.

## Fixed
- **Suppress developer debug warning when listing rate-limited users**  
  Updated the rate-limit user selector query to load all required name fields (`firstnamephonetic`, `lastnamephonetic`, `middlename`, `alternatename`) so that `fullname()` no longer triggers the developer `debugging()` warning when building the allowed users lists.


## 1.0.7

**Released on:** 2026-01-26

**Compatibility note:** This version is compatible **from Moodle 4.5 to Moodle 5.1**.

## Added
- **Configurable base URLs for DataCurso AI services**  
  Added support for configurable base URLs for both the **standard** and **EU-hosted** DataCurso AI services, allowing greater flexibility across environments.
- **Optional base URL parameters in constructors**  
  Updated service constructors to accept optional base URL parameters, enabling explicit overrides when needed.

## Changed
- **Centralized base URL resolution via instance method**  
  Refactored base URL access to ensure the correct instance method is used when resolving the active base URL, improving consistency and maintainability.
- **Service initialization flow updated**  
  Adjusted internal initialization logic so all API requests correctly respect the configured base URL (standard or EU-hosted).
- **Version bump**  
  Release version bumped to **1.0.7**.


## 1.0.6

**Released on:** 2026-01-19

## Added

- **Enhanced webservice setup error logging.**  
Improved error reporting during webservice registration by including the original exception message, providing clearer diagnostics when the setup process fails.

## Changed

- **Improved boolean evaluation logic.**  
Adjusted the `is_for_ue` method to ensure proper and safe boolean comparison, preventing unintended conditional behavior.

## Fixed

- **Webservice setup debugging limitations.**  
Resolved an issue where webservice registration failures did not expose sufficient context, making troubleshooting difficult.

## Changed

- **Release bump to 1.0.6**  
Updated the plugin version and release metadata to **1.0.6** to reflect the included improvements and fixes.

## 1.0.5

**Released on:** 2025-12-04

 **Compatibility note:** This version is compatible **only with Moodle 4.5**.

## Fixed
- **Upgrade savepoint order corrected**  
  Reordered the upgrade savepoint to prevent upgrade failures related to the `aiprovider_datacurso_userlimit`

## 1.0.4

**Released on:** 2025-12-02

Fixed
- add missing capabilities and web service functions

## 1.0.3

**Released on:** 2025-12-02

 **Compatibility note:** This version is compatible **only with Moodle 4.5**.

## Added
- **Automated release workflow for the plugin.**  
  A new GitHub Actions workflow was added to streamline/automate Moodle plugin releases.
- **Support only for Moodle 4.5.**  
  Added `$plugin->supported` in `version.php` to declare Moodle 4.5 as the only supported version.

## Changed
- **Release bump to 1.0.3**  
  The plugin release number was updated to **1.0.3**.
