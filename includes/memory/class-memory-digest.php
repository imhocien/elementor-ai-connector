<?php
/**
 * Memory Digest — builds and renders a digest of project memory for agent context.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds a snapshot of published memory entries plus recent changes.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Memory_Digest {

	/**
	 * Build a digest of all active memory guidance and recent changes.
	 *
	 * @since 3.7.0
	 *
	 * @return array{ memory: string[], changes: array[] }
	 */
	public static function build(): array {
		$guidance = class_exists( 'EMCP_Tools_Memory_Store' )
			? EMCP_Tools_Memory_Store::active_guidance()
			: array();

		$changes = class_exists( 'EMCP_Tools_Change_Log' )
			? array_slice( EMCP_Tools_Change_Log::all(), -10 )
			: array();

		return array(
			'memory'  => $guidance,
			'changes' => $changes,
		);
	}

	/**
	 * Render the digest as a markdown string for injection into agent context.
	 *
	 * @since 3.7.0
	 *
	 * @param array $digest A digest from build().
	 * @return string
	 */
	public static function render( array $digest ): string {
		$lines = array();

		$memory = isset( $digest['memory'] ) && is_array( $digest['memory'] ) ? $digest['memory'] : array();
		if ( ! empty( $memory ) ) {
			$lines[] = '## Project Memory';
			$lines[] = '';
			foreach ( $memory as $guidance ) {
				$lines[] = '- ' . str_replace( array( "\r\n", "\r", "\n" ), ' ', $guidance );
			}
		}

		$changes = isset( $digest['changes'] ) && is_array( $digest['changes'] ) ? $digest['changes'] : array();
		if ( ! empty( $changes ) ) {
			$lines[] = '';
			$lines[] = '### Recent Changes';
			$lines[] = '';
			foreach ( $changes as $change ) {
				$summary = isset( $change['summary'] ) ? (string) $change['summary'] : '';
				$domain  = isset( $change['domain'] ) ? (string) $change['domain'] : '';
				if ( '' !== $summary ) {
					$lines[] = '- [' . $domain . '] ' . $summary;
				}
			}
		}

		return implode( "\n", $lines );
	}
}
