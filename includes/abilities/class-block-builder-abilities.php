<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Builder_Abilities {

	private $ability_names = array();

	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function register(): void {
		$tools = array(
			'create-custom-block' => array(
				'label'       => __( 'Create Custom Block', 'emcp-tools' ),
				'description' => __( 'Create a custom Gutenberg block from a structured spec. Creates as draft. Arguments: { spec: { name, title, description?, icon?, category?, attributes?, render_template (PHP), supports? } }.', 'emcp-tools' ),
				'callback'    => array( $this, 'execute_create' ),
				'readonly'    => false,
			),
			'list-custom-blocks'  => array(
				'label'       => __( 'List Custom Blocks', 'emcp-tools' ),
				'description' => __( 'List custom Gutenberg blocks created by the Block Builder. Optional: { status filter }.', 'emcp-tools' ),
				'callback'    => array( $this, 'execute_list' ),
				'readonly'    => true,
			),
			'get-custom-block'    => array(
				'label'       => __( 'Get Custom Block', 'emcp-tools' ),
				'description' => __( 'Get a custom block\'s spec by ID. Arguments: { block_id (int, required) }.', 'emcp-tools' ),
				'callback'    => array( $this, 'execute_get' ),
				'readonly'    => true,
			),
			'update-custom-block' => array(
				'label'       => __( 'Update Custom Block', 'emcp-tools' ),
				'description' => __( 'Update a custom block\'s spec and regenerate block files. Arguments: { block_id (int, required), spec (object, required) }.', 'emcp-tools' ),
				'callback'    => array( $this, 'execute_update' ),
				'readonly'    => false,
			),
			'delete-custom-block' => array(
				'label'       => __( 'Delete Custom Block', 'emcp-tools' ),
				'description' => __( 'Delete a custom block and remove its files. Requires confirm:true. Arguments: { block_id (int, required), confirm (bool, required) }.', 'emcp-tools' ),
				'callback'    => array( $this, 'execute_delete' ),
				'readonly'    => false,
			),
		);

		foreach ( $tools as $name => $config ) {
			$ability_name = 'emcp-tools/' . $name;

			$annotations = array(
				'readonly'    => $config['readonly'],
				'destructive' => ( 'delete-custom-block' === $name ),
				'idempotent'  => ( 'list-custom-blocks' === $name || 'get-custom-block' === $name ),
			);

			if ( in_array( $name, array( 'list-custom-blocks', 'get-custom-block' ), true ) ) {
				$perm = array( $this, 'can_read' );
			} else {
				$perm = array( $this, 'can_write' );
			}

			emcp_tools_register_ability(
				$ability_name,
				array(
					'label'               => $config['label'],
					'description'         => $config['description'],
					'category'            => 'emcp-tools',
					'execute_callback'    => $config['callback'],
					'permission_callback' => $perm,
					'input_schema'        => $this->input_schema( $name ),
					'meta'                => array(
						'annotations'  => $annotations,
						'show_in_rest' => true,
					),
				)
			);

			$this->ability_names[] = $ability_name;
		}
	}

	public function can_read(): bool {
		return current_user_can( 'manage_options' );
	}

	public function can_write(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	private function input_schema( string $name ): array {
		$schemas = array(
			'create-custom-block' => array(
				'type'       => 'object',
				'properties' => array(
					'spec' => array(
						'type'       => 'object',
						'properties' => array(
							'name'            => array( 'type' => 'string', 'description' => __( 'Block name (without prefix — "emcp/" is added automatically).', 'emcp-tools' ) ),
							'title'           => array( 'type' => 'string', 'description' => __( 'Block title shown in the inserter.', 'emcp-tools' ) ),
							'description'     => array( 'type' => 'string', 'description' => __( 'Block description.', 'emcp-tools' ) ),
							'icon'            => array( 'type' => 'string', 'description' => __( 'Block icon (dashicon or SVG).', 'emcp-tools' ) ),
							'category'        => array( 'type' => 'string', 'description' => __( 'Block category.', 'emcp-tools' ) ),
							'attributes'      => array( 'type' => 'object', 'description' => __( 'Block attribute definitions.', 'emcp-tools' ) ),
							'render_template' => array( 'type' => 'string', 'description' => __( 'PHP render template code.', 'emcp-tools' ) ),
							'supports'        => array( 'type' => 'object', 'description' => __( 'Block supports configuration.', 'emcp-tools' ) ),
						),
						'required'   => array( 'name', 'title', 'render_template' ),
					),
				),
				'required'   => array( 'spec' ),
			),
			'list-custom-blocks' => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array( 'type' => 'string', 'description' => __( 'Filter by status: draft, publish, or any.', 'emcp-tools' ) ),
				),
			),
			'get-custom-block'   => array(
				'type'       => 'object',
				'properties' => array(
					'block_id' => array( 'type' => 'integer', 'description' => __( 'The block post ID.', 'emcp-tools' ) ),
				),
				'required'   => array( 'block_id' ),
			),
			'update-custom-block' => array(
				'type'       => 'object',
				'properties' => array(
					'block_id' => array( 'type' => 'integer', 'description' => __( 'The block post ID.', 'emcp-tools' ) ),
					'spec'     => array(
						'type'       => 'object',
						'properties' => array(
							'name'            => array( 'type' => 'string' ),
							'title'           => array( 'type' => 'string' ),
							'description'     => array( 'type' => 'string' ),
							'icon'            => array( 'type' => 'string' ),
							'category'        => array( 'type' => 'string' ),
							'attributes'      => array( 'type' => 'object' ),
							'render_template' => array( 'type' => 'string' ),
							'supports'        => array( 'type' => 'object' ),
						),
						'required'   => array( 'name', 'title', 'render_template' ),
					),
				),
				'required'   => array( 'block_id', 'spec' ),
			),
			'delete-custom-block' => array(
				'type'       => 'object',
				'properties' => array(
					'block_id' => array( 'type' => 'integer', 'description' => __( 'The block post ID.', 'emcp-tools' ) ),
					'confirm'  => array( 'type' => 'boolean', 'description' => __( 'Must be true to delete.', 'emcp-tools' ) ),
				),
				'required'   => array( 'block_id', 'confirm' ),
			),
		);

		return $schemas[ $name ] ?? array( 'type' => 'object', 'properties' => array() );
	}

	public function execute_create( $input ) {
		$spec = isset( $input['spec'] ) && is_array( $input['spec'] ) ? $input['spec'] : array();

		if ( empty( $spec['name'] ) || empty( $spec['title'] ) || empty( $spec['render_template'] ) ) {
			return new WP_Error( 'missing_params', __( 'The spec must include name, title, and render_template.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$post_id = wp_insert_post( array(
			'post_type'   => EMCP_Tools_Block_Store::POST_TYPE,
			'post_title'  => sanitize_text_field( (string) $spec['title'] ),
			'post_status' => 'draft',
		), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = EMCP_Tools_Block_Store::write_block( $post_id, $spec );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( $post_id, true );
			return $result;
		}

		$parsed = json_decode( $result, true );
		$block_name = $parsed['name'] ?? '';

		EMCP_Tools_Block_Loader::add_to_manifest( $block_name );

		return array(
			'block_id'   => $post_id,
			'name'       => $block_name,
			'block_json' => $result,
			'edit_link'  => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
		);
	}

	public function execute_list( $input ): array {
		$blocks = EMCP_Tools_Block_Store::list();
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : '';

		if ( '' !== $status ) {
			$blocks = array_values( array_filter( $blocks, function ( $b ) use ( $status ) {
				return ( $b['status'] ?? '' ) === $status;
			} ) );
		}

		return array(
			'blocks' => $blocks,
			'total'  => count( $blocks ),
		);
	}

	public function execute_get( $input ) {
		$id = absint( $input['block_id'] ?? 0 );
		$spec = EMCP_Tools_Block_Store::get( $id );
		if ( null === $spec ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}
		return $spec;
	}

	public function execute_update( $input ) {
		$id   = absint( $input['block_id'] ?? 0 );
		$spec = isset( $input['spec'] ) && is_array( $input['spec'] ) ? $input['spec'] : array();

		$existing = EMCP_Tools_Block_Store::get( $id );
		if ( null === $existing ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		if ( empty( $spec['name'] ) || empty( $spec['title'] ) || empty( $spec['render_template'] ) ) {
			return new WP_Error( 'missing_params', __( 'The spec must include name, title, and render_template.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$old_block_name = '';
		$old_spec = get_post_meta( $id, '_emcp_block_spec', true );
		if ( is_array( $old_spec ) && isset( $old_spec['name'] ) ) {
			$old_block_name = 'emcp/' . ltrim( (string) $old_spec['name'], '/' );
		}

		EMCP_Tools_Block_Store::store( $id, $spec );

		$result = EMCP_Tools_Block_Store::write_block( $id, $spec );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$parsed = json_decode( $result, true );
		$new_block_name = $parsed['name'] ?? '';

		if ( $old_block_name && $old_block_name !== $new_block_name ) {
			EMCP_Tools_Block_Loader::remove_from_manifest( $old_block_name );
		}
		EMCP_Tools_Block_Loader::add_to_manifest( $new_block_name );

		return array(
			'block_id'       => $id,
			'name'           => $new_block_name,
			'block_json'     => $result,
		);
	}

	public function execute_delete( $input ) {
		$id      = absint( $input['block_id'] ?? 0 );
		$confirm = ! empty( $input['confirm'] );

		if ( ! $confirm ) {
			return new WP_Error( 'confirmation_required', __( 'Set confirm: true to delete the block.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$spec = EMCP_Tools_Block_Store::get( $id );
		if ( null === $spec ) {
			return new WP_Error( 'not_found', __( 'Block not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		$block_name = 'emcp/' . ltrim( (string) ( $spec['name'] ?? '' ), '/' );

		$upload_dir = wp_upload_dir();
		if ( ! is_wp_error( $upload_dir ) ) {
			$block_dir = $upload_dir['basedir'] . '/emcp-blocks/' . sanitize_key( $block_name );
			if ( is_dir( $block_dir ) ) {
				$files = glob( $block_dir . '/*' );
				if ( is_array( $files ) ) {
					foreach ( $files as $file ) {
						@unlink( $file );
					}
				}
				@rmdir( $block_dir );
			}
		}

		EMCP_Tools_Block_Loader::remove_from_manifest( $block_name );
		EMCP_Tools_Block_Store::delete( $id );

		return array(
			'deleted'    => true,
			'block_id'   => $id,
			'block_name' => $block_name,
		);
	}
}
