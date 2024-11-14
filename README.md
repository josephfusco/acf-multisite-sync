# ACF Multisite Sync

Synchronize ACF field groups, post types, and taxonomies from your primary site to all subsites in a WordPress multisite network.

## Requirements

- WordPress Multisite
- ACF Pro (activated on all sites)
- PHP 8.0+

## Use Cases

- Franchise/dealer websites sharing the same content structure
- Educational institutions with multiple department sites
- Corporate microsites requiring consistent data architecture

## Installation

1. Install and activate ACF Pro on all sites
2. Upload plugin to `/wp-content/plugins/`
3. Network activate via `/wp-admin/network/plugins.php`
4. Activate ACF Pro license on each subsite

## Usage

1. Configure ACF on your primary site
2. Visit Custom Fields > Sync to Subsites
3. Click "Sync Now"

## Actions & Filters

```php
// Modify post type data before sync
add_filter( 'acf_ms_sync_post_type', function( $post_type ) {
    return $post_type;
});

// Modify taxonomy data before sync
add_filter( 'acf_ms_sync_taxonomy', function( $taxonomy ) {
    return $taxonomy;
});

// Modify field group data before sync
add_filter( 'acf_ms_sync_field_group', function( $field_group ) {
    return $field_group;
});

// After sync completion
do_action( 'acf_ms_sync_complete', $site_id );
```

## Support

- [File an issue](https://github.com/username/acf-multisite-sync/issues)
- WordPress 6.0+
- [GPL v3 or later](https://www.gnu.org/licenses/gpl-3.0.html)
