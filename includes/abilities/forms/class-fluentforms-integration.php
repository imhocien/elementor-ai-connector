<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_FluentForms_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'fluentforms';
	}

	public function label(): string {
		return 'Fluent Forms';
	}

	public function is_active(): bool {
		return defined( 'FLUENTFORM' );
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
				'desc' => 'List all Fluent Forms forms (id, title, field count).',
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
			'get-entry'   => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_entry' ),
				'perm' => $can_read,
				'desc' => 'Get one submission by { entry_id }.',
			),
		);
	}

	public function op_list_forms( array $args ): array {
		$out   = array();
		$forms = wpFluent()->table( 'fluentform_forms' )->get();
		if ( $forms && is_array( $forms ) ) {
			foreach ( $forms as $form ) {
				$fields = array();
				if ( ! empty( $form->form_fields ) ) {
					$data = json_decode( $form->form_fields, true );
					if ( is_array( $data ) && isset( $data['fields'] ) ) {
						$fields = $data['fields'];
					}
				}
				$out[] = array(
					'id'          => $form->id,
					'title'       => $form->title,
					'field_count' => count( $fields ),
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
		$form = wpFluent()->table( 'fluentform_forms' )->find( $id );
		if ( ! $form ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No Fluent Forms form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		$fields     = array();
		$raw_fields = array();
		if ( ! empty( $form->form_fields ) ) {
			$data = json_decode( $form->form_fields, true );
			if ( is_array( $data ) ) {
				$raw_fields = $data;
				if ( isset( $data['fields'] ) ) {
					$fields = $data['fields'];
				}
			}
		}
		return array(
			'id'          => $form->id,
			'title'       => $form->title,
			'fields'      => $fields,
			'form_fields' => $raw_fields,
		);
	}

	public function op_get_entries( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$submissions = wpFluent()->table( 'fluentform_submissions' )->where( 'form_id', $id )->get();
		$out         = array();
		if ( $submissions && is_array( $submissions ) ) {
			foreach ( $submissions as $sub ) {
				$out[] = array(
					'id'       => $sub->id,
					'form_id'  => $sub->form_id,
					'response' => json_decode( $sub->response, true ),
					'status'   => $sub->status,
					'date'     => $sub->created_at,
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
		$sub = wpFluent()->table( 'fluentform_submissions' )->find( $id );
		if ( ! $sub ) {
			return new WP_Error(
				'entry_not_found',
				sprintf(
					/* translators: %d: entry id */
					__( 'No Fluent Forms submission with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		return array(
			'id'       => $sub->id,
			'form_id'  => $sub->form_id,
			'response' => json_decode( $sub->response, true ),
			'status'   => $sub->status,
			'date'     => $sub->created_at,
		);
	}
}
