/**
 * LumiCode — Scanner Page JS v1.4.8
 * Handles: scan AJAX, apply, dismiss, modal, chips
 * Dismissed blocks now persist across refresh — they are included in scan
 * results with a dismissed:true flag, rendered greyed, and counted in chips.
 * Cr8v Stacks · cr8vstacks.com
 */
(function ($) {
    'use strict';

    var cfg = window.LumiCodeAdmin || {};

    $(document).ready(function () {
        if (!document.getElementById('lc-run-scan')) return;
        initScanner();
    });

    function initScanner() {
        var pending   = 0;
        var applied   = 0;
        var dismissed = 0;

        var $results     = $('#lc-results');
        var $summaryBar  = $('#lc-summary-bar');
        var $modal       = $('#lc-scan-modal');
        var $placeholder = $('#lc-results-placeholder');

        /* ── Scan button ─────────────────────────────────────── */
        $('#lc-run-scan').on('click', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            $modal.addClass('is-open');
            setStatus('', '');

            $.post(cfg.ajax_url, { action: 'lumicode_scan', nonce: cfg.nonce })
            .done(function (res) {
                $modal.removeClass('is-open');
                $btn.prop('disabled', false);

                if (!res || !res.success) {
                    setStatus(cfg.i18n.error || 'Request failed.', 'err');
                    return;
                }

                var rows = res.data || [];
                pending = applied = dismissed = 0;
                $results.empty();
                $placeholder.hide();

                if (!rows.length) {
                    setStatus(cfg.i18n.noResults || 'No unformatted code found.', 'ok');
                    $summaryBar.hide();
                    return;
                }

                rows.forEach(function (r) {
                    renderRow(r);
                    if (r.dismissed) {
                        dismissed++;
                    } else {
                        pending++;
                    }
                });

                refreshChips();
                $summaryBar.show();
            })
            .fail(function (xhr) {
                $modal.removeClass('is-open');
                $btn.prop('disabled', false);
                setStatus((cfg.i18n.error || 'Request failed.') + ' (HTTP ' + xhr.status + ')', 'err');
            });
        });

        /* ── Clear dismissed ─────────────────────────────────── */
        $('#lc-clear-dismissed').on('click', function () {
            if (!confirm(cfg.i18n.confirmClearDismissed || 'Show dismissed blocks again?')) return;
            var $btn = $(this);
            $.post(cfg.ajax_url, { action: 'lumicode_clear_dismissed', nonce: cfg.nonce })
            .done(function () {
                $btn.fadeOut(200);
                // Re-enable dismissed rows in current view
                $results.find('.lc-row.is-dismissed').each(function () {
                    $(this).removeClass('is-dismissed');
                    setRowStatus($(this), '', '');
                    dismissed = Math.max(0, dismissed - 1);
                    pending++;
                });
                refreshChips();
            });
        });

        /* ── Bulk accept all ─────────────────────────────────── */
        $('#lc-accept-all').on('click', function () {
            if (!confirm(cfg.i18n.confirmApplyAll || 'Apply to all pending?')) return;
            $results.find('.lc-row').not('.is-applied').not('.is-dismissed').each(function () {
                doApply($(this));
            });
        });

        /* ── Bulk dismiss all ────────────────────────────────── */
        $('#lc-dismiss-all').on('click', function () {
            $results.find('.lc-row').not('.is-applied').not('.is-dismissed').each(function () {
                doDismiss($(this));
            });
        });

        /* ── Per-row actions (delegated) ─────────────────────── */
        $results
            .on('click', '.lc-apply-btn',   function () { doApply($(this).closest('.lc-row')); })
            .on('click', '.lc-dismiss-btn', function () { doDismiss($(this).closest('.lc-row')); });

        /* ── Render row from <template> ──────────────────────── */
        function renderRow(r) {
            var tpl = document.getElementById('lc-row-tpl');
            if (!tpl) return;

            var fullCode = (r.raw_code || r.snippet || '').trim();

            var html = tpl.innerHTML
                .replace(/__FP__/g,      esc(String(r.fingerprint  || '')))
                .replace(/__PID__/g,     esc(String(r.post_id      || '')))
                .replace(/__EDIT__/g,    esc(r.edit_url   || '#'))
                .replace(/__TITLE__/g,   esc(r.post_title || '(no title)'))
                .replace(/__URL__/g,     esc(r.post_url   || '#'))
                .replace(/__CODE__/g,    esc(fullCode));

            var $row = $(html);
            if (r.lang) $row.find('.lc-lang-sel').val(r.lang);

            // If this row was already dismissed, render it greyed immediately
            if (r.dismissed) {
                $row.addClass('is-dismissed');
                setRowStatus($row, 'Dismissed', 'mute');
                // Hide action buttons for already-dismissed rows
                $row.find('.lc-apply-btn, .lc-dismiss-btn').prop('disabled', true).css('opacity', '0.3');
            }

            $results.append($row);
        }

        /* ── Apply ───────────────────────────────────────────── */
        function doApply($row) {
            if ($row.hasClass('is-dismissed') || $row.hasClass('is-applied')) return;
            var fp  = $row.data('fp');
            var pid = $row.data('pid');
            var lng = $row.find('.lc-lang-sel').val() || '';

            $row.find('.lc-apply-btn').prop('disabled', true);
            setRowStatus($row, cfg.i18n.applying || 'Applying…', 'wait');

            $.post(cfg.ajax_url, {
                action: 'lumicode_apply', nonce: cfg.nonce,
                post_id: pid, fingerprint: fp, lang: lng,
            })
            .done(function (res) {
                if (res && res.success) {
                    $row.addClass('is-applied');
                    setRowStatus($row, cfg.i18n.appliedSuccess || 'Applied successfully', 'ok');
                    applied++; pending = Math.max(0, pending - 1);
                } else {
                    setRowStatus($row, ((res && res.data) || 'Error'), 'err');
                    $row.find('.lc-apply-btn').prop('disabled', false);
                }
                refreshChips();
            })
            .fail(function () {
                setRowStatus($row, cfg.i18n.error || 'Request failed', 'err');
                $row.find('.lc-apply-btn').prop('disabled', false);
            });
        }

        /* ── Dismiss ─────────────────────────────────────────── */
        function doDismiss($row) {
            if ($row.hasClass('is-dismissed') || $row.hasClass('is-applied')) return;
            $row.addClass('is-dismissed');
            setRowStatus($row, cfg.i18n.dismissed || 'Dismissed', 'mute');
            $row.find('.lc-apply-btn, .lc-dismiss-btn').prop('disabled', true).css('opacity', '0.3');
            dismissed++; pending = Math.max(0, pending - 1);
            refreshChips();
            $.post(cfg.ajax_url, {
                action: 'lumicode_dismiss', nonce: cfg.nonce,
                post_id: $row.data('pid'), fingerprint: $row.data('fp'),
            });
        }

        /* ── Chip counters ───────────────────────────────────── */
        function refreshChips() {
            $('#lc-chip-pending').text(pending    + ' ' + (cfg.i18n.pendingCount || 'pending'));
            $('#lc-chip-applied').text(applied    + ' ' + (cfg.i18n.appliedCount || 'applied'));
            $('#lc-chip-dismissed').text(dismissed + ' ' + (cfg.i18n.dismissedCount || 'dismissed'));
            $('#lc-accept-all, #lc-dismiss-all').prop('disabled', pending === 0);
        }

        /* ── Status helpers ──────────────────────────────────── */
        function setRowStatus($row, msg, type) {
            var $s = $row.find('.lc-row-status');
            $s.removeClass('is-ok is-err is-wait is-mute is-visible');
            if (msg) $s.addClass('is-' + type + ' is-visible').text(msg);
        }

        function setStatus(msg, type) {
            $('#lc-scan-status')
                .removeClass('is-ok is-err is-wait')
                .addClass(type ? 'is-' + type : '').text(msg);
        }
    }

    function esc(s) { return $('<div>').text(String(s)).html(); }

})(jQuery);
