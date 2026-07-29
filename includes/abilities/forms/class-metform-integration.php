<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_MetForm_Integration extends EMCP_Tools_Form_Integration {

	public function id(): string {
		return 'metform';
	}

	public function label(): string {
		return 'MetForm';
	}

	public function is_active(): bool {
		return defined( 'METFORM_VERSION' ) || defined( 'MF_PLUGIN_DIR' );
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
				'desc' => 'List all MetForm forms (id, title).',
			),
			'get-form'   => array(
				'mode' => 'read',
				'run'  => array( $this, 'op_get_form' ),
				'perm' => $can_read,
				'desc' => 'Get one form by { form_id }: settings and fields.',
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
		$forms = get_posts(
			array(
				'post_type'      => 'metform-form',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out   = array();
		foreach ( $forms as $post ) {
			$out[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
			);
		}
		return array( 'forms' => $out );
	}

	public function op_get_form( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$post = get_post( $id );
		if ( ! $post || 'metform-form' !== $post->post_type ) {
			return new WP_Error(
				'form_not_found',
				sprintf(
					/* translators: %d: form id */
					__( 'No MetForm form with id %d.', 'emcp-tools' ),
					$id
				),
				array( 'status' => 404 )
			);
		}
		$settings = get_post_meta( $id, '_mf_form_settings', true );
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'settings' => $settings ? $settings : array(),
		);
	}

	public function op_get_entries( array $args ) {
		$id = isset( $args['form_id'] ) ? absint( $args['form_id'] ) : 0;
		if ( ! $id ) {
			return new WP_Error( 'missing_argument', __( 'Missing required argument: form_id.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		$entries = get_posts(
			array(
				'post_type'      => 'metform-entry',
				'post_parent'    => $id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out     = array();
		foreach ( $entries as $entry ) {
			$data = get_post_meta( $entry->ID, 'mf_entry_data', true );
			$out[] = array(
				'id'          => $entry->ID,
				'form_id'     => $entry->post_parent,
				'entry_data'  => $data ? $data : array(),
				'date'        => $entry->post_date,
			);
		}
		return array( 'entries' => $out );
	}
}
