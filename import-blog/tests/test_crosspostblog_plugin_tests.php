<?php

/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 *
 * @package wordpress-plugins-tests
 */
require_once('./import_blog.php');
class WP_Test_WordPress_Plugin_Tests extends WP_UnitTestCase {
    private $plugin;  
    private $url;
    private $falseurl;
    /**
    * Fired before each test and retrieves a reference to the plugin and assigns it
    * to the local instance variable.
    */
    function setUp() {
        parent::setUp();
        global $wpdb;
        $this->plugin = $GLOBALS['crosspost']; 
        $this->url = "http://google.com";
        $this->falseurl = "foobartin.com";        
    } // end setup
    /**
    * Verifies that the plugin isn't null and was properly retrieved in setup.
    */
    function testPluginInitialization() {
            $this->assertFalse( null == $this->plugin );
    } // end testPluginInitialization
    
    function testValidateURL() {        
        $this->assertTrue( $this->plugin->validate_url($this->url), 'validate_url() will return true when valid url is sent.' );
        $this->assertFalse( $this->plugin->validate_url($this->falseurl), 'validate_url() will return false when url is not sent with http or https.' );
    }
    
    function testURLData() {
        $this->assertTrue($this->plugin->get_content_from_url($this->url), 'get_content_from_url() will return true saying that content has been scrapped');
        $this->assertFalse($this->plugin->get_content_from_url($this->falseurl), 'get_content_from_url() will return false saying that content is not there');
    }
    
    function testPostContent() {       
        $this->assertTrue($this->plugin->preparePostContent($this->url), 'Something went wrong while preparing the postContent');
    }
}
