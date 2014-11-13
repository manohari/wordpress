<?php
/*
Plugin Name: Import Blog Page
Plugin URI: http://wordpress.org/extend/plugins/
Description: Import pages from your blog.
Version: 1.1
Author: Manohari Vagicherla
 */

if ( !class_exists('BlogImporter') ) {
    class BlogImporter {
        var $plugin_domain='BlogImporter';
        var $getContent = array();
        var $appError = '';
        function BlogImporter()  {	
            $this->plugin_url = defined('WP_PLUGIN_URL') ? WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)) : trailingslashit(get_bloginfo('wpurl')) . PLUGINDIR . '/' . dirname(plugin_basename(__FILE__)); 
            $this->error = new WP_Error();
            $this->init_errors();
            // Add Options Page
            add_action('admin_menu',  array(&$this, 'admin_menu'));
        }
        function admin_menu() {          	
            // submenu pages           
            add_submenu_page('post-new.php', __('Add CrossPost', $this->plugin_domain) ,  __('CrossPost', $this->plugin_domain)	, 1 	, 'add-crosspost',  array(&$this, 'display_form') ); 	  	 
        } 
        // Init error messages	
	function init_errors()
	{
            $this->error->add('e_url', __('Please enter URL.',$this->plugin_domain));			
            $this->error->add('e_protocol', __('Please add "http://" or "https://" before the domain name.',$this->plugin_domain));           
            $this->error->add('e_importError', __('No content to import.Please check the page',$this->plugin_domain));
            $this->error->add('e_importBlogError', __('Could not import the data.Something went wrong.',$this->plugin_domain));			
            $this->error->add('e_divcontent', __('No content exist in div.', $this->plugin_domain));
	}
        // Retrieve an error message
	function my_error($e = '') {		
		$msg = $this->error->get_error_message($e);		
		if ($msg == null) {
			return __("Unknown error occured, please contact the administrator.", $this->plugin_domain);
		}
		return $msg;
	}
        function display_form() {            			
            $page=trim($_GET['page']);
            $published=isset($_POST['publish']);            	
            $get_div_content= trim($_POST['contentdivid']);
            if($page == 'add-crosspost') {
                $url= trim($_POST['url']);										
                if ($published)
                {
                    if (!empty($url) && isset($url)) {                                        
                        $this->cross_post_info = "<h3>Cross post from </h3> <a href='".$url."'>$url</a>";                        
                        if((stristr($url,'http://') || stristr($url,'https://') ) === false)
                        {                             
                            $this->appError = $this->my_error('e_protocol');                            
                        } 
                        else{
                            if( (@file_get_contents($url)) === false ) {
                                $this->appError = $this->my_error('e_importError');
                            }else{
                                $this->tempfile = file_get_contents($url);
                                if(!empty($get_div_content)) {
                                    $this->getContent = explode(",",$get_div_content);                                    
                                }                                   
                               $post_id = $this->insert_pageContent();                                
                            }   
                        }                            							
                    }
                    else {						
                        $this->appError = $this->my_error('e_url');
                    }
                }
                include( 'crosspost.php');
            }
        }
        
        function insert_pageContent()
        {
            set_magic_quotes_runtime(0);
            $postid = $this->get_post();           
            if(!empty($postid)) {
                $this->find_images();
                return $postid;
            }
        }
        function find_images() {
            if(is_array($this->filearr)) {
                $postid = key($this->filearr);
                $this->import_images($postid);
            }             
        }
        // largely borrowed from the Add Linked Images to Gallery plugin, except we do a simple str_replace at the end
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
                                $custom = str_replace($src, $imgpath, $custom);
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
        //Handle an individual file import. 
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

                //Win32 fix:
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
        function remove_dot_segments( $path ) {
            //This function is mainly used to create absolute URL of image and store in DB.
            //It excludes any parameters if added to absolute url and provides baseurl
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
        
        function cleanHTML($string){
            $replaceElements = array('\n','&#13;','//','?','&nbsp');
            $string = str_replace($replaceElements, '', $string);    
            $string = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $string);            
            // reduce line breaks and remove empty tags             
            $string = preg_replace( "/<[^\/>]*>([\s]?)*<\/[^>]*>/", ' ', $string );            
            // get rid of remaining newlines; basic HTML cleanup           
            $string = ereg_replace("[\n\r]", " ", $string); 
            $string = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $string);
            $string = str_replace('<br>', '<br />', $string);
            
            return $string;
        }
        function handle_accents() {
            // from: http://www.php.net/manual/en/domdocument.loadhtml.php#91513
            $content = $this->tempfile;                
            if (!empty($content) && function_exists('mb_convert_encoding')) {
                mb_detect_order("ASCII,UTF-8,ISO-8859-1,windows-1252,iso-8859-15");
                if (empty($encod)) {
                    $encod = mb_detect_encoding($content);
                    $headpos = mb_strpos($content,'<head>');
                }
                if (FALSE === $headpos) {
                    $headpos= mb_strpos($content,'<HEAD>');
                }
                if (FALSE !== $headpos) {
                    $headpos+=6;
                    $content = mb_substr($content,0,$headpos) . '<meta http-equiv="Content-Type" content="text/html; charset='.$encod.'">' .mb_substr($content,$headpos);
                }
                $content = mb_convert_encoding($content, 'HTML-ENTITIES', $encod);
            }
            return $content;
	}
        function get_post() {
            // this gets the content AND imports the post because we have to build $this->filearr as we go so we can find the new post IDs of files' parent directories
            set_time_limit(540);                      
            set_magic_quotes_runtime(0);
            $doc = new DOMDocument();
            $doc->strictErrorChecking = false; // ignore invalid HTML, we hope
            $doc->preserveWhiteSpace = false;  
            $doc->formatOutput = false;  // speed this up   
            $content = $this->handle_accents();            
            @$doc->loadHTML($content);          
            $xml = @simplexml_import_dom($doc);                      
            // avoid asXML errors when it encounters character range issues
            libxml_clear_errors();
            libxml_use_internal_errors(false);            
            // start building the WP post object to insert
            $my_post = array();	
            $post_id = '';
            //getting title
            $titletag = "title";				
            $titlequery = '//'.$titletag;				
            $title = $xml->xpath($titlequery);             
            
            if (isset($title)) {
                $title = $title[0]->asXML(); // asXML() preserves HTML in content
            }
            else { // fallback                
                $title = $xml->xpath($titlequery);
                if (isset($title[0])) {
                    $title = $title[0];
                }
                if (empty($title)) {
                    $title = '';
                }
                else {
                    $title = (string)$title;                    
                }    
            }
            $title = str_replace('<br>',' ',$title);           
            //If div ids are given then we need to retreive only those div data            
            if(is_array($this->getContent) && !empty($this->getContent)) {
                $totalDivContent = '';
                for($divcount =0; $divcount<count($this->getContent);$divcount++) {                    
                    $xquery = '//div[@id="'.$this->getContent[$divcount].'"]';                   
                    $divcontent = $xml->xpath($xquery);
                    if (is_array($divcontent) && isset($divcontent[0]) && is_object($divcontent[0])) {
			$totalDivContent .= $divcontent[0]->asXML();
                    }else{
                        //need to place error here
                        $this->appError = $this->my_error('e_divcontent');
                        return;
                    }                    
                }
                if(empty($totalDivContent)){
                    $this->appError = $this->my_error('e_divcontent');
                    return;
                }else {
                    $my_post['post_content'] = $this->cross_post_info.$this->cleanHTML($totalDivContent);
                }
            } else {
                $my_post['post_content'] = $this->cross_post_info.$this->cleanHTML($content);
            } 
            
            $my_post['post_title'] = trim(strip_tags($title));
            $my_post['post_name'] = trim(strip_tags($title));
            
            // post type
            $my_post['post_type'] = "post";
            //$my_post['post_parent'] lets make this as 0
            $my_post['post_date'] = date("Y-m-d H:i:s", time());
            $my_post['post_date_gmt'] = date("Y-m-d H:i:s", time());
            // status
            $my_post['post_status'] = "publish";
            // author
            $currentuser = wp_get_current_user();
            $post_author = $currentuser->ID;
            $my_post['post_author'] = $post_author;
            if(empty($this->appError)) {
                $post_id = wp_insert_post($my_post);
            }
           // add_post_meta($post_id, 'siteURL', $this->cross_post_url, true);
            // handle errors
            if ( is_wp_error( $post_id ) ) {
                $this->appError = $this->my_error('e_importBlogError'); 
            }
            if (!$post_id) {
                $this->appError = $this->my_error('e_importBlogError');                
            }  else {
                $this->filearr[$post_id] = $this->tempfile;
                return $post_id;
            }            
            
        }       
    }
}
if ( class_exists('BlogImporter') ) {	
    $blog_import = new BlogImporter();
    if (isset($blog_import)) {
        register_activation_hook( __FILE__, array(&$blog_import, 'install') );
    }
}	

?>