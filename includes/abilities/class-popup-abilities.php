<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Popup_Abilities {

	private $data;
	private $factory;

	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/create-popup-builder',
			array(
				'label'               => __( 'Create Popup (Builder)', 'emcp-tools' ),
				'description'         => __( 'Creates an Elementor Pro popup with triggers and timing configured in one call. Supports triggers: on_page_load (with delay), on_scroll (direction + offset percent), on_click (close after N times), on_exit_intent (mouse leaves window), on_inactivity (N seconds idle). Optionally adds a trigger button to a target page. Timing options: show after X page views, after X sessions, up to X total times, or on specific devices only. Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'       => array(
							'type'        => 'string',
							'description' => __( 'Popup title.', 'emcp-tools' ),
						),
						'triggers'    => array(
							'type'        => 'object',
							'description' => __( 'Trigger configurations (all optional, at least one required).', 'emcp-tools' ),
							'properties'  => array(
								'on_page_load'    => array(
									'type'       => 'object',
									'properties' => array(
										'delay' => array( 'type' => 'integer', 'description' => 'Delay in seconds before showing.' ),
									),
								),
								'on_scroll'       => array(
									'type'       => 'object',
									'properties' => array(
										'direction' => array( 'type' => 'string', 'enum' => array( 'down', 'up' ), 'description' => 'Scroll direction.' ),
										'offset'    => array( 'type' => 'number', 'description' => 'Scroll offset in percent (0-100). Default: 25.' ),
									),
								),
								'on_click'        => array(
									'type'       => 'object',
									'properties' => array(
										'times' => array( 'type' => 'integer', 'description' => 'Times to display before hiding permanently.' ),
									),
								),
								'on_exit_intent'  => array(
									'type'       => 'object',
									'properties' => array(),
								),
								'on_inactivity'   => array(
									'type'       => 'object',
									'properties' => array(
										'time' => array( 'type' => 'integer', 'description' => 'Idle time in seconds before showing.' ),
									),
								),
							),
						),
						'timing'      => array(
							'type'        => 'object',
							'description' => __( 'Timing/display rules.', 'emcp-tools' ),
							'properties'  => array(
								'show_after_x_page_views' => array( 'type' => 'integer', 'description' => 'Show after N page views.' ),
								'show_after_x_sessions'   => array( 'type' => 'integer', 'description' => 'Show after N sessions.' ),
								'show_up_to_x_times'      => array( 'type' => 'integer', 'description' => 'Show up to N times total (0 = unlimited).' ),
								'devices'                 => array( 'type' => 'array', 'items' => array( 'type' => 'string', 'enum' => array( 'desktop', 'tablet', 'mobile' ) ), 'description' => 'Devices to show on.' ),
								'url_contains'            => array( 'type' => 'string', 'description' => 'Only show when URL contains this string.' ),
								'url_not_contains'        => array( 'type' => 'string', 'description' => 'Do not show when URL contains this string.' ),
							),
						),
						'trigger_button' => array(
							'type'        => 'object',
							'description' => __( 'Optionally add a trigger button to a target page that opens the popup on click.', 'emcp-tools' ),
							'properties'  => array(
								'post_id'   => array( 'type' => 'integer', 'description' => 'Target page ID to insert the trigger button.' ),
								'parent_id' => array( 'type' => 'string', 'description' => 'Parent container element ID on the target page.' ),
								'text'      => array( 'type' => 'string', 'description' => 'Button text. Default: Open Popup.' ),
								'position'  => array( 'type' => 'integer', 'description' => 'Insert position (-1 = append).' ),
							),
						),
					),
					'required'     => array( 'title', 'triggers' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'            => array( 'type' => 'boolean' ),
						'popup_id'           => array( 'type' => 'integer' ),
						'popup_title'        => array( 'type' => 'string' ),
						'popup_edit_url'     => array( 'type' => 'string' ),
						'enabled_triggers'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'trigger_element_id' => array( 'type' => 'string' ),
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
		return array( 'emcp-tools/create-popup-builder' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute( $input ) {
		$title    = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$triggers = isset( $input['triggers'] ) && is_array( $input['triggers'] ) ? $input['triggers'] : array();
		$timing   = isset( $input['timing'] ) && is_array( $input['timing'] ) ? $input['timing'] : array();

		if ( empty( $title ) || empty( $triggers ) ) {
			return new WP_Error(
				'missing_parameters',
				__( 'title and triggers are required.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		// Create popup post.
		$popup_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'popup',
				),
			),
			true
		);

		if ( is_wp_error( $popup_id ) ) {
			return $popup_id;
		}

		wp_set_object_terms( $popup_id, 'popup', 'elementor_library_type' );
		$this->data->save_page_data( $popup_id, array() );

		// Build trigger settings in Elementor Pro format.
		$trigger_settings = array();
		$enabled_triggers = array();

		foreach ( $triggers as $type => $config ) {
			$entry = array( 'enabled' => true );
			if ( is_array( $config ) ) {
				foreach ( $config as $key => $value ) {
					$entry[ $key ] = $value;
				}
			}
			$trigger_settings[ $type ] = $entry;
			$enabled_triggers[] = $type;
		}

		if ( ! empty( $trigger_settings ) ) {
			update_post_meta( $popup_id, '_elementor_popup_triggers', $trigger_settings );
		}

		// Build timing settings.
		$timing_settings = array();
		if ( isset( $timing['devices'] ) && is_array( $timing['devices'] ) ) {
			$timing_settings['devices'] = array( 'devices' => array_map( 'sanitize_text_field', $timing['devices'] ) );
		}
		if ( isset( $timing['show_after_x_page_views'] ) ) {
			$timing_settings['page_views'] = array( 'views' => absint( $timing['show_after_x_page_views'] ), 'period' => 'session' );
		}
		if ( isset( $timing['show_after_x_sessions'] ) ) {
			$timing_settings['sessions'] = array( 'sessions' => absint( $timing['show_after_x_sessions'] ) );
		}
		if ( isset( $timing['show_up_to_x_times'] ) ) {
			$timing_settings['times'] = array( 'times' => absint( $timing['show_up_to_x_times'] ), 'period' => 'all_time', 'count' => 0 );
		}
		if ( ! empty( $timing['url_contains'] ) ) {
			$timing_settings['url'] = array( 'url' => sanitize_text_field( $timing['url_contains'] ), 'action' => 'show' );
		}
		if ( ! empty( $timing['url_not_contains'] ) ) {
			$timing_settings['url'] = array( 'url' => sanitize_text_field( $timing['url_not_contains'] ), 'action' => 'hide' );
		}

		if ( ! empty( $timing_settings ) ) {
			update_post_meta( $popup_id, '_elementor_popup_timing', $timing_settings );
		}

		// Set popup conditions — default to "entire site".
		update_post_meta( $popup_id, '_elementor_conditions', array( array( 'include', 'general' ) ) );

		$trigger_element_id = '';

		// Optionally add a trigger button to a target page.
		if ( isset( $input['trigger_button'] ) && is_array( $input['trigger_button'] ) ) {
			$tb        = $input['trigger_button'];
			$tb_post   = isset( $tb['post_id'] ) ? absint( $tb['post_id'] ) : 0;
			$tb_parent = isset( $tb['parent_id'] ) ? sanitize_text_field( (string) $tb['parent_id'] ) : '';
			$tb_text   = ! empty( $tb['text'] ) ? sanitize_text_field( $tb['text'] ) : __( 'Open Popup', 'emcp-tools' );
			$tb_pos    = isset( $tb['position'] ) ? intval( $tb['position'] ) : -1;

			if ( $tb_post && $tb_parent ) {
				$widget = $this->factory->create_widget(
					'button',
					array(
						'text'         => $tb_text,
						'link'         => array(
							'url'          => '#elementor-action:action=popup:open:settings=' . rawurlencode( wp_json_encode( array( 'id' => $popup_id, 'toggle' => false ) ) ),
							'is_external'  => '',
							'nofollow'     => '',
							'custom_attributes' => '',
						),
						'button_size'  => 'md',
						'button_align' => 'center',
					)
				);

				$page_data = $this->data->get_page_data( $tb_post );
				if ( ! is_wp_error( $page_data ) && ! empty( $page_data ) ) {
					$inserted = $this->data->insert_element( $page_data, $tb_parent, $widget, $tb_pos );
					if ( $inserted ) {
						$this->data->save_page_data( $tb_post, $page_data );
						$trigger_element_id = $widget['id'];
					}
				}
			}
		}

		return array(
			'success'          => true,
			'popup_id'         => $popup_id,
			'popup_title'      => $title,
			'popup_edit_url'   => admin_url( 'post.php?post=' . $popup_id . '&action=elementor' ),
			'enabled_triggers' => $enabled_triggers,
			'trigger_element_id' => $trigger_element_id,
		);
	}
}
