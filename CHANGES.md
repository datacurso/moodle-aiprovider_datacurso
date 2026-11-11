## [1.7.2-wp] - 2026-11-11

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

**Brings the security controls of `MOODLE_405_STABLE` that this branch lacked:** the provider "Enabled" switch (1.7.0), the separation of reading the reports from changing the configuration (AIP-SEC-003, 1.6.0) and the Jedomi call contract tests (1.7.0), adapted to the configuration per tenant of Workplace.

### Security
- **The AI clients respect the provider's "Enabled" switch, for the site and for each tenant**
  With the provider disabled in Site administration > AI > AI providers, `ai_services_api` and `ai_course_api` (through `datacurso_api_base`) refuse every AI request, upload and download of every tenant with the `providerdisabled` exception before a connection is opened, so no plugin that uses them reaches the AI services and no credit is consumed; the refusal is logged by the audit event as `refused` with that error code. On Workplace a tenant may also switch the provider off for itself with the new "Enabled for this tenant" field of its Datacurso AI settings (`provider_enabled` in the tenant configuration): the requests of its users are refused with `providerdisabled_tenant`, the core AI actions of its users return that message without calling the service and are logged as `refused`, and the other tenants keep working. The site switch always wins over the tenant one, and every change of the tenant switch is written to the config log naming the tenant. The shop client (`datacurso_api`) is not affected, so balance, consumption history and synchronisation keep working. The rules live in the new `\aiprovider_datacurso\local\provider_status` class. New strings `providerdisabled`, `providerdisabled_tenant`, `provider_enabled` and `provider_enabled_desc` in all the plugin's languages. Backed by `provider_disabled_test` (the five cases of `MOODLE_405_STABLE` plus `test_disabled_tenant_refuses_its_requests_and_other_tenants_keep_sending`, `test_site_switch_wins_over_an_enabled_tenant`, `test_a_tenant_without_a_saved_value_follows_the_site_switch` and `test_tenant_switch_changes_are_written_to_the_config_log`), `external_request_completed_test::test_disabled_provider_logs_refused_without_a_connection` and `test_core_ai_action_of_a_disabled_tenant_is_refused_and_logged`; `datacurso_api_base_test` and `datacurso_api_plugin_headers_test` now enable the provider, as on `MOODLE_405_STABLE`
- **Upgrade keeps existing sites sending**
  Until 1.7.1-wp the AI clients ignored the switch, so a site could use the suite plugins with the provider switched off in AI providers. The upgrade to 2026111101 switches the provider on when nobody ever changed its switch (no `config_log` row), so nothing stops working; a switch an administrator turned off stays off and now stops the AI requests. Turning the switch on also lets core AI offer the provider's actions where its placements are enabled, as with any enabled provider. No tenant value is written, so every tenant follows the site switch until it saves its own. Backed by `upgrade_test::test_upgrade_keeps_the_ai_clients_sending_when_nobody_touched_the_switch`, `test_upgrade_keeps_the_provider_off_when_an_administrator_switched_it_off` and `test_upgrade_keeps_an_enabled_provider_on`
- **Changing the configuration needs its own capability, per tenant (AIP-SEC-003)**
  The Datacurso AI settings page (license key, the new tenant switch and the per-service rate limits) and its tab in the reports page are now offered through `settings_tenant_form::can_manage()`: `aiprovider/datacurso:manageconfig` (`RISK_CONFIG`, granted to no archetype) or `moodle/site:config` as before. Holding only `aiprovider/datacurso:viewreports`, the read capability granted to managers, gives neither the tab nor the page. Saving moved from the page into `settings_tenant_form::save()`, which checks the capability again, so a reports-only user cannot post to it either, and always writes to the tenant of the current user, so nobody changes another tenant from it. Cancelling the form now returns to the reports page, which a user without `moodle/site:config` can open. Site administrators keep their access. Grant `aiprovider/datacurso:manageconfig` through a role override to the managers, including tenant managers, who must change the configuration of their tenant. Backed by `access_control_test` (`test_manageconfig_capability_is_declared`, `test_viewreports_only_user_cannot_manage_config`, `test_viewreports_only_user_cannot_save`, `test_manageconfig_user_can_save`, `test_site_administrator_can_save`, `test_save_switches_the_provider_for_the_tenant`, `test_manageconfig_user_changes_only_their_own_tenant` and `test_form_offers_the_tenant_switch_enabled_by_default`) and by the new scenarios of `tests/behat/access_control.feature`

### Added
- **Jedomi call contract on the AI services client**
  A call to `/jedomi/v1/chat/completions` is pinned to send `License-Key`, Jedomi's `X-RateLimit-*` headers when its limit is enabled, the pseudonymised `userid`, `site_id`, `lang`, `site_url` and, on Workplace, the `tenant_id` of the user, and no `timezone`, keeping `model` and `messages` as given; and the answers of plugins-ai are pinned to their exceptions: 400 `jedomi_invalid_model`, 429, 502 and 504 as `httperror` with the status, 403 without credit as `notenoughtokens` and 403 over the window limit as `error_ratelimit_exceeded`, never echoing the body. Backed by `jedomi_contract_test::test_chat_call_sends_the_headers_and_fields_plugins_ai_requires`, `test_chat_call_without_a_jedomi_limit_sends_no_rate_limit_headers` and `test_error_answers_become_provider_exceptions`

## [1.7.1-wp] - 2026-11-11

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

**Brings release 1.7.1 of `MOODLE_405_STABLE` (fixes of the third Mindfree report) to Workplace.** It also brings the user pseudonym of `MOODLE_405_STABLE` (`outbound_privacy`), as it is there. The provider "Enabled" switch (`providerdisabled`) of `MOODLE_405_STABLE` is not part of this branch and is not ported.

### Fixed
- **A site that ran the provider of MOODLE_405_STABLE gets the Workplace tables and columns**
  Moodle only runs the upgrade steps numbered above the installed version. A site that had the provider 1.7.0 (2026110400) or 1.7.1 (2026111000) of `MOODLE_405_STABLE` and then moved to this branch skipped the Workplace steps 2025121900 (table `aiprovider_datacurso_tenant_config`) and 2026100800 (fields `tenant_id` and `licence` of `aiprovider_datacurso_consumption`), and the consumption history failed with `Unknown column 'rbalias3.tenant_id' in 'WHERE'`. The new step 2026111001 creates whatever of them is missing and leaves a site that already has them as it is; when the fields are added, the mirrored rows that carry no tenant are emptied and pulled again on the next visit, as 2026100800 does. Its number is above 1.7.1 of `MOODLE_405_STABLE`, so a site on that release can move to this branch without a downgrade. Tests: `tests/upgrade_test.php`

### Changed
- **The AI services no longer receive the user's timezone where they do not use it (AIP-PRIV-001-R1)**
  Requests of the AI services client (`ai_services_api`) carried the user's `timezone` on every route, although the AI services read it only on their CV and job matching routes. It is now sent only on paths starting with `/cv/` or `/jobmatch/`; every other request and upload of that client leaves it out. The course service client (`ai_course_api`), which uses it, keeps receiving it, and `site_id`, `site_url`, `userid`, `lang` and `tenant_id` are sent as before. Backed by `timezone_minimisation_test::test_ai_services_request_omits_timezone`, `test_ai_services_upload_omits_timezone`, `test_cv_and_jobmatch_routes_keep_timezone` and `test_course_client_keeps_timezone`
- **The README documents the privacy of the requests**
  New "Privacy and audit" section: the audit event, the transport security, what the services receive, how the user pseudonym is computed and what changes when a site updates to it, how deletion of a user is handled today, what the token manager service keeps per user, the retention of the data at the services, where the European services are hosted, and the remote erase operation of the course service

### Removed
- **GitHub Actions workflows**
  The `.github` folder (the plugin CI workflow) is removed. CI runs on Jenkins, which publishes the required status check and blocks merges until every test and validation passes.

### Security
- **The user identifier sent to the Datacurso services is a pseudonym (AIP-PRIV-001-R1)**
  Every request to the AI services, the course service and the shop carried the raw Moodle user id as `userid`. It now carries a site-scoped pseudonym, as on `MOODLE_405_STABLE`: an HMAC-SHA256 of the id, truncated to 32 hexadecimal characters, keyed with a per-site random secret (`pseudonym_secret`) that the plugin generates at install and in the new upgrade step 2026111002, never sends to the services and never regenerates; it is not the site UUID sent as `site_id`. The token is stable per person and site, so the services keep their per-user accounting and rate limits. It is derived in one place per path: the base API client (`datacurso_api_base`, requests, uploads and downloads, where a numeric id handed by a consumer plugin is pseudonymised and a pseudonym it already derived is kept) and the core AI processors (`abstract_processor::query_ai_api`, which still resolves the tenant licence from the local user). `site_id`, `site_url`, `lang` and `tenant_id` are sent as before. New class `\aiprovider_datacurso\local\outbound_privacy` (`pseudonymise_userid()`, `is_pseudonym()`, `pseudonymise_userid_value()`, `get_pseudonym_secret()`, `resolve_pseudonym()`) and application cache `aiprovider_datacurso/pseudonyms`; the consumption synchronisation resolves a pseudonym back to the local user (and so to its tenant), keeps a shorter all-digit value as a user id (history written before this version) and stores 0 for anything else; the audit event never reverses a pseudonym to name a related user. There is no data migration: after the update every user has a new identifier at the services, so per-user consumption counters and rate-limit windows start again (the licence totals are kept), the consumption recorded under the old identifier stays in the shop history without a user, and the data the course service stored under the old identifier is not reached by the Moodle data erasure and export requests. The privacy metadata strings of both external locations describe the token. Backed by `outbound_privacy_test`, `datacurso_api_base_test::test_post_sends_pseudonymous_userid_and_keeps_site_fields`, `test_post_pseudonymises_caller_provided_numeric_userid` and `test_post_keeps_caller_provided_pseudonym`, `test_request_body_carries_pseudonymous_userid` in the three processor test classes, the pseudonym cases of `consumption_sync_test` and `external_request_completed_test`, and `upgrade_test`
- **Error logging no longer includes the remote body (AIP-PRIV-002)**
  When the AI service failed, the processors wrote up to 1000 characters of the upstream body to the developer debugging output, which may carry the prompt or upstream internals. They now log the HTTP status and the body size only, as on `MOODLE_405_STABLE`. Backed by `process_generate_text_test::test_error_debugging_omits_remote_body`, `test_generic_service_error_is_sanitized` and `test_non_json_error_body_is_not_surfaced`
- **Every external request is recorded in the Moodle log (AIP-SEC-005-R1)**
  The new event `\aiprovider_datacurso\event\external_request_completed` is logged once per request to a Datacurso service: requests, uploads and downloads of the AI services and course service clients, requests of the shop client, and the requests of the core AI actions, including the download of a generated image. It records the user, the context, the client, the service, the HTTP method, the endpoint path without its query string, the result (`success`, `failure`, or `refused` when nothing left the site, such as without a license key or with a service URL that is not `https`), the HTTP status and, when the request did not succeed, an error code. Its data is validated against an allowlist, so it never carries the license key, headers, payload, prompt, file names, response body, site URL, the user pseudonym or transport error text. The event is logged in the system context unless the plugin making the request passes its own context, and optionally the user the request is for, with the new `set_audit_context()` of the client; the core AI actions use the context of the action. A problem while logging is reported to developers and never breaks the request. New string `event_external_request_completed` in all the plugin's languages. Backed by `external_request_completed_test`
- **Requests to the Datacurso services only travel over verified HTTPS and never follow a redirect**
  The AI services and course service clients (`datacurso_api_base`, including file downloads), the shop client (`datacurso_api`) and the core AI actions now verify the certificate chain and the host name of the service, and refuse a redirect answer instead of following it, so the license key and the content of the users never reach another host or travel in clear. A redirect answer fails with `httperror` and its status. A service URL that is not `https` is refused before any connection with the new `insecuretransport` exception (in all the plugin's languages) and logged as `refused`; developers who run the services locally over plain HTTP can allow it with `$CFG->aiprovider_datacurso_allow_insecure_transport = true;` in `config.php`. The rules live in the new `\aiprovider_datacurso\local\transport_policy` class. Downloads now use the same transport as the other requests instead of `create_file_from_url()`, which followed redirects. Backed by `transport_hardening_test::test_ai_request_sends_hardened_curl_options`, `test_shop_request_sends_hardened_curl_options`, `test_ai_redirect_answer_is_not_followed`, `test_shop_redirect_answer_is_not_followed`, `test_http_base_url_is_refused_without_a_connection`, `test_http_base_url_is_allowed_with_the_developer_flag`, `test_require_secure_url_accepts_only_https_by_default`, `test_download_sends_hardened_curl_options`, `test_download_redirect_answer_is_not_followed`, `test_download_from_http_base_url_is_refused`, `test_core_ai_request_sends_hardened_guzzle_options`, `test_core_ai_redirect_answer_is_not_followed` and `test_core_ai_http_endpoint_is_refused`
- **Requests to the Datacurso services give up after a total timeout**
  Besides the 30 seconds allowed to connect, a request to the AI services or a core AI action now gives up after 300 seconds, a file download after 300 seconds and a shop request after 30 seconds, so a service that stops answering no longer holds a web server process until PHP's own limit. Backed by the `transport_hardening_test` option tests
- **The consumption report escapes the action and the service it shows**
  The action and service columns of the consumption history printed unknown values from the shop as HTML. They are now escaped, and the synchronisation strips from each record anything that is not part of a component name (service) or a route path (action) before storing it. Backed by `consumption_history_test::test_markup_in_action_and_service_is_escaped` and `consumption_sync_test::test_markup_in_service_and_action_is_stripped`
- **Deleting a user unlinks the consumption history already copied to the site**
  When a user is deleted, the records of that user in the local consumption history are now attributed to nobody (userid 0) and kept in the site totals, so the report no longer shows the name of the deleted person with the consumption, and the pseudonym of that user is dropped from the reverse map, so records synchronised later are not attributed to the account either. The observer is `\aiprovider_datacurso\local\outbound_privacy::user_deleted` (`db/events.php`). Backed by `outbound_privacy_test::test_deleted_user_consumption_rows_are_unlinked` and `test_deleted_user_token_no_longer_resolves`
- **The audit log records the refusals it was missing and no identifiers in paths**
  A shop request without a license key (of the tenant, or of the site) is now refused when the request is made, and logged as `refused` with its endpoint, instead of failing when the client is built. A file download without a license key is refused and logged instead of sending an empty key. Path segments that identify a record or a session (numbers, UUIDs, long hexadecimal strings and long tokens mixing letters and digits) are logged as `{id}`. The README now says that browser-side streams, such as the chat stream, do not pass through the server and are not logged. Backed by `transport_hardening_test::test_shop_without_license_key_is_refused_and_logged`, `test_download_without_license_key_is_refused`, `request_audit_test::test_normalise_endpoint` and `test_event_records_the_normalised_endpoint`; `datacurso_api_test::test_missing_license_key_raises_localized_exception` now expects the refusal on the request
- **Viewing the reports is flagged as a personal data risk**
  The capability `aiprovider/datacurso:viewreports` now declares `RISK_PERSONAL`, since the reports show the consumption of every user by name. It takes effect on the next upgrade of the plugin. The reports page title is now the language string `reportspagetitle`, in all the plugin's languages. Backed by `capabilities_test`

## [1.7.0-wp] - 2026-11-09

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Added
- **Each suite plugin is switched on and off from the provider, per tenant**
  The Datacurso plugins tab lists the suite plugins from a single list, `\aiprovider_datacurso\local\suite_plugins`, which `provider::get_services()` also reads without changing any service id, so rate limits and consumption keep working. The state of each plugin is kept in `plugin_enabled_<component>` per tenant, as the rate limits are: a tenant value is stored in `aiprovider_datacurso_tenant_config`, a tenant without one inherits the site default (`aiprovider_datacurso/plugin_enabled_<component>`), and a plugin with no saved state is enabled. Disabling a plugin in one tenant leaves every other tenant unchanged. Plugins read it with `\aiprovider_datacurso\local\plugin_settings::is_enabled('<component>', ?int $tenantid = null)`, where a null tenant is the tenant of the current user (`tenant_resolver`); on a site without tenancy only the site value is used. `set_enabled('<component>', bool $enabled, ?int $tenantid = null)` writes the site default without a tenant and the tenant value with one; every change is written to the config log (tenant changes as `plugin_enabled_<component> (tenant <id>)`).
- **Suite plugins stop their pages and web services while disabled**
  `plugin_settings::require_enabled('<component>', ?int $tenantid = null)` returns when the plugin is enabled for the tenant and otherwise throws a `moodle_exception`: `plugindisabled` for users without `moodle/site:config`, `plugindisabled_admin` with a link to the Datacurso plugins list for administrators. Suite plugins that use it require version 2026110901.
- **The plugins tab switches each plugin on and off in place, per tenant**
  The table shows Plugin, Description, Installed, Enabled, Settings and Link. The Enabled switch calls the new `aiprovider_datacurso_set_plugin_enabled` web service (`classes/external/set_plugin_enabled.php`, AJAX), which takes an optional `tenantid` and checks the same permission as the tenant configuration page (`moodle/site:config`, or the new `aiprovider/datacurso:manageconfig`). A tenant administrator changes only their own tenant; a site administrator chooses between the site default and their tenant with the Scope selector of the tab, and may name another tenant. Only installed suite plugins have a switch, and users who may only view the reports see the state without switches. New capability `aiprovider/datacurso:manageconfig`, granted to no archetype.
- **Each suite plugin has a settings page in the provider**
  `admin/plugin_settings.php?component=<component>[&tenantid=<id>]` has an "Enabled" field for the scope being edited. A suite plugin that ships `\<component>\aiprovider\settings_extension` implementing `\aiprovider_datacurso\local\plugin_settings_extension` adds its own fields; they are kept per site, so they are offered only on the site default, and the Settings column links to the page only there.
- **Jedomi is a provider service**
  `local_jedomi` is listed in the plugins tab and registered as a service of the provider with a single `default` action of 2 credits, `/jedomi/` resolves to it in the rate limiter, and the reports label the service as "Jedomi" and `/jedomi/v1/chat/completions` as "Generate Jedomi chat message".
- **Upgrade keeps the state of every plugin**
  The upgrade to 2026102900 copies the current "Enabled" value of each plugin that had one (`enableforumai`, `enableassignai`, `enabled` of Tutor AI, Ranking Activities AI and Jedomi, where for Jedomi only `0` disabled it) into the provider's site default, so no site changes state. Share Certificate AI is left out on Workplace: its `enableai` only switched the AI assistant of a tenant off and is kept by the plugin as `aiassistant_enabled`. It never writes tenant values: each suite plugin copies its own per-tenant values in its own upgrade with `set_enabled($component, $value, $tenantid)`. New strings in all the plugin's languages: `datacurso:manageconfig`, `generate_jedomi_chat`, `invalidsuiteplugin`, `plugindesc_jedomi`, `plugindisabled`, `plugindisabled_admin`, `pluginlink_title`, `pluginname_jedomi`, `pluginnotinstalled`, `pluginscope`, `pluginscope_othertenant`, `pluginscope_site`, `pluginscope_site_desc`, `pluginscope_tenant`, `pluginscope_tenant_desc`, `pluginsettings`, `pluginsettings_enabled`, `pluginsettings_enabled_desc`, `pluginsettings_saved` and `pluginsettings_title`. Backed by `suite_plugins_test`, `plugin_settings_test`, `plugin_settings_form_test`, `plugin_list_page_test`, `set_plugin_enabled_test`, `upgrade_test`, the Jedomi cases of `provider_test`, `ratelimiter_test` and `consumption_service_test`, and the Behat scenario `plugins_list.feature`.

## [1.6.4-wp] - 2026-10-26

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Changed
- **Every request also carries the build number of the plugin**
  Besides `X-Plugin-Name` and `X-Plugin-Version` (the release), the requests now send `X-Plugin-Build`, the number in `$plugin->version` of the calling plugin (for example `2026102602`). The services compare that whole number with the minimum they accept, so they do not have to read release names such as `1.6.3-wp`. `plugin_identity` builds the header from its `version` property.

## [1.6.3-wp] - 2026-10-26

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Added
- **Every request says which plugin makes it and its release**
  The Datacurso services need to know which plugin release is calling so that one that is too old can be told to update instead of failing in a confusing way. `datacurso_api_base` now sends `X-Plugin-Name` and `X-Plugin-Version` (the release in the plugin's `version.php`) with every request and file download, next to `License-Key`. A plugin that uses the client names itself with the new `$callercomponent` constructor argument (`local_coursegen` and `local_coursedynamicrules` do); a client that is not told sends no `X-Plugin-*` header, because the provider does not speak for the plugins that use it. The headers are built by the new `plugin_identity` class and `get_plugin_headers()` returns them.

### Changed
- **A plugin that is too old gets a clear message**
  When a service answers HTTP 426 the person now sees "update the plugin to version X" (`plugin_update_required`, naming the plugin, its release and the minimum) or "update the plugin to the latest version" (`plugin_version_required`, when no release was reported) instead of a generic HTTP error. The texts are in all the plugin's languages. What happened is read from the service's `error_code` (`PLUGIN_UPDATE_REQUIRED`), never from its text, by the new `upgrade_required_response` class.

## [1.6.2-wp] - 2026-10-19

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Changed
- **Course creator API endpoint for the European Union**
  Sites licensed in the European Union now reach the course generation service at `https://eu.course-ai-v2.datacurso.com/api/v1` instead of the standard endpoint, so their course creation requests are served by the EU deployment. Sites of other regions keep using `https://course-ai-v2.datacurso.com/api/v1`.

## [1.5.4-wp] - 2026-10-08

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Fixed
- **The consumption history is read per tenant**: the local mirror of the consumption history kept no tenant and no licence. Each administrator synced the history of the licence of their own tenant into the same table, and the history, the charts and their totals showed the consumption of every licence of the site together. A single watermark for the whole table also meant that the history of a licence with lower ids than another one was never synced. Each record now keeps the tenant of the user who consumed and the fingerprint of the licence it was synced with; every licence keeps its own watermark; and the history and the charts only show the consumption of the users of the tenant of the viewer paid with the licence that tenant uses now, so they add up to the balance of that licence. Consumption paid with an earlier licence of the tenant, or with the licence of another tenant, is not shown. Records without a user belong to the tenant that holds the licence.
- **The user filter of the charts only offers users of the tenant**: it searched every user of the site, so the administrator of a tenant could look up the names of the users of other tenants.

### Changed
- **Upgrade step `2026100800`** adds the tenant and licence columns to the consumption mirror and empties it: the rows already mirrored cannot be attributed to a tenant. The history is pulled again from the service the next time the history page is opened.
- **Privacy**: the tenant of the consumption record is declared to the privacy API.

## [1.5.3-wp] - 2026-10-07

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

Brings `MOODLE_405_STABLE` up to 1.5.3 (entries 1.5.0 to 1.5.3 below) into the Workplace branch.

### Changed
- **The region of the licence is resolved once per tenant**
  The source resolves the region once and keeps it in the site configuration, because a site has a single licence. A Workplace site has one licence per tenant, so a region kept for the whole site let the first tenant to reach the shop decide where every other tenant's requests went. The region is now kept per tenant, with the fingerprint of the licence that tenant uses (its own, or the site licence it falls back to), and the shop is asked with that same licence. A site without tenancy keeps it in the site configuration exactly as the source does. Saving the licence on the tenant configuration page drops the region kept for that tenant; saving the site licence drops it everywhere.
- **The provider's own actions use the tenant licence**
  Text generation, summaries and image generation sent the site licence in the `License-Key` header while the region was resolved with the tenant licence, so a tenant with its own licence spent the site's credits. They now send the licence of the user's tenant, falling back to the site licence.

### Fixed
- **Consumption table on sites upgrading from 1.4.3-wp**
  The source creates the consumption history table in an upgrade step numbered `2026090100`, lower than the `2026091601` that the Workplace branch had already reached, so a Workplace site upgrading from 1.4.3-wp skipped it and the history page failed reading a table that did not exist. A new step creates the table on those sites.
- **Configuration acceptance test**
  `tests/behat/configuration.feature` still drove the "Configuration" tab and the field names of the site-wide form that was replaced by the tenant configuration page. It now drives the tenant configuration page.

## [1.5.3] - 2026-10-05

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Fixed
- **A fresh install no longer prints a debugging message**
  The `service` field of the consumption table was declared as a required text column with an empty default, which XMLDB does not accept: every new install printed a debugging message, and `moodle-plugin-ci` stops the install of any plugin that depends on this one when it sees it. The empty default is gone. Sites that already have the table are unaffected, because the upgrade step created the field without a default.

## [1.5.2] - 2026-09-30

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Changed
- **The region of the licence is resolved once and kept**
  Which region serves a site was asked of the shop while building every API client, so each call to an AI service paid a round trip to `shop.datacurso.com` first, and a shop that did not answer took every service down with it although the services themselves were up. The region is a property of the licence, not of the request: it is now resolved once, kept with a fingerprint of the licence it belongs to, and asked for again only when that licence changes or after a week. On a site of the pilot the first resolution costs 1.4 seconds and every one after it costs a millisecond.
  When the region is already known and the shop cannot be reached, the known region is used and the service keeps working. When it is not known —a licence that has never reached the shop— the call still fails rather than guess: choosing a region wrongly would send the data of a site to a deployment it may not be allowed to reach.
  Saving the licence key drops the region resolved for the previous one, so a change of licence is seen at once instead of waiting for the answer to go stale. The week is there for the other case: a site whose region is moved between deployments without its key changing.
- **The privacy declaration names the whole chain**
  The declaration of the AI services said the request data reaches Datacurso and stopped there, although those services do not generate the answers themselves: they hand the request on to a large language model of a third party, today Google Gemini or OpenAI depending on the model, which may sit outside the European Union. The declaration now says so, and says what that provider does not receive: the identifier of the user, the identifier of the site and the timezone are stripped before the request leaves. It also stops claiming that every erasure request goes through support, which is no longer true of the AI tutor: the platform now asks the service to delete the conversations of a person or of a course on its own.

## [1.5.1-wp] - 2026-09-07

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Security
- **Upstream error responses are no longer shown to end users**
  When the AI service failed (HTTP error or transport failure), the processors returned the raw upstream body or the transport message as `errormessage`, which Moodle core displays in the editor and persists in `ai_action_register`. They now return the localized `httperror` / `serviceunavailable` messages; the raw detail is only written to developer debugging output.
- **Hardened image download fallback**
  When the AI service returns an image URL instead of inline base64, the plugin now only fetches it over `https`, from the same host as the AI service endpoint, with an allowed image extension (`png`, `jpg`, `jpeg`, `webp`), without following redirects, within 30 seconds and up to 10 MiB (declared length and streamed length). The downloaded bytes, like inline base64 images, must sniff as PNG, JPEG or WEBP before being stored, and the stored name is always `datacurso_image_<timestamp>.<ext>`. Contract note: images hosted on a third-party CDN are rejected by design; the service must return `b64_json` (already the preferred format). The public `download_file()` method of the image processor was removed and the request body (including the prompt) is no longer written to debugging output.
- **Shop client, API client and user lookup no longer leak internals**
  The shop API client raises localized exceptions (`invalidlicensekey`, `curlerror`, `httperror`, `jsondecodeerror`) carrying only the cURL error number or the HTTP status, never the request URL or the response body; the base API client stops writing response bodies to debugging output; and the `get_users` web service returns a localized message instead of the database error text.
- **AI service endpoint resolved once per request**
  The processors resolve the AI service endpoint (which performs a license check against the shop) a single time per request instead of two or three times, and a failure while pinning the image host now degrades to the localized invalid-image error instead of raising an exception after a successful AI response.
- **Base API client no longer exposes cURL error text**
  `datacurso_api_base` reports transport failures with the cURL error number only; the raw libcurl message (which may contain hostnames) is confined to developer debugging output.
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
  Strings for the single generic external location (`privacy:metadata:aiprovider_datacurso:*`) and for the rate-limit table removed in `2026062601` (`privacy:metadata:aiprovider_datacurso_rlimit*`) were removed in every language; `privacy:metadata:aiprovider_datacurso` is kept as the export root label.
- **Testable transport in the HTTP clients**
  `datacurso_api` and `datacurso_api_base` build their cURL wrapper through a `create_curl()` substitution point, and `user_service::get_users()` accepts an optional database instance, so error handling is covered by automated tests without network access.
- **Changelog correction for 1.4.3**
  The 1.4.3 "Service user cleanup on upgrade" entry described the opposite of what the code does; see the corrected entry below.

## [1.5.0] - 2026-09-03

**Compatibility note:** This version is compatible only with **Moodle 4.5**.

### Added
- **Consumption history built on Moodle Report Builder**
  The credit consumption history is now a native Moodle report backed by a local mirror table, with filters by user, service, action and date range, column sorting, pagination and downloads in standard formats (CSV, Excel, ODS). The mirror is refreshed when the page is opened, pulling only the records created after the last known consumption, so no scheduled task is required.
- **License key in the plugin configuration tab**
  The configuration tab now exposes the license key and shares the same setting as the native AI provider page, so saving it in either place updates the other.
- **Year filter and aggregated data in the general report**
  A year selector, defaulting to the current year, scopes the credit cards and every chart. The chart data is now produced by a dedicated aggregation endpoint.
- **Privacy coverage for the local consumption store**
  The per-user consumption mirror is declared in the plugin privacy metadata and is exported and deleted together with the rest of the user's personal data.
- **Automated test suite**
  PHPUnit coverage for the rate limiter, the service catalogue, the configuration form, the consumption service and its synchronisation, the AI processors and the privacy provider, plus Behat feature definitions for the four report tabs and the access control.

### Changed
- **Charts read from the local mirror with server-side aggregation**
  The general report no longer fetches the full history from the external service on every load; it reads pre-aggregated buckets from the local mirror, reducing the transferred payload substantially.
- **Default tab and tab order**
  Consumption history is now the landing tab and Configuration was moved to the last position.
- **Downloads export the whole filtered set**
  Exporting the consumption history returns every record of the filtered set instead of only the rows visible on the current page.
- **Removal of the legacy consumption history service**
  The previous custom history service, its output classes and the `get_all_consumption` endpoint were removed, superseded by the report and the aggregation endpoint.
- **Substitutable client in the consumption synchronisation**
  The synchronisation now builds its API client through a substitution point, so its payload mapping, incremental watermark and date handling are covered by automated tests without network access.
- **GitHub Actions workflow runs on manual dispatch only**
  The workflow is no longer triggered automatically on push and pull request.

### Fixed
- **System instruction never reached the model**
  The instruction configured by the administrator was stored under `action_{action}_instruction` but read under a different setting name that nothing ever wrote, so the configured text had no effect on generation or summarisation. The processor now derives the setting name from the action, matching what the settings page writes.
- **Configuration tab did not persist any change**
  Saving reloaded the page on the default tab and discarded every change, because the page URL dropped the tab parameter and the save branch never ran. The tab is now part of the page URL, so the form posts back to its own tab and the redirect returns to it.
- **Privacy export returned a single consumption record**
  Every row was written to the same export subcontext and overwrote the previous one, so only the last record survived. All records are now exported together, with readable dates.
- **Invalid validation message in the limits form**
  The limit, window and cost validations referenced a language string that does not exist in Moodle, emitting debugging notices on every rejected field. They now use a plugin string.
- **Connection failures were not handled**
  Only request exceptions were caught, so a dropped connection to the AI service escaped the graceful degradation path. Transfer-level failures are now caught and reported with their code and message.

## [1.4.3-wp] - 2026-08-14

**Compatibility note:** This version is compatible only with **Moodle Workplace 4.5**.

### Changed
- **Course creator API endpoint**
  The course generation service now points to `https://course-ai-v2.datacurso.com/api/v1` for both the standard and EU regions.
- **Default per-window rate limit**
  The rate limit configuration form now prefills the credit limit per service from `provider::get_default_window_limit()` instead of a flat `10` for every service, so each service starts with a sensible default based on its most expensive action.
- **Site URL in outgoing payloads**
  Requests to the Datacurso API now include `site_url` so the service can identify the originating site.

### Fixed
- **Missing database schema on a fresh install**
  The plugin shipped no `db/install.xml`, so `aiprovider_datacurso_tenant_config`
  was only ever created by an upgrade step. Sites that installed the plugin from
  scratch got no table at all, and the tenant settings page died with
  `La tabla "aiprovider_datacurso_tenant_config" no existe` the moment it read the
  licence key. The install file now defines the table, and an upgrade step creates
  it on sites that were installed while it was missing.
- **Leftover webservice setup tab in the report page**
  The report page still offered a "webservice setup" tab guarded by
  `aiprovider/datacurso:configurews`, a capability removed in 1.3.0 together with
  the page it linked to. The tab therefore never rendered, but the check made
  Moodle log `Capability "aiprovider/datacurso:configurews" was not found!` on
  every visit with debugging on. The dead block is gone.
- **Tenant resolution on sites without Workplace tenancy**
  Every entry point resolved the current tenant by calling `\tool_tenant\tenancy`
  directly, so any site that does not provide that plugin — a plain Moodle install,
  or the environment where the test suite runs — fatally failed the moment an API
  client was built. Tenant resolution now goes through
  `local\tenant_resolver`, which falls back to a single implicit tenant (`0`) when
  tenancy is unavailable, and tenant configuration then falls back to the site-wide
  values.
- **Service user cleanup on upgrade**
  The `2026071601` upgrade step removes the artifacts of the deprecated Datacurso webservice setup: the `datacursows` external service (which cascades to its tokens), the `datacursows` role and the `registration_*` configuration keys.

  > **Correction (1.5.1):** this entry originally stated that the upgrade step also deletes the `datacursows` service account. That is not what the code does. The account is deliberately left for the site administrator to delete from the users management page, because `delete_user()` cannot run inside an upgrade step (see the 2026-08-03 entry below, "The upgrade step no longer removes the service user account").

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

## [1.4.2-dev] - 2026-08-03

Interim build `2026080300`, labelled 1.4.2 at the time and superseded by the tagged 1.4.2 of 2026-08-10 above.

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

## [1.1.6-wp] - 2026-05-05

**Compatibility note:** This version is compatible **only with Moodle Workplace 4.5**

### 🔄 Changed

- Forward-ported Workplace branch updates and aligned plugin behavior with the Moodle 4.5 baseline
- Added and refined tenant-focused configuration flow, including tenant settings form support
- Restored Workplace navigation integration for plugin admin/report sections
- Updated language string sets across maintained locales for Workplace-specific admin/report labels
- **Optimized ratelimit settings class lookup in admin settings**
  Replaced dynamic class discovery per service with an explicit service-to-class map in the provider, reducing unnecessary autoload checks when loading plugin settings.

### ❌ Removed

- Removed legacy user token limits UI/build references that are no longer part of the Workplace flow

### ✅ Added

- **Tests for ratelimit settings mapping**
  Added PHPUnit coverage for known and unknown service ids to ensure class resolution remains predictable and maintainable.

### 🐞 Fixed

- Fixed report tabs flow to avoid redirect-after-output errors in admin pages
- Fixed language key ordering issues required by coding standards checks

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
