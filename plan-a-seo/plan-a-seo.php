<?php
/**
 * Plugin Name:       Plan A SEO
 * Description:       Sam popunjava SEO za cijelu stranicu (uz Rank Math): ključna riječ, SEO naslov i opis za Google za stranice, objave i vodiče, alt slika (postojećih i novih), noindex i sitemap bez probnih i pomoćnih stranica, strukturirani podaci (udruga, izleti kao događaji, pitanja i odgovori) i /llms.txt za AI pretraživače. Ručno upisano ne dira.
 * Version:           1.0.2
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 * Text Domain:       plan-a-seo
 *
 * @package Plan_A_Seo
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_SEO_VERSION', '1.0.2' );

require_once __DIR__ . '/includes/class-plan-a-seo-text.php';
require_once __DIR__ . '/includes/class-plan-a-seo-meta.php';
require_once __DIR__ . '/includes/class-plan-a-seo-images.php';
require_once __DIR__ . '/includes/class-plan-a-seo-index.php';
require_once __DIR__ . '/includes/class-plan-a-seo-schema.php';
require_once __DIR__ . '/includes/class-plan-a-seo-llms.php';
require_once __DIR__ . '/includes/class-plan-a-seo-admin.php';

final class Plan_A_Seo {

	const OPT = 'plan_a_seo';

	/** Probne i pomoćne stranice (dogovoreno s Igorom 13.10.2026.). */
	const NOINDEX = "aaa\nproba\nlinkovi\nttbm-tour-list\nhotel-search\nhotel-search-result\nwishlist\norchid-grid\nlotus-grid\nfind\nshop\npotvrda";

	public static function opts(): array {
		$o = get_option( self::OPT, array() );
		return wp_parse_args(
			is_array( $o ) ? $o : array(),
			array(
				'suffix'  => ' – Plan A planinarenje',
				'noindex' => self::NOINDEX,
			)
		);
	}

	public static function opt( string $k ): string {
		return (string) ( self::opts()[ $k ] ?? '' );
	}

	/** Nova verzija: osvježi sitemap Rank Matha i llms.txt (novi popis stranica izvan Googlea). */
	public static function upgraded() {
		if ( get_option( 'plan_a_seo_ver' ) === PLAN_A_SEO_VERSION ) {
			return;
		}
		update_option( 'plan_a_seo_ver', PLAN_A_SEO_VERSION, false );
		if ( class_exists( '\\RankMath\\Sitemap\\Cache' ) && method_exists( '\\RankMath\\Sitemap\\Cache', 'invalidate_storage' ) ) {
			\RankMath\Sitemap\Cache::invalidate_storage();
		}
		Plan_A_Seo_Llms::flush();
	}

	public static function init() {
		Plan_A_Seo_Meta::init();
		Plan_A_Seo_Images::init();
		Plan_A_Seo_Index::init();
		Plan_A_Seo_Schema::init();
		Plan_A_Seo_Llms::init();
		Plan_A_Seo_Admin::init();
		add_action( 'init', array( __CLASS__, 'upgraded' ), 99 );
	}
}

add_action( 'plugins_loaded', array( 'Plan_A_Seo', 'init' ) );
