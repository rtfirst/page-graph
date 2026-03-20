# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

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
