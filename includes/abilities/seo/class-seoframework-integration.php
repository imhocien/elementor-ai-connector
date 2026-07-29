<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_SEOFramework_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string {
		return 'seoframework';
	}

	public function label(): string {
		return 'The SEO Framework';
	}

	public function is_active(): bool {
		return defined( 'THE_SEO_FRAMEWORK_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_DIR' );
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
				'desc' => 'Get a post\'s The SEO Framework metadata by { post_id } (title, description, canonical, noindex, nofollow, og_image, twitter_image).',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a term\'s The SEO Framework metadata by { term_id }.',
			),
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $manage,
				'desc' => 'Get The SEO Framework site settings.',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s The SEO Framework metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, og_image?, twitter_image? }. Only provided fields change.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a term\'s The SEO Framework metadata: { term_id, title?, description?, ... }.',
			),
		);
	}

	private function map(): array {
		return array(
			'title'         => '_genesis_title',
			'description'   => '_genesis_description',
			'canonical'     => '_tsf_canonical',
			'noindex'       => '_tsf_noindex',
			'nofollow'      => '_tsf_nofollow',
			'og_image'      => '_tsf_social_image_url',
			'twitter_image' => '_tsf_social_image_url',
		);
	}

	private function read_view( array $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			$val = $data[ $key ] ?? '';
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$out[ $field ] = ! empty( $val );
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
				$current[ $key ] = ! empty( $args[ $field ] ) ? '1' : '0';
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
		if ( function_exists( 'the_seo_framework' ) ) {
			$tsf = the_seo_framework();
			if ( is_object( $tsf ) && method_exists( $tsf, 'get_settings' ) ) {
				return array( 'settings' => $tsf->get_settings() );
			}
		}
		$opt = get_option( 'the_seo_framework_settings', array() );
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
