<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Overlay_Abilities {

	private $data;

	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-background-overlay',
			array(
				'label'               => __( 'Set Background Overlay', 'emcp-tools' ),
				'description'         => __( 'Configures background overlays and hover effects on any Elementor element. Supports background_overlay (normal state) and background_overlay_hover (hover state). Each overlay accepts: background type (classic or gradient), color (as hex/rgba), image_id, blend_mode (multiply, screen, overlay, etc.), and opacity (0-1). Set hover_transition for smooth hover transitions.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'            => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'element_id'         => array(
							'type'        => 'string',
							'description' => __( 'The target element ID (from get-page-structure).', 'emcp-tools' ),
						),
						'background_overlay' => array(
							'type'        => 'object',
							'description' => __( 'Normal state overlay settings.', 'emcp-tools' ),
							'properties'  => array(
								'background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ), 'description' => 'Background type.' ),
								'color'      => array( 'type' => 'string', 'description' => 'Background color (hex or rgba).' ),
								'color_a'    => array( 'type' => 'string', 'description' => 'Gradient first color.' ),
								'color_b'    => array( 'type' => 'string', 'description' => 'Gradient second color.' ),
								'gradient_type' => array( 'type' => 'string', 'enum' => array( 'linear', 'radial' ), 'description' => 'Gradient type.' ),
								'gradient_angle' => array( 'type' => 'number', 'description' => 'Gradient angle in degrees.' ),
								'image_id'   => array( 'type' => 'integer', 'description' => 'Attachment ID for overlay image.' ),
								'blend_mode' => array( 'type' => 'string', 'description' => 'CSS blend-mode: multiply, screen, overlay, darken, lighten, etc.' ),
								'opacity'    => array( 'type' => 'number', 'description' => 'Overlay opacity (0-1).' ),
							),
						),
						'background_overlay_hover' => array(
							'type'        => 'object',
							'description' => __( 'Hover state overlay settings. Same structure as background_overlay.', 'emcp-tools' ),
							'properties'  => array(
								'background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ) ),
								'color'      => array( 'type' => 'string' ),
								'color_a'    => array( 'type' => 'string' ),
								'color_b'    => array( 'type' => 'string' ),
								'gradient_type' => array( 'type' => 'string', 'enum' => array( 'linear', 'radial' ) ),
								'gradient_angle' => array( 'type' => 'number' ),
								'image_id'   => array( 'type' => 'integer' ),
								'blend_mode' => array( 'type' => 'string' ),
								'opacity'    => array( 'type' => 'number' ),
							),
						),
						'hover_transition'   => array(
							'type'        => 'object',
							'description' => __( 'Hover transition settings.', 'emcp-tools' ),
							'properties'  => array(
								'duration' => array( 'type' => 'number', 'description' => 'Transition duration in seconds (e.g. 0.3).' ),
								'easing'   => array( 'type' => 'string', 'enum' => array( 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out' ), 'description' => 'Transition timing function.' ),
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
		return array( 'emcp-tools/set-background-overlay' );
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

		if ( isset( $input['background_overlay'] ) && is_array( $input['background_overlay'] ) ) {
			$overlay = $input['background_overlay'];
			if ( isset( $overlay['background'] ) ) {
				$settings['background_overlay_background'] = sanitize_text_field( $overlay['background'] );
			}
			if ( isset( $overlay['color'] ) ) {
				$settings['background_overlay_color'] = sanitize_text_field( $overlay['color'] );
			}
			if ( isset( $overlay['color_a'] ) ) {
				$settings['background_overlay_color_a'] = sanitize_text_field( $overlay['color_a'] );
			}
			if ( isset( $overlay['color_b'] ) ) {
				$settings['background_overlay_color_b'] = sanitize_text_field( $overlay['color_b'] );
			}
			if ( isset( $overlay['gradient_type'] ) ) {
				$settings['background_overlay_gradient_type'] = sanitize_text_field( $overlay['gradient_type'] );
			}
			if ( isset( $overlay['gradient_angle'] ) && is_numeric( $overlay['gradient_angle'] ) ) {
				$settings['background_overlay_gradient_angle'] = array( 'unit' => 'deg', 'size' => (float) $overlay['gradient_angle'] );
			}
			if ( isset( $overlay['image_id'] ) ) {
				$settings['background_overlay_image'] = array( 'id' => absint( $overlay['image_id'] ), 'url' => wp_get_attachment_url( absint( $overlay['image_id'] ) ) );
			}
			if ( isset( $overlay['blend_mode'] ) ) {
				$settings['background_overlay_blend_mode'] = sanitize_text_field( $overlay['blend_mode'] );
			}
			if ( isset( $overlay['opacity'] ) && is_numeric( $overlay['opacity'] ) ) {
				$settings['background_overlay_opacity'] = array( 'unit' => 'px', 'size' => (float) $overlay['opacity'] );
			}
		}

		if ( isset( $input['background_overlay_hover'] ) && is_array( $input['background_overlay_hover'] ) ) {
			$hover = $input['background_overlay_hover'];
			if ( isset( $hover['background'] ) ) {
				$settings['background_overlay_hover_background'] = sanitize_text_field( $hover['background'] );
			}
			if ( isset( $hover['color'] ) ) {
				$settings['background_overlay_hover_color'] = sanitize_text_field( $hover['color'] );
			}
			if ( isset( $hover['color_a'] ) ) {
				$settings['background_overlay_hover_color_a'] = sanitize_text_field( $hover['color_a'] );
			}
			if ( isset( $hover['color_b'] ) ) {
				$settings['background_overlay_hover_color_b'] = sanitize_text_field( $hover['color_b'] );
			}
			if ( isset( $hover['gradient_type'] ) ) {
				$settings['background_overlay_hover_gradient_type'] = sanitize_text_field( $hover['gradient_type'] );
			}
			if ( isset( $hover['gradient_angle'] ) && is_numeric( $hover['gradient_angle'] ) ) {
				$settings['background_overlay_hover_gradient_angle'] = array( 'unit' => 'deg', 'size' => (float) $hover['gradient_angle'] );
			}
			if ( isset( $hover['image_id'] ) ) {
				$settings['background_overlay_hover_image'] = array( 'id' => absint( $hover['image_id'] ), 'url' => wp_get_attachment_url( absint( $hover['image_id'] ) ) );
			}
			if ( isset( $hover['blend_mode'] ) ) {
				$settings['background_overlay_hover_blend_mode'] = sanitize_text_field( $hover['blend_mode'] );
			}
			if ( isset( $hover['opacity'] ) && is_numeric( $hover['opacity'] ) ) {
				$settings['background_overlay_hover_opacity'] = array( 'unit' => 'px', 'size' => (float) $hover['opacity'] );
			}
		}

		if ( isset( $input['hover_transition'] ) && is_array( $input['hover_transition'] ) ) {
			if ( isset( $input['hover_transition']['duration'] ) && is_numeric( $input['hover_transition']['duration'] ) ) {
				$settings['_hover_transition_duration'] = $input['hover_transition']['duration'];
			}
			if ( isset( $input['hover_transition']['easing'] ) ) {
				$settings['_hover_transition_easings'] = array(
					'background'        => sanitize_text_field( $input['hover_transition']['easing'] ),
					'border'            => 'ease-in-out',
					'border_radius'     => 'ease-in-out',
					'background_overlay' => sanitize_text_field( $input['hover_transition']['easing'] ),
					'box_shadow'        => 'ease-in-out',
					'transform'         => 'ease-in-out',
					'mask'              => 'ease-in-out',
				);
			}
		}

		if ( empty( $settings ) ) {
			return new WP_Error(
				'no_settings',
				__( 'No overlay settings provided.', 'emcp-tools' ),
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
}
