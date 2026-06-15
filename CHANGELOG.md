# Changelog

All notable changes to LumiCode Syntax Highlighter will be documented in this file.

## 1.5.6 - 2026-05-18

### Added

- Added automatic container detection that hides redundant headers and copy buttons, and neutralizes styling (margins, padding, background, border, shadow, radius) on any purely wrapping parent containers to prevent CSS leaks.
- Stripped background styling on code elements and their descendants inside highlight wrapper boxes to ensure they transparently inherit from the active theme's code box container.

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
