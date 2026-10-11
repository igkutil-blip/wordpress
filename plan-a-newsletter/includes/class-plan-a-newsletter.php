<?php
/**
 * Spremanje newslettera, postavke i redoslijed slanja (cron svakih 5 minuta).
 *
 * @package Plan_A_Newsletter
 */

defined( 'ABSPATH' ) || exit;

final class Plan_A_Newsletter {

	const CPT    = 'pan_newsletter';
	const OPTION = 'plan_a_newsletter';
	const OPTOUT = 'plan_a_newsletter_odjave'; // e-mail => datum odjave
	const SENTLOG = 'plan_a_newsletter_log';   // vremena slanja u zadnjih sat vremena
	const CRON   = 'plan_a_newsletter_tick';

	/** Stanja newslettera. */
	const STATUS = array(
		'draft'     => 'Skica',
		'scheduled' => 'Zakazan',
		'sending'   => 'Šalje se',
		'paused'    => 'Pauziran',
		'sent'      => 'Poslan',
		'cancelled' => 'Prekinut',
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval
		add_action( self::CRON, array( __CLASS__, 'tick' ) );
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( self::CRON ) ) {
					wp_schedule_event( time() + 60, 'pan_5min', self::CRON );
				}
			},
			20
		);
	}

	public static function activate() {
		self::register();
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'pan_5min', self::CRON );
		}
	}

	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON );
	}

	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'label'           => 'Newsletteri',
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
			)
		);
	}

	public static function schedules( $s ) {
		$s['pan_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => 'Svakih 5 minuta (newsletter)',
		);
		return $s;
	}

	/** Tko smije pisati i slati (svi s pristupom sučelju weba koji mogu pisati objave). */
	public static function cap(): string {
		return (string) apply_filters( 'plan_a_newsletter_capability', 'edit_posts' );
	}

	/* ---------- Postavke ---------- */

	public static function defaults(): array {
		return array(
			'from_email' => 'newsletter@srd-plan-a.hr',
			'from_name'  => 'Plan A',
			'rate'       => 40,
			'logo'       => '',
			'days'       => 60,
		);
	}

	public static function settings(): array {
		return wp_parse_args( (array) get_option( self::OPTION, array() ), self::defaults() );
	}

	public static function setting( string $key ) {
		return self::settings()[ $key ] ?? null;
	}

	/** Logo za zaglavlje: iz postavki, inače logotip teme Flatsome. */
	public static function logo(): string {
		$logo = (string) self::setting( 'logo' );
		if ( '' === $logo ) {
			$logo = (string) get_theme_mod( 'site_logo', '' );
			if ( is_numeric( $logo ) ) {
				$logo = (string) wp_get_attachment_image_url( (int) $logo, 'full' );
			}
		}
		return $logo;
	}

	/* ---------- Newsletter ---------- */

	public static function meta( int $id, string $key, $default = '' ) {
		$v = get_post_meta( $id, '_pan_' . $key, true );
		return '' === $v || null === $v ? $default : $v;
	}

	public static function status( int $id ): string {
		return (string) self::meta( $id, 'status', 'draft' );
	}

	/** Odjavljeni e-mailovi. */
	public static function optouts(): array {
		return (array) get_option( self::OPTOUT, array() );
	}

	/* ---------- Slanje u paketima ---------- */

	/** Koliko se još smije poslati u ovih sat vremena (ograničenje iz postavki). */
	public static function allowance(): int {
		$now = time();
		$log = array_values( array_filter( (array) get_option( self::SENTLOG, array() ), static fn( $t ) => (int) $t > $now - HOUR_IN_SECONDS ) );
		update_option( self::SENTLOG, $log, false );
		return max( 0, max( 1, (int) self::setting( 'rate' ) ) - count( $log ) );
	}

	private static function logged( int $n ) {
		$log = (array) get_option( self::SENTLOG, array() );
		for ( $i = 0; $i < $n; $i++ ) {
			$log[] = time();
		}
		update_option( self::SENTLOG, $log, false );
	}

	/**
	 * Svakih 5 minuta: zakazani newsletteri kreću, a najstariji koji se šalje dobiva paket.
	 * Paket je dvanaestina satnog ograničenja, pa mailovi idu ravnomjerno kroz sat.
	 */
	public static function tick() {
		if ( get_transient( 'plan_a_newsletter_lock' ) ) {
			return;
		}
		set_transient( 'plan_a_newsletter_lock', 1, 4 * MINUTE_IN_SECONDS );
		try {
			foreach ( self::by_status( 'scheduled' ) as $id ) {
				if ( (int) self::meta( $id, 'at', 0 ) <= time() ) {
					Plan_A_Newsletter_Mail::start( $id );
				}
			}
			$ids = self::by_status( 'sending' );
			if ( ! $ids ) {
				return;
			}
			$rate  = max( 1, (int) self::setting( 'rate' ) );
			$batch = min( self::allowance(), (int) ceil( $rate / 12 ) );
			foreach ( $ids as $id ) {
				if ( $batch < 1 ) {
					break;
				}
				$n      = Plan_A_Newsletter_Mail::send_batch( $id, $batch );
				$batch -= $n;
				self::logged( $n );
			}
		} finally {
			delete_transient( 'plan_a_newsletter_lock' );
		}
	}

	/** Newsletteri u stanju, najstariji prvi. */
	public static function by_status( string $status ): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => self::CPT,
					'post_status'    => 'any',
					'posts_per_page' => 20,
					'fields'         => 'ids',
					'orderby'        => 'date',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'meta_key'       => '_pan_status', // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $status, // phpcs:ignore WordPress.DB.SlowDBQuery
				)
			)
		);
	}
}
