<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_SeoPress_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string {
		return 'seopress';
	}

	public function label(): string {
		return 'SEO Press';
	}

	public function is_active(): bool {
		return defined( 'SEOPRESS_VERSION' ) || defined( 'SEOPRESS_PRO_VERSION' );
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
				'desc' => 'Get a post\'s SEO Press metadata by { post_id } (title, description, canonical, noindex, nofollow, og_image, twitter_image, focus_keyword).',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a term\'s SEO Press metadata by { term_id }.',
			),
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $manage,
				'desc' => 'Get SEO Press site settings.',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s SEO Press metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, og_image?, twitter_image?, focus_keyword? }. Only provided fields change.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a term\'s SEO Press metadata: { term_id, title?, description?, ... }.',
			),
		);
	}

	private function map(): array {
		return array(
			'title'          => '_seopress_titles_title',
			'description'    => '_seopress_titles_desc',
			'canonical'      => '_seopress_robots_canonical',
			'noindex'        => '_seopress_robots_index',
			'nofollow'       => '_seopress_robots_follow',
			'og_image'       => '_seopress_social_fb_img',
			'twitter_image'  => '_seopress_social_twitter_img',
			'focus_keyword'  => '_seopress_analysis_target_kw',
		);
	}

	private function meta_keys(): array {
		return array_values( $this->map() );
	}

	private function read_view( array $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			$val = $data[ $key ] ?? '';
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$out[ $field ] = ! empty( $val ) && 'yes' === $val;
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
				$current[ $key ] = ! empty( $args[ $field ] ) ? 'yes' : '';
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
		foreach ( $this->meta_keys() as $key ) {
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
		foreach ( $this->meta_keys() as $key ) {
			$data[ $key ] = get_term_meta( $id, $key, true );
		}
		return array( 'term_id' => $id, 'seo' => $this->read_view( $data ) );
	}

	public function op_get_settings( array $args ): array {
		$opt = get_option( 'seopress_activated', array() );
		return array( 'settings' => is_array( $opt ) ? $opt : array() );
	}

	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$current = array();
		foreach ( $this->meta_keys() as $key ) {
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
		foreach ( $this->meta_keys() as $key ) {
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
