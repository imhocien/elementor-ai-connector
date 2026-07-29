<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Site_Kit_Abilities {

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/configure-site-kit',
			array(
				'label'               => __( 'Configure Site Kit', 'emcp-tools' ),
				'description'         => __( 'Sets multiple Elementor kit (site-wide design system) settings in one call: site identity (name, description, logo, favicon), layout (container width, content width, space between, page wrapper), global button defaults (colors, typography), custom CSS, and custom colors/typography. For colors and typography, use the existing update-global-colors/update-global-typography tools — this tool handles site-level settings.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_manage' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'site_identity' => array(
							'type'        => 'object',
							'description' => __( 'Site identity settings.', 'emcp-tools' ),
							'properties'  => array(
								'name'        => array( 'type' => 'string', 'description' => 'Site title.' ),
								'description' => array( 'type' => 'string', 'description' => 'Site tagline.' ),
								'logo_id'     => array( 'type' => 'integer', 'description' => 'Media library attachment ID for the site logo.' ),
								'favicon_id'  => array( 'type' => 'integer', 'description' => 'Media library attachment ID for the favicon.' ),
							),
						),
						'layout'         => array(
							'type'        => 'object',
							'description' => __( 'Layout settings for the site container.', 'emcp-tools' ),
							'properties'  => array(
								'container_width'          => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', 'vw', '%' ) ) ), 'description' => 'Container max-width as {size, unit}.' ),
								'content_width'            => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', '%' ) ) ), 'description' => 'Content area width as {size, unit}.' ),
								'space_between'            => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', 'em' ) ) ), 'description' => 'Space between widgets as {size, unit}.' ),
								'stretched_section_container' => array( 'type' => 'string', 'description' => 'CSS class for stretched section container.' ),
								'page_wrapper'             => array( 'type' => 'boolean', 'description' => 'Enable page wrapper.' ),
							),
						),
						'global_button'  => array(
							'type'        => 'object',
							'description' => __( 'Global button defaults.', 'emcp-tools' ),
							'properties'  => array(
								'background_color' => array( 'type' => 'string', 'description' => 'Button background color (hex/rgba).' ),
								'text_color'       => array( 'type' => 'string', 'description' => 'Button text color (hex/rgba).' ),
								'border_radius'    => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', 'em', '%' ) ) ), 'description' => 'Border radius as {size, unit}.' ),
								'padding'          => array( 'type' => 'object', 'properties' => array( 'unit' => array( 'type' => 'string' ), 'top' => array( 'type' => 'string' ), 'right' => array( 'type' => 'string' ), 'bottom' => array( 'type' => 'string' ), 'left' => array( 'type' => 'string' ) ), 'description' => 'Padding dimensions.' ),
								'font_family'      => array( 'type' => 'string', 'description' => 'Font family.' ),
								'font_size'        => array( 'type' => 'object', 'properties' => array( 'size' => array( 'type' => 'number' ), 'unit' => array( 'type' => 'string', 'enum' => array( 'px', 'em', 'rem' ) ) ), 'description' => 'Font size as {size, unit}.' ),
								'font_weight'      => array( 'type' => 'string', 'description' => 'Font weight.' ),
								'text_transform'   => array( 'type' => 'string', 'enum' => array( 'none', 'uppercase', 'lowercase', 'capitalize' ), 'description' => 'Text transform.' ),
							),
						),
						'custom_css'     => array(
							'type'        => 'string',
							'description' => __( 'Site-wide custom CSS (appended to existing). Use replace_css to overwrite.', 'emcp-tools' ),
						),
						'replace_css'    => array(
							'type'        => 'boolean',
							'description' => __( 'If true, replaces existing custom CSS instead of appending.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function get_ability_names(): array {
		return array( 'emcp-tools/configure-site-kit' );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public function execute( $input ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error( 'elementor_inactive', __( 'Elementor is not active.', 'emcp-tools' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
		if ( ! $kit || ! $kit->get_id() ) {
			return new WP_Error( 'kit_not_found', __( 'Active Elementor kit not found.', 'emcp-tools' ) );
		}

		$settings = array();

		if ( isset( $input['site_identity'] ) && is_array( $input['site_identity'] ) ) {
			$identity = $input['site_identity'];
			if ( ! empty( $identity['name'] ) ) {
				$settings['site_name'] = sanitize_text_field( $identity['name'] );
			}
			if ( isset( $identity['description'] ) ) {
				$settings['site_description'] = sanitize_text_field( $identity['description'] );
			}
			if ( ! empty( $identity['logo_id'] ) ) {
				$settings['site_logo'] = array(
					'id'  => absint( $identity['logo_id'] ),
					'url' => wp_get_attachment_url( absint( $identity['logo_id'] ) ),
				);
			}
			if ( ! empty( $identity['favicon_id'] ) ) {
				$settings['site_favicon'] = array(
					'id'  => absint( $identity['favicon_id'] ),
					'url' => wp_get_attachment_url( absint( $identity['favicon_id'] ) ),
				);
			}
		}

		if ( isset( $input['layout'] ) && is_array( $input['layout'] ) ) {
			$layout = $input['layout'];
			if ( isset( $layout['container_width'] ) && is_array( $layout['container_width'] ) ) {
				$settings['container_width'] = $this->sanitize_dim( $layout['container_width'] );
			}
			if ( isset( $layout['content_width'] ) && is_array( $layout['content_width'] ) ) {
				$settings['content_width'] = $this->sanitize_dim( $layout['content_width'] );
			}
			if ( isset( $layout['space_between'] ) && is_array( $layout['space_between'] ) ) {
				$settings['space_between'] = $this->sanitize_dim( $layout['space_between'] );
			}
			if ( ! empty( $layout['stretched_section_container'] ) ) {
				$settings['stretched_section_container'] = sanitize_text_field( $layout['stretched_section_container'] );
			}
			if ( isset( $layout['page_wrapper'] ) ) {
				$settings['page_wrapper'] = $layout['page_wrapper'] ? 'yes' : '';
			}
		}

		if ( isset( $input['global_button'] ) && is_array( $input['global_button'] ) ) {
			$button = $input['global_button'];
			if ( ! empty( $button['background_color'] ) ) {
				$settings['button_background_color'] = sanitize_text_field( $button['background_color'] );
			}
			if ( ! empty( $button['text_color'] ) ) {
				$settings['button_text_color'] = sanitize_text_field( $button['text_color'] );
			}
			if ( isset( $button['border_radius'] ) && is_array( $button['border_radius'] ) ) {
				$settings['button_border_radius'] = $this->sanitize_dim( $button['border_radius'] );
			}
			if ( isset( $button['padding'] ) && is_array( $button['padding'] ) ) {
				$settings['button_padding'] = $button['padding'];
			}
			if ( ! empty( $button['font_family'] ) ) {
				$settings['button_typography_font_family'] = sanitize_text_field( $button['font_family'] );
			}
			if ( isset( $button['font_size'] ) && is_array( $button['font_size'] ) ) {
				$settings['button_typography_font_size'] = $this->sanitize_dim( $button['font_size'] );
			}
			if ( ! empty( $button['font_weight'] ) ) {
				$settings['button_typography_font_weight'] = sanitize_text_field( $button['font_weight'] );
			}
			if ( ! empty( $button['text_transform'] ) ) {
				$settings['button_typography_text_transform'] = sanitize_text_field( $button['text_transform'] );
			}
			if ( ! empty( $settings['button_typography_font_family'] ) || ! empty( $settings['button_typography_font_size'] ) ) {
				$settings['button_typography_typography'] = 'custom';
			}
		}

		if ( isset( $input['custom_css'] ) && is_string( $input['custom_css'] ) ) {
			$replace = ! empty( $input['replace_css'] );
			$existing = $kit->get_settings( 'custom_css' );
			if ( $replace || empty( $existing ) ) {
				$settings['custom_css'] = $input['custom_css'];
			} else {
				$settings['custom_css'] = $existing . "\n" . $input['custom_css'];
			}
		}

		if ( empty( $settings ) ) {
			return new WP_Error(
				'no_settings',
				__( 'No settings provided.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$kit->update_settings( $settings );

		return array( 'success' => true );
	}

	private function sanitize_dim( array $dim ): array {
		$out = array();
		if ( isset( $dim['size'] ) && is_numeric( $dim['size'] ) ) {
			$out['size'] = $dim['size'] + 0;
		}
		if ( isset( $dim['unit'] ) && is_string( $dim['unit'] ) ) {
			$out['unit'] = sanitize_text_field( $dim['unit'] );
		}
		return $out;
	}
}
