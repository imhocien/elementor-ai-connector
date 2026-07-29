<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_PremiumAddons_Integration extends EMCP_Tools_Addon_Pack_Integration {

	public function id(): string {
		return 'premium-addons';
	}

	public function label(): string {
		return __( 'Premium Addons for Elementor', 'emcp-tools' );
	}

	public function is_available(): bool {
		return defined( 'PREMIUM_ADDONS_VERSION' ) || defined( 'PREMIUM_ADDONS_FILE' );
	}

	protected $widget_prefix = 'premium-';
}
