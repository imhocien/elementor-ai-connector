<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Formidable_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'formidable';
	}

	public function label(): string {
		return 'Formidable Forms';
	}

	public function is_active(): bool {
		return class_exists( 'FrmForm' );
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
				'desc' => 'List all Formidable Forms forms (id, name, key).',
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
				'desc' => 'List entries for a form by { form_id }.',
			),
		);
	}

	public function op_list_forms( array $args ): array {
		$out   = array();
		$forms = \FrmForm::getAll();
		if ( is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$field_count = 0;
				if ( ! empty( $form->id ) ) {
					$field_count = \FrmField::get_count( $form->id );
				}
				$out[] = array(
					'id'          => $form->id,
					'name'        => $form->name,
					'key'         => $form->form_key,
					'field_count' => (int) $field_count,
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
		$form = \FrmForm::getOne( $id );
		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Formidable Forms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		$fields = \FrmField::get_all_for_form( $id );
		$out    = array();
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				$out[] = array(
					'id'    => $field->id,
					'key'   => $field->field_key,
					'type'  => $field->type,
					'name'  => $field->name,
				);
			}
		}
		return array(
			'id'     => $form->id,
			'name'   => $form->name,
			'key'    => $form->form_key,
			'fields' => $out,
		);
	}

	public function op_get_entries( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$entries = \FrmEntry::getAll( array( 'it.form_id' => $id ), '', '', true, false );
		$out     = array();
		if ( is_array( $entries ) ) {
			foreach ( $entries as $entry ) {
				$out[] = array(
					'id'         => $entry->id,
					'item_key'   => $entry->item_key,
					'metas'      => $entry->metas,
					'created_at' => $entry->created_at,
					'updated_at' => $entry->updated_at,
				);
			}
		}
		return array( 'entries' => $out );
	}
}
