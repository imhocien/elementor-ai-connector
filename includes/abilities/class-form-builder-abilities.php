<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Form_Builder_Abilities {

	private $data;
	private $factory;

	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/create-form',
			array(
				'label'               => __( 'Create Form', 'emcp-tools' ),
				'description'         => __( 'Creates an Elementor Pro Form widget with structured field definitions, submit actions (email, redirect, webhook), and button styling. Each field supports type, label, placeholder, required, width (100/75/66/50/33/25/20 percent), and type-specific options. Use field_options (newline-separated) for select/radio/checkbox. Actions include email (to/subject/from/reply_to/cc/bcc), redirect (url), and webhook (url). Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => __( 'The target post/page ID.', 'emcp-tools' ),
						),
						'parent_id'    => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID to insert the form into.', 'emcp-tools' ),
						),
						'position'     => array(
							'type'        => 'integer',
							'description' => __( 'Insert position within parent (-1 = append). Default: -1.', 'emcp-tools' ),
						),
						'form_name'    => array(
							'type'        => 'string',
							'description' => __( 'Form name (internal identifier). Required.', 'emcp-tools' ),
						),
						'fields'       => array(
							'type'        => 'array',
							'description' => __( 'Array of form field definitions.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'             => array( 'type' => 'string', 'enum' => array( 'text', 'email', 'textarea', 'url', 'tel', 'select', 'radio', 'checkbox', 'number', 'date', 'time', 'upload', 'acceptance', 'password', 'html', 'hidden', 'step' ), 'description' => 'Field type.' ),
									'label'            => array( 'type' => 'string', 'description' => 'Field label.' ),
									'placeholder'      => array( 'type' => 'string', 'description' => 'Placeholder text.' ),
									'required'         => array( 'type' => 'boolean', 'description' => 'Whether field is required.' ),
									'width'            => array( 'type' => 'number', 'enum' => array( 100, 80, 75, 66, 50, 33, 25, 20 ), 'description' => 'Column width in percent.' ),
									'options'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Options for select/radio/checkbox fields.' ),
									'rows'             => array( 'type' => 'integer', 'description' => 'Rows for textarea fields.' ),
									'default_value'    => array( 'type' => 'string', 'description' => 'Default value.' ),
									'html'             => array( 'type' => 'string', 'description' => 'HTML content for html type fields.' ),
									'max_file_size'    => array( 'type' => 'integer', 'description' => 'Max file size in MB (upload fields).' ),
									'allowed_types'    => array( 'type' => 'string', 'description' => 'Comma-separated file extensions (upload fields).' ),
									'multiple_upload'  => array( 'type' => 'boolean', 'description' => 'Allow multiple file upload.' ),
									'acceptance_text'  => array( 'type' => 'string', 'description' => 'Acceptance field text.' ),
									'checked'          => array( 'type' => 'boolean', 'description' => 'Checked by default (acceptance/checkbox).' ),
								),
							),
						),
						'actions'      => array(
							'type'        => 'object',
							'description' => __( 'Submit actions configuration.', 'emcp-tools' ),
							'properties'  => array(
								'email'    => array(
									'type'       => 'object',
									'properties' => array(
										'to'       => array( 'type' => 'string', 'description' => 'Recipient email(s). Use [field_id] shortcodes.' ),
										'subject'  => array( 'type' => 'string', 'description' => 'Email subject.' ),
										'from'     => array( 'type' => 'string', 'description' => 'From email address.' ),
										'from_name' => array( 'type' => 'string', 'description' => 'From name.' ),
										'reply_to' => array( 'type' => 'string', 'description' => 'Reply-to email.' ),
										'cc'       => array( 'type' => 'string', 'description' => 'CC email(s).' ),
										'bcc'      => array( 'type' => 'string', 'description' => 'BCC email(s).' ),
										'content_type' => array( 'type' => 'string', 'enum' => array( 'html', 'plain' ), 'description' => 'Email content type. Default: html.' ),
									),
								),
								'redirect' => array(
									'type'       => 'object',
									'properties' => array(
										'url' => array( 'type' => 'string', 'description' => 'Redirect URL after successful submission.' ),
									),
								),
								'webhook'  => array(
									'type'       => 'object',
									'properties' => array(
										'url' => array( 'type' => 'string', 'description' => 'Webhook URL to POST submission data to.' ),
									),
								),
							),
						),
						'submit_button' => array(
							'type'        => 'object',
							'description' => __( 'Submit button settings.', 'emcp-tools' ),
							'properties'  => array(
								'text'       => array( 'type' => 'string', 'description' => 'Button text. Default: Send.' ),
								'size'       => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => 'Button size.' ),
								'full_width' => array( 'type' => 'boolean', 'description' => 'Full width button.' ),
								'align'      => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => 'Button alignment.' ),
								'icon'       => array( 'type' => 'object', 'properties' => array( 'value' => array( 'type' => 'string' ), 'library' => array( 'type' => 'string' ) ), 'description' => 'Button icon: {value, library}.' ),
								'icon_align' => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => 'Icon position.' ),
							),
						),
						'settings'     => array(
							'type'        => 'object',
							'description' => __( 'Additional form settings.', 'emcp-tools' ),
							'properties'  => array(
								'show_labels'            => array( 'type' => 'boolean', 'description' => 'Show field labels.' ),
								'mark_required'          => array( 'type' => 'boolean', 'description' => 'Mark required fields with asterisk.' ),
								'input_size'             => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => 'Input field size.' ),
								'success_message'        => array( 'type' => 'string', 'description' => 'Message after successful submission.' ),
								'error_message'          => array( 'type' => 'string', 'description' => 'Message on submission error.' ),
								'required_field_message' => array( 'type' => 'string', 'description' => 'Validation message for required fields.' ),
							),
						),
					),
					'required'     => array( 'post_id', 'parent_id', 'form_name', 'fields' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'          => array( 'type' => 'boolean' ),
						'element_id'       => array( 'type' => 'string' ),
						'form_name'        => array( 'type' => 'string' ),
						'field_count'      => array( 'type' => 'integer' ),
						'actions'          => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'post_id'          => array( 'type' => 'integer' ),
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
		return array( 'emcp-tools/create-form' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$post_id   = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$parent_id = isset( $input['parent_id'] ) ? sanitize_text_field( (string) $input['parent_id'] ) : '';
		$position  = isset( $input['position'] ) ? intval( $input['position'] ) : -1;
		$form_name = isset( $input['form_name'] ) ? sanitize_text_field( $input['form_name'] ) : '';
		$fields    = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();

		if ( ! $post_id || ! $parent_id || empty( $form_name ) || empty( $fields ) ) {
			return new WP_Error(
				'missing_parameters',
				__( 'post_id, parent_id, form_name, and fields are required.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$form_fields = array();
		foreach ( $fields as $index => $field ) {
			$entry = $this->build_field( $field, $index );
			if ( $entry ) {
				$form_fields[] = $entry;
			}
		}

		if ( empty( $form_fields ) ) {
			return new WP_Error(
				'no_valid_fields',
				__( 'No valid fields provided.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$settings = array(
			'form_name'   => $form_name,
			'form_fields' => $form_fields,
		);

		// Submit button.
		$button = isset( $input['submit_button'] ) && is_array( $input['submit_button'] ) ? $input['submit_button'] : array();
		if ( ! empty( $button['text'] ) ) {
			$settings['button_text'] = sanitize_text_field( $button['text'] );
		} else {
			$settings['button_text'] = __( 'Send', 'emcp-tools' );
		}
		if ( ! empty( $button['size'] ) ) {
			$settings['button_size'] = sanitize_text_field( $button['size'] );
		}
		if ( ! empty( $button['full_width'] ) ) {
			$settings['button_width'] = '100';
		}
		if ( ! empty( $button['align'] ) ) {
			$settings['button_align'] = sanitize_text_field( $button['align'] );
		}
		if ( ! empty( $button['icon'] ) && is_array( $button['icon'] ) ) {
			$settings['selected_button_icon'] = $button['icon'];
		}
		if ( ! empty( $button['icon_align'] ) ) {
			$settings['button_icon_align'] = sanitize_text_field( $button['icon_align'] );
		}

		// Actions.
		$actions       = isset( $input['actions'] ) && is_array( $input['actions'] ) ? $input['actions'] : array();
		$action_names  = array();
		$action_config = $this->build_actions( $actions, $action_names );
		$settings      = array_merge( $settings, $action_config );
		$settings['submit_actions'] = ! empty( $action_names ) ? $action_names : array( 'email' );

		// Additional settings.
		$form_settings = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();
		if ( isset( $form_settings['show_labels'] ) ) {
			$settings['show_labels'] = $form_settings['show_labels'] ? 'yes' : '';
		}
		if ( isset( $form_settings['mark_required'] ) ) {
			$settings['mark_required'] = $form_settings['mark_required'] ? 'yes' : '';
		}
		if ( ! empty( $form_settings['input_size'] ) ) {
			$settings['input_size'] = sanitize_text_field( $form_settings['input_size'] );
		}
		if ( ! empty( $form_settings['success_message'] ) ) {
			$settings['success_message'] = sanitize_text_field( $form_settings['success_message'] );
		}
		if ( ! empty( $form_settings['error_message'] ) ) {
			$settings['error_message'] = sanitize_text_field( $form_settings['error_message'] );
		}
		if ( ! empty( $form_settings['required_field_message'] ) ) {
			$settings['required_field_message'] = sanitize_text_field( $form_settings['required_field_message'] );
		}

		$widget = $this->factory->create_widget( 'form', $settings );

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );
		if ( ! $inserted ) {
			return new WP_Error(
				'parent_not_found',
				sprintf( __( 'Parent container element "%s" not found.', 'emcp-tools' ), $parent_id ),
				array( 'status' => 404 )
			);
		}

		$result = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'     => true,
			'element_id'  => $widget['id'],
			'form_name'   => $form_name,
			'field_count' => count( $form_fields ),
			'actions'     => $settings['submit_actions'],
			'post_id'     => $post_id,
		);
	}

	private function build_field( array $field, int $index ): ?array {
		if ( empty( $field['type'] ) ) {
			return null;
		}

		$type = sanitize_text_field( $field['type'] );
		$id   = EMCP_Tools_Id_Generator::generate();

		$entry = array(
			'_id'        => $id,
			'field_type' => $type,
			'field_label' => ! empty( $field['label'] ) ? sanitize_text_field( $field['label'] ) : '',
		);

		if ( ! empty( $field['placeholder'] ) ) {
			$entry['placeholder'] = sanitize_text_field( $field['placeholder'] );
		}
		if ( ! empty( $field['required'] ) ) {
			$entry['required'] = 'yes';
		}
		if ( isset( $field['width'] ) && in_array( (int) $field['width'], array( 100, 80, 75, 66, 50, 33, 25, 20 ), true ) ) {
			$entry['width'] = (string) $field['width'];
		}
		if ( ! empty( $field['default_value'] ) ) {
			$entry['field_value'] = sanitize_text_field( $field['default_value'] );
		}

		if ( in_array( $type, array( 'select', 'radio', 'checkbox' ), true ) ) {
			if ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
				$entry['field_options'] = implode( "\n", array_map( 'sanitize_text_field', $field['options'] ) );
			}
		}

		switch ( $type ) {
			case 'textarea':
				if ( isset( $field['rows'] ) ) {
					$entry['rows'] = absint( $field['rows'] );
				}
				break;
			case 'html':
				if ( ! empty( $field['html'] ) ) {
					$entry['field_html'] = $field['html'];
				}
				break;
			case 'upload':
				if ( ! empty( $field['max_file_size'] ) ) {
					$entry['file_sizes'] = absint( $field['max_file_size'] );
				}
				if ( ! empty( $field['allowed_types'] ) ) {
					$entry['file_types'] = sanitize_text_field( $field['allowed_types'] );
				}
				if ( ! empty( $field['multiple_upload'] ) ) {
					$entry['allow_multiple_upload'] = 'yes';
				}
				break;
			case 'acceptance':
				if ( ! empty( $field['acceptance_text'] ) ) {
					$entry['acceptance_text'] = sanitize_text_field( $field['acceptance_text'] );
				}
				if ( ! empty( $field['checked'] ) ) {
					$entry['checked_by_default'] = 'yes';
				}
				break;
			case 'checkbox':
				if ( ! empty( $field['checked'] ) ) {
					$entry['checked_by_default'] = 'yes';
				}
				break;
		}

		return $entry;
	}

	private function build_actions( array $actions, array &$action_names ): array {
		$config  = array();
		$enabled = array();

		if ( isset( $actions['email'] ) && is_array( $actions['email'] ) ) {
			$enabled[] = 'email';
			$email = $actions['email'];
			if ( ! empty( $email['to'] ) ) {
				$config['email_to'] = sanitize_text_field( $email['to'] );
			}
			if ( ! empty( $email['subject'] ) ) {
				$config['email_subject'] = sanitize_text_field( $email['subject'] );
			}
			if ( ! empty( $email['from'] ) ) {
				$config['email_from'] = sanitize_email( $email['from'] );
			}
			if ( ! empty( $email['from_name'] ) ) {
				$config['email_from_name'] = sanitize_text_field( $email['from_name'] );
			}
			if ( ! empty( $email['reply_to'] ) ) {
				$config['email_reply_to'] = sanitize_text_field( $email['reply_to'] );
			}
			if ( ! empty( $email['cc'] ) ) {
				$config['email_cc'] = sanitize_text_field( $email['cc'] );
			}
			if ( ! empty( $email['bcc'] ) ) {
				$config['email_bcc'] = sanitize_text_field( $email['bcc'] );
			}
			if ( ! empty( $email['content_type'] ) ) {
				$config['email_content_type'] = sanitize_text_field( $email['content_type'] );
			}
		}

		if ( isset( $actions['redirect'] ) && is_array( $actions['redirect'] ) && ! empty( $actions['redirect']['url'] ) ) {
			$enabled[] = 'redirect';
			$config['redirect_to'] = esc_url_raw( $actions['redirect']['url'] );
		}

		if ( isset( $actions['webhook'] ) && is_array( $actions['webhook'] ) && ! empty( $actions['webhook']['url'] ) ) {
			$enabled[] = 'webhook';
			$config['webhooks'] = esc_url_raw( $actions['webhook']['url'] );
		}

		$action_names = $enabled;
		return $config;
	}
}
