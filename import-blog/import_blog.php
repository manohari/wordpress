<?php
/*
Plugin Name: Import Blog Page
Plugin URI: http://wordpres/import-blog
Description: Import pages from your blog.
Author: Manohari Vagicherla
Version: 1.1
License: GPLv2 or later
 */

/* Short description about this class
 * This class scrap the html web page and get the content and copy to your blog.
 * 
 * @plugin_domain
 * @getContent
 * @appEroor are class variables used in the class    
 *  */
if ( !class_exists('BlogImporter') ) {
    class BlogImporter {
        var $plugin_domain='BlogImporter';
        var $getContent = array();
        var $appError = '';
        var $postContentArray = array();
        var $cross_post_info;
        var $filearr;
        function BlogImporter()  {
            $this->plugin_url = defined('WP_PLUGIN_URL') ? WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) : trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)); 
            $this->error = new WP_Error();
            $this->init_errors();
            // Add Options Page
            add_action('admin_menu',  array(&$this, 'admin_menu'));
        }
        /**
         * Add the submenu crosspost to post types
         * @return custom post type
         */
        function admin_menu() {          	
            // Adding cross post as custom post type in posts         
            add_submenu_page('post-new.php', __('Add CrossPost', $this->plugin_domain) ,  __('CrossPost', $this->plugin_domain)	, 1 	, 'add-crosspost',  array(&$this, 'display_form') ); 	  	 
            
        } 
        /**
         * Initialize the error messages to WP_error class
         * 
         * @return WP_Error Shows error message that suits error code
         */
	function init_errors()
	{
            $this->error->add('e_url', __('Please enter URL.',$this->plugin_domain));			
            $this->error->add('e_protocol', __('Please add "http://" or "https://" before the domain name.',$this->plugin_domain));           
            $this->error->add('e_importError', __('No content to import.Please check the page',$this->plugin_domain));
            $this->error->add('e_importBlogError', __('Could not import the data.Something went wrong.',$this->plugin_domain));			
            $this->error->add('e_divcontent', __('No content exist in div.', $this->plugin_domain));
            $this->error->add('e_dataBaseError',__('Something went wrong with Database insertion.Check the post array values',  $this->plugin_domain));
	}
        /**
         * Retrieve an error message
         * 
         * @param $e the error code
         * @return string the complete error message
         */
	function my_error($e = '') {		
		$msg = $this->error->get_error_message($e);		
		if ($msg == null) {
			return __("Unknown error occured, please contact the administrator.", $this->plugin_domain);
		}
		return $msg;
	}
        /*This function displays the UI of crosspost with include file
         * Validations are also done in this function for all ui fields
         */
        function display_form() {            			
            $page=trim($_GET['page']);
            $published=isset($_POST['publish']);            	
            $get_selectors= trim($_POST['contentdivid']);
            if($page === 'add-crosspost') {
                $url= trim($_POST['url']);
                if(!empty($get_selectors)) {
                    $this->getContent = explode(",",$get_selectors);                                    
                }
                if(empty($url)) {
                    $this->appError = $this->my_error('e_url');
                }
                if ($published)
                {
                    //validations of UI field
                    $validateFlag = $this->validate_url($url);                        
                    if($validateFlag) {                        
                        $getContent = $this->get_content_from_url($url);
                        if($getContent) {                                                            
                            //insert the content either complete file or based on selectors
                            $post_id = $this->insert_post($url);
                        }                        
                    }                    
                } 
                include('crosspost.php');
            }
        }
        
        /**
         * Gets the content from the url
         * 
         * @param type $url eg:http://foo.com
         * @return boolean returns true if contents fetched else false
         */
        function get_content_from_url($url) {
            if( (@file_get_contents($url)) === false ) { 
                $this->appError = $this->my_error('e_importError');
                return false;
            }else{                
                return true;                               
            } 
        }
        /**
         * Checks whether the url format is correct or not and contains only http or https
         * @param type $url  eg:http://foo.com or https://foo.com
         * @return boolean returns true if url is valid else false
         */
        function validate_url($url) {            
            if (!empty($url) && isset($url)) {
                if( (stristr($url,'http://') || stristr($url,'https://') ) === false)
                {                            
                    $this->appError = $this->my_error('e_protocol'); 
                    return false;
                } 
                else{
                    if((stristr($url, 'http') && stristr($url, 'https'))) {                                
                        $this->appError = $this->my_error('e_protocol');
                        return false;
                    }                     
                }
                return true;
            }            
        }
        /**
         * Prepare the post content from given url and returns post id
         * @param type $url valid http or https url format
         * @return boolean true if data inserted to DB is success else false
         */
        function insert_post($url)
        {
            //set_magic_quotes_runtime(0);            
           $this->preparePostContent($url); 
           $postid = $this->get_postID();
           if($postid) {
                $this->import_images($postid);
                return $postid;
            }else {
                $this->appError = $this->my_error('e_dataBaseError');
                return false;
            }
            
        }        
        
        /**
         * This function add linked images of imported content to Media Library
         * @param type $id  last inserted postid from WP_Posts 
         */
        function import_images($id) {
            $post_data = get_post($id); //post array retreived based on postid
            $srcs = array();
            $content = $post_data->post_content;
            $title = $post_data->post_title;
            if (empty($title)) { 
                $title = __('(no title)'); 
            }
            $update = false;
            // find all src attributes
            preg_match_all('/<img[^>]* src=[\'"]?([^>\'" ]+)/', $post_data->post_content, $matches);
            for ($i=0; $i<count($matches[0]); $i++) {
                    $srcs[] = $matches[1][$i];
            }
            if (!empty($srcs)) {                         
                foreach ($srcs as $src) {
                    // src="http://foo.com/images/foo"
                    if (preg_match('/^http:\/\//', $src) || preg_match('/^https:\/\//', $src)) { 
                            $imgpath = $src;			
                    }               
                    // intersect base path and src, or just clean up junk
                    $imgpath = $this->remove_dot_segments($imgpath);

                    //  load the image from $imgpath
                    $imgid = $this->handle_import_media_file($imgpath, $id);
                    if ( is_wp_error( $imgid ) ) {
                        $this->my_error();
                    }
                    else {
                        $imgpath = wp_get_attachment_url($imgid);
                        //  replace paths in the content
                        if (!is_wp_error($imgpath)) {			
                                $content = str_replace($src, $imgpath, $content);
                                $update = true;
                        }
                    } // is_wp_error else
                } // foreach
                // update the post only once
                if ($update == true) {
                        $my_post = array();
                        $my_post['ID'] = $id;
                        $my_post['post_content'] = $content;
                        wp_update_post($my_post);
                }
                flush();
            } // if empty            
        }
        /**
         * Upload the images into upload dir and change the img path from remote to local absolute path
         * @param type $file img file path i.e.,img src
         * @param type $post_id parent postid from WP_Posts
         * @return integer WP_Posts id which is of post_type attachment
         */
        function handle_import_media_file($file, $post_id = 0) {
            // see if the attachment already exists
            $id = array_search($file, $this->filearr);	
            if ($id === false) { 
                set_time_limit(120);
                $post = get_post($post_id);
                $time = $post->post_date_gmt;

                // A writable uploads dir will pass this test. Again, there's no point overriding this one.
                if ( ! ( ( $uploads = wp_upload_dir($time) ) && false === $uploads['error'] ) ){
                    return new WP_Error( 'upload_error', $uploads['error']);
                }
                $filename = wp_unique_filename( $uploads['path'], basename($file));

                // copy the file to the uploads dir
                $new_file = $uploads['path'] . '/' . $filename;
                if ( false === @copy( $file, $new_file ) ){
                    $this->my_error();
                }
                // Set correct file permissions
                $stat = stat( dirname( $new_file ));
                $perms = $stat['mode'] & 0000666;
                @chmod( $new_file, $perms );
                // Compute the URL
                $url = $uploads['url'] . '/' . $filename;

                //Apply upload filters
                $return = apply_filters( 'wp_handle_upload', array( 'file' => $new_file, 'url' => $url, 'type' => wp_check_filetype( $file, null ) ) );
                $new_file = $return['file'];
                $url = $return['url'];

                $title = preg_replace('!\.[^.]+$!', '', basename($file));
                $content = '';

                if ( $time ) {
                        $post_date_gmt = $time;
                        $post_date = $time;
                } 
                else {
                        $post_date = current_time('mysql');
                        $post_date_gmt = current_time('mysql', 1);
                }
                // Construct the attachment array
                $wp_filetype = wp_check_filetype(basename($filename), null );
                $attachment = array(
                        'post_mime_type' => $wp_filetype['type'],
                        'guid' => $url,
                        'post_parent' => $post_id,
                        'post_title' => $title,
                        'post_name' => $title,
                        'post_content' => $content,
                        'post_date' => $post_date,
                        'post_date_gmt' => $post_date_gmt
                );                
                $new_file = str_replace( strtolower(str_replace('\\', '/', $uploads['basedir'])), $uploads['basedir'], $new_file);
                // Insert attachment
                $id = wp_insert_attachment($attachment, $new_file, $post_id);
                if ( !is_wp_error($id) ) {
                        $data = wp_generate_attachment_metadata( $id, $new_file );
                        wp_update_attachment_metadata( $id, $data );
                        $this->filearr[$id] = $file; // $file contains the original, absolute path to the file
                }
            } // if attachment already exists
            return $id;
        }
        
        /**
         * This function is mainly used to create absolute URL path for image
         * @param img path 
         * @return string absolute path of image
         */       
        function remove_dot_segments( $path ) {
            
            $inSegs  = preg_split( '!/!u', $path );
            $outSegs = array( );
            foreach ( $inSegs as $seg )
            {
                if ( empty( $seg ) || $seg == '.' ) {
                    continue;
                }
                if ( $seg == '..' ) {
                    array_pop( $outSegs );
                }
                else {
                    array_push( $outSegs, $seg );
                }
            }
            $outPath = implode( '/', $outSegs );
            if ( isset($path[0]) && $path[0] == '/' ) {
                $outPath = '/' . $outPath;
            }
            if ( $outPath != '/' &&
                (mb_strlen($path)-1) == mb_strrpos( $path, '/', 'UTF-8' ) ) {
                $outPath .= '/';
            }
            $outPath = str_replace('http:/', 'http://', $outPath);
            $outPath = str_replace('https:/', 'https://', $outPath);
            $outPath = str_replace(':///', '://', $outPath);
            return rawurldecode($outPath);
        }
        /**
         * Cleans the HTML file
         * @param $string html content string
         * @return string 
         */
        function cleanHTML($string){
            $searchEle = array('\n','&#13;');
            $string = str_replace($searchEle, '', $string); 
            $string = str_replace('< !DOCTYPE', '<DOCTYPE', $string);            
            $string = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $string);           
            $string = preg_replace('#<link (.*?)>#is', '', $string);
            // reduce line breaks and remove empty tags             
            $string = preg_replace( "/<[^\/>]*>([\s]?)*<\/[^>]*>/", ' ', $string );              
            // get rid of remaining newlines; basic HTML cleanup           
            //$string = ereg_replace("[\n\r]", " ", $string); 
            $string = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $string);
            $string = str_replace('<br>', '<br />', $string);            
            return $string;
        }
        /**
         * This function will set the html file inporper format.
         * loadHTML() in DomDocument append meta tags and char encoding if it is plain html load
         * In order to not append meta tags to html page we take the precaution and 
         * make sure encoding chars are displayed correctly and BOM markers are not present
         * @param URL url
         * @return HTMLcontent
         */
        function handle_accents($url) {
            // from: http://www.php.net/manual/en/domdocument.loadhtml.php#91513         
            if(is_null($url)) {
                $this->appError = $this->my_error('e_importBlogError');
                return false;
            }
            $content = file_get_contents($url);
            if (!empty($content) && function_exists('mb_convert_encoding')) {
                    mb_detect_order("ASCII,UTF-8,ISO-8859-1,windows-1252,iso-8859-15");
                
                $encod = mb_detect_encoding($content);
                $headpos = mb_strpos($content,'<head>');
                if (FALSE === $headpos)
                    $headpos= mb_strpos($content,'<HEAD>');
                if (FALSE !== $headpos) {
                    $headpos+=6;
                    $content = mb_substr($content,0,$headpos) . '<meta http-equiv="Content-Type" content="text/html; charset='.$encod.'">' .mb_substr($content,$headpos);
                }
                $content = mb_convert_encoding($content, 'HTML-ENTITIES', $encod);
            }
            return $content;
	}
        /**
         * Reads the content from html file and clean the filePrepares the WP_Posts post_content and post_title
         * @param URL
         * @return boolean true on successful Dom parsing else false
         */
        function preparePostContent($url) {
             if(is_null($url)) {                
                return false;
            }           
            $cross_post_info = "<h3>Cross post from </h3> <a href='".$url."'>$url</a>";
            $doc = new DOMDocument();
            $doc->strictErrorChecking = false; // ignore invalid HTML, we hope
            $doc->preserveWhiteSpace = false;  
            $doc->formatOutput = false;  // speed this up   
            //$content = file_get_contents($url);           
            $content = $this->handle_accents($url);           
            @$doc->loadHTML($content);             
            $xml = @simplexml_import_dom($doc);                      
            // avoid asXML errors when it encounters character range issues
            libxml_clear_errors();
            libxml_use_internal_errors(false);  
            //getting title
            $titletag = "title";				
            $titlequery = '//'.$titletag;				
            $title = $xml->xpath($titlequery); 
            if (isset($title) && !empty($title)) {
                $this->postContentArray['post_title'] = $title[0]->asXML(); // asXML() preserves HTML in content
            }
            else { // fallback                
                if (isset($title[0]) && !empty($title[0])) {
                    $this->postContentArray['post_title'] = $title[0];
                }
                elseif (!empty ($title)) {
                    $this->postContentArray['post_title'] = (string)$title;                    
                }
                else{
                    $this->postContentArray['post_title'] = '';
                }
            }        
            //If div ids are given then we need to retreive only those div data            
            if(is_array($this->getContent) && !empty($this->getContent)) {
                $totalDivContent = '';
                $count = count($this->getContent);                
                for($divcount =0; $divcount<$count;$divcount++) {                    
                    $xquery = '//div[@id="'.$this->getContent[$divcount].'"]';                   
                    $blockContent = $xml->xpath($xquery);
                    if (is_array($blockContent) && isset($blockContent[0]) && is_object($blockContent[0]) && !empty($blockContent[0])) {
			$totalDivContent .= $blockContent[0]->asXML();
                    }else{
                        //need to place error here
                        $this->appError = $this->my_error('e_divcontent');
                        return false;
                    }                    
                }
                if(empty($totalDivContent)){
                    $this->appError = $this->my_error('e_divcontent');
                    return false;
                }else {                     
                    $this->postContentArray['post_content'] = $cross_post_info.$this->cleanHTML($totalDivContent);
                }
            } else {
                $this->postContentArray['post_content'] = $cross_post_info.$this->cleanHTML($content);
            }
            return true;
        }
        /**
         * Insert a post with prepared post_array
         * @return  int|false The post ID on success false on failure
         * 
         */
        function get_postID() {
            // this gets the content AND imports the post because we have to build $this->filearr as we go so we can find the new post IDs of files'
            $post_id = '';
            $this->postContentArray['post_title'] = trim(strip_tags($this->postContentArray['post_title']));
            $this->postContentArray['post_name'] = $this->postContentArray['post_title'];            
            // post type
            $this->postContentArray['post_type'] = "post";
            //$my_post['post_parent'] lets make this as 0
            $this->postContentArray['post_date'] = date("Y-m-d H:i:s", time());
            $this->postContentArray['post_date_gmt'] = date("Y-m-d H:i:s", time());
            // status
            $this->postContentArray['post_status'] = "publish";
            // author
            $currentuser = wp_get_current_user();
            $post_author = $currentuser->ID;
            $this->postContentArray['post_author'] = $post_author;              
            if(empty($this->appError)) {
                $post_id = wp_insert_post($this->postContentArray);
            }
            if($post_id) {
                $this->filearr[$post_id] = $this->postContentArray['post_content'];
                return $post_id;
            }else {
                return false;
            }
            // handle errors
            if ( is_wp_error( $post_id ) ) {                 
                return false;
            }     
            
            
        }
        function install() {
            //nothing to set options
        }
    }
}
if ( class_exists('BlogImporter') ) {	
    $blog_import = new BlogImporter();
    if (isset($blog_import)) {
        register_activation_hook( __FILE__, array(&$blog_import, 'install') );
        $GLOBALS['crosspost'] = $blog_import;
    }
}	
?>