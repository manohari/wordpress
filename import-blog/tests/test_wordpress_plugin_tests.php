<?php

/**
 * Tests to test that that testing framework is testing tests. Meta, huh?
 *
 * @package wordpress-plugins-tests
 */
//require_once('./import_blog.php');
class WP_Test_WordPress_Plugin_Tests extends WP_UnitTestCase {
    private $plugin;    
    private $tempfile;
    /**
    * Fired before each test and retrieves a reference to the plugin and assigns it
    * to the local instance variable.
    */
    function setUp() {
       parent::setUp();
       $this->plugin = $GLOBALS['crosspost'];
       $this->tempfile;
    } // end setup
    /**
    * Verifies that the plugin isn't null and was properly retrieved in setup.
    */
    function testPluginInitialization() {
            $this->assertFalse( null == $this->plugin );
    } // end testPluginInitialization
    
    function testValidateURL() {        
        $this->assertTrue( $this->plugin->validate_url("http://google.com"), 'validate_url() will return true when valid url is sent.' );
        $this->assertFalse( $this->plugin->validate_url("google.com"), 'validate_url() will return false when url is not sent with http or https.' );
    }
    
    function testURLData() {
        $this->assertTrue($this->plugin->get_content_from_url("http://google.com"), 'get_content_from_url() will return true saying that content has been scrapped');
        $this->assertFalse($this->plugin->get_content_from_url("fdgdh.com"), 'get_content_from_url() will return false saying that content is not there');
    }
    /*function testContentImport() {
        $this->plugin->temp_file = balanceTags(file_get_contents('http://google.com'), true);
        $this->assertTrue($this->plugin->insert_post('http://google.com'), 'insert_post insert the data to wp_post table if everything goes correct and return post_id.if not return error');
    }*/
   
}
