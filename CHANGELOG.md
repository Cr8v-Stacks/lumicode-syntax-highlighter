# Changelog

All notable changes to LumiCode Syntax Highlighter will be documented in this file.

## 1.5.8 - 2026-06-15

### Added

- Smart DOM auto-detection: The plugin automatically scans parent containers of `<pre>` tags. If a block is nested inside custom layout wrappers containing pre-existing "Copy" buttons, headers, or decorative window dots (such as page builder widgets), the plugin's mockup window chrome is silently disabled to avoid visual conflicts.

## 1.5.7 - 2026-06-15

### Added

- Support for per-block overrides to disable or customize mockup window chrome (`data-chrome`, `data-titlebar`, `data-statusbar`, `data-copy-button`, `data-line-numbers` attributes or `lc-no-chrome` class).

### Fixed

- Improved layout isolation and font rendering for raw/unwrapped code blocks inside custom pages.

## 1.5.6 - 2026-05-18

### Maintenance

- Updated Plugin URI to use the public LumiCode preview page.
- Cleaned up package assets.
- Updated bundled Highlight.js to v11.11.1 and kept it served locally.
- Refined bundled asset loading.
- Refined code block handling across shortcode, scanner, block editor, and auto-enhance flows.
- Improved scanner behavior and output consistency.

## 1.5.3 - 2026-05-07

### Added

- Added frontend recognition for inline code-style elements: `<code>`, `<kbd>`, `<samp>`, and `<var>`.
- Added inline styling for those elements so examples like `service-row`, `service-row-1`, and similar tokens render clearly in paragraphs and lists.

### Changed

- Updated inline code styling to use a transparent background, `#1e40af` text color, and a matching blue border color scheme.
