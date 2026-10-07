<?php
/**
 * Plugin Name:       Plan A poklon bon
 * Description:       Prodaja poklon bonova za izlete: stranica za kupnju (shortcode [plan-a-poklon-bon]), izdavanje bona s PDF-om i QR kodom nakon uplate, korištenje kao kupon u košarici i popis bonova u administraciji.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Plan A
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plan-a-poklon-bon
 *
 * Dodatak ne mijenja WooCommerce, WpTravelly, temu ni druge dodatke: bon je skriveni
 * virtualni proizvod, a iskorištava se kao obični WooCommerce kupon.
 *
 * Uključene biblioteke: chillerlan/php-qrcode 4.4.2 i chillerlan/php-settings-container 2.1.6
 * (MIT, nazivni prostor promijenjen u PlanAPoklonBon\Vendor), font Lato (SIL OFL 1.1).
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_BON_VERSION', '1.1.0' );
define( 'PLAN_A_BON_FILE', __FILE__ );
define( 'PLAN_A_BON_DIR', plugin_dir_path( __FILE__ ) );
define( 'PLAN_A_BON_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-plan-a-bon-settings.php';
require_once __DIR__ . '/includes/class-plan-a-bon-voucher.php';
require_once __DIR__ . '/includes/class-plan-a-bon-render.php';
require_once __DIR__ . '/includes/class-plan-a-bon.php';
require_once __DIR__ . '/includes/class-plan-a-bon-redeem.php';
require_once __DIR__ . '/includes/class-plan-a-bon-shortcode.php';
require_once __DIR__ . '/includes/class-plan-a-bon-admin.php';

/**
 * Biblioteka za QR kod (samo kad je potrebna).
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = 'PlanAPoklonBon\\Vendor\\chillerlan\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$rest = substr( $class, strlen( $prefix ) );
		foreach ( array( 'QRCode\\' => 'lib/php-qrcode/src/', 'Settings\\' => 'lib/php-settings-container/src/' ) as $ns => $dir ) {
			if ( 0 === strpos( $rest, $ns ) ) {
				$file = PLAN_A_BON_DIR . $dir . str_replace( '\\', '/', substr( $rest, strlen( $ns ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
				return;
			}
		}
	}
);

register_activation_hook( __FILE__, array( 'Plan_A_Bon', 'activate' ) );

Plan_A_Bon_Settings::init();
add_action( 'plugins_loaded', array( 'Plan_A_Bon', 'init' ), 20 );
