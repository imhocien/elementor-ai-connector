<?php
/**
 * Skill Catalog — registry of available EMCP skills for agent discovery.
 *
 * Skills are bundled markdown documents that teach an AI agent how to work
 * with a specific domain (theme, plugin, performance, etc.).
 *
 * @package EMCP_Tools
 * @since   3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static catalog of available EMCP skills.
 *
 * Since there is no pro/ submodule with SKILL.md files in the free tree,
 * the catalog is hardcoded. The `emcp_tools_discovery_skills` filter seam
 * is registered by init() so Pro can inject its real skill catalog.
 *
 * @since 3.7.0
 */
class EMCP_Tools_Skill_Catalog {

	/**
	 * Registered skills.
	 *
	 * @var array|null
	 */
	private static $skills = null;

	/**
	 * Wire the discovery filter. Call during plugin bootstrap.
	 *
	 * @since 3.7.0
	 */
	public static function init(): void {
		add_filter( 'emcp_tools_discovery_skills', array( __CLASS__, 'discovery_catalog' ), 10 );
	}

	/**
	 * Filter callback: render the skills catalog as a markdown block.
	 *
	 * @since 3.7.0
	 *
	 * @param string $current Current value from other filters.
	 * @return string
	 */
	public static function discovery_catalog( string $current ): string {
		if ( '' !== $current ) {
			return $current;
		}

		$catalog = self::catalog();
		if ( empty( $catalog ) ) {
			return '';
		}

		$lines   = array();
		$lines[] = '## Skills';
		$lines[] = '';
		foreach ( $catalog as $skill ) {
			$slug = isset( $skill['slug'] ) ? (string) $skill['slug'] : '';
			$name = isset( $skill['name'] ) ? (string) $skill['name'] : '';
			$desc = isset( $skill['description'] ) ? (string) $skill['description'] : '';
			if ( '' !== $slug && '' !== $name ) {
				$lines[] = sprintf( '- **%s** (`%s`)', $name, $slug );
				if ( '' !== $desc ) {
					$lines[] = '  ' . $desc;
				}
			}
		}

		return implode( "\n", $lines );
	}

	/**
	 * Return the full skills catalog.
	 *
	 * @since 3.7.0
	 *
	 * @return array[] Each entry: { slug, name, description, category }.
	 */
	public static function catalog(): array {
		if ( null !== self::$skills ) {
			return self::$skills;
		}

		self::$skills = array(
			array(
				'slug'        => 'emcp-skills',
				'name'        => 'General EMCP Skills',
				'description' => 'General guidance on using EMCP Tools effectively with Elementor.',
				'category'    => 'general',
			),
			array(
				'slug'        => 'emcp-performance',
				'name'        => 'Performance Optimization',
				'description' => 'Optimize site performance: caching, asset management, server configuration.',
				'category'    => 'performance',
			),
			array(
				'slug'        => 'emcp-security',
				'name'        => 'Security & Malware',
				'description' => 'Harden WordPress security, scan for malware, and follow best practices.',
				'category'    => 'security',
			),
			array(
				'slug'        => 'emcp-themer',
				'name'        => 'Theme Builder',
				'description' => 'Build and manage Elementor theme templates (header, footer, single, archive).',
				'category'    => 'themer',
			),
			array(
				'slug'        => 'emcp-gutenberg',
				'name'        => 'Gutenberg Blocks',
				'description' => 'Create and manage Gutenberg blocks, patterns, and block-based layouts.',
				'category'    => 'gutenberg',
			),
			array(
				'slug'        => 'emcp-seo-a11y',
				'name'        => 'SEO & Accessibility',
				'description' => 'Improve on-page SEO and WCAG accessibility compliance.',
				'category'    => 'seo-a11y',
			),
			array(
				'slug'        => 'emcp-php-snippets',
				'name'        => 'PHP Snippets',
				'description' => 'Author and manage sandboxed PHP code snippets for custom functionality.',
				'category'    => 'php-snippets',
			),
			array(
				'slug'        => 'emcp-plugins',
				'name'        => 'Plugin Integrations',
				'description' => 'Work with plugin integrations: ACF, Meta Box, WooCommerce, forms, and SEO tools.',
				'category'    => 'plugins',
			),
			array(
				'slug'        => 'emcp-themes',
				'name'        => 'Theme Management',
				'description' => 'Manage WordPress themes: install, activate, customize, and create child themes.',
				'category'    => 'themes',
			),
		);

		return self::$skills;
	}

	/**
	 * Get a single skill by slug.
	 *
	 * Path-traversal guarded: rejects dots and slashes in the slug.
	 *
	 * @since 3.7.0
	 *
	 * @param string $slug The skill slug.
	 * @return array|null The skill entry, or null if not found.
	 */
	public static function get( string $slug ): ?array {
		if ( '' === $slug || false !== strpos( $slug, '.' ) || false !== strpos( $slug, '/' ) || false !== strpos( $slug, '\\' ) ) {
			return null;
		}

		foreach ( self::catalog() as $skill ) {
			if ( isset( $skill['slug'] ) && $skill['slug'] === $slug ) {
				return $skill;
			}
		}

		return null;
	}

	/**
	 * Search skills by name or description.
	 *
	 * @since 3.7.0
	 *
	 * @param string $query The search query.
	 * @return array[] Matching skill entries.
	 */
	public static function search( string $query ): array {
		if ( '' === $query ) {
			return self::catalog();
		}

		$lower = strtolower( $query );
		$out   = array();

		foreach ( self::catalog() as $skill ) {
			$name = isset( $skill['name'] ) ? strtolower( $skill['name'] ) : '';
			$desc = isset( $skill['description'] ) ? strtolower( $skill['description'] ) : '';

			if ( false !== strpos( $name, $lower ) || false !== strpos( $desc, $lower ) ) {
				$out[] = $skill;
			}
		}

		return $out;
	}
}
