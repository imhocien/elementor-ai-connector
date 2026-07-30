<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Store {

	const POST_TYPE = 'emcp_block';

	public static function user_has_access(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Custom Blocks', 'emcp-tools' ),
					'singular_name' => __( 'Custom Block', 'emcp-tools' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'rewrite'             => false,
				'capability_type'     => 'post',
				'capabilities'        => array(
					'create_posts' => 'manage_options',
				),
				'map_meta_cap'        => true,
				'supports'            => array( 'title', 'author' ),
			)
		);
	}

	public static function store( int $post_id, array $spec ): bool {
		$existing = get_post_meta( $post_id, '_emcp_block_spec', true );
		if ( false === $existing ) {
			return false !== add_post_meta( $post_id, '_emcp_block_spec', $spec, true );
		}
		return false !== update_post_meta( $post_id, '_emcp_block_spec', $spec );
	}

	public static function get( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}
		$spec = get_post_meta( $post_id, '_emcp_block_spec', true );
		if ( ! is_array( $spec ) ) {
			return null;
		}
		$spec['id']    = $post_id;
		$spec['title'] = $post->post_title;
		$spec['status'] = $post->post_status;
		return $spec;
	}

	public static function delete( int $post_id ): bool {
		$post = get_post( $post_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return false;
		}
		return false !== wp_delete_post( $post_id, true );
	}

	public static function list(): array {
		$query = new WP_Query( array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => array( 'draft', 'publish' ),
		) );

		$blocks = array();
		foreach ( $query->posts as $post ) {
			$spec = get_post_meta( $post->ID, '_emcp_block_spec', true );
			if ( ! is_array( $spec ) ) {
				continue;
			}
			$spec['id']     = $post->ID;
			$spec['status'] = $post->post_status;
			$blocks[]       = $spec;
		}

		return $blocks;
	}

	public static function write_block( int $post_id, array $spec ) {
		$block_json = EMCP_Tools_Block_Generator::generate( $spec );
		if ( '' === $block_json ) {
			return new WP_Error(
				'generation_failed',
				__( 'Block generation failed. Ensure name, title, and render_template are provided.', 'emcp-tools' )
			);
		}

		$parsed = json_decode( $block_json, true );
		if ( ! is_array( $parsed ) || empty( $parsed['name'] ) ) {
			return new WP_Error( 'invalid_json', __( 'Generated block.json is invalid.', 'emcp-tools' ) );
		}

		$upload_dir = wp_upload_dir();
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir;
		}

		$block_dir = $upload_dir['basedir'] . '/emcp-blocks/' . sanitize_key( $parsed['name'] );
		wp_mkdir_p( $block_dir );

		$block_json_path = $block_dir . '/block.json';
		$render_php_path = $block_dir . '/render.php';

		$bytes_json = file_put_contents( $block_json_path, $block_json );
		if ( false === $bytes_json ) {
			return new WP_Error( 'write_failed', __( 'Could not write block.json.', 'emcp-tools' ) );
		}

		$render_php = isset( $spec['render_template'] ) ? (string) $spec['render_template'] : '';

		if ( empty( trim( $render_php ) ) ) {
			$render_php = '<?php
if ( ! defined( "ABSPATH" ) ) {
	exit;
}
?><p>' . esc_html__( 'Hello from the block.', 'emcp-tools' ) . '</p>';
		}

		$bytes_php = file_put_contents( $render_php_path, $render_php );
		if ( false === $bytes_php ) {
			return new WP_Error( 'write_failed', __( 'Could not write render.php.', 'emcp-tools' ) );
		}

		self::store( $post_id, $spec );

		return $block_json;
	}
}
