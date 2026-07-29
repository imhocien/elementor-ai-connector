<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_GravityForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'gravityforms';
	}

	public function label(): string {
		return 'Gravity Forms';
	}

	public function is_active(): bool {
		return class_exists( 'GFAPI' );
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
				'desc' => 'List all Gravity Forms forms (id, title, field count).',
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
				'desc' => 'Update a form property by { form_id, property, value }.',
			),
		);
	}

	public function op_list_forms( array $args ): array {
		$forms = \GFAPI::get_forms();
		$out   = array();
		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$out[] = array(
					'id'          => isset( $form['id'] ) ? $form['id'] : 0,
					'title'       => isset( $form['title'] ) ? $form['title'] : '',
					'field_count' => isset( $form['fields'] ) && is_array( $form['fields'] ) ? count( $form['fields'] ) : 0,
				);
			}
		}
		return array( 'forms' => $out );
	}

	public function op_get_form( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$form = \GFAPI::get_form( $id );
		if ( ! $form || is_wp_error( $form ) ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Gravity Forms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		return array(
			'form' => $form,
		);
	}

	public function op_get_entries( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$entries = \GFAPI::get_entries( $id );
		if ( is_wp_error( $entries ) ) {
			return $entries;
		}
		return array( 'entries' => is_array( $entries ) ? $entries : array() );
	}

	public function op_get_entry( array $args ) {
		$id = isset( $args['entry_id'] ) ? absint( $args['entry_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: entry_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$entry = \GFAPI::get_entry( $id );
		if ( is_wp_error( $entry ) ) {
			return $entry;
		}
		return array( 'entry' => $entry );
	}

	public function op_update_form_settings( array $args ) {
		$id   = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		$prop = isset( $args['property'] ) ? sanitize_key( $args['property'] ) : '';
		if ( ! $id || ! $prop ) {
			return new WP_Error( 'missing_argument', __( 'Missing required arguments: form_id, property.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		if ( ! isset( $args['value'] ) ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: value.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$result = \GFAPI::update_form_property( $id, $prop, $args['value'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'updated' => true, 'form_id' => $id, 'property' => $prop );
	}
}
