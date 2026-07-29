<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_WPForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'wpforms';
	}

	public function label(): string {
		return 'WPForms';
	}

	public function is_active(): bool {
		return function_exists( 'wpforms' );
	}

	protected function operations(): array {
		$can_read  = static function (): bool {
			return current_user_can( 'edit_posts' );
		};
		$can_write = static function (): bool {
			return current_user_can( 'manage_options' );
		};

		return array(
			'list-forms'           => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_forms' ),
				'perm' => $can_read,
				'desc' => 'List all WPForms forms (id, title, field count).',
			),
			'get-form'             => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_form' ),
				'perm' => $can_read,
				'desc' => 'Get one form by { form_id }: fields and settings.',
			),
			'get-entries'           => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_entries' ),
				'perm' => $can_read,
				'desc' => 'List entries for a form by { form_id }.',
			),
			'get-entry'             => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_entry' ),
				'perm' => $can_read,
				'desc' => 'Get one entry by { entry_id }.',
			),
			'update-form-settings' => array(
				'mode' => 'write',
				'run'  => array( $this, 'op_update_form_settings' ),
				'perm' => $can_write,
				'desc' => 'Update form settings by { form_id, settings }.',
			),
		);
	}

	private function form( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$form = wpforms()->form->get( $id );
		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No WPForms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		return $form;
	}

	public function op_list_forms( array $args ): array {
		$out  = array();
		$forms = wpforms()->form->get( '', array( 'orderby' => 'title' ) );
		if ( ! is_array( $forms ) ) {
			return array( 'forms' => array() );
		}
		foreach ( $forms as $form ) {
			$fields = array();
			if ( ! empty( $form->post_content ) ) {
				$data = json_decode( $form->post_content, true );
				if ( is_array( $data ) && isset( $data['fields'] ) ) {
					$fields = $data['fields'];
				}
			}
			$out[] = array(
				'id'          => $form->id,
				'title'       => $form->post_title,
				'field_count' => count( $fields ),
			);
		}
		return array( 'forms' => $out );
	}

	public function op_get_form( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		$fields = array();
		$data   = array();
		if ( ! empty( $form->post_content ) ) {
			$data = json_decode( $form->post_content, true );
			if ( is_array( $data ) && isset( $data['fields'] ) ) {
				$fields = $data['fields'];
			}
		}
		return array(
			'id'     => $form->id,
			'title'  => $form->post_title,
			'fields' => $fields,
			'settings' => isset( $data['settings'] ) ? $data['settings'] : array(),
		);
	}

	public function op_get_entries( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		$entries = wpforms()->entry->get_entries( $form->id );
		$out     = array();
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				$out[] = array(
					'id'      => $entry->id,
					'form_id' => $entry->form_id,
					'fields'  => json_decode( $entry->fields, true ),
					'date'    => $entry->date,
					'status'  => $entry->status,
				);
			}
		}
		return array( 'entries' => $out );
	}

	public function op_get_entry( array $args ) {
		$id = isset( $args['entry_id'] ) ? absint( $args['entry_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: entry_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$entry = wpforms()->entry->get( $id );
		if ( ! $entry ) {
			return new WP_Error(
				'entry_not_found',
				sprintf(
					/* translators: %d: entry id */
					__( 'No WPForms entry with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		return array(
			'id'      => $entry->id,
			'form_id' => $entry->form_id,
			'fields'  => json_decode( $entry->fields, true ),
			'date'    => $entry->date,
			'status'  => $entry->status,
		);
	}

	public function op_update_form_settings( array $args ) {
		$form = $this->form( $args );
		if ( is_wp_error( $form ) ) {
			return $form;
		}
		if ( ! isset( $args['settings'] ) || ! is_array( $args['settings'] ) ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: settings (object).', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$data = array();
		if ( ! empty( $form->post_content ) ) {
			$data = json_decode( $form->post_content, true );
		}
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$data['settings'] = array_merge( isset( $data['settings'] ) ? $data['settings'] : array(), $args['settings'] );
		wpforms()->form->update( $form->id, $data );
		return array( 'updated' => true, 'form_id' => $form->id );
	}
}
