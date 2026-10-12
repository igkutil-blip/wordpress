<?php
	/*
	 * Plan A: prema WpTravellyju 2.3.4 (GPL, mage-people.com), admin/TTBM_Admin.php – samo dijelovi koji
	 * mijenjaju ponašanje stranice: klasični uređivač za izlete, „Duplicate” u popisima, HTML oblik svih
	 * mailova (wp_mail_content_type), objava zakašnjelih zakazanih izleta. Bez postavki i izbornika WpTravellyja.
	 * Ime datoteke mora ostati TTBM_Admin.php (o njemu ovisi nonce za „Duplicate”).
	 */
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('TTBM_Admin')) {
		class TTBM_Admin {
			public function __construct() {
				add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
				add_action('admin_action_ttbm_duplicate', [$this, 'ttbm_duplicate']);
				add_filter('post_row_actions', [$this, 'post_duplicator'], 10, 2);
				add_filter('wp_mail_content_type', array($this, 'email_content_type'));
				add_action('admin_init', [$this, 'repair_missed_tour_schedules'], 20);
			}
			public function disable_gutenberg($current_status, $post_type) {
				$user_status = TTBM_Global_Function::get_settings('ttbm_global_settings', 'disable_block_editor', 'yes');
				if ($user_status == 'yes' && ($post_type === TTBM_Function::get_cpt_name() || $post_type === 'ttbm_hotel')) {
					return false;
				}
				return $current_status;
			}
			public function ttbm_duplicate() {
				// Check if post ID is provided
				if (!(isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'ttbm_duplicate' == $_REQUEST['action']))) {
					wp_die(esc_html__('No post to duplicate has been supplied!', 'tour-booking-manager'));
				}
				// Verify nonce
				if (!isset($_GET['duplicate_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['duplicate_nonce'])), basename(__FILE__))) {
					wp_die(esc_html__('Security check failed!', 'tour-booking-manager'));
				}
				$post_id = isset($_GET['post']) ? absint($_GET['post']) : absint($_POST['post']);
				// Try to get post from cache first
				$post = wp_cache_get('ttbm_post_' . $post_id, 'ttbm');
				if (false === $post) {
					$post = get_post($post_id);
					if ($post) {
						wp_cache_set('ttbm_post_' . $post_id, $post, 'ttbm', 3600); // Cache for 1 hour
					}
				}
				if (!$post) {
					wp_die(esc_html__('Original post not found!', 'tour-booking-manager'));
				}
				$current_user = wp_get_current_user();
				$new_post_author = $current_user->ID;
				$args = array(
					'comment_status' => $post->comment_status,
					'ping_status' => $post->ping_status,
					'post_author' => $new_post_author,
					'post_content' => wp_slash($post->post_content),
					'post_excerpt' => $post->post_excerpt,
					'post_name' => $post->post_name,
					'post_parent' => $post->post_parent,
					'post_password' => $post->post_password,
					'post_status' => 'draft',
					'post_title' => $post->post_title . ' (' . esc_html__('Copy', 'tour-booking-manager') . ')',
					'post_type' => $post->post_type,
					'to_ping' => $post->to_ping,
					'menu_order' => $post->menu_order,
				);
				$new_post_id = wp_insert_post($args);
				if (is_wp_error($new_post_id)) {
					wp_die(
						esc_html__('Failed to create duplicate post: ', 'tour-booking-manager') . ' ' . esc_html($new_post_id->get_error_message())
					);
				}
				// Cache the new post immediately
				wp_cache_set('ttbm_post_' . $new_post_id, get_post($new_post_id), 'ttbm', 3600);
				// Copy taxonomies - uses WordPress functions which handle caching internally
				$taxonomies = get_object_taxonomies($post->post_type);
				foreach ($taxonomies as $taxonomy) {
					$post_terms = get_the_terms($post_id, $taxonomy);
					if (!empty($post_terms) && !is_wp_error($post_terms)) {
						$term_slugs = wp_list_pluck($post_terms, 'slug');
						wp_set_object_terms($new_post_id, $term_slugs, $taxonomy, false);
					}
				}
				// Copy post meta (excluding 'total_booking') - uses get_post_meta which is cached
				$post_meta = get_post_meta($post_id);
				if (!empty($post_meta)) {
					foreach ($post_meta as $meta_key => $meta_values) {
						if (in_array($meta_key, array('link_wc_product', 'check_if_run_once', 'total_booking', '_wp_old_slug'), true)) {
							continue;
						}
						foreach ($meta_values as $meta_value) {
							// Unserialize data to ensure proper storage
							$meta_value = maybe_unserialize($meta_value);
							update_post_meta($new_post_id, $meta_key, wp_slash($meta_value));
						}
					}
				}
				// Initialize 'total_booking' - uses update_post_meta which handles caching
				update_post_meta($new_post_id, 'total_booking', 0);
				// Clear any relevant cache after operations
				wp_cache_delete('ttbm_post_' . $post_id, 'ttbm');
				clean_post_cache($new_post_id);
				// Redirect to edit new post - properly escaped
				wp_safe_redirect(
					esc_url_raw(
						admin_url('post.php?action=edit&post=' . absint($new_post_id))
					)
				);
				exit;
			}
			public function post_duplicator($actions, $post) {
				if (current_user_can('edit_posts')) {
					$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=ttbm_duplicate&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce') . '" title="' . esc_html__('Duplicate Post', 'tour-booking-manager') . '" rel="permalink">' . esc_html__('Duplicate', 'tour-booking-manager') . '</a>';
				}
				return $actions;
			}
			public function email_content_type() {
				return "text/html";
			}
			public function repair_missed_tour_schedules(): void {
				if (wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
					return;
				}
				if (!current_user_can('edit_posts')) {
					return;
				}
				$lock_key = 'ttbm_missed_schedule_repair_lock';
				if (get_transient($lock_key)) {
					return;
				}
				set_transient($lock_key, 1, 10 * MINUTE_IN_SECONDS);
				$args = array(
					'post_type' => TTBM_Function::get_cpt_name(),
					'post_status' => 'future',
					'posts_per_page' => 100,
					'date_query' => array(
						array(
							'column' => 'post_date_gmt',
							'before' => current_time('mysql', true),
							'inclusive' => true,
						),
					),
					'fields' => 'ids',
					'no_found_rows' => true,
					'cache_results' => false,
				);
				$overdue_post_ids = get_posts($args);
				if (!empty($overdue_post_ids)) {
					foreach ($overdue_post_ids as $overdue_post_id) {
						wp_publish_post((int)$overdue_post_id);
					}
				}
				// Keep upcoming/expire metadata in sync after status corrections.
				TTBM_Function::update_all_upcoming_date_month();
			}
		}
		new TTBM_Admin();
	}
