/**
 * AI Schema Generator — Admin JavaScript
 */
(function ($) {
    'use strict';

    // ==========================================
    // SETTINGS PAGE
    // ==========================================

    $('#wasg-provider').on('change', function () {
        $('.wasg-provider-config').hide();
        $('[data-provider="' + $(this).val() + '"]').show();
    });

    $(document).on('click', '.wasg-toggle-key', function () {
        var $i = $(this).closest('td').find('.wasg-api-key');
        $i.attr('type', $i.attr('type') === 'password' ? 'text' : 'password');
        $(this).text($i.attr('type') === 'password' ? '👁️' : '🙈');
    });

    $(document).on('click', '.wasg-test-btn', function () {
        var $btn = $(this), p = $btn.data('provider'), $s = $btn.closest('.wasg-provider-config'), $r = $btn.siblings('.wasg-test-result');
        $btn.prop('disabled', true).text('⏳');
        $r.text('');
        var d = { action: 'wasg_test_connection', nonce: wasg_ajax.nonce, provider: p };
        if (p === 'ollama') { d.url = $s.find('.wasg-ollama-url').val(); d.model = $s.find('.wasg-ollama-model').val(); }
        else { d.api_key = $s.find('.wasg-api-key').val(); d.model = $s.find('.wasg-model').val(); }
        $.post(wasg_ajax.ajax_url, d).done(function (r) {
            $r.text(r.success ? '✅ ' + r.data : '❌ ' + r.data).css('color', r.success ? '#28a745' : '#dc3545');
        }).always(function () { $btn.prop('disabled', false).text('🔌 Test'); });
    });

    $('#wasg-save-settings').on('click', function () {
        var $btn = $(this); $btn.prop('disabled', true).text('⏳');
        var pts = []; $('.wasg-post-type:checked').each(function () { pts.push($(this).val()); });

        var d = {
            action: 'wasg_save_settings', nonce: wasg_ajax.nonce,
            ai_provider: $('#wasg-provider').val(),
            auto_inject: $('#wasg-auto-inject').is(':checked') ? '1' : '0',
            auto_detect: '1',
            post_types: pts,
            org_name: $('#wasg-org-name').val(),
            org_url: $('#wasg-org-url').val(),
            org_logo: $('#wasg-org-logo').val()
        };
        $('.wasg-provider-config').each(function () {
            var p = $(this).data('provider');
            if (p === 'ollama') { d.ollama_url = $(this).find('.wasg-ollama-url').val(); d.ollama_model = $(this).find('.wasg-ollama-model').val(); }
            else { d[p + '_api_key'] = $(this).find('.wasg-api-key').val(); d[p + '_model'] = $(this).find('.wasg-model').val(); }
        });
        $.post(wasg_ajax.ajax_url, d).done(function (r) {
            $('#wasg-save-status').text(r.success ? '✅ ' + r.data : '❌ ' + r.data).css('color', r.success ? '#28a745' : '#dc3545');
        }).always(function () { $btn.prop('disabled', false).text('💾 Save Settings'); setTimeout(function () { $('#wasg-save-status').text(''); }, 4000); });
    });

    // ==========================================
    // META BOX — Generate Schema
    // ==========================================

    // AI Generate
    $('#wasg-generate-ai, #wasg-regenerate').on('click', function () {
        var $btn = $(this), pid = $('#wasg-metabox').data('post-id');
        $btn.prop('disabled', true).text('⏳ AI analyzing...');
        $('#wasg-meta-status').text('AI is detecting content type and extracting data...');

        $.post(wasg_ajax.ajax_url, { action: 'wasg_generate_schema', nonce: wasg_ajax.nonce, post_id: pid })
            .done(function (r) {
                if (r.success) {
                    $('#wasg-json-editor').val(r.data.schema_json);
                    $('#wasg-detected-info').html(
                        '<strong>Detected:</strong> ' + esc(r.data.schema_type) +
                        ' (' + r.data.confidence + '% confidence)<br>' +
                        '<strong>Reason:</strong> ' + esc(r.data.reason)
                    );
                    $('#wasg-preview-area').show();
                    $('#wasg-meta-status').text('✅ Schema generated!');
                } else {
                    $('#wasg-meta-status').text('❌ ' + r.data).css('color', '#dc3545');
                }
            })
            .fail(function () { $('#wasg-meta-status').text('❌ Request failed').css('color', '#dc3545'); })
            .always(function () { $btn.prop('disabled', false).text('🤖 Generate with AI'); });
    });

    // Quick Generate (no AI)
    $('#wasg-generate-rules').on('click', function () {
        var $btn = $(this), pid = $('#wasg-metabox').data('post-id');
        $btn.prop('disabled', true).text('⏳');
        $('#wasg-meta-status').text('Generating with rule-based detection...');

        $.post(wasg_ajax.ajax_url, { action: 'wasg_bulk_generate', nonce: wasg_ajax.nonce, post_id: pid })
            .done(function (r) {
                if (r.success) {
                    // Reload the page to show active schema
                    location.reload();
                } else {
                    $('#wasg-meta-status').text('❌ ' + r.data).css('color', '#dc3545');
                }
            })
            .always(function () { $btn.prop('disabled', false).text('⚡ Quick Generate (No AI)'); });
    });

    // Save schema from editor
    $('#wasg-save-schema, #wasg-save-edited').on('click', function () {
        var $btn = $(this), pid = $('#wasg-metabox').data('post-id');
        var json = $('#wasg-json-editor').val();

        try { JSON.parse(json); } catch (e) { alert('Invalid JSON! Please fix syntax errors.\n\n' + e.message); return; }

        $btn.prop('disabled', true).text('⏳');

        // Detect type from JSON
        var parsed = JSON.parse(json);
        var stype = parsed['@type'] || 'Article';

        $.post(wasg_ajax.ajax_url, {
            action: 'wasg_save_schema', nonce: wasg_ajax.nonce,
            post_id: pid, schema_type: stype, schema_json: json
        })
        .done(function (r) {
            if (r.success) {
                $('#wasg-save-status, #wasg-edit-status').text('✅ ' + r.data).css('color', '#28a745');
                setTimeout(function () { location.reload(); }, 1000);
            } else {
                alert('Error: ' + r.data);
            }
        })
        .always(function () { $btn.prop('disabled', false).text('💾 Save'); });
    });

    // Remove schema
    $('#wasg-remove-schema').on('click', function () {
        if (!confirm('Remove schema markup from this page?')) return;
        var pid = $('#wasg-metabox').data('post-id');

        $.post(wasg_ajax.ajax_url, { action: 'wasg_remove_schema', nonce: wasg_ajax.nonce, post_id: pid })
            .done(function (r) {
                if (r.success) location.reload();
                else alert('Error: ' + r.data);
            });
    });

    // ==========================================
    // COVERAGE REPORT
    // ==========================================

    $('#wasg-scan-btn').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('⏳ Scanning...');
        $('#wasg-scan-status').text('Scanning all content...');

        $.post(wasg_ajax.ajax_url, { action: 'wasg_scan_coverage', nonce: wasg_ajax.nonce })
            .done(function (r) {
                if (r.success) { renderCoverage(r.data); $('#wasg-scan-status').text('Scan complete!'); }
                else { alert('Error: ' + r.data); }
            })
            .fail(function () { alert('Connection error.'); })
            .always(function () { $btn.prop('disabled', false).text('🔍 Scan All Content'); });
    });

    function renderCoverage(data) {
        $('#wasg-coverage-summary').show();
        $('#wasg-total').text(data.total);
        $('#wasg-has-schema').text(data.has_schema.length);
        $('#wasg-no-schema').text(data.no_schema.length);

        // Type breakdown bars
        var $bd = $('#wasg-type-breakdown').empty();
        var maxCount = 0;
        $.each(data.type_breakdown, function (t, c) { if (c > maxCount) maxCount = c; });

        $.each(data.type_breakdown, function (type, count) {
            var pct = maxCount > 0 ? Math.round((count / maxCount) * 100) : 0;
            $bd.append(
                '<div class="wasg-type-bar">'
                + '<span class="wasg-type-label">' + esc(type) + '</span>'
                + '<div class="wasg-type-fill" style="width:' + pct + '%;"></div>'
                + '<span class="wasg-type-count">' + count + '</span>'
                + '</div>'
            );
        });

        // Missing table
        if (data.no_schema.length > 0) {
            var $mb = $('#wasg-missing-body').empty();
            $.each(data.no_schema, function (i, item) {
                $mb.append(
                    '<tr data-id="' + item.id + '">'
                    + '<td><a href="' + esc(item.edit_url) + '">' + esc(item.title) + '</a></td>'
                    + '<td><small>' + esc(item.post_type) + '</small></td>'
                    + '<td><span class="wasg-badge wasg-badge-blue">' + esc(item.detected_type) + '</span></td>'
                    + '<td class="wasg-row-status"><span class="wasg-badge wasg-badge-red">Missing</span></td>'
                    + '<td><a href="' + esc(item.edit_url) + '" class="button button-small">✏️ Edit</a></td>'
                    + '</tr>'
                );
            });
            $('#wasg-missing-heading, #wasg-missing-table').show();
            $('#wasg-bulk-area').show();
        } else {
            $('#wasg-missing-heading, #wasg-missing-table, #wasg-bulk-area').hide();
        }

        // Active table
        if (data.has_schema.length > 0) {
            var $ab = $('#wasg-active-body').empty();
            $.each(data.has_schema, function (i, item) {
                var validateUrl = 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(item.url);
                $ab.append(
                    '<tr>'
                    + '<td><a href="' + esc(item.edit_url) + '">' + esc(item.title) + '</a></td>'
                    + '<td><small>' + esc(item.post_type) + '</small></td>'
                    + '<td><span class="wasg-badge wasg-badge-green">' + esc(item.stored_type) + '</span></td>'
                    + '<td><a href="' + validateUrl + '" target="_blank" class="button button-small">🔍 Validate</a></td>'
                    + '</tr>'
                );
            });
            $('#wasg-active-heading, #wasg-active-table').show();
        }
    }

    // Bulk generate
    $('#wasg-bulk-generate').on('click', function () {
        var $rows = $('#wasg-missing-body tr');
        var total = $rows.length, current = 0;
        if (total === 0) return;

        $(this).prop('disabled', true);
        $('#wasg-bulk-progress').show();

        function next() {
            if (current >= total) {
                $('#wasg-progress-text').text('✅ Done! ' + total + ' schemas generated.');
                $('#wasg-bulk-generate').prop('disabled', false);
                return;
            }

            var $row = $($rows[current]);
            var pid = $row.data('id');

            $.post(wasg_ajax.ajax_url, { action: 'wasg_bulk_generate', nonce: wasg_ajax.nonce, post_id: pid })
                .done(function (r) {
                    if (r.success) {
                        $row.addClass('wasg-row-done');
                        $row.find('.wasg-row-status').html('<span class="wasg-badge wasg-badge-green">✅ ' + esc(r.data.schema_type) + '</span>');
                    }
                })
                .always(function () {
                    current++;
                    var pct = Math.round((current / total) * 100);
                    $('#wasg-progress-fill').css('width', pct + '%');
                    $('#wasg-progress-text').text(current + ' / ' + total);
                    setTimeout(next, 200);
                });
        }
        next();
    });

    // ==========================================
    // UTILITY
    // ==========================================
    function esc(s) { if (!s) return ''; var d = document.createElement('div'); d.appendChild(document.createTextNode(s)); return d.innerHTML; }

})(jQuery);