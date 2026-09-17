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
