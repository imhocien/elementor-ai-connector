<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_RankMath_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string {
		return 'rankmath';
	}

	public function label(): string {
		return 'Rank Math';
	}

	public function is_active(): bool {
		return defined( 'RANK_MATH_VERSION' );
	}

	protected function operations(): array {
		$edit_posts = static function (): bool {
			return current_user_can( 'edit_posts' );
		};
		$manage     = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		return array(
			'get-post-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a post\'s Rank Math SEO metadata by { post_id } (title, description, canonical, noindex, nofollow, og_image, twitter_image, focus_keyword).',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a term\'s Rank Math SEO metadata by { term_id }.',
			),
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $manage,
				'desc' => 'Get Rank Math site settings.',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s Rank Math SEO metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, og_image?, twitter_image?, focus_keyword? }. Only provided fields change.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a term\'s Rank Math SEO metadata: { term_id, title?, description?, ... }.',
			),
		);
	}

	private function map(): array {
		return array(
			'title'          => 'rank_math_title',
			'description'    => 'rank_math_description',
			'canonical'      => 'rank_math_canonical_url',
			'noindex'        => 'rank_math_robots',
			'nofollow'       => 'rank_math_robots',
			'og_image'       => 'rank_math_facebook_image_id',
			'twitter_image'  => 'rank_math_twitter_image_id',
			'focus_keyword'  => 'rank_math_focus_keyword',
		);
	}

	private function read_view( array $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			$val = $data[ $key ] ?? '';
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$robots = is_array( $val ) ? $val : array();
				if ( 'noindex' === $field ) {
					$out[ $field ] = in_array( 'noindex', $robots, true );
				} else {
					$out[ $field ] = in_array( 'nofollow', $robots, true );
				}
			} else {
				$out[ $field ] = is_scalar( $val ) ? (string) $val : $val;
			}
		}
		return $out;
	}

	private function apply( array $current, array $args ): array {
		foreach ( $this->map() as $field => $key ) {
			if ( ! array_key_exists( $field, $args ) ) {
				continue;
			}
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$robots = isset( $current[ $key ] ) && is_array( $current[ $key ] ) ? $current[ $key ] : array();
				if ( ! empty( $args[ $field ] ) ) {
					$robots[] = ( 'noindex' === $field ) ? 'noindex' : 'nofollow';
				} else {
					$robots = array_values(
						array_filter( $robots, function ( string $r ) use ( $field ): bool {
							return $r !== ( 'noindex' === $field ? 'noindex' : 'nofollow' );
						} )
					);
				}
				$current[ $key ] = array_unique( $robots );
			} else {
				$current[ $key ] = is_scalar( $args[ $field ] ) ? (string) $args[ $field ] : $args[ $field ];
			}
		}
		return $current;
	}

	public function op_get_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$data = array();
		foreach ( $this->map() as $field => $key ) {
			$data[ $key ] = get_post_meta( $id, $key, true );
		}
		return array( 'post_id' => $id, 'seo' => $this->read_view( $data ) );
	}

	public function op_get_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$data = array();
		foreach ( $this->map() as $field => $key ) {
			$data[ $key ] = get_term_meta( $id, $key, true );
		}
		return array( 'term_id' => $id, 'seo' => $this->read_view( $data ) );
	}

	public function op_get_settings( array $args ): array {
		$opt = get_option( 'rank_math', array() );
		return array( 'settings' => is_array( $opt ) ? $opt : array() );
	}

	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$current = array();
		foreach ( $this->map() as $field => $key ) {
			$current[ $key ] = get_post_meta( $id, $key, true );
		}
		$merged = $this->apply( $current, $args );
		foreach ( $merged as $key => $val ) {
			update_post_meta( $id, $key, $val );
		}
		return array( 'updated' => true, 'post_id' => $id, 'seo' => $this->read_view( $merged ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$current = array();
		foreach ( $this->map() as $field => $key ) {
			$current[ $key ] = get_term_meta( $id, $key, true );
		}
		$merged = $this->apply( $current, $args );
		foreach ( $merged as $key => $val ) {
			update_term_meta( $id, $key, $val );
		}
		return array( 'updated' => true, 'term_id' => $id, 'seo' => $this->read_view( $merged ) );
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
