<?php
/**
 * Project Memory MCP abilities.
 *
 * Three tools for storing and recalling project memory across agent sessions.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Memory MCP tools: recall, remember, save-session-summary.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Memory_Abilities {

	/**
	 * Registered ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Returns the names of all abilities registered by this group.
	 *
	 * @since 3.7.0
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Register this group's MCP abilities.
	 *
	 * @since 3.7.0
	 */
	public function register(): void {
		$this->register_recall();
		$this->register_remember();
		$this->register_save_session_summary();
	}

	/**
	 * Check read permission.
	 *
	 * @since 3.7.0
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	// -------------------------------------------------------------------------
	// emcp-tools/recall
	// -------------------------------------------------------------------------

	/**
	 * Register the recall tool.
	 *
	 * @since 3.7.0
	 */
	private function register_recall(): void {
		$this->ability_names[] = 'emcp-tools/recall';
		emcp_tools_register_ability(
			'emcp-tools/recall',
			array(
				'label'               => __( 'Recall Project Memory', 'emcp-tools' ),
				'description'         => __( 'Retrieve all approved project memory guidance for this site. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_recall' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'entries' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'guidance' => array( 'type' => 'string' ),
									'created'  => array( 'type' => 'string' ),
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

	/**
	 * Execute recall: return all approved memory guidance.
	 *
	 * @since 3.7.0
	 * @param array $input Tool input (unused).
	 * @return array
	 */
	public function execute_recall( array $input ): array {
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			return array( 'entries' => array() );
		}

		$entries = array();
		$all     = EMCP_Tools_Memory_Store::list( 'publish' );
		foreach ( $all as $entry ) {
			$entries[] = array(
				'id'       => $entry['id'],
				'guidance' => $entry['guidance'],
				'created'  => $entry['created'],
			);
		}

		return array( 'entries' => $entries );
	}

	// -------------------------------------------------------------------------
	// emcp-tools/remember
	// -------------------------------------------------------------------------

	/**
	 * Register the remember tool.
	 *
	 * @since 3.7.0
	 */
	private function register_remember(): void {
		$this->ability_names[] = 'emcp-tools/remember';
		emcp_tools_register_ability(
			'emcp-tools/remember',
			array(
				'label'               => __( 'Remember', 'emcp-tools' ),
				'description'         => __( 'Store a project memory item. Entries are saved as pending and must be approved by a human admin before they become active.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_remember' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'guidance' => array(
							'type'        => 'string',
							'description' => __( 'The project memory guidance text.', 'emcp-tools' ),
						),
						'context'  => array(
							'type'        => 'object',
							'description' => __( 'Optional metadata context.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'guidance' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'memory_id' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Execute remember: store a new pending memory entry.
	 *
	 * @since 3.7.0
	 * @param array $input Tool input.
	 * @return array
	 */
	public function execute_remember( array $input ): array {
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			return array( 'success' => false, 'memory_id' => 0 );
		}

		$guidance = isset( $input['guidance'] ) ? (string) $input['guidance'] : '';
		$context  = isset( $input['context'] ) && is_array( $input['context'] ) ? $input['context'] : array();

		$id = EMCP_Tools_Memory_Store::add( $guidance, 'pending', $context );
		if ( is_wp_error( $id ) ) {
			return array( 'success' => false, 'memory_id' => 0 );
		}

		return array( 'success' => true, 'memory_id' => $id );
	}

	// -------------------------------------------------------------------------
	// emcp-tools/save-session-summary
	// -------------------------------------------------------------------------

	/**
	 * Register the save-session-summary tool.
	 *
	 * @since 3.7.0
	 */
	private function register_save_session_summary(): void {
		$this->ability_names[] = 'emcp-tools/save-session-summary';
		emcp_tools_register_ability(
			'emcp-tools/save-session-summary',
			array(
				'label'               => __( 'Save Session Summary', 'emcp-tools' ),
				'description'         => __( 'Save a summary of the current agent session to project memory for continuity across sessions.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_save_session_summary' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'summary' => array(
							'type'        => 'string',
							'description' => __( 'The session summary text.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'summary' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'memory_id' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Execute save-session-summary: store a session summary as pending memory.
	 *
	 * @since 3.7.0
	 * @param array $input Tool input.
	 * @return array
	 */
	public function execute_save_session_summary( array $input ): array {
		if ( ! class_exists( 'EMCP_Tools_Memory_Store' ) ) {
			return array( 'success' => false, 'memory_id' => 0 );
		}

		$summary = isset( $input['summary'] ) ? (string) $input['summary'] : '';

		$id = EMCP_Tools_Memory_Store::add( $summary, 'pending', array( 'type' => 'session_summary' ) );
		if ( is_wp_error( $id ) ) {
			return array( 'success' => false, 'memory_id' => 0 );
		}

		return array( 'success' => true, 'memory_id' => $id );
	}
}
