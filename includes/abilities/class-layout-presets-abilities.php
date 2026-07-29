<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Layout_Presets_Abilities {

	private $data;
	private $factory;

	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/add-layout-preset',
			array(
				'label'               => __( 'Add Layout Preset', 'emcp-tools' ),
				'description'         => __( 'Creates common layout patterns in one call: hero (heading + text + buttons), features-grid (2-6 columns with icon + heading + text), sidebar-content (main + sidebar containers), and faq (accordion with Q&A items). Each preset creates properly nested containers with ready-to-edit widgets.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The target post/page ID.', 'emcp-tools' ),
						),
						'parent_id' => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID to insert into. Empty for top-level.', 'emcp-tools' ),
						),
						'position' => array(
							'type'        => 'integer',
							'description' => __( 'Insert position (-1 = append). Default: -1.', 'emcp-tools' ),
						),
						'preset'   => array(
							'type'        => 'string',
							'enum'        => array( 'hero', 'features-grid', 'sidebar-content', 'faq' ),
							'description' => __( 'Layout preset to create.', 'emcp-tools' ),
						),
						// Hero settings.
						'heading'  => array(
							'type'        => 'string',
							'description' => __( 'Hero heading text.', 'emcp-tools' ),
						),
						'text'     => array(
							'type'        => 'string',
							'description' => __( 'Hero body text.', 'emcp-tools' ),
						),
						'buttons'  => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'text' => array( 'type' => 'string' ),
									'link' => array( 'type' => 'string' ),
									'type' => array( 'type' => 'string', 'enum' => array( 'primary', 'secondary' ) ),
								),
							),
							'description' => __( 'Hero button definitions.', 'emcp-tools' ),
						),
						// Features grid settings.
						'features' => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'icon'        => array( 'type' => 'string', 'description' => 'Icon CSS class (e.g. "fas fa-star").' ),
									'title'       => array( 'type' => 'string', 'description' => 'Feature title.' ),
									'description' => array( 'type' => 'string', 'description' => 'Feature description.' ),
								),
							),
							'description' => __( 'Feature definitions for features-grid preset.', 'emcp-tools' ),
						),
						'columns'  => array(
							'type'        => 'integer',
							'description' => __( 'Number of feature columns (2-6, default: 3).', 'emcp-tools' ),
						),
						// Sidebar-content settings.
						'main_width' => array(
							'type'        => 'string',
							'enum'        => array( '66', '75', '50' ),
							'description' => __( 'Main content width in percent for sidebar-content preset. Default: 66.', 'emcp-tools' ),
						),
						// FAQ settings.
						'faq_items'  => array(
							'type'        => 'array',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'question' => array( 'type' => 'string' ),
									'answer'   => array( 'type' => 'string' ),
								),
							),
							'description' => __( 'FAQ question/answer pairs for faq preset.', 'emcp-tools' ),
						),
					),
					'required'     => array( 'post_id', 'preset' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'preset'       => array( 'type' => 'string' ),
						'container_id' => array( 'type' => 'string' ),
						'post_id'      => array( 'type' => 'integer' ),
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
		return array( 'emcp-tools/add-layout-preset' );
	}

	public function can_edit(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function execute( $input ) {
		$post_id   = isset( $input['post_id'] ) ? absint( $input['post_id'] ) : 0;
		$parent_id = isset( $input['parent_id'] ) ? sanitize_text_field( (string) $input['parent_id'] ) : '';
		$position  = isset( $input['position'] ) ? intval( $input['position'] ) : -1;
		$preset    = isset( $input['preset'] ) ? sanitize_text_field( $input['preset'] ) : '';

		if ( ! $post_id || ! $preset ) {
			return new WP_Error(
				'missing_parameters',
				__( 'post_id and preset are required.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}

		$page_data = $this->data->get_page_data( $post_id );

		switch ( $preset ) {
			case 'hero':
				$container = $this->build_hero( $input );
				break;
			case 'features-grid':
				$container = $this->build_features_grid( $input );
				break;
			case 'sidebar-content':
				$container = $this->build_sidebar_content( $input );
				break;
			case 'faq':
				$container = $this->build_faq( $input );
				break;
			default:
				return new WP_Error(
					'invalid_preset',
					sprintf( __( 'Unknown preset: %s', 'emcp-tools' ), $preset ),
					array( 'status' => 400 )
				);
		}

		$inserted = $this->data->insert_element( $page_data, $parent_id, $container, $position );
		if ( ! $inserted ) {
			return new WP_Error(
				'insert_failed',
				__( 'Failed to insert preset. Parent element not found.', 'emcp-tools' ),
				array( 'status' => 404 )
			);
		}

		$this->data->save_page_data( $post_id, $page_data );

		return array(
			'success'      => true,
			'preset'       => $preset,
			'container_id' => $container['id'],
			'post_id'      => $post_id,
		);
	}

	private function build_hero( array $input ): array {
		$heading = ! empty( $input['heading'] ) ? $input['heading'] : __( 'Welcome', 'emcp-tools' );
		$text    = ! empty( $input['text'] ) ? $input['text'] : __( 'This is a hero section created with EMCP Tools.', 'emcp-tools' );
		$buttons = isset( $input['buttons'] ) && is_array( $input['buttons'] ) ? $input['buttons'] : array();

		$elements = array(
			$this->factory->create_widget( 'heading', array(
				'title' => $heading,
				'align' => 'center',
				'size'  => 'xl',
			) ),
			$this->factory->create_widget( 'text', array(
				'editor' => wp_kses_post( $text ),
				'align'  => 'center',
			) ),
		);

		if ( ! empty( $buttons ) ) {
			$button_widgets = array();
			foreach ( $buttons as $btn ) {
				$btn_settings = array(
					'text' => ! empty( $btn['text'] ) ? sanitize_text_field( $btn['text'] ) : __( 'Learn More', 'emcp-tools' ),
				);
				if ( ! empty( $btn['link'] ) ) {
					$btn_settings['link'] = array( 'url' => esc_url_raw( $btn['link'] ) );
				}
				if ( ! empty( $btn['type'] ) && 'secondary' === $btn['type'] ) {
					$btn_settings['button_background_color'] = 'transparent';
					$btn_settings['button_border_border']    = 'solid';
				}
				$button_widgets[] = $this->factory->create_widget( 'button', $btn_settings );
			}

			if ( count( $button_widgets ) > 1 ) {
				$btn_container = $this->factory->create_container(
					array(
						'flex_direction'      => 'row',
						'flex_justify_content' => 'center',
						'column_gap'          => array( 'size' => 15, 'unit' => 'px' ),
						'content_width'       => 'full',
					),
					$button_widgets
				);
				$elements[] = $btn_container;
			} else {
				$elements[] = $button_widgets[0];
			}
		}

		return $this->factory->create_container(
			array(
				'flex_direction' => 'column',
				'padding'        => array( 'unit' => 'px', 'top' => '80', 'right' => '20', 'bottom' => '80', 'left' => '20' ),
				'gap'            => array( 'size' => 20, 'unit' => 'px' ),
				'content_width'  => 'boxed',
			),
			$elements
		);
	}

	private function build_features_grid( array $input ): array {
		$features = isset( $input['features'] ) && is_array( $input['features'] ) ? $input['features'] : array();
		$count    = count( $features );
		if ( 0 === $count ) {
			$features = array(
				array( 'icon' => 'fas fa-star', 'title' => __( 'Feature 1', 'emcp-tools' ), 'description' => __( 'Description for feature one.', 'emcp-tools' ) ),
				array( 'icon' => 'fas fa-heart', 'title' => __( 'Feature 2', 'emcp-tools' ), 'description' => __( 'Description for feature two.', 'emcp-tools' ) ),
				array( 'icon' => 'fas fa-bolt', 'title' => __( 'Feature 3', 'emcp-tools' ), 'description' => __( 'Description for feature three.', 'emcp-tools' ) ),
			);
			$count = 3;
		}

		$cols = isset( $input['columns'] ) ? max( 2, min( 6, absint( $input['columns'] ) ) ) : min( $count, 3 );
		$col_width = (string) round( 100 / min( $cols, $count ) );

		$inner_containers = array();
		foreach ( $features as $feature ) {
			$title = ! empty( $feature['title'] ) ? sanitize_text_field( $feature['title'] ) : '';
			$desc  = ! empty( $feature['description'] ) ? wp_kses_post( $feature['description'] ) : '';

			$children = array();

			if ( ! empty( $feature['icon'] ) ) {
				$children[] = $this->factory->create_widget( 'icon', array(
					'icon'            => sanitize_text_field( $feature['icon'] ),
					'size'            => array( 'size' => 40, 'unit' => 'px' ),
					'icon_align'      => 'center',
					'primary_color'   => '#4054B2',
				) );
			}

			if ( $title ) {
				$children[] = $this->factory->create_widget( 'heading', array(
					'title' => $title,
					'align' => 'center',
					'size'  => 'sm',
				) );
			}

			if ( $desc ) {
				$children[] = $this->factory->create_widget( 'text', array(
					'editor' => $desc,
					'align'  => 'center',
				) );
			}

			$inner_containers[] = $this->factory->create_container(
				array(
					'flex_direction'        => 'column',
					'flex_align_items'      => 'center',
					'padding'               => array( 'unit' => 'px', 'top' => '20', 'right' => '15', 'bottom' => '20', 'left' => '15' ),
					'gap'                   => array( 'size' => 10, 'unit' => 'px' ),
					'content_width'         => 'full',
				'width'                 => array( 'size' => (int) $col_width, 'unit' => '%' ),
			),
			$children
		);
	}

		return $this->factory->create_container(
			array(
				'flex_direction'  => 'row',
				'flex_wrap'       => 'wrap',
				'column_gap'      => array( 'size' => 20, 'unit' => 'px' ),
				'row_gap'         => array( 'size' => 20, 'unit' => 'px' ),
				'padding'         => array( 'unit' => 'px', 'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20' ),
				'content_width'   => 'boxed',
			),
			$inner_containers
		);
	}

	private function build_sidebar_content( array $input ): array {
		$main_width = isset( $input['main_width'] ) ? absint( $input['main_width'] ) : 66;
		$main_pct   = (string) $main_width;
		$side_pct   = (string) ( 100 - $main_width );

		$main_container = $this->factory->create_container(
			array(
				'content_width'         => 'full',
				'flex_direction'        => 'column',
				'gap'                   => array( 'size' => 15, 'unit' => 'px' ),
				'padding'               => array( 'unit' => 'px', 'top' => '0', 'right' => '15', 'bottom' => '0', 'left' => '0' ),
				'width'                 => array( 'size' => (int) $main_width, 'unit' => '%' ),
			),
			array(
				$this->factory->create_widget( 'heading', array(
					'title' => __( 'Main Content', 'emcp-tools' ),
					'size'  => 'lg',
				) ),
				$this->factory->create_widget( 'text', array(
					'editor' => __( 'This is the main content area. Replace this text with your content.', 'emcp-tools' ),
				) ),
			)
		);

		$sidebar_container = $this->factory->create_container(
			array(
				'content_width'         => 'full',
				'flex_direction'        => 'column',
				'gap'                   => array( 'size' => 15, 'unit' => 'px' ),
				'padding'               => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '15' ),
				'width'                 => array( 'size' => (int) ( 100 - $main_width ), 'unit' => '%' ),
			),
			array(
				$this->factory->create_widget( 'heading', array(
					'title' => __( 'Sidebar', 'emcp-tools' ),
					'size'  => 'sm',
				) ),
				$this->factory->create_widget( 'text', array(
					'editor' => __( 'Sidebar content goes here.', 'emcp-tools' ),
				) ),
			)
		);

		return $this->factory->create_container(
			array(
				'flex_direction'  => 'row',
				'content_width'   => 'boxed',
				'padding'         => array( 'unit' => 'px', 'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20' ),
			),
			array( $main_container, $sidebar_container )
		);
	}

	private function build_faq( array $input ): array {
		$items = isset( $input['faq_items'] ) && is_array( $input['faq_items'] ) ? $input['faq_items'] : array();

		if ( empty( $items ) ) {
			$items = array(
				array( 'question' => __( 'What is this?', 'emcp-tools' ), 'answer' => __( 'This is an FAQ section created with EMCP Tools.', 'emcp-tools' ) ),
				array( 'question' => __( 'How does it work?', 'emcp-tools' ), 'answer' => __( 'Replace these with your own questions and answers.', 'emcp-tools' ) ),
			);
		}

		return $this->factory->create_container(
			array(
				'flex_direction'  => 'column',
				'content_width'   => 'boxed',
				'padding'         => array( 'unit' => 'px', 'top' => '40', 'right' => '20', 'bottom' => '40', 'left' => '20' ),
				'gap'             => array( 'size' => 5, 'unit' => 'px' ),
			),
			array(
				$this->factory->create_widget( 'heading', array(
					'title' => __( 'Frequently Asked Questions', 'emcp-tools' ),
					'align' => 'center',
					'size'  => 'lg',
				) ),
				$this->factory->create_widget( 'accordion', array(
					'tabs' => array_map( function ( $item ) {
						return array(
							'_id'          => EMCP_Tools_Id_Generator::generate(),
							'tab_title'    => isset( $item['question'] ) ? sanitize_text_field( $item['question'] ) : '',
							'tab_content'  => isset( $item['answer'] ) ? wp_kses_post( $item['answer'] ) : '',
						);
					}, $items ),
				) ),
			)
		);
	}
}
