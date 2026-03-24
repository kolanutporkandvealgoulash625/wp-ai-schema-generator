<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$post_id      = $post->ID;
$is_saved     = ( $post->post_status !== 'auto-draft' && ! empty( $post->post_content ) );
$stored_type  = get_post_meta( $post_id, '_wasg_schema_type', true );
$stored_json  = get_post_meta( $post_id, '_wasg_schema_json', true );
$disabled     = get_post_meta( $post_id, '_wasg_schema_disabled', true );
$has_schema   = ! empty( $stored_json ) && ! $disabled;

$detected_type = $is_saved ? WASG_Schema_Detector::detect_type( $post_id ) : '';
$type_labels   = WASG_Schema_Detector::get_supported_types();

$settings = get_option( 'wasg_settings', array() );
$provider = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'gemini';
$has_ai   = false;
if ( $provider === 'ollama' ) {
    $has_ai = ! empty( $settings['ollama']['url'] );
} else {
    $has_ai = ! empty( $settings[ $provider ]['api_key'] );
}
?>

<div id="wasg-metabox" data-post-id="<?php echo esc_attr( $post_id ); ?>">

    <?php if ( ! $is_saved ) : ?>
        <p class="wasg-notice">💡 Save this post first to detect schema type.</p>

    <?php elseif ( $has_schema ) : ?>
        <!-- Schema is active -->
        <div class="wasg-active-schema">
            <p>✅ <strong>Schema Active:</strong>
                <?php echo isset( $type_labels[ $stored_type ] ) ? esc_html( $type_labels[ $stored_type ] ) : esc_html( $stored_type ); ?>
            </p>
            <a href="https://search.google.com/test/rich-results?url=<?php echo urlencode( get_permalink( $post_id ) ); ?>"
               target="_blank" class="button button-small" style="margin-bottom:8px; width:100%; text-align:center;">
                🔍 Validate with Google
            </a>
            <button type="button" id="wasg-regenerate" class="button button-small" style="width:100%; text-align:center;">
                🔄 Regenerate Schema
            </button>
            <button type="button" id="wasg-remove-schema" class="button button-small" style="width:100%; text-align:center; margin-top:4px; color:#dc3545;">
                🗑️ Remove Schema
            </button>
        </div>

        <div id="wasg-schema-preview" style="margin-top:10px;">
            <p><strong>Current JSON-LD:</strong></p>
            <textarea id="wasg-json-editor" rows="10" style="width:100%; font-family:monospace; font-size:11px;"><?php echo esc_textarea( $stored_json ); ?></textarea>
            <button type="button" id="wasg-save-edited" class="button button-primary button-small" style="width:100%; margin-top:5px;">
                💾 Save Edited Schema
            </button>
            <span id="wasg-edit-status"></span>
        </div>

    <?php else : ?>
        <!-- No schema yet -->
        <p><strong>Detected type:</strong>
            <?php echo isset( $type_labels[ $detected_type ] ) ? esc_html( $type_labels[ $detected_type ] ) : esc_html( $detected_type ); ?>
        </p>

        <p class="wasg-notice-info" style="font-size:12px; color:#666;">
            <?php if ( $has_ai ) : ?>
                AI will analyze content for accurate type detection and extract structured data (FAQ, steps, recipe).
            <?php else : ?>
                Using rule-based detection. <a href="<?php echo esc_url( admin_url( 'admin.php?page=wasg-settings' ) ); ?>">Add AI provider</a> for better accuracy.
            <?php endif; ?>
        </p>

        <?php if ( $has_ai ) : ?>
            <button type="button" id="wasg-generate-ai" class="button button-primary" style="width:100%;">
                🤖 Generate with AI
            </button>
        <?php endif; ?>

        <button type="button" id="wasg-generate-rules" class="button" style="width:100%; margin-top:5px;">
            ⚡ Quick Generate (No AI)
        </button>

        <span id="wasg-meta-status" class="wasg-status-text" style="display:block; margin-top:8px;"></span>

        <div id="wasg-preview-area" style="display:none; margin-top:10px;">
            <p><strong>Generated JSON-LD:</strong></p>
            <textarea id="wasg-json-editor" rows="10" style="width:100%; font-family:monospace; font-size:11px;"></textarea>
            <p id="wasg-detected-info" style="font-size:12px; color:#666;"></p>
            <button type="button" id="wasg-save-schema" class="button button-primary button-small" style="width:100%; margin-top:5px;">
                ✅ Save & Activate
            </button>
            <span id="wasg-save-status"></span>
        </div>
    <?php endif; ?>
</div>