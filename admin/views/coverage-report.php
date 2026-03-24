<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$type_labels = WASG_Schema_Detector::get_supported_types();
?>

<div class="wrap">
    <h1>📋 AI Schema Generator — Coverage Report</h1>

    <div class="wasg-scanner-controls">
        <button type="button" id="wasg-scan-btn" class="button button-primary button-hero">🔍 Scan All Content</button>
        <span id="wasg-scan-status" class="wasg-status-text"></span>
    </div>

    <div id="wasg-coverage-summary" style="display:none;">

        <div class="wasg-stats-grid">
            <div class="wasg-stat-card wasg-stat-total">
                <span class="wasg-stat-number" id="wasg-total">0</span>
                <span class="wasg-stat-label">📄 Total Pages</span>
            </div>
            <div class="wasg-stat-card wasg-stat-has">
                <span class="wasg-stat-number" id="wasg-has-schema">0</span>
                <span class="wasg-stat-label">✅ Has Schema</span>
            </div>
            <div class="wasg-stat-card wasg-stat-missing">
                <span class="wasg-stat-number" id="wasg-no-schema">0</span>
                <span class="wasg-stat-label">❌ No Schema</span>
            </div>
        </div>

        <!-- Type Breakdown -->
        <div class="wasg-card" style="margin:20px 0;">
            <h3>Schema Type Breakdown</h3>
            <div id="wasg-type-breakdown"></div>
        </div>

        <!-- Bulk Actions for missing -->
        <div class="wasg-bulk-actions" id="wasg-bulk-area" style="display:none;">
            <button type="button" id="wasg-bulk-generate" class="button button-primary">
                ⚡ Bulk Generate Schema for All Missing Pages
            </button>
            <span id="wasg-bulk-status" class="wasg-status-text"></span>

            <div id="wasg-bulk-progress" style="display:none; margin-top:10px;">
                <div class="wasg-progress-track">
                    <div class="wasg-progress-fill" id="wasg-progress-fill"></div>
                </div>
                <span id="wasg-progress-text">0 / 0</span>
            </div>
        </div>

        <!-- Missing Schema Table -->
        <h3 id="wasg-missing-heading" style="display:none;">Pages Without Schema</h3>
        <table class="wp-list-table widefat fixed striped" id="wasg-missing-table" style="display:none;">
            <thead>
                <tr>
                    <th>Title</th>
                    <th style="width:100px;">Type</th>
                    <th style="width:120px;">Detected As</th>
                    <th style="width:80px;">Status</th>
                    <th style="width:100px;">Action</th>
                </tr>
            </thead>
            <tbody id="wasg-missing-body"></tbody>
        </table>

        <!-- Has Schema Table -->
        <h3 id="wasg-active-heading" style="display:none; margin-top:30px;">Pages With Active Schema</h3>
        <table class="wp-list-table widefat fixed striped" id="wasg-active-table" style="display:none;">
            <thead>
                <tr>
                    <th>Title</th>
                    <th style="width:100px;">Type</th>
                    <th style="width:120px;">Schema Type</th>
                    <th style="width:100px;">Validate</th>
                </tr>
            </thead>
            <tbody id="wasg-active-body"></tbody>
        </table>
    </div>
</div>