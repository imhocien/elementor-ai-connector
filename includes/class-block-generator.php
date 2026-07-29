<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Block_Generator {

	public static function generate( array $spec ): string {
		$name          = isset( $spec['name'] ) ? sanitize_key( (string) $spec['name'] ) : '';
		$title         = isset( $spec['title'] ) ? sanitize_text_field( (string) $spec['title'] ) : '';
		$description   = isset( $spec['description'] ) ? sanitize_text_field( (string) $spec['description'] ) : '';
		$icon          = isset( $spec['icon'] ) ? sanitize_text_field( (string) $spec['icon'] ) : 'block-default';
		$category      = isset( $spec['category'] ) ? sanitize_key( (string) $spec['category'] ) : 'emcp-custom';
		$attributes    = isset( $spec['attributes'] ) ? (array) $spec['attributes'] : array();
		$render_php    = isset( $spec['render_template'] ) ? (string) $spec['render_template'] : '';
		$supports      = isset( $spec['supports'] ) ? (array) $spec['supports'] : array();

		if ( empty( $name ) || empty( $title ) || empty( $render_php ) ) {
			return '';
		}

		if ( false === strpos( $name, '/' ) ) {
			$name = 'emcp/' . $name;
		}

		$block_json = array(
			'apiVersion'     => 3,
			'name'           => $name,
			'title'          => $title,
			'description'    => $description,
			'icon'           => $icon,
			'category'       => $category,
			'keywords'       => isset( $spec['keywords'] ) && is_array( $spec['keywords'] ) ? $spec['keywords'] : array(),
			'attributes'     => (object) $attributes,
			'supports'       => array_merge(
				array(
					'html'     => false,
					'align'    => true,
					'color'    => true,
					'spacing'  => array( 'margin' => true, 'padding' => true ),
				),
				$supports
			),
			'editorScript'   => 'emcp-block-editor',
			'style'          => 'emcp-block-style',
			'render'         => 'file:./render.php',
		);

		return wp_json_encode( $block_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	}
}
