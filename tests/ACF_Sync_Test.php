<?php
/**
 * Class ACF_Sync_Test
 *
 * @package AcfMultisiteSync
 */

namespace AcfMultisiteSync\Tests;

use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use AcfMultisiteSync\ACF_Sync;
use Mockery;

/**
 * Test the ACF_Sync class.
 */
class ACF_Sync_Test extends TestCase {
    /**
     * Set up function.
     */
    protected function setUp(): void {
        parent::setUp();
        Brain\Monkey\setUp();
        
        // Mock WordPress functions commonly used.
        Functions\when( 'plugin_dir_url' )->justReturn( 'http://example.org/wp-content/plugins/acf-multisite-sync/' );
        Functions\when( 'plugin_dir_path' )->justReturn( '/tmp/wordpress/wp-content/plugins/acf-multisite-sync/' );
        Functions\when( 'wp_normalize_path' )->returnArg();
    }

    /**
     * Tear down function.
     */
    protected function tearDown(): void {
        Mockery::close();
        Brain\Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Test singleton instance.
     *
     * @covers AcfMultisiteSync\ACF_Sync::get_instance
     */
    public function test_get_instance() {
        Functions\expect( 'add_action' )
            ->times( 4 )
            ->andReturn( true );

        $instance = ACF_Sync::get_instance();
        $this->assertInstanceOf( ACF_Sync::class, $instance );

        // Test singleton.
        $second_instance = ACF_Sync::get_instance();
        $this->assertSame( $instance, $second_instance );
    }

    /**
     * Test initialization when ACF is not active.
     *
     * @covers AcfMultisiteSync\ACF_Sync::init
     */
    public function test_init_without_acf() {
        Functions\expect( 'add_action' )
            ->times( 4 )
            ->andReturn( true );

        Functions\expect( 'is_plugin_active' )
            ->once()
            ->with( 'advanced-custom-fields-pro/acf.php' )
            ->andReturn( false );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_notices', [ $this->isInstanceOf( ACF_Sync::class ), 'acf_missing_notice' ] );

        $instance = ACF_Sync::get_instance();
        $instance->init();
    }

    /**
     * Test initialization when not in multisite.
     *
     * @covers AcfMultisiteSync\ACF_Sync::init
     */
    public function test_init_without_multisite() {
        Functions\expect( 'add_action' )
            ->times( 4 )
            ->andReturn( true );

        Functions\expect( 'is_plugin_active' )
            ->once()
            ->with( 'advanced-custom-fields-pro/acf.php' )
            ->andReturn( true );

        Functions\expect( 'is_multisite' )
            ->once()
            ->andReturn( false );

        Functions\expect( 'add_action' )
            ->once()
            ->with( 'admin_notices', [ $this->isInstanceOf( ACF_Sync::class ), 'multisite_missing_notice' ] );

        $instance = ACF_Sync::get_instance();
        $instance->init();
    }
}
