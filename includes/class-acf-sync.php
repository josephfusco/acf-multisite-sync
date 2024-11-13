<?php
/**
 * Main plugin class file.
 *
 * @package AcfMultisiteSync
 */

namespace AcfMultisiteSync;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class ACF_Sync {

	/**
	 * Plugin instance.
	 *
	 * @var object
	 */
	private static $instance = null;

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
		add_action( 'acf/save_post_type', array( $this, 'sync_post_types' ) );
		add_action( 'acf/save_taxonomy', array( $this, 'sync_taxonomies' ) );
		add_action( 'acf/update_field_group', array( $this, 'sync_field_groups' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
			add_action( 'admin_notices', array( $this, 'acf_missing_notice' ) );
			return;
		}

		if ( ! is_multisite() ) {
			add_action( 'admin_notices', array( $this, 'multisite_missing_notice' ) );
			return;
		}
	}

	/**
	 * Display notice if ACF Pro is not active.
	 */
	public function acf_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ACF Pro Multisite Sync requires Advanced Custom Fields Pro to be installed and activated.', 'acf-multisite-sync' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Display notice if not in multisite.
	 */
	public function multisite_missing_notice() {
		?>
		<div class="notice notice-error">
			<p><?php esc_html_e( 'ACF Pro Multisite Sync requires WordPress Multisite to be enabled.', 'acf-multisite-sync' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Sync post types across sites.
	 *
	 * @param array $post_type Post type configuration.
	 */
	public function sync_post_types( $post_type ) {
		if ( ! is_main_site() ) {
			return;
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );

			// Sanitize and validate post type data.
			$sanitized_post_type = $this->sanitize_post_type_data( $post_type );

			// Update post type registration.
			acf_update_post_type( $sanitized_post_type );

			restore_current_blog();
		}
	}

	/**
	 * Sync taxonomies across sites.
	 *
	 * @param array $taxonomy Taxonomy configuration.
	 */
	public function sync_taxonomies( $taxonomy ) {
		if ( ! is_main_site() ) {
			return;
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );

			// Sanitize and validate taxonomy data.
			$sanitized_taxonomy = $this->sanitize_taxonomy_data( $taxonomy );

			// Update taxonomy registration.
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

			// Sanitize and validate field group data.
			$sanitized_field_group = $this->sanitize_field_group_data( $field_group );

			// Update field group.
			acf_update_field_group( $sanitized_field_group );

			// Sync associated fields.
			$fields = acf_get_fields( $field_group );
			if ( $fields ) {
				foreach ( $fields as $field ) {
					acf_update_field( $field );
				}
			}

			restore_current_blog();
		}
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
