<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Motion_Abilities {

	private $data;

	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-motion-effects',
			array(
				'label'               => __( 'Set Motion Effects', 'emcp-tools' ),
				'description'         => __( 'Configures Elementor motion effects on any element: entrance animations (fadeIn, slideInUp, bounceIn, etc.), hover animations, sticky positioning (requires Pro), scroll effects, and mouse effects. Set sticky_on to the device(s) where sticky is active. Pass raw scroll_effects/mouse_effects objects for Pro-only scroll/mouse tracking effects.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'                 => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'element_id'              => array(
							'type'        => 'string',
							'description' => __( 'The target element ID (from get-page-structure).', 'emcp-tools' ),
						),
						'entrance_animation'      => array(
							'type'        => 'string',
							'description' => __( 'Entrance animation type: fadeIn, slideInUp, bounceIn, zoomIn, rotateIn, rollIn, flipInX, lightSpeedIn, jackInTheBox, etc.', 'emcp-tools' ),
						),
						'entrance_animation_delay' => array(
							'type'        => 'integer',
							'description' => __( 'Animation delay in milliseconds.', 'emcp-tools' ),
						),
						'entrance_animation_duration' => array(
							'type'        => 'string',
							'enum'        => array( 'default', 'slow', 'fast' ),
							'description' => __( 'Animation duration preset.', 'emcp-tools' ),
						),
						'hover_animation'         => array(
							'type'        => 'string',
							'description' => __( 'Hover animation type: grow, shrink, pulse, bounce, flash, shake, etc.', 'emcp-tools' ),
						),
						'sticky'                  => array(
							'type'        => 'string',
							'enum'        => array( '', 'top', 'bottom' ),
							'description' => __( 'Sticky position (requires Elementor Pro). Empty string to disable.', 'emcp-tools' ),
						),
						'sticky_on'               => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => array( 'desktop', 'tablet', 'mobile' ) ),
							'description' => __( 'Devices where sticky is active.', 'emcp-tools' ),
						),
						'sticky_offset'           => array(
							'type'        => 'integer',
							'description' => __( 'Offset from top/bottom in pixels.', 'emcp-tools' ),
						),
						'sticky_effects_offset'   => array(
							'type'        => 'integer',
							'description' => __( 'Effects offset in pixels.', 'emcp-tools' ),
						),
						'sticky_parent'           => array(
							'type'        => 'string',
							'enum'        => array( '', 'yes' ),
							'description' => __( 'Stick within parent column (yes or empty).', 'emcp-tools' ),
						),
						'scroll_effects'          => array(
							'type'        => 'object',
							'description' => __( 'Raw scroll effects settings (Pro). Keys like scroll_effect_transform, scroll_effect_opacity, etc.', 'emcp-tools' ),
						),
						'mouse_effects'           => array(
							'type'        => 'object',
							'description' => __( 'Raw mouse effects settings (Pro). Keys like mouse_effect_track, mouse_effect_transform, etc.', 'emcp-tools' ),
						),
						'hide_on'                 => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string', 'enum' => array( 'desktop', 'tablet', 'mobile' ) ),
							'description' => __( 'Devices to hide this element on.', 'emcp-tools' ),
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
		return array( 'emcp-tools/set-motion-effects' );
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

		if ( isset( $input['entrance_animation'] ) && '' !== $input['entrance_animation'] ) {
			$settings['_animation'] = sanitize_text_field( $input['entrance_animation'] );
		}
		if ( isset( $input['entrance_animation_delay'] ) && '' !== $input['entrance_animation_delay'] ) {
			$settings['_animation_delay'] = absint( $input['entrance_animation_delay'] );
		}
		if ( isset( $input['entrance_animation_duration'] ) && '' !== $input['entrance_animation_duration'] ) {
			$settings['_animation_duration'] = sanitize_text_field( $input['entrance_animation_duration'] );
		}
		if ( isset( $input['hover_animation'] ) && '' !== $input['hover_animation'] ) {
			$settings['_hover_animation'] = sanitize_text_field( $input['hover_animation'] );
		}
		if ( isset( $input['sticky'] ) ) {
			$settings['sticky'] = sanitize_text_field( $input['sticky'] );
		}
		if ( isset( $input['sticky_on'] ) && is_array( $input['sticky_on'] ) ) {
			$settings['sticky_on'] = array_map( 'sanitize_text_field', $input['sticky_on'] );
		}
		if ( isset( $input['sticky_offset'] ) && '' !== $input['sticky_offset'] ) {
			$settings['sticky_offset'] = absint( $input['sticky_offset'] );
		}
		if ( isset( $input['sticky_effects_offset'] ) && '' !== $input['sticky_effects_offset'] ) {
			$settings['sticky_effects_offset'] = absint( $input['sticky_effects_offset'] );
		}
		if ( isset( $input['sticky_parent'] ) ) {
			$settings['sticky_parent'] = sanitize_text_field( $input['sticky_parent'] );
		}
		if ( isset( $input['scroll_effects'] ) && is_array( $input['scroll_effects'] ) ) {
			foreach ( $input['scroll_effects'] as $key => $value ) {
				$settings[ sanitize_key( $key ) ] = $value;
			}
		}
		if ( isset( $input['mouse_effects'] ) && is_array( $input['mouse_effects'] ) ) {
			foreach ( $input['mouse_effects'] as $key => $value ) {
				$settings[ sanitize_key( $key ) ] = $value;
			}
		}
		if ( isset( $input['hide_on'] ) && is_array( $input['hide_on'] ) ) {
			$settings['hide_on'] = array_map( 'sanitize_text_field', $input['hide_on'] );
		}

		if ( empty( $settings ) ) {
			return new WP_Error(
				'no_settings',
				__( 'No motion effect settings provided.', 'emcp-tools' ),
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
