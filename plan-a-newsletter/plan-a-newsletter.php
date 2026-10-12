<?php
/**
 * Plugin Name:       Plan A newsletter
 * Description:       Slanje newslettera članovima i sudionicima izleta iz sučelja weba: grupe primatelja iz baze članova i narudžbi, nadolazeći izleti u mailu, polagano slanje u paketima i odjava jednim klikom.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            S.R.D. Plan A
 * License:           GPLv2 or later
 * Text Domain:       plan-a-newsletter
 *
 * @package Plan_A_Newsletter
 */

defined( 'ABSPATH' ) || exit;

define( 'PLAN_A_NEWSLETTER_VERSION', '1.1.0' );
define( 'PLAN_A_NEWSLETTER_FILE', __FILE__ );
define( 'PLAN_A_NEWSLETTER_DIR', plugin_dir_path( __FILE__ ) );

require_once PLAN_A_NEWSLETTER_DIR . 'includes/class-plan-a-newsletter.php';
require_once PLAN_A_NEWSLETTER_DIR . 'includes/class-plan-a-newsletter-recipients.php';
require_once PLAN_A_NEWSLETTER_DIR . 'includes/plan-a-email.php';
require_once PLAN_A_NEWSLETTER_DIR . 'includes/class-plan-a-newsletter-mail.php';
require_once PLAN_A_NEWSLETTER_DIR . 'includes/class-plan-a-newsletter-admin.php';

register_activation_hook( __FILE__, array( 'Plan_A_Newsletter', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Plan_A_Newsletter', 'deactivate' ) );

Plan_A_Newsletter::init();
Plan_A_Newsletter_Mail::init();
Plan_A_Newsletter_Admin::init();
