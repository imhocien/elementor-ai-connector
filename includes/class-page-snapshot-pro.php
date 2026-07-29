<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Page_Snapshot_Pro {

	public static function init(): void {
		add_filter( 'emcp_tools_page_snapshot_sections', array( __CLASS__, 'add_sections' ), 10, 2 );
	}

	public static function add_sections( array $sections, int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $sections;
		}

		$sections['seo'] = self::seo_section( $post_id );
		$sections['a11y'] = self::a11y_section( $post_id );

		return $sections;
	}

	private static function seo_section( int $post_id ): array {
		$data = array( 'available' => true );

		if ( class_exists( 'EMCP_Tools_Seo_Meta' ) && method_exists( 'EMCP_Tools_Seo_Meta', 'get' ) ) {
			$data['meta'] = EMCP_Tools_Seo_Meta::get( $post_id );
		}

		if ( class_exists( 'EMCP_Tools_Content_Extractor' ) ) {
			$content = EMCP_Tools_Content_Extractor::extract( $post_id );
			if ( is_array( $content ) && ! empty( $content['headings'] ) ) {
				$data['heading_structure'] = $content['headings'];
			}
		}

		$headings = self::extract_headings( $post_id );
		if ( ! empty( $headings ) ) {
			$data['heading_structure'] = $headings;
		}

		$data['h1_count'] = 0;
		if ( ! empty( $data['heading_structure'] ) ) {
			foreach ( $data['heading_structure'] as $h ) {
				if ( isset( $h['tag'] ) && 'h1' === strtolower( (string) $h['tag'] ) ) {
					++$data['h1_count'];
				}
			}
		}

		return $data;
	}

	private static function a11y_section( int $post_id ): array {
		$data = array(
			'available'       => true,
			'color_contrast'  => array(),
			'alt_text_audit'  => array(),
		);

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $elementor_data ) ) {
			$elementor_data = json_decode( $elementor_data, true );
		}
		if ( ! is_array( $elementor_data ) ) {
			$elementor_data = array();
		}

		$images = self::collect_images( $elementor_data );
		foreach ( $images as $img ) {
			$alt = isset( $img['alt'] ) ? trim( (string) $img['alt'] ) : '';
			if ( '' === $alt ) {
				$data['alt_text_audit'][] = array(
					'element_id' => $img['id'] ?? '',
					'widget'     => $img['widget_type'] ?? '',
					'issue'      => 'missing_alt_text',
				);
			}
		}

		if ( class_exists( 'EMCP_Tools_Color_Contrast' ) ) {
			$contrast_issues = EMCP_Tools_Color_Contrast::check( $elementor_data );
			if ( is_array( $contrast_issues ) ) {
				$data['color_contrast'] = $contrast_issues;
			}
		}

		return $data;
	}

	private static function extract_headings( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array();
		}

		$content  = $post->post_content;
		$headings = array();

		if ( preg_match_all( '/<h([1-6])[^>]*>(.*?)<\/h\1>/si', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$headings[] = array(
					'tag'  => 'h' . $m[1],
					'text' => trim( wp_strip_all_tags( $m[2] ) ),
				);
			}
		}

		if ( class_exists( 'EMCP_Tools_Content_Extractor' ) && method_exists( 'EMCP_Tools_Content_Extractor', 'headings_from_elementor' ) ) {
			$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
			if ( is_string( $elementor_data ) ) {
				$elementor_data = json_decode( $elementor_data, true );
			}
			if ( is_array( $elementor_data ) ) {
				$elementor_headings = EMCP_Tools_Content_Extractor::headings_from_elementor( $elementor_data );
				if ( is_array( $elementor_headings ) ) {
					$headings = array_merge( $headings, $elementor_headings );
				}
			}
		}

		return $headings;
	}

	private static function collect_images( array $elements, int $depth = 0 ): array {
		$images = array();
		if ( $depth > 20 ) {
			return $images;
		}

		foreach ( $elements as $el ) {
			if ( ! is_array( $el ) ) {
				continue;
			}

			$wt = isset( $el['widgetType'] ) ? (string) $el['widgetType'] : '';
			$s  = ( isset( $el['settings'] ) && is_array( $el['settings'] ) ) ? $el['settings'] : array();

			if ( in_array( $wt, array( 'image', 'theme-site-logo' ), true ) ) {
				if ( isset( $s['image'] ) && is_array( $s['image'] ) ) {
					$images[] = array(
						'id'          => (string) ( $el['id'] ?? '' ),
						'widget_type' => $wt,
						'alt'         => (string) ( $s['image']['alt'] ?? '' ),
					);
				}
			}

			if ( 'e-image' === $wt ) {
				$image_data = $s['image'] ?? null;
				if ( is_array( $image_data ) ) {
					$alt = '';
					if ( isset( $image_data['$$type'] ) && isset( $image_data['value']['alt'] ) ) {
						$alt = (string) $image_data['value']['alt'];
					} elseif ( isset( $image_data['alt'] ) ) {
						$alt = (string) $image_data['alt'];
					}
					$images[] = array(
						'id'          => (string) ( $el['id'] ?? '' ),
						'widget_type' => $wt,
						'alt'         => $alt,
					);
				}
			}

			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$images = array_merge( $images, self::collect_images( $el['elements'], $depth + 1 ) );
			}
		}

		return $images;
	}
}
