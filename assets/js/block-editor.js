/**
 * LumiCode Gutenberg Block Editor
 */
(function(blocks, element, editor, components) {
    const { registerBlockType } = blocks;
    const { createElement: el, useState } = element;
    const { RichText, InspectorControls } = editor;
    const { PanelBody, TextControl, SelectControl, ToggleControl } = components;

    const i18n = window.LumiCodeBlockI18n || {};
    registerBlockType('lumicode/code-block', {
        title: i18n.title || 'LumiCode Block',
        icon: 'editor-code',
        category: 'formatting',
        attributes: {
            code:      { type: 'string', default: '' },
            language:  { type: 'string', default: '' },
            highlight: { type: 'string', default: '' },
            title:     { type: 'string', default: '' },
            collapse:  { type: 'boolean', default: false },
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const langs = [
                { label: i18n.autoDetect || 'Auto-detect', value: '' },
                { label: 'Bash', value: 'bash' },
                { label: 'C', value: 'c' },
                { label: 'C++', value: 'cpp' },
                { label: 'C#', value: 'csharp' },
                { label: 'CSS', value: 'css' },
                { label: 'Go', value: 'go' },
                { label: 'HTML', value: 'html' },
                { label: 'Java', value: 'java' },
                { label: 'JavaScript', value: 'javascript' },
                { label: 'JSON', value: 'json' },
                { label: 'Kotlin', value: 'kotlin' },
                { label: 'PHP', value: 'php' },
                { label: 'Python', value: 'python' },
                { label: 'Ruby', value: 'ruby' },
                { label: 'Rust', value: 'rust' },
                { label: 'SQL', value: 'sql' },
                { label: 'Swift', value: 'swift' },
                { label: 'TypeScript', value: 'typescript' },
                { label: 'XML', value: 'xml' },
            ];

            return [
                el(InspectorControls, { key: 'inspector' },
                    el(PanelBody, { title: i18n.settings || 'LumiCode Settings', initialOpen: true },
                        el(SelectControl, {
                            label: i18n.language || 'Language',
                            value: attributes.language,
                            options: langs,
                            onChange: (val) => setAttributes({ language: val }),
                        }),
                        el(TextControl, {
                            label: i18n.titleLabel || 'Title / filename (optional)',
                            value: attributes.title,
                            onChange: (val) => setAttributes({ title: val }),
                        }),
                        el(TextControl, {
                            label: i18n.highlight || 'Highlight lines (e.g. 3,5-7)',
                            value: attributes.highlight,
                            onChange: (val) => setAttributes({ highlight: val }),
                        }),
                        el(ToggleControl, {
                            label: i18n.collapsible || 'Collapsible',
                            checked: attributes.collapse,
                            onChange: (val) => setAttributes({ collapse: val }),
                        })
                    )
                ),
                el('div', { key: 'editor', className: 'lumicode-block-editor-wrap' },
                    attributes.language && el('span', { className: 'lumicode-block-editor-badge' }, attributes.language),
                    el('textarea', {
                        className: 'lumicode-block-editor-textarea',
                        value: attributes.code,
                        placeholder: i18n.placeholder || 'Paste your code here...',
                        spellCheck: false,
                        onChange: (e) => setAttributes({ code: e.target.value }),
                    })
                )
            ];
        },

        save: function() {
            // Server-side rendered
            return null;
        }
    });
})(
    window.wp.blocks,
    window.wp.element,
    window.wp.blockEditor,
    window.wp.components
);
