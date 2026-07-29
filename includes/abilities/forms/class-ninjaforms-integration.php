<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_NinjaForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'ninjaforms';
	}

	public function label(): string {
		return 'Ninja Forms';
	}

	public function is_active(): bool {
		return defined( 'NF_SERVER_URL' ) || class_exists( 'Ninja_Forms' );
	}

	protected function operations(): array {
		$can_read = static function (): bool {
			return current_user_can( 'edit_posts' );
		};

		return array(
			'list-forms' => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_list_forms' ),
				'perm' => $can_read,
				'desc' => 'List all Ninja Forms forms (id, title, field count).',
			),
			'get-form'   => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_form' ),
				'perm' => $can_read,
				'desc' => 'Get one form by { form_id }: fields and settings.',
			),
			'get-entries' => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_entries' ),
				'perm' => $can_read,
				'desc' => 'List submissions for a form by { form_id }.',
			),
		);
	}

	public function op_list_forms( array $args ): array {
		$out   = array();
		$forms = \Ninja_Forms()->form()->get_forms();
		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$field_count = 0;
				try {
					$field_count = count( $form->get_fields() );
				} catch ( \Exception $e ) {
					$field_count = 0;
				}
				$out[] = array(
					'id'          => $form->get_id(),
					'title'       => $form->get_setting( 'title' ),
					'field_count' => $field_count,
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
		try {
			$form = \Ninja_Forms()->form( $id )->get();
		} catch ( \Exception $e ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Ninja Forms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		if ( ! $form || ! $form->get_id() ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Ninja Forms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		$fields = array();
		try {
			$form_fields = $form->get_fields();
			if ( is_array( $form_fields ) ) {
				foreach ( $form_fields as $field ) {
					$fields[] = array(
						'id'    => $field->get_id(),
						'key'   => $field->get_setting( 'key' ),
						'type'  => $field->get_setting( 'type' ),
						'label' => $field->get_setting( 'label' ),
					);
				}
			}
		} catch ( \Exception $e ) {
			$fields = array();
		}
		return array(
			'id'     => $form->get_id(),
			'title'  => $form->get_setting( 'title' ),
			'fields' => $fields,
		);
	}

	public function op_get_entries( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$out = array();
		try {
			$subs = \Ninja_Forms()->form( $id )->get_subs();
			if ( is_array( $subs ) ) {
				foreach ( $subs as $sub ) {
					$out[] = array(
						'id'      => $sub->get_id(),
						'form_id' => $sub->get_form_id(),
						'fields'  => $sub->get_field_values(),
						'date'    => $sub->get_action_date(),
					);
				}
			}
		} catch ( \Exception $e ) {
			return array( 'entries' => array() );
		}
		return array( 'entries' => $out );
	}
}
