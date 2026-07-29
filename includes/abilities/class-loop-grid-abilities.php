<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Loop_Grid_Abilities {

	private $data;
	private $factory;

	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	public function register(): void {
		$this->register_create_loop_grid();
		$this->register_list_loop_templates();
	}

	private function register_create_loop_grid(): void {
		emcp_tools_register_ability(
			'emcp-tools/create-loop-grid',
			array(
				'label'               => __( 'Create Loop Grid', 'emcp-tools' ),
				'description'         => __( 'Creates a complete Loop Grid on a page in one call: creates a Loop Item template (elementor_library, type loop-item), adds a loop-grid widget to the target page configured to use that template, and optionally sets query/pagination/layout settings. Specify a parent container element_id and position to place the grid. Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_loop_grid' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'              => array(
							'type'        => 'integer',
							'description' => __( 'The target post/page ID where the loop grid will be added.', 'emcp-tools' ),
						),
						'parent_id'            => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID to insert the loop grid into.', 'emcp-tools' ),
						),
						'position'             => array(
							'type'        => 'integer',
							'description' => __( 'Insert position within parent (-1 = append). Default: -1.', 'emcp-tools' ),
						),
						'loop_template_title'  => array(
							'type'        => 'string',
							'description' => __( 'Title for the new Loop Item template. Auto-generated if omitted.', 'emcp-tools' ),
						),
						'loop_template_id'     => array(
							'type'        => 'integer',
							'description' => __( 'Existing loop template ID to reuse. If omitted, a new one is created.', 'emcp-tools' ),
						),
						'columns'              => array(
							'type'        => 'integer',
							'description' => __( 'Number of grid columns. Default: 3.', 'emcp-tools' ),
						),
						'columns_tablet'       => array(
							'type'        => 'integer',
							'description' => __( 'Columns on tablet. Default: 2.', 'emcp-tools' ),
						),
						'columns_mobile'       => array(
							'type'        => 'integer',
							'description' => __( 'Columns on mobile. Default: 1.', 'emcp-tools' ),
						),
						'posts_per_page'       => array(
							'type'        => 'integer',
							'description' => __( 'Items per page. Default: 6.', 'emcp-tools' ),
						),
						'masonry'              => array(
							'type'        => 'boolean',
							'description' => __( 'Enable masonry layout.', 'emcp-tools' ),
						),
						'equal_height'         => array(
							'type'        => 'boolean',
							'description' => __( 'Equal height items.', 'emcp-tools' ),
						),
						'post_type'            => array(
							'type'        => 'string',
							'description' => __( 'Query post type: post, page, by_id, current_query, related. Default: post.', 'emcp-tools' ),
						),
						'orderby'              => array(
							'type'        => 'string',
							'description' => __( 'Sort field: post_date, post_title, menu_order, modified, comment_count, rand. Default: post_date.', 'emcp-tools' ),
						),
						'order'                => array(
							'type'        => 'string',
							'enum'        => array( 'asc', 'desc' ),
							'description' => __( 'Sort order. Default: desc.', 'emcp-tools' ),
						),
						'offset'               => array(
							'type'        => 'integer',
							'description' => __( 'Query offset.', 'emcp-tools' ),
						),
						'include_terms'        => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => __( 'Term IDs to include (requires include_by to be set to "terms").', 'emcp-tools' ),
						),
						'include_by'           => array(
							'type'        => 'string',
							'enum'        => array( 'terms', 'authors' ),
							'description' => __( 'Include by terms or authors.', 'emcp-tools' ),
						),
						'exclude'              => array(
							'type'        => 'string',
							'enum'        => array( 'current_post', 'manual_selection', 'terms', 'authors' ),
							'description' => __( 'Exclude criteria.', 'emcp-tools' ),
						),
						'pagination'           => array(
							'type'        => 'string',
							'enum'        => array( 'numbers', 'prev_next', 'numbers_and_prev_next', '' ),
							'description' => __( 'Pagination type.', 'emcp-tools' ),
						),
						'pagination_prev_label' => array(
							'type'        => 'string',
							'description' => __( 'Previous page label.', 'emcp-tools' ),
						),
						'pagination_next_label' => array(
							'type'        => 'string',
							'description' => __( 'Next page label.', 'emcp-tools' ),
						),
					),
					'required'     => array( 'post_id', 'parent_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'              => array( 'type' => 'boolean' ),
						'loop_template_id'     => array( 'type' => 'integer' ),
						'loop_template_title'  => array( 'type' => 'string' ),
						'loop_template_url'    => array( 'type' => 'string' ),
						'grid_element_id'      => array( 'type' => 'string' ),
						'grid_settings'        => array( 'type' => 'object' ),
						'post_id'              => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	private function register_list_loop_templates(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-loop-templates',
			array(
				'label'               => __( 'List Loop Templates', 'emcp-tools' ),
				'description'         => __( 'Lists all existing Loop Item templates (elementor_library with type loop-item). Returns template IDs, titles, and edit URLs for use with create-loop-grid.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_loop_templates' ),
				'permission_callback' => array( $this, 'can_read' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Search by title.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'templates' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'title'    => array( 'type' => 'string' ),
									'edit_url' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function get_ability_names(): array {
		return array( 'emcp-tools/create-loop-grid', 'emcp-tools/list-loop-templates' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute_list_loop_templates( $input ) {
		$args = array(
			'post_type'      => 'elementor_library',
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'tax_query'      => array(
				array(
					'taxonomy' => 'elementor_library_type',
					'field'    => 'slug',
					'terms'    => 'loop-item',
				),
			),
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		$posts     = get_posts( $args );
		$templates = array();

		foreach ( $posts as $post ) {
			$templates[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'edit_url' => admin_url( 'post.php?post=' . $post->ID . '&action=elementor' ),
			);
		}

		return array( 'templates' => $templates );
	}

	public function execute_create_loop_grid( $input ) {
		$post_id    = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$parent_id  = isset( $input['parent_id'] ) ? sanitize_text_field( (string) $input['parent_id'] ) : '';
		$position   = isset( $input['position'] ) ? intval( $input['position'] ) : -1;

		if ( ! $post_id || ! $parent_id ) {
			return new WP_Error(
				'missing_parameters',
				__( 'Post ID and parent container element ID are required.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		// Create or reuse loop item template.
		$template_id = isset( $input['loop_template_id'] ) ? absint( $input['loop_template_id'] ) : 0;

		if ( ! $template_id ) {
			$template_title = ! empty( $input['loop_template_title'] )
				? sanitize_text_field( $input['loop_template_title'] )
				: sprintf( __( 'Loop Item - %s', 'emcp-tools' ), get_the_title( $post_id ) );

			$template_id = wp_insert_post(
				array(
					'post_title'  => $template_title,
					'post_status' => 'publish',
					'post_type'   => 'elementor_library',
					'meta_input'  => array(
						'_elementor_edit_mode'     => 'builder',
						'_elementor_template_type' => 'loop-item',
					),
				),
				true
			);

			if ( is_wp_error( $template_id ) ) {
				return $template_id;
			}

			wp_set_object_terms( $template_id, 'loop-item', 'elementor_library_type' );
		}

		// Build loop grid widget settings.
		$grid_settings = array(
			'_skin'          => 'post',
			'template_id'    => (string) $template_id,
			'columns'        => isset( $input['columns'] ) ? absint( $input['columns'] ) : 3,
			'columns_tablet' => isset( $input['columns_tablet'] ) ? absint( $input['columns_tablet'] ) : 2,
			'columns_mobile' => isset( $input['columns_mobile'] ) ? absint( $input['columns_mobile'] ) : 1,
			'posts_per_page' => isset( $input['posts_per_page'] ) ? absint( $input['posts_per_page'] ) : 6,
			'post_query_post_type' => isset( $input['post_type'] ) ? sanitize_text_field( $input['post_type'] ) : 'post',
		);

		if ( isset( $input['masonry'] ) && $input['masonry'] ) {
			$grid_settings['masonry'] = 'yes';
		}
		if ( isset( $input['equal_height'] ) && $input['equal_height'] ) {
			$grid_settings['equal_height'] = 'yes';
		}

		$query_mapped = false;

		if ( ! empty( $input['orderby'] ) ) {
			$grid_settings['post_query_orderby'] = sanitize_text_field( $input['orderby'] );
			$query_mapped = true;
		}
		if ( ! empty( $input['order'] ) ) {
			$grid_settings['post_query_order'] = sanitize_text_field( $input['order'] );
			$query_mapped = true;
		}
		if ( isset( $input['offset'] ) && '' !== $input['offset'] ) {
			$grid_settings['post_query_offset'] = absint( $input['offset'] );
			$query_mapped = true;
		}
		if ( ! empty( $input['include_by'] ) ) {
			$grid_settings['post_query_include'] = sanitize_text_field( $input['include_by'] );
			$query_mapped = true;
		}
		if ( ! empty( $input['exclude'] ) ) {
			$grid_settings['post_query_exclude'] = sanitize_text_field( $input['exclude'] );
			$query_mapped = true;
		}

		// Pagination.
		if ( ! empty( $input['pagination'] ) ) {
			$grid_settings['pagination_type'] = sanitize_text_field( $input['pagination'] );
			if ( ! empty( $input['pagination_prev_label'] ) ) {
				$grid_settings['pagination_prev_label'] = sanitize_text_field( $input['pagination_prev_label'] );
			}
			if ( ! empty( $input['pagination_next_label'] ) ) {
				$grid_settings['pagination_next_label'] = sanitize_text_field( $input['pagination_next_label'] );
			}
		}

		// Create the loop-grid widget.
		$widget = $this->factory->create_widget( 'loop-grid', $grid_settings );

		// Get page data and insert the widget.
		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );

		if ( ! $inserted ) {
			return new WP_Error(
				'parent_not_found',
				sprintf(
					__( 'Parent container element "%s" not found.', 'emcp-tools' ),
					$parent_id
				),
				array( 'status' => 404 )
			);
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'             => true,
			'loop_template_id'    => $template_id,
			'loop_template_title' => get_the_title( $template_id ),
			'loop_template_url'   => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
			'grid_element_id'     => $widget['id'],
			'grid_settings'       => $grid_settings,
			'post_id'             => $post_id,
		);
	}
}
