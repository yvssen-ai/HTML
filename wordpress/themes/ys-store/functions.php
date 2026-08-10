<?php
/**
 * YS Store — theme bootstrap.
 *
 * This file does nothing but define the two constants every other file needs
 * and require the modules in dependency order. Anything that looks like logic
 * belongs in inc/.
 *
 * @package YS_Store
 */

defined( 'ABSPATH' ) || exit;

define( 'YS_STORE_VERSION', '1.0.0' );
define( 'YS_STORE_DIR', trailingslashit( get_template_directory() ) );
define( 'YS_STORE_URI', trailingslashit( get_template_directory_uri() ) );

require_once YS_STORE_DIR . 'inc/setup.php';
require_once YS_STORE_DIR . 'inc/assets.php';
require_once YS_STORE_DIR . 'inc/template-tags.php';
require_once YS_STORE_DIR . 'inc/customizer.php';
require_once YS_STORE_DIR . 'inc/class-ys-nav-walker.php';

/*
 * The commerce modules are the reason the theme exists, but they must not fatal
 * a site whose owner has deactivated WooCommerce for an afternoon. Every
 * function inside these two files assumes Woo is loaded; the guard here is what
 * makes that assumption safe.
 */
if ( class_exists( 'WooCommerce' ) ) {
	require_once YS_STORE_DIR . 'inc/woocommerce.php';
	require_once YS_STORE_DIR . 'inc/ajax.php';
}
