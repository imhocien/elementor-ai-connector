<?php
/**
 * Plugin Name:       Elementor AI Connector
 * Plugin URI:        https://github.com/imhocien/elementor-ai-connector
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           1.0.0
 * Requires at least: 6.9
 * Tested up to:      6.9
 * Requires PHP:      8.1
 * Author:            Shahid Hussain
 * Author URI:        https://hocien.me
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       emcp-tools
 * Domain Path:       /languages
 *
 * This file is the bootstrap ONLY: plugin header, the legacy-rename guard,
 * constants, the Freemius SDK helper, the uninstall hook, and the entry point
 * that hands off to EMCP_Tools_Bootstrap. All feature logic lives in classes
 * under includes/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy coexistence guard.
 *
 * This plugin was renamed from the `elementor-mcp` folder/slug to `emcp-tools`.
 * On an existing site the old `elementor-mcp/elementor-mcp.php` plugin may still
 * be active alongside this one during the transition. All PHP symbols were
 * re-prefixed (EMCP_Tools_* / emcp_tools_*) so the two can coexist without
 * "cannot redeclare" fatals — but they would still both register the same MCP
 * abilities/server and share data. So while the old plugin is active we do NOT
 * boot: we snapshot its settings (admin only) and show a notice, then bail
 * before defining constants, initializing Freemius, or registering anything.
 */
require_once __DIR__ . '/includes/class-migration.php';

if ( EMCP_Tools_Migration::is_legacy_plugin_active() ) {
	// Snapshot the old plugin's settings into the new keys WHILE it's still
	// installed — once the user deletes it, its uninstall hook wipes them.
	if ( is_admin() ) {
		EMCP_Tools_Migration::migrate();
	}
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			echo wp_kses(
				__( '<strong>EMCP Tools:</strong> The previous &#8220;MCP Tools for Elementor&#8221; plugin (folder <code>elementor-mcp</code>) is still active. EMCP Tools has replaced it &mdash; please <strong>deactivate and delete</strong> the old plugin to finish the upgrade. Your settings and license carry over automatically. EMCP Tools stays paused until then.', 'emcp-tools' ),
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
			echo '</p></div>';
		}
	);
	// Bail before booting anything else (no constants, no Freemius, no abilities).
	return;
}

// Plugin constants.
define( 'EMCP_TOOLS_VERSION', '1.0.0' );
define( 'EMCP_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMCP_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'EMCP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

// Claim the WP\MCP namespace for our bundled MCP Adapter copy, at file-load —
// BEFORE any other plugin can autoload an adapter class. Other plugins bundle
// the adapter behind their own autoloaders (Rank Math SEO, …) and PHP allows
// only one class of a given name per request, so without this the namespace
// can shear across two adapter versions and the MCP session dies mid-request
// ("Session terminated", -32600). Lazy: registers a resolver, loads nothing.
require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
EMCP_Tools_Adapter_Bootstrap::preload_bundled_namespace();

/**
 * Minimal stub that matches the Freemius SDK's public API so existing
 * `can_use_premium_code()` / `is_premium()` calls work without loading
 * the full Freemius SDK. All methods return false/defaults — this fork
 * has no Pro edition and no license/upgrade flow.
 */
if ( ! class_exists( 'EMCP_Tools_FS_Stub' ) ) {
	class EMCP_Tools_FS_Stub {
		public function can_use_premium_code(): bool { return false; }
		public function is_premium(): bool { return false; }
		public function is__premium_only(): bool { return false; }
		public function is_paying(): bool { return false; }
		public function has_active_valid_license(): bool { return false; }
		public function has_affiliate_program(): bool { return false; }
		public function is_activation_mode(): bool { return false; }
		public function add_action( ...$args ): void {}
		public function add_filter( ...$args ): void {}
		public function get_upgrade_url(): string { return ''; }
		public function pricing_url(): string { return ''; }
		public function get_text_inline( ...$args ): string { return ''; }
		public function get_module_label( ...$args ): string { return ''; }
	}
}

if ( ! function_exists( 'emcp_tools_fs' ) ) {
	function emcp_tools_fs() {
		global $emcp_tools_fs;
		if ( ! isset( $emcp_tools_fs ) ) {
			$emcp_tools_fs = new EMCP_Tools_FS_Stub();
		}
		return $emcp_tools_fs;
	}
	emcp_tools_fs();
}

// Uninstall support (no Freemius).
require_once EMCP_TOOLS_DIR . 'includes/class-uninstaller.php';

/**
 * Stub — upgrade URL no longer used.
 */
function emcp_tools_upgrade_url(): string {
	return '';
}

// Hand off to the bootstrap (loads classes + wires hooks) once dependencies
// like Elementor are available.
require_once EMCP_TOOLS_DIR . 'includes/class-bootstrap.php';
add_action( 'plugins_loaded', array( 'EMCP_Tools_Bootstrap', 'boot' ), 20 );
