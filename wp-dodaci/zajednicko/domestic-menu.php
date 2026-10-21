<?php
/**
 * Zajednički izbornik „Domestic” u lijevoj traci WordPress admina.
 *
 * Ista datoteka ide u svaki Domestic dodatak (includes/domestic-menu.php) i mora biti jednaka u svima.
 * Prvi učitani dodatak napravi glavni izbornik, ostali samo dodaju svoje podstavke.
 *
 * Upotreba u dodatku:
 *   require_once __DIR__ . '/includes/domestic-menu.php';
 *   Domestic_Menu::add( 'domestic-izvoz', 'Izvoz i uvoz', array( 'Moj_Dodatak', 'page' ), 20 );
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Domestic_Menu' ) ) {
	final class Domestic_Menu {
		const SLUG = 'domestic';
		const CAP  = 'manage_woocommerce';

		/** @var array<int, array> */
		private static $items = array();

		/** Dodaj podstavku u izbornik Domestic (poredak: manji broj = više). */
		public static function add( string $slug, string $title, callable $cb, int $order = 50, string $cap = self::CAP ): void {
			self::$items[] = compact( 'slug', 'title', 'cb', 'order', 'cap' );
		}

		public static function register(): void {
			if ( ! self::$items ) {
				return;
			}
			usort( self::$items, function ( $a, $b ) { return $a['order'] <=> $b['order']; } );
			$first = self::$items[0];
			add_menu_page( 'Domestic', 'Domestic', self::CAP, self::SLUG, $first['cb'], 'dashicons-store', 56 );
			foreach ( self::$items as $i => $it ) {
				// prva podstavka preuzima adresu glavnog izbornika, da nema dvostruke stavke „Domestic”
				add_submenu_page( self::SLUG, $it['title'], $it['title'], $it['cap'], $i ? $it['slug'] : self::SLUG, $it['cb'] );
			}
		}
	}
	add_action( 'admin_menu', array( 'Domestic_Menu', 'register' ), 9 );
}
