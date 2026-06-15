# Changelog

All notable changes to LumiCode Syntax Highlighter will be documented in this file.

## 1.5.7 - 2026-06-15

### Fixed

- Reworked container detection into a **two-pass system** to handle all wrapping scenarios generically:
  - **Pass 1** (up to 3 ancestor levels): detects containers with fake chrome — custom headers, titlebars, toolbars, or copy buttons not belonging to the plugin. Hides those elements and neutralizes the container plus any intermediate children between it and the `<pre>` (e.g. `.widget-code` inside `.widget-block`).
  - **Pass 2** (direct parent only): detects plain wrapper divs that contain nothing but the `<pre>` (e.g. `.arch-box`). Neutralizes their visual decoration so they don't envelop the plugin UI.
- Fixed previous regression where plain wrapper divs were wrongly neutralized regardless of whether they contained any fake chrome.
- Removed `[class*="dots"]` from the header-detection selector to prevent false positives on our own `.lc-pw-dots` elements.
- Traversal stops at the first fake-chrome match — no cascade neutralization of unrelated ancestors.
- Fixed `lc-neutralized` CSS: removed `margin`, `height`, `min-height`, `max-height`, and `cursor` resets. These are structural/layout properties that broke Elementor columns and flex layouts. The class now only strips visual decoration (background, border, shadow, border-radius, padding, overflow).

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
