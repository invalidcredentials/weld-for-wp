<?php
/**
 * Plugin Name: Weld Cardano
 * Plugin URI:  https://github.com/invalidcredentials/weld-for-wp
 * Description: Cardano wallet connectivity and transaction signing for WordPress. Inspired by Weld by Anvil, powered by PHP-Cardano. A pb project.
 * Version:     0.3.3
 * Author:      pb
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: weld-cardano
 * Requires at least: 5.8
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

define( 'WELDPRESS_VERSION', '0.3.3' );
define( 'WELDPRESS_FILE', __FILE__ );
define( 'WELDPRESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WELDPRESS_URL', plugin_dir_url( __FILE__ ) );

// Core (no WP dependency).
require_once WELDPRESS_DIR . 'includes/core/class-settings.php';
require_once WELDPRESS_DIR . 'includes/core/class-anvil-client.php';
require_once WELDPRESS_DIR . 'includes/core/class-blockfrost-client.php';
require_once WELDPRESS_DIR . 'includes/core/class-encryption.php';
require_once WELDPRESS_DIR . 'includes/core/class-wallet-model.php';

// WordPress integration.
require_once WELDPRESS_DIR . 'includes/wp/class-assets.php';
require_once WELDPRESS_DIR . 'includes/wp/class-shortcodes.php';
require_once WELDPRESS_DIR . 'includes/wp/class-rest-api.php';
require_once WELDPRESS_DIR . 'includes/wp/class-admin.php';
require_once WELDPRESS_DIR . 'includes/wp/class-wallet-controller.php';
require_once WELDPRESS_DIR . 'includes/wp/class-plugin.php';

// Boot.
WeldPress_Plugin::init();

register_activation_hook( __FILE__, array( 'WeldPress_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WeldPress_Plugin', 'deactivate' ) );
