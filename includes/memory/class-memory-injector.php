<?php
/**
 * Memory Injector — hooks into the discovery context to inject memory guidance.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Injects the project memory digest into the agent discovery system prompt.
 *
 * Hooks the `emcp_tools_discovery_memory` filter (priority 10) to build a
 * digest via MemoryDigest and render it as a markdown block.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Memory_Injector {

	/**
	 * Wire the filter hook. Call during plugin bootstrap.
	 *
	 * @since 3.7.0
	 */
	public static function init(): void {
		add_filter( 'emcp_tools_discovery_memory', array( __CLASS__, 'inject' ), 10 );
	}

	/**
	 * Filter callback: build the memory digest and return rendered markdown.
	 *
	 * @since 3.7.0
	 *
	 * @param string $current Current value from other filters.
	 * @return string
	 */
	public static function inject( string $current ): string {
		if ( '' !== $current ) {
			return $current;
		}

		if ( ! class_exists( 'EMCP_Tools_Memory_Digest' ) ) {
			return '';
		}

		$digest = EMCP_Tools_Memory_Digest::build();
		if ( empty( $digest['memory'] ) && empty( $digest['changes'] ) ) {
			return '';
		}

		return EMCP_Tools_Memory_Digest::render( $digest );
	}
}
