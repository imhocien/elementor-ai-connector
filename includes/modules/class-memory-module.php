<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Memory_Module extends EMCP_Tools_Module {

	public function id(): string {
		return 'memory';
	}

	public function title(): string {
		return __( 'Project Memory', 'emcp-tools' );
	}

	public function description(): string {
		return __( 'Project Memory remembers site context across agent sessions so a connected agent stops guessing. Stores guidance (pending → human-approved → active), exposes 3 MCP tools (recall/remember/save-session-summary), and injects active memory into the discovery context.', 'emcp-tools' );
	}

	public function tier(): string {
		return 'free';
	}

	public function default_active(): bool {
		return true;
	}

	public function is_available(): bool {
		return true;
	}

	public function render_settings(): void {}

	public function register(): void {
		if ( class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			add_action( 'init', array( 'EMCP_Tools_Memory_Store', 'register_post_type' ) );
		}
		if ( class_exists( 'EMCP_Tools_Memory_Injector' ) ) {
			EMCP_Tools_Memory_Injector::init();
		}
	}

	public static function is_enabled(): bool {
		$active = (array) get_option( EMCP_Tools_Module::OPTION_ACTIVE, array() );
		return in_array( 'memory', $active, true );
	}
}
