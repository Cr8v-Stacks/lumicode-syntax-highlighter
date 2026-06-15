# Changelog

All notable changes to LumiCode Syntax Highlighter will be documented in this file.

## 1.5.7 - 2026-06-15

### Fixed

- Fixed fake-chrome traversal regression: plain wrapper divs (e.g. `.arch`, layout containers) were being wrongly neutralized because the old algorithm stripped any parent that had no visible siblings alongside `<pre>`, regardless of whether that parent contained any fake chrome at all.
- Containers are now **only** neutralized when they actually contain fake chrome: custom headers, titlebars, toolbars, or copy buttons that are not part of the LumiCode UI itself.
- Intermediate child containers between the detected fake-chrome parent and the `<pre>` element (e.g. `.widget-code` inside `.widget-block`) are also neutralized, eliminating double-background and overflow CSS leaks from inner wrappers.
- Removed `[class*="dots"]` from the header-detection selector to prevent false positives.
- Traversal now stops as soon as the first fake-chrome container is found, preventing cascade neutralization of unrelated ancestors.

## 1.5.6 - 2026-05-18


### Added

- Added automatic container detection that hides redundant headers and copy buttons, and neutralizes styling (margins, padding, background, border, shadow, radius) on any purely wrapping parent containers to prevent CSS leaks.

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
