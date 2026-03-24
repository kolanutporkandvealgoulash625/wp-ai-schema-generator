<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$settings   = get_option( 'wasg_settings', array() );
$provider   = isset( $settings['ai_provider'] ) ? $settings['ai_provider'] : 'gemini';
$auto_inj   = isset( $settings['auto_inject'] ) ? $settings['auto_inject'] : true;
$auto_det   = isset( $settings['auto_detect'] ) ? $settings['auto_detect'] : true;
$post_types = isset( $settings['post_types'] ) ? $settings['post_types'] : array( 'post', 'page' );
$org_name   = isset( $settings['org_name'] ) ? $settings['org_name'] : get_bloginfo( 'name' );
$org_url    = isset( $settings['org_url'] ) ? $settings['org_url'] : home_url();
$org_logo   = isset( $settings['org_logo'] ) ? $settings['org_logo'] : '';
$providers  = WASG_AI_Provider::get_providers_info();

$all_types = get_post_types( array( 'public' => true ), 'objects' );
?>

<div class="wrap">
    <h1>📋 AI Schema Generator — Settings</h1>

    <!-- Provider Table -->
    <div class="wasg-card" style="margin-top:20px;">
        <h2>AI Provider Comparison</h2>
        <table class="widefat" style="max-width:700px;">
            <thead><tr><th>Provider</th><th>Free Tier</th><th>Cost</th><th>Get Key</th></tr></thead>
            <tbody>
                <?php foreach ( $providers as $key => $info ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $info['name'] ); ?></strong></td>
                    <td><?php echo $info['free_tier'] ? '✅' : '❌'; ?></td>
                    <td><?php echo esc_html( $info['cost'] ); ?></td>
                    <td><a href="<?php echo esc_url( $info['key_url'] ); ?>" target="_blank">Get Key ↗</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description" style="margin-top:10px;">💡 AI is <strong>optional</strong>. Basic schema works without AI using rule-based detection. AI improves accuracy and extracts FAQ/HowTo/Recipe data.</p>
    </div>

    <!-- AI Config -->
    <div class="wasg-card" style="margin-top:20px;">
        <h2>AI Provider</h2>
        <table class="form-table">
            <tr>
                <th>Active Provider</th>
                <td>
                    <select id="wasg-provider">
                        <?php foreach ( $providers as $key => $info ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $provider, $key ); ?>>
                                <?php echo esc_html( $info['name'] ); echo $info['free_tier'] ? ' (Has free tier)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>

        <?php foreach ( $providers as $key => $info ) :
            $ps = isset( $settings[ $key ] ) ? $settings[ $key ] : array();
        ?>
        <div class="wasg-provider-config" data-provider="<?php echo esc_attr( $key ); ?>" style="<?php echo $key !== $provider ? 'display:none;' : ''; ?>">
            <h3><?php echo esc_html( $info['name'] ); ?></h3>
            <table class="form-table">
                <?php if ( $key === 'ollama' ) : ?>
                    <tr><th>Server URL</th><td><input type="url" class="regular-text wasg-ollama-url" value="<?php echo esc_attr( $ps['url'] ?? 'http://localhost:11434' ); ?>" /></td></tr>
                    <tr><th>Model</th><td><input type="text" class="regular-text wasg-ollama-model" value="<?php echo esc_attr( $ps['model'] ?? 'llama3.3' ); ?>" /></td></tr>
                <?php else : ?>
                    <tr><th>API Key</th><td>
                        <div style="display:flex;gap:10px;"><input type="password" class="regular-text wasg-api-key" value="<?php echo esc_attr( $ps['api_key'] ?? '' ); ?>" /><button type="button" class="button wasg-toggle-key">👁️</button></div>
                        <p class="description"><a href="<?php echo esc_url( $info['key_url'] ); ?>" target="_blank">Get key ↗</a></p>
                    </td></tr>
                    <tr><th>Model</th><td>
                        <select class="wasg-model"><?php foreach ( $info['models'] as $mk => $ml ) : ?><option value="<?php echo esc_attr( $mk ); ?>" <?php selected( $ps['model'] ?? '', $mk ); ?>><?php echo esc_html( $ml ); ?></option><?php endforeach; ?></select>
                    </td></tr>
                <?php endif; ?>
                <tr><th></th><td><button type="button" class="button wasg-test-btn" data-provider="<?php echo esc_attr( $key ); ?>">🔌 Test</button> <span class="wasg-test-result"></span></td></tr>
            </table>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Schema Settings -->
    <div class="wasg-card" style="margin-top:20px;">
        <h2>Schema Settings</h2>
        <table class="form-table">
            <tr>
                <th>Auto-Inject Schema</th>
                <td>
                    <label><input type="checkbox" id="wasg-auto-inject" <?php checked( $auto_inj ); ?> /> Automatically output JSON-LD in page &lt;head&gt;</label>
                    <p class="description">If disabled, schema is saved but not output. Useful if another SEO plugin handles output.</p>
                </td>
            </tr>
            <tr>
                <th>Enabled Post Types</th>
                <td>
                    <?php foreach ( $all_types as $pt ) : if ( in_array( $pt->name, array( 'attachment', 'revision', 'nav_menu_item', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_font_family', 'wp_font_face', 'wp_global_styles' ), true ) ) continue; ?>
                        <label style="display:block; margin-bottom:4px;">
                            <input type="checkbox" class="wasg-post-type" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, $post_types, true ) ); ?> />
                            <?php echo esc_html( $pt->labels->singular_name ); ?> <small>(<?php echo esc_html( $pt->name ); ?>)</small>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        </table>
    </div>

    <!-- Organization Info -->
    <div class="wasg-card" style="margin-top:20px;">
        <h2>Organization / Publisher Info</h2>
        <p class="description">Used in Article & Course schema as the publisher.</p>
        <table class="form-table">
            <tr><th>Organization Name</th><td><input type="text" id="wasg-org-name" class="regular-text" value="<?php echo esc_attr( $org_name ); ?>" /></td></tr>
            <tr><th>Website URL</th><td><input type="url" id="wasg-org-url" class="regular-text" value="<?php echo esc_attr( $org_url ); ?>" /></td></tr>
            <tr><th>Logo URL</th><td><input type="url" id="wasg-org-logo" class="regular-text" value="<?php echo esc_attr( $org_logo ); ?>" placeholder="https://example.com/logo.png" /></td></tr>
        </table>
    </div>

    <p class="submit" style="max-width:850px;">
        <button type="button" id="wasg-save-settings" class="button button-primary button-large">💾 Save Settings</button>
        <span id="wasg-save-status" style="margin-left:15px;"></span>
    </p>
</div>