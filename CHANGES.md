## [1.1.6-wp] - 2026-05-05

**Compatibility note:** This version is compatible **only with Moodle Workplace 4.5**

### 🔄 Changed

- Forward-ported Workplace branch updates and aligned plugin behavior with the Moodle 4.5 baseline
- Added and refined tenant-focused configuration flow, including tenant settings form support
- Restored Workplace navigation integration for plugin admin/report sections
- Updated language string sets across maintained locales for Workplace-specific admin/report labels

### ❌ Removed

- Removed legacy user token limits UI/build references that are no longer part of the Workplace flow

### 🐞 Fixed

- Fixed report tabs flow to avoid redirect-after-output errors in admin pages
- Fixed language key ordering issues required by coding standards checks
