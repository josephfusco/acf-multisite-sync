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
		add_action( 'admin_menu', array( $this, 'add_sync_menu' ) );
		add_filter( 'plugin_action_links', array( $this, 'add_plugin_links' ), 10, 2 );
		add_filter( 'network_admin_plugin_action_links', array( $this, 'add_plugin_links' ), 10, 2 );
	}

	/**
	 * Plugin activation handler.
	 */
	public static function activate() {
		if ( ! is_main_site() ) {
			return;
		}

		$instance = self::get_instance();
		$instance->sync_all_data();
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
	 * Add plugin links to the plugins page.
	 *
	 * @param array  $links Existing plugin links.
	 * @param string $file  Plugin file path.
	 * @return array Modified plugin links.
	 */
	public function add_plugin_links( $links, $file ) {
		if ( plugin_basename( ACF_MS_SYNC_PLUGIN_DIR . 'acf-multisite-sync.php' ) === $file && is_main_site() ) {
			$sync_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'edit.php?post_type=acf-field-group&page=acf-sync-subsites' ) ),
				esc_html__( 'Sync Settings', 'acf-multisite-sync' )
			);
			array_unshift( $links, $sync_link );
		}
		return $links;
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
	 * Add sync menu to admin.
	 */
	public function add_sync_menu() {
		if ( ! is_main_site() ) {
			return;
		}

		add_submenu_page(
			'edit.php?post_type=acf-field-group',
			__( 'Sync to Subsites', 'acf-multisite-sync' ),
			__( 'Sync to Subsites', 'acf-multisite-sync' ),
			'manage_options',
			'acf-sync-subsites',
			array( $this, 'render_sync_page' )
		);
	}

	/**
	 * Render sync page.
	 */
	public function render_sync_page() {
		if ( isset( $_POST['acf_sync_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['acf_sync_nonce'] ) ), 'acf_sync_action' ) ) {
			$this->sync_all_data();
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Sync completed successfully!', 'acf-multisite-sync' ) . '</p></div>';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync ACF to Subsites', 'acf-multisite-sync' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( 'acf_sync_action', 'acf_sync_nonce' ); ?>
				<p><?php esc_html_e( 'Click the button below to sync all ACF post types, taxonomies, and field groups to subsites.', 'acf-multisite-sync' ); ?></p>
				<?php submit_button( __( 'Sync Now', 'acf-multisite-sync' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sync all ACF data to subsites.
	 */
	private function sync_all_data() {
		// First sync field groups.
		$this->sync_field_groups( array() );

		// Then sync post types if they exist.
		if ( function_exists( 'acf_get_post_types' ) ) {
			$post_types = acf_get_post_types();
			foreach ( $post_types as $post_type ) {
				$this->sync_post_types( $post_type );
			}
		}

		// Finally sync taxonomies if they exist.
		if ( function_exists( 'acf_get_taxonomies' ) ) {
			$taxonomies = acf_get_taxonomies();
			foreach ( $taxonomies as $taxonomy ) {
				$this->sync_taxonomies( $taxonomy );
			}
		}

		/**
		 * Fires after all data has been synced to a subsite.
		 *
		 * @param int $site_id The ID of the current subsite.
		 */
		do_action( 'acf_ms_sync_complete', get_current_blog_id() );
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

			/**
			 * Filter the post type data before sync.
			 *
			 * @param array $post_type The post type configuration.
			 */
			$filtered_post_type = apply_filters( 'acf_ms_sync_post_type', $this->sanitize_post_type_data( $post_type ) );
			
			acf_update_post_type( $filtered_post_type );
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

			/**
			 * Filter the taxonomy data before sync.
			 *
			 * @param array $taxonomy The taxonomy configuration.
			 */
			$filtered_taxonomy = apply_filters( 'acf_ms_sync_taxonomy', $this->sanitize_taxonomy_data( $taxonomy ) );
			
			acf_update_taxonomy( $filtered_taxonomy );
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

		// If no field group provided, get all field groups from main site.
		if ( empty( $field_group ) ) {
			$field_groups = acf_get_field_groups();
		} else {
			$field_groups = array( $field_group );
		}

		$sites = get_sites( array( 'fields' => 'ids' ) );

		foreach ( $sites as $site_id ) {
			if ( get_current_blog_id() === $site_id ) {
				continue;
			}

			switch_to_blog( $site_id );

			foreach ( $field_groups as $group ) {
				// Get the original field group with all its fields.
				$original_group = acf_get_field_group( $group['key'] );
				if ( ! $original_group ) {
					continue;
				}

				// Ensure we have a clean key.
				$original_group['key'] = wp_unslash( $original_group['key'] );

				/**
				 * Filter the field group data before sync.
				 *
				 * @param array $original_group The field group configuration.
				 */
				$filtered_group = apply_filters( 'acf_ms_sync_field_group', $original_group );

				// Get all fields for this group.
				$fields = acf_get_fields( $filtered_group );

				// Update or create the field group.
				$group_id = acf_update_field_group( $filtered_group );

				// Handle the fields.
				if ( $fields ) {
					foreach ( $fields as $field ) {
						// Ensure field has the correct parent.
						$field['parent'] = $group_id;

						// Clean the field key.
						$field['key'] = wp_unslash( $field['key'] );

						// Update or create the field.
						acf_update_field( $field );
					}
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
