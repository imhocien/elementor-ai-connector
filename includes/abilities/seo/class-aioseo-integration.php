<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_AIOSEO_Integration extends EMCP_Tools_SEO_Integration {

	public function id(): string {
		return 'aioseo';
	}

	public function label(): string {
		return 'All in One SEO';
	}

	public function is_active(): bool {
		return defined( 'AIOSEO_FILE' ) || defined( 'AIOSEO_VERSION' );
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
				'desc' => 'Get a post\'s AIOSEO metadata by { post_id } (title, description, canonical, noindex, nofollow, og_image, twitter_image, focus_keyword).',
			),
			'get-term-seo'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Get a term\'s AIOSEO metadata by { term_id }.',
			),
			'get-settings'    => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_settings' ),
				'perm' => $manage,
				'desc' => 'Get AIOSEO site settings.',
			),
			'update-post-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_post_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a post\'s AIOSEO metadata: { post_id, title?, description?, canonical?, noindex?, nofollow?, og_image?, twitter_image?, focus_keyword? }. Only provided fields change.',
			),
			'update-term-seo' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_term_seo' ),
				'perm' => $edit_posts,
				'desc' => 'Update a term\'s AIOSEO metadata: { term_id, title?, description?, ... }.',
			),
		);
	}

	const META_KEY = '_aioseo_meta';

	private function map(): array {
		return array(
			'title'          => 'title',
			'description'    => 'description',
			'canonical'      => 'canonicalUrl',
			'noindex'        => 'noindex',
			'nofollow'       => 'nofollow',
			'og_image'       => 'ogImageUrl',
			'twitter_image'  => 'twitterImageUrl',
			'focus_keyword'  => 'keyphrases',
		);
	}

	private function read_view( \stdClass $data ): array {
		$out = array();
		foreach ( $this->map() as $field => $key ) {
			if ( 'focus_keyword' === $field ) {
				$kw = isset( $data->keyphrases->focus->keyphrase ) ? $data->keyphrases->focus->keyphrase : '';
				$out[ $field ] = (string) $kw;
				continue;
			}
			$val = $data->$key ?? '';
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$out[ $field ] = ! empty( $val );
			} elseif ( 'og_image' === $field || 'twitter_image' === $field ) {
				$out[ $field ] = is_string( $val ) ? $val : ( isset( $val[0]->url ) ? (string) $val[0]->url : '' );
			} else {
				$out[ $field ] = is_scalar( $val ) ? (string) $val : '';
			}
		}
		return $out;
	}

	private function apply( \stdClass $current, array $args ): \stdClass {
		foreach ( $this->map() as $field => $key ) {
			if ( ! array_key_exists( $field, $args ) ) {
				continue;
			}
			if ( 'focus_keyword' === $field ) {
				if ( ! isset( $current->keyphrases ) ) {
					$current->keyphrases = new \stdClass();
				}
				if ( ! isset( $current->keyphrases->focus ) ) {
					$current->keyphrases->focus = new \stdClass();
				}
				$current->keyphrases->focus->keyphrase = (string) $args[ $field ];
				continue;
			}
			if ( in_array( $field, array( 'noindex', 'nofollow' ), true ) ) {
				$current->$key = ! empty( $args[ $field ] );
			} elseif ( 'og_image' === $field || 'twitter_image' === $field ) {
				$current->$key = (string) $args[ $field ];
			} else {
				$current->$key = is_scalar( $args[ $field ] ) ? (string) $args[ $field ] : $args[ $field ];
			}
		}
		return $current;
	}

	public function op_get_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$raw = get_post_meta( $id, self::META_KEY, true );
		$obj = is_object( $raw ) ? $raw : new \stdClass();
		return array( 'post_id' => $id, 'seo' => $this->read_view( $obj ) );
	}

	public function op_get_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$raw = get_term_meta( $id, self::META_KEY, true );
		$obj = is_object( $raw ) ? $raw : new \stdClass();
		return array( 'term_id' => $id, 'seo' => $this->read_view( $obj ) );
	}

	public function op_get_settings( array $args ): array {
		if ( function_exists( 'aioseo' ) ) {
			$settings = aioseo()->options->getAll();
			return array( 'settings' => is_array( $settings ) ? $settings : array() );
		}
		return array( 'settings' => array() );
	}

	public function op_update_post_seo( array $args ) {
		$id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $id || ! get_post( $id ) ) {
			return $this->missing_or_not_found( 'post_id', $id, 'post' );
		}
		$raw      = get_post_meta( $id, self::META_KEY, true );
		$current  = is_object( $raw ) ? clone $raw : new \stdClass();
		$merged   = $this->apply( $current, $args );
		update_post_meta( $id, self::META_KEY, $merged );
		return array( 'updated' => true, 'post_id' => $id, 'seo' => $this->read_view( $merged ) );
	}

	public function op_update_term_seo( array $args ) {
		$id = isset( $args['term_id'] ) ? absint( $args['term_id'] ) : 0;
		if ( ! $id || ! get_term( $id ) ) {
			return $this->missing_or_not_found( 'term_id', $id, 'term' );
		}
		$raw      = get_term_meta( $id, self::META_KEY, true );
		$current  = is_object( $raw ) ? clone $raw : new \stdClass();
		$merged   = $this->apply( $current, $args );
		update_term_meta( $id, self::META_KEY, $merged );
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
