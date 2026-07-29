<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Loader {

	public static function register_hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_blocks' ), 20 );
	}

	public static function register_blocks(): void {
		$manifest = self::get_manifest();
		if ( empty( $manifest ) ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		if ( is_wp_error( $upload_dir ) ) {
			return;
		}

		foreach ( $manifest as $block_name ) {
			$block_dir = $upload_dir['basedir'] . '/emcp-blocks/' . sanitize_key( $block_name );
			$json_path = $block_dir . '/block.json';

			if ( ! file_exists( $json_path ) ) {
				continue;
			}

			$registered = register_block_type( $json_path );
			if ( $registered ) {
				$registered->editor_script = 'emcp-block-editor';
				$registered->style         = 'emcp-block-style';
			}
		}
	}

	private static function get_manifest(): array {
		$manifest = get_option( 'emcp_tools_block_manifest', array() );
		return is_array( $manifest ) ? $manifest : array();
	}

	public static function update_manifest( array $block_names ): bool {
		return update_option( 'emcp_tools_block_manifest', array_values( array_unique( $block_names ) ) );
	}

	public static function add_to_manifest( string $block_name ): bool {
		$manifest = self::get_manifest();
		if ( in_array( $block_name, $manifest, true ) ) {
			return true;
		}
		$manifest[] = $block_name;
		return self::update_manifest( $manifest );
	}

	public static function remove_from_manifest( string $block_name ): bool {
		$manifest = self::get_manifest();
		$index    = array_search( $block_name, $manifest, true );
		if ( false === $index ) {
			return true;
		}
		array_splice( $manifest, $index, 1 );
		return self::update_manifest( $manifest );
	}
}
