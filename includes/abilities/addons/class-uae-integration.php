<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_UAE_Integration extends EMCP_Tools_Addon_Pack_Integration {

	public function id(): string {
		return 'uae';
	}

	public function label(): string {
		return __( 'Ultimate Addons for Elementor', 'emcp-tools' );
	}

	public function is_available(): bool {
		return defined( 'UAEL_VER' ) || class_exists( 'UAEL_Loader' );
	}

	protected $widget_prefix = 'uael-';

	protected function operations(): array {
		$ops = array(
			'list-widgets'      => array(
				'desc' => __( 'Compact index of UAE widgets. Optional filters: { category, search }.', 'emcp-tools' ),
				'run'  => array( $this, 'execute_list_widgets' ),
			),
			'get-widget-schema' => array(
				'desc' => __( 'Return the schema for one UAE widget type. Arguments: { name } or { names: [...] }, optional { full: true } for raw controls.', 'emcp-tools' ),
				'run'  => array( $this, 'execute_get_widget_schema' ),
			),
		);

		if ( defined( 'HFE_VER' ) ) {
			$ops['list-templates'] = array(
				'desc' => __( 'List Header Footer Elementor templates (header/footer/block).', 'emcp-tools' ),
				'run'  => array( $this, 'execute_list_templates' ),
			);
			$ops['get-template'] = array(
				'desc' => __( 'Get a single HFE template by ID. Arguments: { template_id }.', 'emcp-tools' ),
				'run'  => array( $this, 'execute_get_template' ),
			);
		}

		return $ops;
	}

	public function execute_list_templates( $input ): array {
		$type = isset( $input['type'] ) ? sanitize_key( (string) $input['type'] ) : '';

		$query = new WP_Query( array(
			'post_type'      => 'elementor-hf',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'meta_key'       => '',
		) );

		$rows = array();
		foreach ( $query->posts as $post ) {
			$template_type = get_post_meta( $post->ID, 'ehf_template_type', true );
			if ( '' !== $type && $type !== $template_type ) {
				continue;
			}
			$rows[] = array(
				'id'          => $post->ID,
				'title'       => $post->post_title,
				'type'        => $template_type,
				'edit_link'   => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
			);
		}

		return array(
			'templates' => $rows,
			'total'     => count( $rows ),
		);
	}

	public function execute_get_template( $input ) {
		$id = absint( $input['template_id'] ?? 0 );
		if ( ! $id ) {
			return new WP_Error( 'missing_id', __( 'A template_id is required.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$post = get_post( $id );
		if ( ! $post || 'elementor-hf' !== $post->post_type ) {
			return new WP_Error( 'not_found', __( 'Template not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		return array(
			'id'            => $post->ID,
			'title'         => $post->post_title,
			'type'          => get_post_meta( $post->ID, 'ehf_template_type', true ),
			'display_on'    => get_post_meta( $post->ID, 'ehf_display_on', true ),
			'edit_link'     => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
			'elementor_url' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
		);
	}
}
