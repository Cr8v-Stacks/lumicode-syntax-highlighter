# LumiCode – Syntax Highlighter for WordPress

**Version:** 1.1.0  
**Author:** [Cr8v Stacks](https://cr8vstacks.com)  
**Requires:** WordPress 5.8+, PHP 7.4+

---

## Installation

1. Upload the `lumicode-syntax-highlighter` folder to `/wp-content/plugins/`
2. Activate the plugin in **Plugins → Installed Plugins**
3. Visit **LumiCode → Settings** to choose your theme and configure features
4. (Optional) Go to **LumiCode → Code Scanner** to review and retrofit existing code blocks

That's it — no files to download, no libraries to install.  
highlight.js loads automatically via CDN and is widely cached across the web.

---

## Features

### ✅ CDN-Powered, Zero Setup
highlight.js and all themes load via the CDN — no manual file placement, no configuration. It just works.

### 🔍 Smart Code Scanner (Safe by Design)
- Scans all published posts and pages for unformatted `<pre>` code blocks.
- **Every block requires your explicit approval** — nothing is applied automatically.
- Each suggestion shows a code snippet preview and a detected language hint.
- **Accept** a block to apply LumiCode styling to that exact block only.
- **Dismiss** a block to permanently hide it from future scans (per user).
- **Accept All** / **Dismiss All** buttons for bulk decisions with confirmation.
- All applied changes go through WordPress post revisions — fully undoable.
- A unique fingerprint identifies each block so accepting one never touches others.

### 📋 Copy Button
- One-click copy with Clipboard API + textarea fallback for older browsers.
- "Copied!" animation that resets after 2 seconds.
- Keyboard accessible.

### 🎨 15+ Themes
Atom One Dark, Dracula, GitHub, Monokai, Nord, VS2015, Xcode, and more — all available instantly.

### 🔢 Line Numbers, Line Highlighting, Collapsible Blocks
Controlled globally in settings or per-block via shortcode attributes.

### 🖊️ Classic Editor (TinyMCE) Integration
A **⚡ Code** button appears in the Classic Editor toolbar. Clicking it opens a modal where you pick the language, optionally add a title and line highlights, paste your code, and insert a properly-formatted `[lumicode]` shortcode — no typing required.

### 🧱 Gutenberg Block
Search for **LumiCode Block** in the block inserter. Full sidebar controls for language, title, line highlighting, and collapsibility.

---

## Shortcode Usage

```
[lumicode lang="php" highlight="3,5-7" title="config.php" collapse="false"]
<?php
// Your code here
echo "Hello World!";
[/lumicode]
```

**Attributes:**
- `lang` — language for syntax highlighting (php, javascript, python, etc.)
- `highlight` — comma-separated lines or ranges to highlight (e.g. `3,5-7`)
- `title` — filename or label shown in the header bar
- `collapse` — `true` to make the block collapsible

---

## License

GPL-2.0+  
© Cr8v Stacks — https://cr8vstacks.com
