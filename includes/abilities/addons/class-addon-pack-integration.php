<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class EMCP_Tools_Addon_Pack_Integration {

	protected $widget_prefix = '';

	abstract public function id(): string;

	abstract public function label(): string;

	public function is_available(): bool {
		return true;
	}

	final public function read_tool(): string {
		return 'emcp-tools/' . $this->id() . '-read';
	}

	public function get_ability_names(): array {
		return array( $this->read_tool() );
	}

	public function register(): void {
		emcp_tools_register_ability(
			$this->read_tool(),
			array(
				'label'               => $this->label(),
				'description'         => $this->label() . ' — widget discovery and schema inspection. Call with no operation to list available operations.',
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_read' ),
				'permission_callback' => array( $this, 'can_read' ),
				'input_schema'        => $this->dispatch_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function run_read( $input ) {
		return $this->dispatch( $input );
	}

	public function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	protected function operations(): array {
		return array(
			'list-widgets'      => array(
				'desc' => __( 'Compact index of widgets from this addon pack. Optional filters: { category, search }.', 'emcp-tools' ),
				'run'  => array( $this, 'execute_list_widgets' ),
			),
			'get-widget-schema' => array(
				'desc' => __( 'Return the schema for one widget type. Arguments: { name } or { names: [...] }, optional { full: true } for raw controls.', 'emcp-tools' ),
				'run'  => array( $this, 'execute_get_widget_schema' ),
			),
		);
	}

	private function dispatch( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$operation = isset( $input['operation'] ) ? str_replace( '_', '-', sanitize_key( (string) $input['operation'] ) ) : '';
		$ops       = $this->operations();

		if ( '' === $operation ) {
			return $this->catalog( $ops );
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'plugin_inactive',
				sprintf( __( 'Install and activate %s to use this tool.', 'emcp-tools' ), $this->label() ),
				array( 'status' => 409 )
			);
		}

		if ( ! isset( $ops[ $operation ] ) ) {
			return new WP_Error(
				'unknown_operation',
				sprintf( __( 'Unknown operation: %s.', 'emcp-tools' ), $operation ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->can_read() ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission for this operation.', 'emcp-tools' ), array( 'status' => 403 ) );
		}

		$args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();
		return call_user_func( $ops[ $operation ]['run'], $args );
	}

	private function catalog( array $ops ): array {
		$out = array();
		foreach ( $ops as $name => $op ) {
			$out[] = array(
				'operation'   => $name,
				'description' => $op['desc'],
			);
		}
		return array(
			'operations' => $out,
		);
	}

	private function dispatch_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'operation' => array(
					'type'        => 'string',
					'description' => __( 'Operation name. Omit to list the available operations.', 'emcp-tools' ),
				),
				'arguments' => array(
					'type'        => 'object',
					'description' => __( 'Arguments for the operation.', 'emcp-tools' ),
				),
			),
		);
	}

	public function execute_list_widgets( $input ): array {
		$search   = isset( $input['search'] ) ? strtolower( sanitize_text_field( (string) $input['search'] ) ) : '';
		$category = isset( $input['category'] ) ? sanitize_key( (string) $input['category'] ) : '';

		$widgets = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
		$rows    = array();

		foreach ( $widgets as $name => $widget ) {
			if ( '' !== $this->widget_prefix && 0 !== strpos( $name, $this->widget_prefix ) ) {
				continue;
			}
			if ( '' !== $search && false === strpos( strtolower( $name ), $search ) && false === strpos( strtolower( $widget->get_title() ), $search ) ) {
				continue;
			}
			$cats = $widget->get_categories();
			if ( '' !== $category && ! in_array( $category, $cats, true ) ) {
				continue;
			}
			$rows[] = array(
				'name'     => $name,
				'title'    => $widget->get_title(),
				'icon'     => $widget->get_icon(),
				'category' => $cats[0] ?? '',
			);
		}

		return array(
			'widgets' => $rows,
			'total'   => count( $rows ),
		);
	}

	public function execute_get_widget_schema( $input ) {
		$names = array();
		if ( isset( $input['names'] ) && is_array( $input['names'] ) ) {
			$names = array_map( 'sanitize_key', $input['names'] );
		} elseif ( isset( $input['name'] ) ) {
			$names = array( sanitize_key( (string) $input['name'] ) );
		}
		if ( empty( $names ) ) {
			return new WP_Error( 'missing_name', __( 'Provide a widget "name" or "names" array.', 'emcp-tools' ), array( 'status' => 400 ) );
		}

		$full = ! empty( $input['full'] );
		$out  = array();

		foreach ( $names as $name ) {
			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $name );
			if ( ! $widget ) {
				$out[ $name ] = array( 'error' => 'widget_not_found' );
				continue;
			}
			$schema = array(
				'name'    => $name,
				'title'   => $widget->get_title(),
				'icon'    => $widget->get_icon(),
				'controls' => array(),
			);

			$controls = $widget->get_controls();
			if ( $full ) {
				$schema['controls'] = $controls;
				$schema['control_count'] = count( $controls );
			} else {
				$shown = 0;
				$cap   = 80;
				foreach ( $controls as $ck => $control ) {
					if ( $shown >= $cap ) {
						break;
					}
					if ( in_array( $control['type'], array( \Elementor\Controls_Manager::TAB, \Elementor\Controls_Manager::HIDDEN, \Elementor\Controls_Manager::DIVIDER ), true ) ) {
						continue;
					}
					$schema['controls'][ $ck ] = array(
						'label' => $control['label'] ?? $ck,
						'type'  => $control['type'] ?? '',
					);
					++$shown;
				}
				$schema['shown']        = $shown;
				$schema['total_controls'] = count( $controls );
				if ( $shown < count( $controls ) ) {
					$schema['note'] = sprintf( __( 'Showing %1$d of %2$d controls; pass full:true for all.', 'emcp-tools' ), $shown, count( $controls ) );
				}
			}

			$out[ $name ] = $schema;
		}

		return ( 1 === count( $out ) ) ? reset( $out ) : array( 'widgets' => $out );
	}
}
