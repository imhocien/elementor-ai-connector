<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_EssentialAddons_Integration extends EMCP_Tools_Addon_Pack_Integration {

	public function id(): string {
		return 'essential-addons';
	}

	public function label(): string {
		return __( 'Essential Addons for Elementor', 'emcp-tools' );
	}

	public function is_available(): bool {
		return class_exists( 'Essential_Addons_Elementor\Classes\Bootstrap' ) || defined( 'EAEL_PLUGIN_URL' );
	}

	protected $widget_prefix = 'eael-';
}
