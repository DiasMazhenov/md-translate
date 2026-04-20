/* global mdtAdmin, jQuery */
(function ($) {
    'use strict';

    var langs = mdtAdmin.langs || {};
    var i18n  = mdtAdmin.i18n  || {};

    $(function () {

        // ---- Provider toggle ----
        $('input[name="mdt_api_provider"]').on('change', function () {
            $('.mdt-api-key-row').toggle($(this).val() === 'google_api');
        });

        // ---- Color picker ↔ text field sync ----
        $(document).on('input change', '.mdt-color-picker', function () {
            var target = $(this).data('target');
            $('#' + target).val( $(this).val() );
        });
        $(document).on('input', '.mdt-color-text', function () {
            var id     = $(this).attr('id');
            var picker = $('.mdt-color-picker[data-target="' + id + '"]');
            var val    = $(this).val().trim();
            // Only update picker for valid 6-digit hex values
            if ( /^#[0-9a-fA-F]{6}$/.test(val) ) {
                picker.val(val);
            }
        });

        // ---- Flush cache ----
        $('#mdt-flush-cache').on('click', function () {
            var $btn = $(this);
            var $msg = $('#mdt-flush-msg');
            $btn.prop('disabled', true).text(i18n.flushing);
            $.post(mdtAdmin.ajaxUrl, { action: 'mdt_flush_cache', nonce: mdtAdmin.nonce })
                .done(function (r) { $msg.text(r.success ? i18n.flushed : (r.data || 'Error')); })
                .fail(function ()  { $msg.text('Request failed.'); })
                .always(function () {
                    $btn.prop('disabled', false).text('Clear Translation Cache');
                    setTimeout(function () { $msg.text(''); }, 3000);
                });
        });

        // ---- Test translation ----
        $('#mdt-test-btn').on('click', function () {
            var text   = $('#mdt-test-input').val().trim();
            var target = $('#mdt-test-lang').val();
            var $res   = $('#mdt-test-result');
            if (!text) { $res.addClass('is-error').text('Please enter some text.'); return; }

            $(this).prop('disabled', true).text(i18n.testing);
            $res.removeClass('is-error').text('…');

            $.post(mdtAdmin.ajaxUrl, { action: 'mdt_test_translate', nonce: mdtAdmin.nonce, text: text, target: target })
                .done(function (r) {
                    r.success ? $res.removeClass('is-error').text(r.data) : $res.addClass('is-error').text(r.data || 'Failed');
                })
                .fail(function () { $res.addClass('is-error').text('Request failed.'); })
                .always(function () { $('#mdt-test-btn').prop('disabled', false).text('Translate'); });
        });

        // ================================================================
        // Glossary
        // ================================================================

        var $tbody   = $('#mdt-glossary-tbody');
        var $msg     = $('#mdt-glossary-msg');
        var $title   = $('#mdt-glossary-form-title');
        var filterLang = '';

        function langName(code) {
            return langs[code] ? langs[code] + ' (' + code.toUpperCase() + ')' : code.toUpperCase();
        }

        function loadGlossary() {
            $tbody.html('<tr><td colspan="5">Loading…</td></tr>');
            $.post(mdtAdmin.ajaxUrl, { action: 'mdt_glossary_list', nonce: mdtAdmin.nonce })
                .done(function (r) {
                    if (!r.success) { $tbody.html('<tr><td colspan="5">Error loading entries.</td></tr>'); return; }
                    var rows = r.data;
                    if (filterLang) {
                        rows = rows.filter(function (e) { return e.target_lang === filterLang; });
                    }
                    if (!rows.length) {
                        $tbody.html('<tr><td colspan="5"><em>No entries yet.</em></td></tr>');
                        return;
                    }
                    var html = '';
                    rows.forEach(function (e) {
                        var badges = '';
                        if (e.case_sensitive) { badges += '<span class="mdt-badge">Case</span>'; }
                        if (e.whole_word)     { badges += '<span class="mdt-badge">Whole word</span>'; }
                        html += '<tr data-id="' + e.id + '">' +
                            '<td>' + esc(e.source_text) + '</td>' +
                            '<td>' + esc(langName(e.target_lang)) + '</td>' +
                            '<td>' + esc(e.translated) + '</td>' +
                            '<td>' + badges + '</td>' +
                            '<td class="row-actions">' +
                                '<a class="mdt-edit-entry" data-id="' + e.id + '">Edit</a> | ' +
                                '<a class="mdt-delete-entry" data-id="' + e.id + '" style="color:#d63638">Delete</a>' +
                            '</td>' +
                        '</tr>';
                    });
                    $tbody.html(html);
                });
        }

        // Escape helper
        function esc(str) {
            return $('<div>').text(str).html();
        }

        // Load on page open
        if ($tbody.length) { loadGlossary(); }

        // Filter by language
        $('#mdt-glossary-filter-lang').on('change', function () {
            filterLang = $(this).val();
            loadGlossary();
        });

        // Save entry
        $('#mdt-glossary-save').on('click', function () {
            var src  = $('#mdt-g-source').val().trim();
            var lang = $('#mdt-g-lang').val();
            var trn  = $('#mdt-g-translated').val().trim();
            var cs   = $('#mdt-g-case').prop('checked') ? 1 : 0;
            var ww   = $('#mdt-g-whole').prop('checked') ? 1 : 0;
            var id   = parseInt($('#mdt-glossary-id').val(), 10) || 0;

            if (!src || !trn) { $msg.text('Source and translation are required.'); return; }

            $(this).prop('disabled', true).text(i18n.saving);

            $.post(mdtAdmin.ajaxUrl, {
                action:         'mdt_glossary_save',
                nonce:          mdtAdmin.nonce,
                id:             id,
                source_text:    src,
                target_lang:    lang,
                translated:     trn,
                case_sensitive: cs,
                whole_word:     ww
            }).done(function (r) {
                if (r.success) {
                    $msg.text(i18n.saved);
                    resetForm();
                    loadGlossary();
                    setTimeout(function () { $msg.text(''); }, 2500);
                } else {
                    $msg.text(r.data || 'Error');
                }
            }).always(function () {
                $('#mdt-glossary-save').prop('disabled', false).text('Save Entry');
            });
        });

        // Edit entry (populate form)
        $tbody.on('click', '.mdt-edit-entry', function () {
            var id = parseInt($(this).data('id'), 10);
            var $row = $(this).closest('tr');
            // Re-fetch entry list and find it
            $.post(mdtAdmin.ajaxUrl, { action: 'mdt_glossary_list', nonce: mdtAdmin.nonce })
                .done(function (r) {
                    if (!r.success) { return; }
                    var entry = r.data.find(function (e) { return e.id === id; });
                    if (!entry) { return; }
                    $('#mdt-glossary-id').val(entry.id);
                    $('#mdt-g-source').val(entry.source_text);
                    $('#mdt-g-lang').val(entry.target_lang);
                    $('#mdt-g-translated').val(entry.translated);
                    $('#mdt-g-case').prop('checked', entry.case_sensitive);
                    $('#mdt-g-whole').prop('checked', entry.whole_word);
                    $title.text('Edit Entry #' + entry.id);
                    $('#mdt-glossary-cancel').show();
                    $('html, body').animate({ scrollTop: $('.mdt-glossary-form').offset().top - 60 }, 300);
                });
        });

        // Delete entry
        $tbody.on('click', '.mdt-delete-entry', function () {
            if (!confirm(i18n.confirmDelete)) { return; }
            var id = parseInt($(this).data('id'), 10);
            $.post(mdtAdmin.ajaxUrl, { action: 'mdt_glossary_delete', nonce: mdtAdmin.nonce, id: id })
                .done(function (r) { if (r.success) { loadGlossary(); } });
        });

        // Cancel edit
        $('#mdt-glossary-cancel').on('click', resetForm);

        function resetForm() {
            $('#mdt-glossary-id').val(0);
            $('#mdt-g-source').val('');
            $('#mdt-g-translated').val('');
            $('#mdt-g-case').prop('checked', false);
            $('#mdt-g-whole').prop('checked', true);
            $title.text('Add Entry');
            $('#mdt-glossary-cancel').hide();
        }

    });

}(jQuery));
