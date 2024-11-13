<?php
/**
 * Plugin Name:    ACF Multisite Sync
 * Description:    Synchronizes ACF settings from the primary site to all subsites in a multisite network. Works with both ACF Free and Pro.
 * Version:        1.0.0
 * Author:         Joseph Fusco
 * License:        GPL v3 or later
 * License URI:    https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain:    acf-multisite-sync
 * 
 * @package AcfMultisiteSync
 */

namespace AcfMultisiteSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class ACF_Sync {

	/**
	 * Plugin instance.
	 *
	 * @var object
	 */
	private static $instance = null;

	/**
	 * Is ACF Pro active.
	 *
	 * @var bool
	 */
	private $is_pro = false;

	/**
	 * Get plugin instance.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'admin_init', array( $this, 'check_acf_version' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! class_exists( 'ACF' ) ) {
			add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
			return;
		}

		if ( ! is_multisite() ) {
			add_action( 'admin_notices', array( $this, 'multisite_missing_notice' ) );
			return;
		}

		// Initialize sync hooks based on ACF version
		$this->initialize_sync_hooks();
	}

	/**
	 * Check ACF version and set Pro flag.
	 */
	public function check_acf_version() {
		$this->is_pro = class_exists( 'ACF_Pro' );
	}

	/**
	 * Initialize appropriate sync hooks based on ACF version.
	 */
	private function initialize_sync_hooks() {
		// Common hooks for both versions
		add_action( 'acf/update_field_group', array( $this, 'sync_field_groups' ) );

		// Pro-only features
		if ( $this->is_pro ) {
			add_action( 'acf/save_post_type', array( $this, 'sync_post_types' ) );
			add_action( 'acf/save_taxonomy', array( $this, 'sync_taxonomies' ) );
		}

		// Free version alternative hooks
		if ( ! $this->is_pro ) {
			add_action( 'acf/save_field_group', array( $this, 'sync_field_groups' ) );
		}
	}

	/**
	 * Display notice if ACF is not active.
	 */
	public function acf_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ACF Multisite Sync requires Advanced Custom Fields (Free or Pro) to be installed and activated.', 'acf-multisite-sync' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display notice if not in multisite.
	 */
	public function multisite_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ACF Multisite Sync requires WordPress Multisite to be enabled.', 'acf-multisite-sync' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sync post types across sites (Pro only).
	 *
	 * @param array $post_type Post type configuration.
	 */
	public function sync_post_types( $post_type ) {
		if ( ! $this->is_pro || ! is_main_site() ) {
			return;
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );
		
		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );
			
			// Sanitize and validate post type data
			$sanitized_post_type = $this->sanitize_post_type_data( $post_type );
			
			// Update post type registration
			acf_update_post_type( $sanitized_post_type );
			
			restore_current_blog();
		}
	}

	/**
	 * Sync taxonomies across sites (Pro only).
	 *
	 * @param array $taxonomy Taxonomy configuration.
	 */
	public function sync_taxonomies( $taxonomy ) {
		if ( ! $this->is_pro || ! is_main_site() ) {
			return;
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );
		
		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );
			
			// Sanitize and validate taxonomy data
			$sanitized_taxonomy = $this->sanitize_taxonomy_data( $taxonomy );
			
			// Update taxonomy registration
			acf_update_taxonomy( $sanitized_taxonomy );
			
			restore_current_blog();
		}
	}

	/**
	 * Sync field groups across sites.
	 *
	 * @param array $field_group Field group configuration.
	 */
	public function sync_field_groups( $field_group ) {
		if ( ! is_main_site() ) {
			return;
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );
		
		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );
			
			// Sanitize and validate field group data
			$sanitized_field_group = $this->sanitize_field_group_data( $field_group );
			
			// Determine correct update function based on version
			if ( $this->is_pro ) {
				acf_update_field_group( $sanitized_field_group );
			} else {
				// Free version uses different function
				$field_group_id = wp_insert_post( array(
					'post_type'    => 'acf-field-group',
					'post_title'   => $sanitized_field_group['title'],
					'post_name'    => $sanitized_field_group['key'],
					'post_status'  => 'publish',
					'post_content' => maybe_serialize( $sanitized_field_group ),
				) );

				// Update field group meta
				update_post_meta( $field_group_id, '_valid', 1 );
			}
			
			// Sync associated fields
			$fields = acf_get_fields( $field_group );
			if ( $fields ) {
				foreach ( $fields as $field ) {
					if ( $this->is_pro ) {
						acf_update_field( $field );
					} else {
						$this->update_field_free_version( $field, $field_group_id );
					}
				}
			}
			
			restore_current_blog();
		}
	}

	/**
	 * Update field in free version.
	 *
	 * @param array $field Field configuration.
	 * @param int   $field_group_id Parent field group ID.
	 */
	private function update_field_free_version( $field, $field_group_id ) {
		$field_id = wp_insert_post( array(
			'post_type'    => 'acf-field',
			'post_parent'  => $field_group_id,
			'post_name'    => $field['key'],
			'post_status'  => 'publish',
			'post_content' => maybe_serialize( $field ),
		) );

		update_post_meta( $field_id, '_valid', 1 );
	}

	/**
	 * Sanitize post type data.
	 *
	 * @param array $post_type Post type configuration.
	 * @return array
	 */
	private function sanitize_post_type_data( $post_type ) {
		return array_map( 'sanitize_text_field', (array) $post_type );
	}

	/**
	 * Sanitize taxonomy data.
	 *
	 * @param array $taxonomy Taxonomy configuration.
	 * @return array
	 */
	private function sanitize_taxonomy_data( $taxonomy ) {
		return array_map( 'sanitize_text_field', (array) $taxonomy );
	}

	/**
	 * Sanitize field group data.
	 *
	 * @param array $field_group Field group configuration.
	 * @return array
	 */
	private function sanitize_field_group_data( $field_group ) {
		$sanitized = array();
		
		foreach ( (array) $field_group as $key => $value ) {
			if ( is_array( $value ) ) {
				$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
			} else {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}
		
		return $sanitized;
	}
}

// Initialize the plugin
add_action( 'plugins_loaded', array( AcfMultisiteSync\ACF_Sync::get_instance(), 'init' ) );
