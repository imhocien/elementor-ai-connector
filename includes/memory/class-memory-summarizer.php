<?php
/**
 * Memory Summarizer — generates session summaries and topic rollups.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summarizes agent sessions and aggregates memory by topic.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Memory_Summarizer {

	/**
	 * Generate a concise summary of an agent session for storage.
	 *
	 * @since 3.7.0
	 *
	 * @param array $session_data { actions: int, tools_used: string[], duration: int, key_outcomes: string[] }.
	 * @return string
	 */
	public static function summarize_session( array $session_data ): string {
		$actions    = isset( $session_data['actions'] ) ? (int) $session_data['actions'] : 0;
		$tools      = isset( $session_data['tools_used'] ) && is_array( $session_data['tools_used'] ) ? $session_data['tools_used'] : array();
		$duration   = isset( $session_data['duration'] ) ? (int) $session_data['duration'] : 0;
		$outcomes   = isset( $session_data['key_outcomes'] ) && is_array( $session_data['key_outcomes'] ) ? $session_data['key_outcomes'] : array();

		$parts = array(
			sprintf(
				/* translators: %d: number of actions performed */
				__( 'Session: %d action(s)', 'emcp-tools' ),
				$actions
			),
		);

		if ( ! empty( $tools ) ) {
			$parts[] = __( 'Tools:', 'emcp-tools' ) . ' ' . implode( ', ', $tools );
		}

		if ( $duration > 0 ) {
			$parts[] = sprintf(
				/* translators: %d: duration in seconds */
				__( 'Duration: %d seconds', 'emcp-tools' ),
				$duration
			);
		}

		if ( ! empty( $outcomes ) ) {
			$parts[] = __( 'Outcomes:', 'emcp-tools' ) . ' ' . implode( '; ', $outcomes );
		}

		return implode( ' — ', $parts );
	}

	/**
	 * Aggregate memory entries by topic and return a summarized view.
	 *
	 * @since 3.7.0
	 *
	 * @return array[] Array of { topic: string, entries: int, latest: string }.
	 */
	public static function rollup(): array {
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			return array();
		}

		$entries = EMCP_Tools_Memory_Store::list( 'publish' );

		$buckets = array();
		foreach ( $entries as $entry ) {
			$guidance = $entry['guidance'] ?? '';
			$topic    = self::detect_topic( $guidance );
			if ( ! isset( $buckets[ $topic ] ) ) {
				$buckets[ $topic ] = array(
					'count'  => 0,
					'latest' => $entry['created'] ?? '',
				);
			}
			$buckets[ $topic ]['count']++;
			if ( $entry['created'] > $buckets[ $topic ]['latest'] ) {
				$buckets[ $topic ]['latest'] = $entry['created'];
			}
		}

		$out = array();
		foreach ( $buckets as $topic => $data ) {
			$out[] = array(
				'topic'   => $topic,
				'entries' => $data['count'],
				'latest'  => $data['latest'],
			);
		}

		usort( $out, function ( $a, $b ) {
			return $b['entries'] - $a['entries'];
		} );

		return $out;
	}

	/**
	 * Simple topic detection from guidance text.
	 *
	 * @since 3.7.0
	 *
	 * @param string $text The guidance text.
	 * @return string
	 */
	private static function detect_topic( string $text ): string {
		$topic_keywords = array(
			'design'    => array( 'design', 'style', 'color', 'typography', 'layout', 'brand' ),
			'content'   => array( 'content', 'copy', 'text', 'page', 'post', 'article' ),
			'structure' => array( 'structure', 'navigation', 'menu', 'header', 'footer' ),
			'functionality' => array( 'function', 'feature', 'plugin', 'widget', 'shortcode' ),
			'performance'   => array( 'performance', 'speed', 'cache', 'optimize' ),
			'seo'       => array( 'seo', 'meta', 'search', 'rank' ),
		);

		$lower = strtolower( $text );
		$scores = array();

		foreach ( $topic_keywords as $topic => $keywords ) {
			$score = 0;
			foreach ( $keywords as $kw ) {
				if ( false !== strpos( $lower, $kw ) ) {
					$score++;
				}
			}
			if ( $score > 0 ) {
				$scores[ $topic ] = $score;
			}
		}

		if ( ! empty( $scores ) ) {
			arsort( $scores );
			return (string) key( $scores );
		}

		return 'general';
	}
}
