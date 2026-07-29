<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Woo_Integration {

	private $ability_names = array();

	public static function woo_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function register(): void {
		$this->register_read_dispatcher();
		$this->register_write_dispatcher();
	}

	private function register_read_dispatcher(): void {
		$this->ability_names[] = 'emcp-tools/woo-read';
		emcp_tools_register_ability(
			'emcp-tools/woo-read',
			array(
				'label'               => __( 'WooCommerce Read', 'emcp-tools' ),
				'description'         => __( 'Read WooCommerce data: products, orders, categories, and settings. Call with no "operation" to list available read operations.', 'emcp-tools' ),
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

	private function register_write_dispatcher(): void {
		$this->ability_names[] = 'emcp-tools/woo-write';
		emcp_tools_register_ability(
			'emcp-tools/woo-write',
			array(
				'label'               => __( 'WooCommerce Write', 'emcp-tools' ),
				'description'         => __( 'Write WooCommerce data: update products, order status, and settings. Disabled by default. Call with no "operation" to list available write operations.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_write' ),
				'permission_callback' => array( $this, 'can_write' ),
				'input_schema'        => $this->dispatch_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function run_read( $input ) {
		return $this->dispatch( 'read', $input );
	}

	public function run_write( $input ) {
		return $this->dispatch( 'write', $input );
	}

	public function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	public function can_write(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	private function operations(): array {
		return array(
			'list-products'       => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_list_products' ),
				'perm'    => array( $this, 'can_read' ),
				'desc'    => __( 'List WooCommerce products. Optional filters: { type, search, category, status, limit }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'get-product'         => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_get_product' ),
				'perm'    => array( $this, 'can_read' ),
				'desc'    => __( 'Get a single product by ID with all attributes, variations, categories, and gallery. Arguments: { product_id }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'list-orders'         => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_list_orders' ),
				'perm'    => array( $this, 'can_manage_orders' ),
				'desc'    => __( 'List WooCommerce orders. Optional filters: { status, limit, customer_id }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'get-order'           => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_get_order' ),
				'perm'    => array( $this, 'can_manage_orders' ),
				'desc'    => __( 'Get a single order by ID with line items, shipping, billing, and notes. Arguments: { order_id }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'list-categories'     => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_list_categories' ),
				'perm'    => array( $this, 'can_read' ),
				'desc'    => __( 'List WooCommerce product categories. Optional: { search, hide_empty }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'get-settings'        => array(
				'mode'    => 'read',
				'run'     => array( $this, 'execute_get_settings' ),
				'perm'    => array( $this, 'can_manage_orders' ),
				'desc'    => __( 'Read WooCommerce settings (currency, weight/dimension units, page IDs).', 'emcp-tools' ),
				'confirm' => false,
			),
			'update-product'      => array(
				'mode'    => 'write',
				'run'     => array( $this, 'execute_update_product' ),
				'perm'    => array( $this, 'can_write' ),
				'desc'    => __( 'Update WooCommerce product fields. Arguments: { product_id, ... fields to update (price, regular_price, sale_price, stock_status, status, description, short_description) }.', 'emcp-tools' ),
				'confirm' => false,
			),
			'update-order-status' => array(
				'mode'    => 'write',
				'run'     => array( $this, 'execute_update_order_status' ),
				'perm'    => array( $this, 'can_write' ),
				'desc'    => __( 'Update order status. Arguments: { order_id, status }. Requires confirm:true.', 'emcp-tools' ),
				'confirm' => true,
			),
			'update-settings'     => array(
				'mode'    => 'write',
				'run'     => array( $this, 'execute_update_settings' ),
				'perm'    => array( $this, 'can_write' ),
				'desc'    => __( 'Update WooCommerce settings. Arguments: key-value pairs of settings to update. Requires confirm:true.', 'emcp-tools' ),
				'confirm' => true,
			),
		);
	}

	public function can_manage_orders(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	private function dispatch( string $mode, $input ) {
		$input     = is_array( $input ) ? $input : array();
		$operation = isset( $input['operation'] ) ? str_replace( '_', '-', sanitize_key( (string) $input['operation'] ) ) : '';
		$ops       = $this->operations();

		if ( '' === $operation ) {
			return $this->catalog( $mode, $ops );
		}

		if ( ! self::woo_active() ) {
			return new WP_Error( 'woo_inactive', __( 'WooCommerce is not active.', 'emcp-tools' ), array( 'status' => 409 ) );
		}

		if ( ! isset( $ops[ $operation ] ) || $ops[ $operation ]['mode'] !== $mode ) {
			return new WP_Error(
				'unknown_operation',
				sprintf( __( 'Unknown %1$s operation: %2$s.', 'emcp-tools' ), $mode, $operation ),
				array( 'status' => 404 )
			);
		}

		$op = $ops[ $operation ];
		if ( ! call_user_func( $op['perm'] ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission for this operation.', 'emcp-tools' ), array( 'status' => 403 ) );
		}

		$args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();

		if ( ! empty( $op['confirm'] ) && ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) ) {
			return new WP_Error(
				'confirmation_required',
				__( 'This operation is irreversible. Pass confirm:true in arguments to proceed.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}
		unset( $args['confirm'] );

		return call_user_func( $op['run'], $args );
	}

	private function catalog( string $mode, array $ops ): array {
		$out = array();
		foreach ( $ops as $name => $op ) {
			if ( $op['mode'] !== $mode ) {
				continue;
			}
			$out[] = array(
				'operation'   => $name,
				'description' => $op['desc'],
				'confirm'     => ! empty( $op['confirm'] ),
			);
		}
		return array(
			'mode'       => $mode,
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

	public function execute_list_products( $input ): array {
		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => min( absint( $input['limit'] ?? 50 ), 100 ),
			'post_status'    => 'any',
		);

		if ( ! empty( $input['type'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_type',
					'field'    => 'slug',
					'terms'    => sanitize_key( (string) $input['type'] ),
				),
			);
		}
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( (string) $input['search'] );
		}
		if ( ! empty( $input['status'] ) ) {
			$args['post_status'] = sanitize_key( (string) $input['status'] );
		}
		if ( ! empty( $input['category'] ) ) {
			$args['tax_query'] = isset( $args['tax_query'] ) ? $args['tax_query'] : array();
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => sanitize_key( (string) $input['category'] ),
			);
		}

		$query   = new WP_Query( $args );
		$products = array();

		foreach ( $query->posts as $post ) {
			$product = wc_get_product( $post );
			if ( ! $product ) {
				continue;
			}
			$products[] = array(
				'id'            => $product->get_id(),
				'name'          => $product->get_name(),
				'sku'           => $product->get_sku(),
				'price'         => $product->get_price(),
				'regular_price' => $product->get_regular_price(),
				'sale_price'    => $product->get_sale_price(),
				'stock_status'  => $product->get_stock_status(),
				'type'          => $product->get_type(),
				'status'        => $post->post_status,
			);
		}

		return array(
			'products' => $products,
			'total'    => $query->found_posts,
		);
	}

	public function execute_get_product( $input ) {
		$id      = absint( $input['product_id'] ?? 0 );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		$data = array(
			'id'            => $product->get_id(),
			'name'          => $product->get_name(),
			'slug'          => $product->get_slug(),
			'sku'           => $product->get_sku(),
			'description'   => $product->get_description(),
			'short_description' => $product->get_short_description(),
			'price'         => $product->get_price(),
			'regular_price' => $product->get_regular_price(),
			'sale_price'    => $product->get_sale_price(),
			'stock_status'  => $product->get_stock_status(),
			'stock_quantity' => $product->get_stock_quantity(),
			'manage_stock'  => $product->get_manage_stock(),
			'type'          => $product->get_type(),
			'status'        => $product->get_status(),
			'featured'      => $product->get_featured(),
			'weight'        => $product->get_weight(),
			'dimensions'    => array(
				'length' => $product->get_length(),
				'width'  => $product->get_width(),
				'height' => $product->get_height(),
			),
			'categories'    => array(),
			'tags'          => array(),
			'attributes'    => array(),
			'gallery'       => array(),
		);

		$cats = wc_get_product_category_list( $product->get_id() );
		if ( $cats ) {
			$terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
			foreach ( $terms as $term ) {
				$data['categories'][] = array(
					'id'   => $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			}
		}

		$tags = wp_get_post_terms( $product->get_id(), 'product_tag' );
		foreach ( $tags as $tag ) {
			$data['tags'][] = array(
				'id'   => $tag->term_id,
				'name' => $tag->name,
				'slug' => $tag->slug,
			);
		}

		foreach ( $product->get_attributes() as $attr_name => $attr ) {
			$attr_data = array(
				'name'    => $attr_name,
				'label'   => wc_attribute_label( $attr_name ),
				'options' => $product->get_attribute( $attr_name ),
				'visible' => $attr->get_visible(),
				'variation' => $attr->get_variation(),
			);
			if ( $attr->is_taxonomy() ) {
				$terms = wp_get_post_terms( $product->get_id(), $attr_name, array( 'fields' => 'names' ) );
				$attr_data['terms'] = $terms;
			}
			$data['attributes'][] = $attr_data;
		}

		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			$data['variations'] = array();
			foreach ( $variations as $variation ) {
				$data['variations'][] = array(
					'id'       => $variation['variation_id'],
					'sku'      => $variation['sku'],
					'price'    => $variation['display_price'],
					'attributes' => $variation['attributes'],
				);
			}
		}

		$gallery_ids = $product->get_gallery_image_ids();
		foreach ( $gallery_ids as $gid ) {
			$data['gallery'][] = array(
				'id'  => $gid,
				'url' => wp_get_attachment_url( $gid ),
			);
		}

		return $data;
	}

	public function execute_list_orders( $input ): array {
		$args = array(
			'limit'  => min( absint( $input['limit'] ?? 50 ), 100 ),
			'return' => 'objects',
		);

		if ( ! empty( $input['status'] ) ) {
			$args['status'] = array( sanitize_key( (string) $input['status'] ) );
		}
		if ( ! empty( $input['customer_id'] ) ) {
			$args['customer_id'] = absint( $input['customer_id'] );
		}

		$query  = new WC_Order_Query( $args );
		$orders = $query->get_orders();

		$rows = array();
		foreach ( $orders as $order ) {
			$rows[] = array(
				'id'       => $order->get_id(),
				'number'   => $order->get_order_number(),
				'status'   => $order->get_status(),
				'total'    => $order->get_total(),
				'currency' => $order->get_currency(),
				'customer' => array(
					'id'    => $order->get_customer_id(),
					'name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
					'email' => $order->get_billing_email(),
				),
				'date_created' => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
				'payment_method' => $order->get_payment_method_title(),
			);
		}

		return array(
			'orders' => $rows,
			'total'  => count( $rows ),
		);
	}

	public function execute_get_order( $input ) {
		$id    = absint( $input['order_id'] ?? 0 );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'id'       => $item->get_id(),
				'name'     => $item->get_name(),
				'product_id' => $item->get_product_id(),
				'quantity' => $item->get_quantity(),
				'total'    => $item->get_total(),
				'subtotal' => $item->get_subtotal(),
			);
		}

		$notes = array();
		foreach ( $order->get_customer_order_notes() as $note ) {
			$notes[] = array(
				'content'   => $note->comment_content,
				'date'      => $note->comment_date,
			);
		}

		return array(
			'id'              => $order->get_id(),
			'number'          => $order->get_order_number(),
			'status'          => $order->get_status(),
			'currency'        => $order->get_currency(),
			'total'           => $order->get_total(),
			'subtotal'        => $order->get_subtotal(),
			'tax_total'       => $order->get_total_tax(),
			'shipping_total'  => $order->get_shipping_total(),
			'discount_total'  => $order->get_discount_total(),
			'payment_method'  => $order->get_payment_method_title(),
			'date_created'    => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
			'billing'         => $order->get_address( 'billing' ),
			'shipping'        => $order->get_address( 'shipping' ),
			'items'           => $items,
			'notes'           => $notes,
		);
	}

	public function execute_list_categories( $input ): array {
		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => isset( $input['hide_empty'] ) ? (bool) $input['hide_empty'] : true,
		);

		if ( ! empty( $input['search'] ) ) {
			$args['name__like'] = sanitize_text_field( (string) $input['search'] );
		}

		$terms = get_terms( $args );
		$rows  = array();

		foreach ( $terms as $term ) {
			$rows[] = array(
				'id'       => $term->term_id,
				'name'     => $term->name,
				'slug'     => $term->slug,
				'count'    => $term->count,
				'parent'   => $term->parent,
			);
		}

		return array(
			'categories' => $rows,
			'total'      => count( $rows ),
		);
	}

	public function execute_get_settings( $input ): array {
		return array(
			'currency'           => get_option( 'woocommerce_currency' ),
			'currency_position'  => get_option( 'woocommerce_currency_pos' ),
			'price_thousand_sep' => get_option( 'woocommerce_price_thousand_sep' ),
			'price_decimal_sep'  => get_option( 'woocommerce_price_decimal_sep' ),
			'price_num_decimals' => get_option( 'woocommerce_price_num_decimals' ),
			'weight_unit'        => get_option( 'woocommerce_weight_unit' ),
			'dimension_unit'     => get_option( 'woocommerce_dimension_unit' ),
			'pages'              => array(
				'shop'     => get_option( 'woocommerce_shop_page_id' ),
				'cart'     => get_option( 'woocommerce_cart_page_id' ),
				'checkout' => get_option( 'woocommerce_checkout_page_id' ),
				'myaccount' => get_option( 'woocommerce_myaccount_page_id' ),
				'terms'    => get_option( 'woocommerce_terms_page_id' ),
			),
			'enable_guest_checkout' => 'yes' === get_option( 'woocommerce_enable_guest_checkout' ),
			'enable_signup_and_login_from_checkout' => 'yes' === get_option( 'woocommerce_enable_signup_and_login_from_checkout' ),
		);
	}

	public function execute_update_product( $input ) {
		$id      = absint( $input['product_id'] ?? 0 );
		$product = wc_get_product( $id );
		if ( ! $product ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		$updated = array();

		if ( isset( $input['regular_price'] ) ) {
			$product->set_regular_price( (string) $input['regular_price'] );
			$updated[] = 'regular_price';
		}
		if ( isset( $input['sale_price'] ) ) {
			$product->set_sale_price( (string) $input['sale_price'] );
			$updated[] = 'sale_price';
		}
		if ( isset( $input['price'] ) ) {
			$product->set_price( (string) $input['price'] );
			$updated[] = 'price';
		}
		if ( isset( $input['stock_status'] ) ) {
			$product->set_stock_status( sanitize_key( (string) $input['stock_status'] ) );
			$updated[] = 'stock_status';
		}
		if ( isset( $input['status'] ) ) {
			$product->set_status( sanitize_key( (string) $input['status'] ) );
			$updated[] = 'status';
		}
		if ( isset( $input['description'] ) ) {
			$product->set_description( wp_kses_post( (string) $input['description'] ) );
			$updated[] = 'description';
		}
		if ( isset( $input['short_description'] ) ) {
			$product->set_short_description( wp_kses_post( (string) $input['short_description'] ) );
			$updated[] = 'short_description';
		}
		if ( isset( $input['name'] ) ) {
			$product->set_name( sanitize_text_field( (string) $input['name'] ) );
			$updated[] = 'name';
		}
		if ( isset( $input['sku'] ) ) {
			$product->set_sku( sanitize_text_field( (string) $input['sku'] ) );
			$updated[] = 'sku';
		}
		if ( isset( $input['manage_stock'] ) ) {
			$product->set_manage_stock( (bool) $input['manage_stock'] );
			$updated[] = 'manage_stock';
		}
		if ( isset( $input['stock_quantity'] ) ) {
			$product->set_stock_quantity( absint( $input['stock_quantity'] ) );
			$updated[] = 'stock_quantity';
		}

		$product->save();

		return array(
			'product_id' => $product->get_id(),
			'updated'    => $updated,
		);
	}

	public function execute_update_order_status( $input ) {
		$id    = absint( $input['order_id'] ?? 0 );
		$order = wc_get_order( $id );
		if ( ! $order ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'emcp-tools' ), array( 'status' => 404 ) );
		}

		$status = sanitize_key( (string) $input['status'] );
		$order->update_status( $status );

		return array(
			'order_id' => $order->get_id(),
			'status'   => $order->get_status(),
		);
	}

	public function execute_update_settings( $input ): array {
		$updated = array();

		$allowed = array(
			'woocommerce_currency',
			'woocommerce_currency_pos',
			'woocommerce_price_thousand_sep',
			'woocommerce_price_decimal_sep',
			'woocommerce_price_num_decimals',
			'woocommerce_weight_unit',
			'woocommerce_dimension_unit',
			'woocommerce_enable_guest_checkout',
			'woocommerce_enable_signup_and_login_from_checkout',
		);

		foreach ( $allowed as $key ) {
			if ( isset( $input[ $key ] ) ) {
				update_option( $key, sanitize_text_field( (string) $input[ $key ] ) );
				$updated[] = $key;
			}
		}

		return array(
			'updated' => $updated,
		);
	}
}
