<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Responsive_Abilities {

	private $data;

	public function __construct( EMCP_Tools_Data $data ) {
		$this->data = $data;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-responsive-settings',
			array(
				'label'               => __( 'Set Responsive Settings', 'emcp-tools' ),
				'description'         => __( 'Sets breakpoint-specific (responsive) settings on any element — Desktop, Tablet, Mobile, Widescreen, and custom breakpoints. Accepts a `responsive` map of breakpoint → { setting_key: value } pairs; the tool appends the correct Elementor suffix (_tablet, _mobile, etc.) automatically. Also handles visibility toggles via `hide: true` per breakpoint and custom CSS per device.', 'emcp-tools' ),
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
						'responsive'         => array(
							'type'        => 'object',
							'description' => __( 'Per-breakpoint settings. Keys: desktop, tablet, mobile, widescreen, tablet_extra, mobile_extra, laptop. Each value is an object of { setting_key: value }. Use `hide: true` to hide on that breakpoint, `hide: false` to show.', 'emcp-tools' ),
							'properties'  => array(
								'desktop'      => array( 'type' => 'object', 'description' => 'Desktop settings (no suffix).' ),
								'tablet'       => array( 'type' => 'object', 'description' => 'Tablet settings (appends _tablet suffix).' ),
								'mobile'       => array( 'type' => 'object', 'description' => 'Mobile settings (appends _mobile suffix).' ),
								'widescreen'   => array( 'type' => 'object', 'description' => 'Widescreen settings (appends _widescreen suffix).' ),
								'tablet_extra' => array( 'type' => 'object', 'description' => 'Tablet extra settings (appends _tablet_extra suffix).' ),
								'mobile_extra' => array( 'type' => 'object', 'description' => 'Mobile extra settings (appends _mobile_extra suffix).' ),
								'laptop'       => array( 'type' => 'object', 'description' => 'Laptop settings (appends _laptop suffix).' ),
							),
						),
						'custom_css'         => array(
							'type'        => 'object',
							'description' => __( 'Custom CSS per breakpoint. Keys: desktop, tablet, mobile, widescreen. Each value is a CSS string.', 'emcp-tools' ),
							'properties'  => array(
								'desktop'    => array( 'type' => 'string' ),
								'tablet'     => array( 'type' => 'string' ),
								'mobile'     => array( 'type' => 'string' ),
								'widescreen' => array( 'type' => 'string' ),
							),
						),
						'visibility'         => array(
							'type'        => 'object',
							'description' => __( 'Visibility toggles per breakpoint. Shorthand for hide_desktop/hide_tablet/hide_mobile.', 'emcp-tools' ),
							'properties'  => array(
								'desktop' => array( 'type' => 'boolean', 'description' => 'Hide on desktop.' ),
								'tablet'  => array( 'type' => 'boolean', 'description' => 'Hide on tablet.' ),
								'mobile'  => array( 'type' => 'boolean', 'description' => 'Hide on mobile.' ),
							),
						),
					),
					'required'     => array( 'post_id', 'element_id' ),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/list-breakpoints',
			array(
				'label'               => __( 'List Breakpoints', 'emcp-tools' ),
				'description'         => __( 'Returns the active Elementor breakpoints and their values (desktop, tablet, mobile, widescreen, etc.) from the site settings.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_breakpoints' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function get_ability_names(): array {
		return array( 'emcp-tools/set-responsive-settings', 'emcp-tools/list-breakpoints' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute_list_breakpoints( $input ): array {
		$breakpoints = array(
			'desktop'      => 1025,
			'tablet'       => 1024,
			'mobile'       => 767,
			'widescreen'   => 2400,
			'tablet_extra' => 1200,
			'mobile_extra' => 880,
			'laptop'       => 1366,
		);

		if ( function_exists( 'elementor_breakpoints_get_config' ) ) {
			$config = elementor_breakpoints_get_config();
			if ( is_array( $config ) ) {
				$breakpoints = array_merge( $breakpoints, $config );
			}
		} elseif ( class_exists( '\Elementor\Plugin' ) ) {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
			if ( $kit ) {
				$settings = $kit->get_settings();
				$custom = array(
					'widescreen'   => isset( $settings['widescreen_breakpoint'] ) ? (int) $settings['widescreen_breakpoint'] : 2400,
					'tablet_extra' => isset( $settings['tablet_extra_breakpoint'] ) ? (int) $settings['tablet_extra_breakpoint'] : 1200,
					'mobile_extra' => isset( $settings['mobile_extra_breakpoint'] ) ? (int) $settings['mobile_extra_breakpoint'] : 880,
					'laptop'       => isset( $settings['laptop_breakpoint'] ) ? (int) $settings['laptop_breakpoint'] : 1366,
				);
				$breakpoints = array_merge( $breakpoints, $custom );
			}
		}

		return array(
			'breakpoints' => $breakpoints,
			'labels'      => array(
				'desktop'      => __( 'Desktop', 'emcp-tools' ),
				'tablet'       => __( 'Tablet', 'emcp-tools' ),
				'mobile'       => __( 'Mobile', 'emcp-tools' ),
				'widescreen'   => __( 'Widescreen', 'emcp-tools' ),
				'tablet_extra' => __( 'Tablet Extra', 'emcp-tools' ),
				'mobile_extra' => __( 'Mobile Extra', 'emcp-tools' ),
				'laptop'       => __( 'Laptop', 'emcp-tools' ),
			),
		);
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

		$responsive = isset( $input['responsive'] ) && is_array( $input['responsive'] ) ? $input['responsive'] : array();
		$settings   = $this->build_responsive_settings( $settings, $responsive );

		$custom_css = isset( $input['custom_css'] ) && is_array( $input['custom_css'] ) ? $input['custom_css'] : array();
		$settings   = $this->build_custom_css_settings( $settings, $custom_css );

		$visibility = isset( $input['visibility'] ) && is_array( $input['visibility'] ) ? $input['visibility'] : array();
		$settings   = $this->build_visibility_settings( $settings, $visibility );

		if ( empty( $settings ) ) {
			return new WP_Error(
				'no_settings',
				__( 'No responsive settings, custom_css, or visibility changes provided.', 'emcp-tools' ),
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

	private function build_responsive_settings( array $settings, array $responsive ): array {
		$suffix_map = array(
			'desktop'      => '',
			'tablet'       => '_tablet',
			'mobile'       => '_mobile',
			'widescreen'   => '_widescreen',
			'tablet_extra' => '_tablet_extra',
			'mobile_extra' => '_mobile_extra',
			'laptop'       => '_laptop',
		);

		foreach ( $responsive as $breakpoint => $breakpoint_settings ) {
			if ( ! is_array( $breakpoint_settings ) ) {
				continue;
			}
			$suffix = isset( $suffix_map[ $breakpoint ] ) ? $suffix_map[ $breakpoint ] : '';

			foreach ( $breakpoint_settings as $key => $value ) {
				if ( 'hide' === $key ) {
					if ( '' === $suffix ) {
						continue;
					}
					$settings[ 'hide' . $suffix ] = (bool) $value;
				} elseif ( '' !== $suffix ) {
					if ( is_string( $value ) ) {
						$settings[ $key . $suffix ] = sanitize_text_field( $value );
					} else {
						$settings[ $key . $suffix ] = $value;
					}
				} else {
					if ( is_string( $value ) ) {
						$settings[ $key ] = sanitize_text_field( $value );
					} else {
						$settings[ $key ] = $value;
					}
				}
			}
		}

		return $settings;
	}

	private function build_custom_css_settings( array $settings, array $custom_css ): array {
		$css_keys = array(
			'desktop'    => 'custom_css',
			'tablet'     => 'custom_css_tablet',
			'mobile'     => 'custom_css_mobile',
			'widescreen' => 'custom_css_widescreen',
		);

		foreach ( $custom_css as $breakpoint => $css ) {
			if ( ! is_string( $css ) ) {
				continue;
			}
			$key = isset( $css_keys[ $breakpoint ] ) ? $css_keys[ $breakpoint ] : '';
			if ( '' !== $key ) {
				$settings[ $key ] = $css;
			}
		}

		return $settings;
	}

	private function build_visibility_settings( array $settings, array $visibility ): array {
		$vis_keys = array(
			'desktop' => 'hide_desktop',
			'tablet'  => 'hide_tablet',
			'mobile'  => 'hide_mobile',
		);

		foreach ( $visibility as $breakpoint => $hide ) {
			$key = isset( $vis_keys[ $breakpoint ] ) ? $vis_keys[ $breakpoint ] : '';
			if ( '' !== $key ) {
				$settings[ $key ] = (bool) $hide;
			}
		}

		return $settings;
	}
}
