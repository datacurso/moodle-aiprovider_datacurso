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
