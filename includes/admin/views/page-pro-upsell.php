<?php
/**
 * Placeholder for Pro-only features in this fork.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$emcp_upsell_feature = isset( $emcp_upsell_feature ) ? (string) $emcp_upsell_feature : __( 'This feature', 'emcp-tools' );
?>
<div class="emcp-pro-upsell" style="max-width:640px;margin:24px 0;padding:40px 32px;text-align:center;background:#fff;border:1px solid #e5e7eb;border-radius:10px;">
	<h2 style="margin:0 0 10px;font-size:22px;color:#1f2330;">
		<?php printf( esc_html__( '%s is not available in this version', 'emcp-tools' ), esc_html( $emcp_upsell_feature ) ); ?>
	</h2>
	<p style="margin:0 auto;max-width:480px;color:#6b7280;font-size:14px;line-height:1.6;">
		<?php esc_html_e( 'This feature has been removed in this fork. Contact the plugin author for custom development.', 'emcp-tools' ); ?>
	</p>
</div>
