<?php
/**
 * Project Memory Store — CPT-backed storage for agent project memory entries.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and retrieves project memory entries via the `emcp_memory` CPT.
 *
 * Post status doubles as the trust gate: 'pending' (agent-proposed, default),
 * 'publish' (admin-approved and injected into agent context).
 *
 * @since 3.7.0
 */
class EMCP_Tools_Memory_Store {

	const POST_TYPE = 'emcp_memory';
	const META_CONTEXT = '_emcp_memory_context';

	/**
	 * Register the CPT. Hooked on `init`.
	 *
	 * @since 3.7.0
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => true,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'editor' ),
				'labels'              => array(
					'name'          => __( 'Project Memory', 'emcp-tools' ),
					'singular_name' => __( 'Memory Entry', 'emcp-tools' ),
				),
			)
		);
	}

	/**
	 * Insert a new memory entry.
	 *
	 * @since 3.7.0
	 *
	 * @param string $guidance The guidance text.
	 * @param string $trust    Post status: 'pending' (default) or 'publish'.
	 * @param array  $context  Optional metadata context.
	 * @return int|WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function add( string $guidance, string $trust = 'pending', array $context = array() ): int {
		$allowed = array( 'pending', 'publish', 'draft' );
		if ( ! in_array( $trust, $allowed, true ) ) {
			$trust = 'pending';
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => $trust,
				'post_title'  => wp_trim_words( $guidance, 20, '…' ),
				'post_content' => $guidance,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $context ) ) {
			update_post_meta( $post_id, self::META_CONTEXT, wp_slash( wp_json_encode( $context ) ) );
		}

		return (int) $post_id;
	}

	/**
	 * Get a single memory entry by ID.
	 *
	 * @since 3.7.0
	 *
	 * @param int $id The memory entry post ID.
	 * @return array|null { id, guidance, trust, context, created, updated } or null.
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$context = array();
		$raw     = get_post_meta( $id, self::META_CONTEXT, true );
		if ( ! empty( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				$context = $decoded;
			}
		}

		return array(
			'id'       => (int) $post->ID,
			'guidance' => (string) $post->post_content,
			'trust'    => (string) $post->post_status,
			'context'  => $context,
			'created'  => (string) $post->post_date,
			'updated'  => (string) $post->post_modified,
		);
	}

	/**
	 * List memory entries, optionally filtered by trust/status.
	 *
	 * @since 3.7.0
	 *
	 * @param string $trust Optional status filter ('pending', 'publish', 'draft').
	 * @return array[] Array of { id, guidance, trust, created }.
	 */
	public static function list( string $trust = '' ): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => '' !== $trust ? $trust : array( 'pending', 'publish', 'draft' ),
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );
		$out   = array();

		foreach ( $query->posts as $post ) {
			$out[] = array(
				'id'       => (int) $post->ID,
				'guidance' => (string) $post->post_content,
				'trust'    => (string) $post->post_status,
				'created'  => (string) $post->post_date,
			);
		}

		return $out;
	}

	/**
	 * Approve a memory entry (set status to 'publish').
	 *
	 * @since 3.7.0
	 *
	 * @param int $id The memory entry post ID.
	 * @return bool True on success.
	 */
	public static function approve( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			),
			true
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Reject a memory entry (set status to 'draft').
	 *
	 * @since 3.7.0
	 *
	 * @param int $id The memory entry post ID.
	 * @return bool True on success.
	 */
	public static function reject( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		$result = wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			),
			true
		);

		return ! is_wp_error( $result );
	}

	/**
	 * Trash a memory entry.
	 *
	 * @since 3.7.0
	 *
	 * @param int $id The memory entry post ID.
	 * @return bool True on success.
	 */
	public static function delete( int $id ): bool {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}

		return (bool) wp_trash_post( $id );
	}

	/**
	 * Get all published (approved) memory guidance strings.
	 *
	 * @since 3.7.0
	 *
	 * @return string[] Array of guidance texts.
	 */
	public static function active_guidance(): array {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		);

		$query = new WP_Query( $args );
		$out   = array();

		foreach ( $query->posts as $post ) {
			$guidance = trim( (string) $post->post_content );
			if ( '' !== $guidance ) {
				$out[] = $guidance;
			}
		}

		return $out;
	}
}
