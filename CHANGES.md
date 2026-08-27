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
