<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_SureRank_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string {
		return 'surerank';
	}

	public function label(): string {
		return 'SureRank';
	}

	public function is_active(): bool {
		return defined( 'SURERANK_VERSION' ) || class_exists( 'SureRank\Plugin' );
	}

	protected function operations(): array {
		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		return array(
			'get-post-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a post\'s SureRank metadata by { post_id } (title, description, canonical).',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s SureRank metadata: { post_id, title?, description?, canonical? }. Only provided fields change.',
			),
		);
	}

	private function map(): array {
		return array(
			'title'       => 'title',
			'description' => 'description',
			'canonical'   => 'canonical',
		);
	}

	const META_KEY = '_surerank_meta';

	private function read_view( array $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			$val = $data[ $key ] ?? '';
			$out[ $field ] = is_scalar( $val ) ? (string) $val : $val;
		}
		$out['noindex']  = false;
		$out['nofollow'] = false;
		return $out;
	}

	private function apply( array $current, array $args ): array {
		foreach ( $this->map() as $field => $key ) {
			if ( ! array_key_exists( $field, $args ) ) {
				continue;
			}
			$current[ $key ] = is_scalar( $args[ $field ] ) ? (string) $args[ $field ] : $args[ $field ];
		}
		return $current;
	}

	public function op_get_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$raw  = get_post_meta( $id, self::META_KEY, true );
		$data = is_array( $raw ) ? $raw : array();
		foreach ( $this->map() as $field => $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				$data[ $key ] = get_post_meta( $id, '_surerank_' . $key, true );
			}
		}
		return array( 'post_id' => $id, 'seo' => $this->read_view( $data ) );
	}

	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$raw     = get_post_meta( $id, self::META_KEY, true );
		$current = is_array( $raw ) ? $raw : array();
		$merged  = $this->apply( $current, $args );
		update_post_meta( $id, self::META_KEY, $merged );
		return array( 'updated' => true, 'post_id' => $id, 'seo' => $this->read_view( $merged ) );
	}

	private function missing_or_not_found( string $field, int $id, string $what ): WP_Error {
		if ( ! $id ) {
			return new WP_Error(
				'missing_argument',
				sprintf(
					__( 'Missing required argument: %s.', 'emcp-tools' ),
					$field
				),
				array( 'status' => 400 )
			);
		}
		return new WP_Error(
			'not_found',
			sprintf(
				__( 'No %1$s with id %2$d.', 'emcp-tools' ),
				$what,
				$id
			),
			array( 'status' => 404 )
		);
	}
}
