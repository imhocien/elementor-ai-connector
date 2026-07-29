<?php
/**
 * Skill MCP abilities — list-skills and get-skill tools.
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the Skill MCP tools for agent-facing skill discovery.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Skill_Abilities {

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
		$this->register_list_skills();
		$this->register_get_skill();
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
	// emcp-tools/list-skills
	// -------------------------------------------------------------------------

	/**
	 * Register the list-skills tool.
	 *
	 * @since 3.7.0
	 */
	private function register_list_skills(): void {
		$this->ability_names[] = 'emcp-tools/list-skills';
		emcp_tools_register_ability(
			'emcp-tools/list-skills',
			array(
				'label'               => __( 'List Skills', 'emcp-tools' ),
				'description'         => __( 'Catalog of available EMCP skills (name, description, category). Filter with search. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_skills' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search' => array(
							'type'        => 'string',
							'description' => __( 'Optional search query to filter skills by name or description.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'skills' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'slug'        => array( 'type' => 'string' ),
									'name'        => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'category'    => array( 'type' => 'string' ),
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
	 * Execute list-skills: return catalog filtered by search.
	 *
	 * @since 3.7.0
	 * @param array $input Tool input.
	 * @return array
	 */
	public function execute_list_skills( array $input ): array {
		if ( ! class_exists( 'EMCP_Tools_Skill_Catalog' ) ) {
			return array( 'skills' => array() );
		}

		$search = isset( $input['search'] ) ? (string) $input['search'] : '';

		if ( '' !== $search ) {
			$skills = EMCP_Tools_Skill_Catalog::search( $search );
		} else {
			$skills = EMCP_Tools_Skill_Catalog::catalog();
		}

		return array( 'skills' => $skills );
	}

	// -------------------------------------------------------------------------
	// emcp-tools/get-skill
	// -------------------------------------------------------------------------

	/**
	 * Register the get-skill tool.
	 *
	 * @since 3.7.0
	 */
	private function register_get_skill(): void {
		$this->ability_names[] = 'emcp-tools/get-skill';
		emcp_tools_register_ability(
			'emcp-tools/get-skill',
			array(
				'label'               => __( 'Get Skill', 'emcp-tools' ),
				'description'         => __( 'Get the full content of a skill by its slug. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_skill' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => __( 'The skill slug.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'skill' => array(
							'type'       => 'object',
							'properties' => array(
								'slug'        => array( 'type' => 'string' ),
								'name'        => array( 'type' => 'string' ),
								'description' => array( 'type' => 'string' ),
								'category'    => array( 'type' => 'string' ),
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
	 * Execute get-skill: return a single skill by slug.
	 *
	 * @since 3.7.0
	 * @param array $input Tool input.
	 * @return array|\WP_Error
	 */
	public function execute_get_skill( array $input ) {
		if ( ! class_exists( 'EMCP_Tools_Skill_Catalog' ) ) {
			return new \WP_Error( 'no_catalog', __( 'Skill catalog is unavailable.', 'emcp-tools' ) );
		}

		$slug  = isset( $input['slug'] ) ? (string) $input['slug'] : '';
		$skill = EMCP_Tools_Skill_Catalog::get( $slug );

		if ( null === $skill ) {
			return new \WP_Error( 'not_found', __( 'Skill not found.', 'emcp-tools' ) );
		}

		return array( 'skill' => $skill );
	}
}
