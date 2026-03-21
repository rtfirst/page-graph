# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.5] - 2026-03-21

### Security

- Restrict `resolveSourcePages()` to `tt_content` and `tx_*` tables only, preventing arbitrary table access via sys_refindex
- Document implicit permission filtering on content element queries

## [1.0.4] - 2026-03-21

### Added

- Vendor update script (`Build/update-vendor.sh`) for reproducible force-graph updates
- CI vendor-version-check job to detect outdated force-graph versions
- Persistent widget state (layout mode, toggles) via localStorage
- Layout modes, references toggle, and persistent settings documented in Usage

### Changed

- Reduced node sizes for cleaner graph appearance
- Tuned force parameters (charge, link distance) for less node overlap
- Hover label now drawn on top of all nodes via `onRenderFramePost`

## [1.0.3] - 2026-03-20

### Fixed

- Content elements not displayed when toggle was active (`includeContent` was `false` in `Services.yaml`)

## [1.0.2] - 2026-03-20

### Added

- Unit tests for `PageGraphDataProvider` (10 test cases covering graph data, doktypes, CTypes, navigation links)
- Unit tests for `PageGraphWidget` (7 test cases covering JS/CSS paths, options, request guards, event data)
- PHPUnit configuration (`phpunit.xml`)
- Unit Tests job in CI pipeline (PHP 8.1–8.4 × TYPO3 12.4/13.4/14.0 matrix)
- `autoload-dev` and `phpunit` in `composer.json`
- Developer documentation for running tests

### Fixed

- Hover highlighting stopped working after simulation cooled down (~7 seconds)
- Documentation rendering failed on docs.typo3.org due to invalid `guides.xml` (wrong namespace and unsupported `<links>` element)

## [1.0.1] - 2026-03-20

### Fixed

- Uninitialized `$request` property causing potential fatal error
- `includeContent` option from Services.yaml was ignored
- Missing `BE_USER` null-check for CLI/test safety
- Missing JS null-guards for DOM elements
- RTE page links (`typolink_tag`) not detected in internal links view
- Duplicate parent-child navigation links inflating reference count
- Missing `clearCacheOnLoad` in ext_emconf.php
- Extension key corrected to `page_graph` for TER compatibility

## [1.0.0] - 2026-03-20

### Added

- Initial release
- Interactive force-directed graph visualization of the TYPO3 page tree
- Content element nodes with toggle visibility
- Hover highlighting with neighbor emphasis
- Click-to-select with info panel showing record details
- Pencil edit link opening TYPO3 backend record editor
- Real-time search filtering with golden highlight ring
- Light and dark mode support (TYPO3 12/13/14)
- ResizeObserver for responsive canvas sizing
- German translation
