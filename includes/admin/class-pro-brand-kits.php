<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Pro_Brand_Kits {

	public static function init(): void {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function add_admin_page(): void {
		add_submenu_page(
			'emcp-tools',
			__( 'Brand Kits', 'emcp-tools' ),
			__( 'Brand Kits', 'emcp-tools' ),
			'manage_options',
			'emcp-tools-brand-kits',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'emcp-tools' ) );
		}

		$kits = self::get_kits();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Brand Kits', 'emcp-tools' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Manage saved system brand kits. Apply a kit to your Elementor global settings or delete kits you no longer need.', 'emcp-tools' ); ?></p>

			<?php if ( empty( $kits ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No brand kits have been saved yet. Create one with the MCP tools and it will appear here.', 'emcp-tools' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Kit Name', 'emcp-tools' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Date', 'emcp-tools' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Actions', 'emcp-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $kits as $kit ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $kit['title'] ?? __( '(untitled)', 'emcp-tools' ) ); ?></strong>
									<?php if ( ! empty( $kit['kit_slug'] ) ) : ?>
										<br><code><?php echo esc_html( $kit['kit_slug'] ); ?></code>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $kit['date'] ?? '' ); ?></td>
								<td>
									<button
										type="button"
										class="button button-primary emcp-kit-apply"
										data-kit-id="<?php echo esc_attr( (int) $kit['id'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_apply_kit_' . (int) $kit['id'] ) ); ?>"
									>
										<?php esc_html_e( 'Apply', 'emcp-tools' ); ?>
									</button>
									<button
										type="button"
										class="button emcp-kit-delete"
										data-kit-id="<?php echo esc_attr( (int) $kit['id'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'emcp_delete_kit_' . (int) $kit['id'] ) ); ?>"
									>
										<?php esc_html_e( 'Delete', 'emcp-tools' ); ?>
									</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function enqueue_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, 'emcp-tools-brand-kits' ) ) {
			return;
		}

		$asset_url = EMCP_Tools_Pro_Loader::url( 'assets/js/brand-kits.js' );
		if ( '' !== $asset_url ) {
			wp_enqueue_script(
				'emcp-tools-pro-brand-kits',
				$asset_url,
				array( 'jquery' ),
				EMCP_Tools_Pro_Loader::asset_version( 'assets/js/brand-kits.js' ),
				true
			);
		}

		$style_url = EMCP_Tools_Pro_Loader::url( 'assets/css/brand-kits.css' );
		if ( '' !== $style_url ) {
			wp_enqueue_style(
				'emcp-tools-pro-brand-kits',
				$style_url,
				array(),
				EMCP_Tools_Pro_Loader::asset_version( 'assets/css/brand-kits.css' )
			);
		}

		wp_localize_script(
			'emcp-tools-pro-brand-kits',
			'emcpProBrandKits',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'applyAction' => 'emcp_tools_apply_brand_kit',
				'deleteAction' => 'emcp_tools_delete_brand_kit',
			)
		);
	}

	public static function user_has_access(): bool {
		return true;
	}

	public static function count_cached_kits(): int {
		if ( ! post_type_exists( 'emcp_kit' ) ) {
			return 0;
		}
		$query = new WP_Query( array(
			'post_type'      => 'emcp_kit',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
		) );
		return $query->post_count;
	}

	private static function get_kits(): array {
		$kits = array();

		if ( ! post_type_exists( 'emcp_kit' ) ) {
			return $kits;
		}

		$query = new WP_Query( array(
			'post_type'      => 'emcp_kit',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		foreach ( $query->posts as $post ) {
			$slug = get_post_meta( $post->ID, '_emcp_kit_slug', true );
			$kits[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'kit_slug' => is_string( $slug ) ? $slug : '',
				'date'     => $post->post_date,
			);
		}

		return $kits;
	}
}
