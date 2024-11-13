# ACF Multisite Sync

Synchronize Advanced Custom Fields settings across all sites in your WordPress multisite network. Works with both ACF Free and Pro.

## Features

- 🔄 Synchronize Field Groups and Fields
- 🔄 Sync Custom Post Types and Taxonomies (Pro only)
- 🛡️ Built with WordPress VIP coding standards
- 🚀 Real-time updates
- 💻 Developer-friendly architecture

## Requirements

- WordPress Multisite
- Advanced Custom Fields (Free or Pro)
- PHP 7.4+
- WordPress 5.8+

## Installation

1. Download and Network Activate the plugin
2. Ensure ACF is activated on sites where you want synchronization
3. Make changes on your primary site - they'll sync automatically

```bash
composer require josephfusco/acf-multisite-sync
```

## Development

### Hooks

```php
// Modify field group data before sync
add_filter( 'acf_ms_sync_field_group', function( $field_group ) {
    return $field_group;
});

// Pro only: Modify post types/taxonomies before sync
add_filter( 'acf_ms_sync_post_types', function( $post_type ) {
    return $post_type;
});
```

### Constants

```php
// Disable automatic sync
define( 'ACF_MS_SYNC_DISABLE', true );

// Enable debug logging
define( 'ACF_MS_SYNC_DEBUG', true );
```

## Troubleshooting

1. Verify ACF is active on all sites
2. Ensure changes are made on primary site
3. Check error logs
4. Clear WordPress object cache if needed
