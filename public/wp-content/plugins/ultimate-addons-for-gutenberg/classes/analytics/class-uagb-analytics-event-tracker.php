<?php
/**
 * UAGB Analytics Event Tracker.
 *
 * Registers hooks and detects state-based milestone events
 * for the BSF Analytics event tracking system.
 *
 * @since 2.19.22
 * @package UAGB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'UAGB_Analytics_Event_Tracker' ) ) {

	/**
	 * Class UAGB_Analytics_Event_Tracker
	 *
	 * @since 2.19.22
	 */
	class UAGB_Analytics_Event_Tracker {

		/**
		 * Instance.
		 *
		 * @var UAGB_Analytics_Event_Tracker|null
		 */
		private static $instance = null;

		/**
		 * Get instance.
		 *
		 * @since 2.19.22
		 * @return UAGB_Analytics_Event_Tracker
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Previous plugin version captured before update.
		 *
		 * @var string
		 * @since 2.19.23
		 */
		private $pre_update_version = '';

		/**
		 * Constructor.
		 *
		 * @since 2.19.22
		 */
		private function __construct() {
			require_once UAGB_DIR . 'classes/analytics/class-uagb-analytics-events.php';

			add_action( 'admin_init', array( $this, 'track_plugin_activated' ) );
			add_action( 'admin_init', array( $this, 'detect_state_events' ) );
			add_action( 'update_option_spectra_usage_optin', array( $this, 'track_analytics_optin' ), 10, 2 );
			add_action( 'save_post', array( $this, 'track_first_spectra_block_used' ), 20, 2 );
			add_action( 'wp_ajax_ast_block_templates_importer', array( $this, 'track_first_template_imported' ), 5 );
			add_action( 'wp_ajax_ast_block_templates_import_template_kit', array( $this, 'track_first_template_imported' ), 5 );
			add_action( 'uagb_update_before', array( $this, 'capture_pre_update_version' ) );
			add_action( 'uagb_update_after', array( $this, 'track_plugin_updated' ) );

			// Track cumulative learn chapter progress.
			add_action( 'spectra_learn_progress_saved', array( $this, 'track_learn_chapter_progress' ) );

			// Track onboarding exits (users who save state without completing).
			add_action( 'one_onboarding_state_saved_spectra', array( $this, 'track_onboarding_skipped' ), 10, 2 );

			// Hook-based onboarding completion — captures rich properties at the moment
			// of completion (current screen, starter templates builder, pro features,
			// selected addons). The polling fallback in detect_onboarding_completed()
			// handles users who completed before this code was deployed.
			add_action( 'one_onboarding_completion_spectra', array( $this, 'track_onboarding_completed' ), 10, 2 );
		}

		/**
		 * Track plugin activation event.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		public function track_plugin_activated() {
			$referrers = get_option( 'bsf_product_referers', array() );
			$source    = 'self';
			if ( is_array( $referrers ) && ! empty( $referrers['ultimate-addons-for-gutenberg'] ) && is_string( $referrers['ultimate-addons-for-gutenberg'] ) ) {
				$source = sanitize_text_field( $referrers['ultimate-addons-for-gutenberg'] );
			}

			$properties = array(
				'source'             => $source,
				'days_since_install' => (string) self::get_days_since_install(),
				'site_language'      => get_locale(),
			);

			UAGB_Analytics_Events::track( 'plugin_activated', UAGB_VER, $properties );
		}

		/**
		 * Days since the plugin was first installed.
		 *
		 * Uses the `spectra_usage_installed_time` option. Returns 0 if unset.
		 *
		 * @since 2.19.23
		 * @return int
		 */
		private static function get_days_since_install() {
			$install_time = get_site_option( 'spectra_usage_installed_time', 0 );
			if ( ! $install_time || ! is_numeric( $install_time ) ) {
				return 0;
			}
			return (int) floor( ( time() - (int) $install_time ) / DAY_IN_SECONDS );
		}

		/**
		 * Track analytics opt-in/opt-out event.
		 *
		 * @since 2.19.22
		 * @param string $old_value Old value.
		 * @param string $new_value New value.
		 * @return void
		 */
		public function track_analytics_optin( $old_value, $new_value ) {
			if ( 'yes' === $new_value ) {
				UAGB_Analytics_Events::track( 'analytics_optin', 'yes' );
			}
		}

		/**
		 * Track first time a Spectra block is used in a post.
		 *
		 * @since 2.19.22
		 * @param int      $post_id Post ID.
		 * @param \WP_Post $post    Post object.
		 * @return void
		 */
		public function track_first_spectra_block_used( $post_id, $post ) {
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
				return;
			}

			if ( UAGB_Analytics_Events::is_tracked( 'first_spectra_block_used' ) ) {
				return;
			}

			if ( empty( $post->post_content ) ) {
				return;
			}

			// Check for any Spectra block (uagb/ or spectra/ namespace).
			if ( ! preg_match( '/<!-- wp:(uagb|spectra)\/(\S+)/', $post->post_content, $matches ) ) {
				return;
			}

			$block_slug = $matches[1] . '/' . $matches[2];

			UAGB_Analytics_Events::track(
				'first_spectra_block_used',
				$block_slug,
				array(
					'post_type'          => get_post_type( $post_id ),
					'days_since_install' => (string) self::get_days_since_install(),
				)
			);
		}

		/**
		 * Capture the plugin version before an update overwrites it.
		 *
		 * @since 2.19.23
		 * @return void
		 */
		public function capture_pre_update_version() {
			$version                  = get_option( 'uagb-version', '' );
			$this->pre_update_version = is_string( $version ) ? $version : '';
		}

		/**
		 * Track plugin version update event.
		 *
		 * Fires on `uagb_update_after` which only runs when a real version change occurs.
		 * Uses flush_pushed so the event re-fires on each update.
		 *
		 * @since 2.19.23
		 * @return void
		 */
		public function track_plugin_updated() {
			UAGB_Analytics_Events::retrack_event(
				'plugin_updated',
				UAGB_VER,
				array( 'from_version' => $this->pre_update_version )
			);
		}

		/**
		 * Detect state-based events on admin load.
		 *
		 * Throttled to run once per 24 hours via transient.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		public function detect_state_events() {
			if ( false !== get_transient( 'uagb_state_events_checked' ) ) {
				return;
			}

			$this->detect_spectra_pro_activated();
			$this->detect_ai_assistant_first_use();
			$this->detect_gbs_first_created();
			$this->detect_onboarding_completed();
			$this->detect_first_form_created();
			$this->detect_first_popup_created();

			set_transient( 'uagb_state_events_checked', 1, DAY_IN_SECONDS );
		}

		/**
		 * Detect if Spectra Pro is active.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_spectra_pro_activated() {
			if ( UAGB_Analytics_Events::is_tracked( 'spectra_pro_activated' ) ) {
				return;
			}

			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			if ( is_plugin_active( 'spectra-pro/spectra-pro.php' ) ) {
				$pro_version = defined( 'SPECTRA_PRO_VER' ) ? SPECTRA_PRO_VER : '';
				UAGB_Analytics_Events::track( 'spectra_pro_activated', $pro_version );
			}
		}

		/**
		 * Detect first use of AI assistant.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_ai_assistant_first_use() {
			if ( UAGB_Analytics_Events::is_tracked( 'ai_assistant_first_use' ) ) {
				return;
			}

			if ( ! class_exists( '\ZipAI\Classes\Helper' ) || ! method_exists( '\ZipAI\Classes\Helper', 'is_authorized' ) ) {
				return;
			}

			if ( \ZipAI\Classes\Helper::is_authorized() ) {
				UAGB_Analytics_Events::track(
					'ai_assistant_first_use',
					'',
					array( 'module' => 'ai_assistant' )
				);
			}
		}

		/**
		 * Detect if Global Block Styles have been created.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_gbs_first_created() {
			if ( UAGB_Analytics_Events::is_tracked( 'gbs_first_created' ) ) {
				return;
			}

			$gbs_enabled = \UAGB_Admin_Helper::get_admin_settings_option( 'uag_enable_gbs_extension', 'enabled' );

			if ( 'enabled' !== $gbs_enabled ) {
				return;
			}

			$gbs_fonts = get_option( 'spectra_gbs_google_fonts', array() );

			if ( ! empty( $gbs_fonts ) && is_array( $gbs_fonts ) ) {
				UAGB_Analytics_Events::track( 'gbs_first_created' );
			}
		}

		/**
		 * Detect if onboarding has been completed.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_onboarding_completed() {
			if ( UAGB_Analytics_Events::is_tracked( 'onboarding_completed' ) ) {
				return;
			}

			if ( ! UAGB_Onboarding::is_onboarding_completed() ) {
				return;
			}

			$analytics  = get_option( 'spectra_onboarding_analytics', array() );
			$analytics  = is_array( $analytics ) ? $analytics : array();
			$properties = array();

			if ( ! empty( $analytics['skippedSteps'] ) && is_array( $analytics['skippedSteps'] ) ) {
				$properties['skipped_steps'] = implode( ',', array_map( 'sanitize_text_field', $analytics['skippedSteps'] ) );
			}

			$properties['exited_early'] = ! empty( $analytics['exitedEarly'] ) ? 'yes' : 'no';
			$properties['consent']      = ! empty( $analytics['consent'] ) ? 'yes' : 'no';

			// User completed onboarding — clear any prior `onboarding_skipped` event so the funnel reflects the final outcome.
			UAGB_Analytics_Events::clear_event( 'onboarding_skipped' );

			UAGB_Analytics_Events::track( 'onboarding_completed', '', $properties );
		}

		/**
		 * Track first template import via AJAX hook.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		public function track_first_template_imported() {
			UAGB_Analytics_Events::track( 'first_template_imported' );
		}

		/**
		 * Detect if a Spectra form block has been created.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_first_form_created() {
			if ( UAGB_Analytics_Events::is_tracked( 'first_form_created' ) ) {
				return;
			}

			$block_stats = UAGB_Block_Stats_Processor::get_block_stats();

			if ( ! empty( $block_stats['uagb/forms'] ) && $block_stats['uagb/forms'] > 0 ) {
				UAGB_Analytics_Events::track( 'first_form_created' );
			}
		}

		/**
		 * Detect if a Spectra popup has been created.
		 *
		 * @since 2.19.22
		 * @return void
		 */
		private function detect_first_popup_created() {
			if ( UAGB_Analytics_Events::is_tracked( 'first_popup_created' ) ) {
				return;
			}

			if ( ! post_type_exists( 'spectra-popup' ) ) {
				return;
			}

			$popup_count = wp_count_posts( 'spectra-popup' );

			if ( is_object( $popup_count ) && ( $popup_count->publish > 0 || $popup_count->draft > 0 ) ) {
				UAGB_Analytics_Events::track( 'first_popup_created' );
			}
		}

		/**
		 * Track onboarding completion from the `one_onboarding_completion_spectra` hook.
		 *
		 * Provides rich properties from the completion payload — completion_screen,
		 * starter_templates_builder, pro_features, selected_addons. These fields are
		 * only available in the hook payload, not in the fallback polling path.
		 *
		 * Mutual exclusion: clears `onboarding_skipped` (if a prior session had a
		 * skip tracked, completion wins).
		 *
		 * @since 2.19.23
		 * @param array                 $completion_data Completion payload from the REST endpoint.
		 * @param \WP_REST_Request|null $request         The REST request (unused).
		 * @return void
		 */
		public function track_onboarding_completed( $completion_data, $request = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( ! is_array( $completion_data ) ) {
				return;
			}

			// Completion wins — clear any prior skip entry from pushed and pending queues.
			UAGB_Analytics_Events::clear_event( 'onboarding_skipped' );

			// If an earlier admin_init poll already tracked onboarding_completed with
			// minimal properties, retrack so the rich payload replaces it.
			$properties = self::build_onboarding_completion_properties( $completion_data );

			UAGB_Analytics_Events::retrack_event( 'onboarding_completed', UAGB_VER, $properties );
		}

		/**
		 * Build the property bag for onboarding_completed from completion data.
		 *
		 * Pure function — safe to call from both the hook handler and tests.
		 *
		 * @since 2.19.23
		 * @param array $completion_data Payload from one_onboarding_completion_spectra.
		 * @return array Property map for the analytics event.
		 */
		private static function build_onboarding_completion_properties( $completion_data ) {
			$screens           = isset( $completion_data['screens'] ) && is_array( $completion_data['screens'] ) ? $completion_data['screens'] : array();
			$skipped_steps     = array();
			$screens_completed = 0;
			foreach ( $screens as $screen ) {
				if ( ! is_array( $screen ) ) {
					continue;
				}
				$screen_id = isset( $screen['id'] ) && is_string( $screen['id'] ) ? $screen['id'] : '';
				if ( ! empty( $screen['skipped'] ) ) {
					if ( '' !== $screen_id ) {
						$skipped_steps[] = sanitize_text_field( $screen_id );
					}
				} else {
					++$screens_completed;
				}
			}

			$completion_screen = isset( $completion_data['completion_screen'] ) && is_string( $completion_data['completion_screen'] )
				? sanitize_text_field( $completion_data['completion_screen'] )
				: '';

			$properties = array(
				'completion_screen' => $completion_screen,
				'screens_completed' => $screens_completed,
				'screens_total'     => count( $screens ),
			);

			if ( ! empty( $skipped_steps ) ) {
				$properties['skipped_steps'] = implode( ',', $skipped_steps );
			}

			// Starter Templates builder — only relevant if user reached that screen.
			$st_builder = isset( $completion_data['starter_templates_builder'] ) && is_string( $completion_data['starter_templates_builder'] )
				? sanitize_text_field( $completion_data['starter_templates_builder'] )
				: '';
			if ( '' !== $st_builder ) {
				$properties['st_builder'] = $st_builder;
			}

			// Pro features selected during onboarding.
			if ( ! empty( $completion_data['pro_features'] ) && is_array( $completion_data['pro_features'] ) ) {
				$properties['pro_features'] = implode( ',', array_map( 'sanitize_text_field', $completion_data['pro_features'] ) );
			}

			// Addons selected during onboarding.
			if ( ! empty( $completion_data['selected_addons'] ) && is_array( $completion_data['selected_addons'] ) ) {
				$properties['selected_addons'] = implode( ',', array_map( 'sanitize_text_field', $completion_data['selected_addons'] ) );
			}

			return $properties;
		}

		/**
		 * Track onboarding exits via the `one_onboarding_state_saved_spectra` hook.
		 *
		 * Fires whenever state is saved without completion — captures users who
		 * abandon the onboarding funnel. Retracks so only the latest exit point
		 * survives (not the first time state was saved). Early-returns if
		 * `onboarding_completed` was tracked in this session.
		 *
		 * @since 2.19.23
		 * @param array                 $state_data Onboarding state from the REST endpoint.
		 * @param \WP_REST_Request|null $request    The REST request (unused).
		 * @return void
		 */
		public function track_onboarding_skipped( $state_data, $request = null ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
			if ( ! is_array( $state_data ) ) {
				return;
			}

			// Only track actual exits — the hook fires on every screen transition;
			// exited_early is only true when the user explicitly closed/dismissed onboarding.
			if ( empty( $state_data['exited_early'] ) ) {
				return;
			}

			// Bail if onboarding was completed in this session — completion wins.
			if ( UAGB_Analytics_Events::is_tracked( 'onboarding_completed' ) ) {
				return;
			}

			$screens           = isset( $state_data['screens'] ) && is_array( $state_data['screens'] ) ? $state_data['screens'] : array();
			$screens_completed = 0;
			foreach ( $screens as $screen ) {
				if ( is_array( $screen ) && empty( $screen['skipped'] ) ) {
					++$screens_completed;
				}
			}

			$exit_screen = '';
			if ( isset( $state_data['exit_screen'] ) && is_string( $state_data['exit_screen'] ) ) {
				$exit_screen = sanitize_text_field( $state_data['exit_screen'] );
			} elseif ( isset( $state_data['current_screen'] ) && is_string( $state_data['current_screen'] ) ) {
				$exit_screen = sanitize_text_field( $state_data['current_screen'] );
			}

			$properties = array(
				'exit_screen'       => $exit_screen,
				'screens_completed' => $screens_completed,
				'screens_total'     => count( $screens ),
			);

			// Retrack so the funnel reflects the user's latest exit point, not their first.
			UAGB_Analytics_Events::retrack_event( 'onboarding_skipped', UAGB_VER, $properties );
		}

		/**
		 * Track cumulative learn chapter progress.
		 *
		 * Fires on `spectra_learn_progress_saved`. Compares the saved progress
		 * against the chapter structure and retracks with a cumulative snapshot
		 * so the server always has the latest state (not just the first save).
		 *
		 * @since 2.19.23
		 * @param array $saved_progress Progress data from user meta: chapter_id => step_id => bool.
		 * @return void
		 */
		public function track_learn_chapter_progress( $saved_progress ) {
			if ( empty( $saved_progress ) || ! class_exists( 'UagAdmin\\Inc\\Admin_Learn' ) ) {
				return;
			}

			$chapters = \UagAdmin\Inc\Admin_Learn::get_chapters_structure();
			if ( empty( $chapters ) ) {
				return;
			}

			$properties   = array();
			$all_complete = true;

			foreach ( $chapters as $chapter ) {
				$chapter_id = isset( $chapter['id'] ) ? $chapter['id'] : '';
				if ( empty( $chapter_id ) || ! isset( $chapter['steps'] ) || ! is_array( $chapter['steps'] ) || empty( $chapter['steps'] ) ) {
					continue;
				}

				$total_steps     = count( $chapter['steps'] );
				$completed_steps = 0;
				foreach ( $chapter['steps'] as $step ) {
					$step_id = isset( $step['id'] ) ? $step['id'] : '';
					if ( $step_id && ! empty( $saved_progress[ $chapter_id ][ $step_id ] ) ) {
						++$completed_steps;
					}
				}

				$properties[ $chapter_id ] = $completed_steps . '/' . $total_steps;

				if ( $completed_steps < $total_steps ) {
					$all_complete = false;
				}
			}

			if ( empty( $properties ) ) {
				return;
			}

			$event_value = $all_complete ? 'completed' : 'in_progress';

			UAGB_Analytics_Events::retrack_event( 'learn_chapter_progress', $event_value, $properties );
		}

	}
}
