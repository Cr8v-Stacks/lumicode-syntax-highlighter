/**
 * LumiCode TinyMCE Plugin
 * Registers the toolbar button that opens the Insert Code Block dialog.
 */
(function() {
    tinymce.PluginManager.add('lumicode_insert', function(editor) {
        var i18n = (window.LumiCodeTMCE && window.LumiCodeTMCE.i18n) || {};
        editor.addButton('lumicode_insert', {
            title: i18n.insertTitle || 'Insert LumiCode Block',
            text: i18n.insertText || '⚡ Code',
            icon: false,
            onclick: function() {
                // Signal our dialog JS to open
                if (typeof window.lumiCodeOpenDialog === 'function') {
                    window.lumiCodeOpenDialog(editor);
                }
            }
        });
    });
})();
