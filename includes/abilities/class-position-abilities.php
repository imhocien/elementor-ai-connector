<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Position_Abilities {

	private $data;

	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-element-position',
			array(
				'label'               => __( 'Set Element Position', 'emcp-tools' ),
				'description'         => __( 'Sets CSS position, z-index, and CSS transforms (rotate, scale, translate, skew) on any Elementor element. Position options: absolute, fixed, relative, or default. Offset X/Y use { size, unit } objects (e.g. {"size":10,"unit":"px"}). For transforms, pass a transform object with rotate/scale/translate/skew, each with x/y/z properties for the corresponding transform axes.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The target element ID (from get-page-structure).', 'emcp-tools' ),
						),
						'position'   => array(
							'type'        => 'string',
							'enum'        => array( '', 'absolute', 'fixed', 'relative' ),
							'description' => __( 'CSS position: absolute, fixed, relative, or empty for default.', 'emcp-tools' ),
						),
						'offset_x'   => array(
							'type'        => 'object',
							'description' => __( 'Horizontal offset as { size, unit }. e.g. {"size":0,"unit":"px"}.', 'emcp-tools' ),
							'properties'  => array(
								'size' => array( 'type' => 'number' ),
								'unit' => array( 'type' => 'string', 'enum' => array( 'px', '%', 'vw' ) ),
							),
						),
						'offset_y'   => array(
							'type'        => 'object',
							'description' => __( 'Vertical offset as { size, unit }. e.g. {"size":0,"unit":"px"}.', 'emcp-tools' ),
							'properties'  => array(
								'size' => array( 'type' => 'number' ),
								'unit' => array( 'type' => 'string', 'enum' => array( 'px', '%', 'vh' ) ),
							),
						),
						'z_index'    => array(
							'type'        => 'object',
							'description' => __( 'Z-index as { size, unit }. e.g. {"size":100,"unit":"px"}.', 'emcp-tools' ),
							'properties'  => array(
								'size' => array( 'type' => 'integer' ),
								'unit' => array( 'type' => 'string', 'enum' => array( 'px' ) ),
							),
						),
						'transform'  => array(
							'type'        => 'object',
							'description' => __( 'CSS transform values: rotate, scale, translate, skew. Each with x/y/z axis values.', 'emcp-tools' ),
							'properties'  => array(
								'rotate'    => array(
									'type'       => 'object',
									'properties' => array(
										'x' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string' ) ) ),
										'y' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string' ) ) ),
										'z' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string' ) ) ),
									),
								),
								'scale'     => array(
									'type'       => 'object',
									'properties' => array(
										'x' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ) ) ),
										'y' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ) ) ),
									),
								),
								'translate' => array(
									'type'       => 'object',
									'properties' => array(
										'x' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', '%', 'vw' ) ) ) ),
										'y' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', '%', 'vh' ) ) ) ),
									),
								),
								'skew'      => array(
									'type'       => 'object',
									'properties' => array(
										'x' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string' ) ) ),
										'y' => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string' ) ) ),
									),
								),
							),
						),
					),
					'required'     => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated'    => array( 'type' => 'boolean' ),
						'post_id'    => array( 'type' => 'integer' ),
						'element_id' => array( 'type' => 'string' ),
						'settings'   => array( 'type' => 'object' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function get_ability_names(): array {
		return array( 'emcp-tools/set-element-position' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$post_id    = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$element_id = isset( $input['element_id'] ) ? sanitize_text_field( (string) $input['element_id'] ) : '';

		if ( ! $post_id || ! $element_id ) {
			return new WP_Error(
				'missing_parameters',
				__( 'Both post_id and element_id are required.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error(
				'post_not_found',
				__( 'Post not found.', 'emcp-tools' ),
				array( 'status' => 404 )
			);
		}

		$data = $this->data->get_page_data( $post_id );
		if ( empty( $data ) ) {
			return new WP_Error(
				'no_elementor_data',
				__( 'This page has no Elementor data.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$settings = array();

		if ( isset( $input['position'] ) && '' !== $input['position'] ) {
			$settings['_position'] = sanitize_text_field( $input['position'] );
		}

		if ( isset( $input['offset_x'] ) && is_array( $input['offset_x'] ) ) {
			$settings['_offset_x'] = $this->sanitize_dimension( $input['offset_x'] );
		}

		if ( isset( $input['offset_y'] ) && is_array( $input['offset_y'] ) ) {
			$settings['_offset_y'] = $this->sanitize_dimension( $input['offset_y'] );
		}

		if ( isset( $input['z_index'] ) && is_array( $input['z_index'] ) ) {
			$settings['_z_index'] = $this->sanitize_dimension( $input['z_index'] );
		}

		if ( isset( $input['transform'] ) && is_array( $input['transform'] ) ) {
			$this->apply_transform_settings( $settings, $input['transform'] );
		}

		if ( empty( $settings ) ) {
			return new WP_Error(
				'no_settings',
				__( 'No position or transform settings provided.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$found = $this->data->update_element_settings( $data, $element_id, $settings );
		if ( ! $found ) {
			return new WP_Error(
				'element_not_found',
				sprintf(
					__( 'Element %s not found on this page.', 'emcp-tools' ),
					$element_id
				),
				array( 'status' => 404 )
			);
		}

		$this->data->save_page_data( $post_id, $data );

		return array(
			'updated'    => true,
			'post_id'    => $post_id,
			'element_id' => $element_id,
			'settings'   => $settings,
		);
	}

	private function sanitize_dimension( array $dim ): array {
		$out = array();
		if ( isset( $dim['size'] ) && is_numeric( $dim['size'] ) ) {
			$out['size'] = $dim['size'] + 0;
		}
		if ( isset( $dim['unit'] ) && is_string( $dim['unit'] ) ) {
			$out['unit'] = sanitize_text_field( $dim['unit'] );
		}
		return $out;
	}

	private function apply_transform_settings( array &$settings, array $transform ): void {
		$axes = array( 'rotate', 'scale', 'translate', 'skew' );

		foreach ( $axes as $type ) {
			if ( ! isset( $transform[ $type ] ) || ! is_array( $transform[ $type ] ) ) {
				continue;
			}

			$values = $transform[ $type ];

			switch ( $type ) {
				case 'rotate':
					foreach ( array( 'x' => 'X', 'y' => 'Y', 'z' => 'Z' ) as $axis => $suffix ) {
						if ( isset( $values[ $axis ] ) && is_array( $values[ $axis ] ) ) {
							$dim = $this->sanitize_dimension( $values[ $axis ] );
							if ( isset( $dim['size'] ) ) {
								$settings[ "_transform_rotate{$suffix}_effect" ] = $dim;
							}
						}
					}
					if ( isset( $values['z'] ) ) {
						$settings['_transform_rotate_popover'] = 'rotate';
					}
					break;

				case 'scale':
					if ( isset( $values['x'] ) && is_array( $values['x'] ) ) {
						$dim = $this->sanitize_dimension( $values['x'] );
						if ( isset( $dim['size'] ) ) {
							$settings['_transform_scale_effect'] = $dim;
						}
					}
					if ( isset( $values['y'] ) && is_array( $values['y'] ) ) {
						$dim = $this->sanitize_dimension( $values['y'] );
						if ( isset( $dim['size'] ) ) {
							$settings['_transform_scaleY_effect'] = $dim;
						}
					}
					if ( isset( $values['x'] ) || isset( $values['y'] ) ) {
						$settings['_transform_scale_popover'] = 'scale';
					}
					break;

				case 'translate':
					if ( isset( $values['x'] ) && is_array( $values['x'] ) ) {
						$settings['_transform_translateX_effect'] = $this->sanitize_dimension( $values['x'] );
					}
					if ( isset( $values['y'] ) && is_array( $values['y'] ) ) {
						$settings['_transform_translateY_effect'] = $this->sanitize_dimension( $values['y'] );
					}
					break;

				case 'skew':
					if ( isset( $values['x'] ) && is_array( $values['x'] ) ) {
						$dim = $this->sanitize_dimension( $values['x'] );
						if ( isset( $dim['size'] ) ) {
							$settings['_transform_skewX_effect'] = $dim;
						}
					}
					if ( isset( $values['y'] ) && is_array( $values['y'] ) ) {
						$dim = $this->sanitize_dimension( $values['y'] );
						if ( isset( $dim['size'] ) ) {
							$settings['_transform_skewY_effect'] = $dim;
						}
					}
					if ( isset( $values['x'] ) || isset( $values['y'] ) ) {
						$settings['_transform_skew_popover'] = 'skew';
					}
					break;
			}
		}
	}
}
