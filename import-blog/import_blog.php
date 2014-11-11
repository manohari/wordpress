<?php
/*
Plugin Name: Import Blog Page
Plugin URI: http://wordpress.org/extend/plugins/
Description: Import pages from your blog.
Version: 1.0
Author: Manohari Vagicherla
*/
if ( !defined('WP_LOAD_IMPORTERS') )
	return;

// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
    $class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
    if ( file_exists( $class_wp_importer ) ) {
        require_once $class_wp_importer;
    }
}
if ( !class_exists('BlogImporter') )  {
    class BlogImporter extends WP_Importer {	
        function header() 
        {
            echo '<div class="wrap">';
            screen_icon();
            echo '<h2>'.__('Import Content from blog with public URL').'</h2><br />';
        }

        function footer() 
        {
            echo '</div>';
        }

        function greet()
        {	
            if(empty($_POST["site"])) 
            {	
                echo 'Hi, this tool can help you to copy your pages from your old blog online. <br \><br \>'.
                     'Please input the URL <b>(including "http://" or "https://")</b> <br \><br \>';
                echo '<form action="admin.php?import=import_blog&amp;step=1" method="post">'.
                         'URL: <input type="text" name="site" size="50"><br \><br \>'.	 
                         '<input type="submit" value="get Content">'.
                         '</form>';	
            }        
        }

        function get_blog_content()
        {
            if(isset($_POST["site"]) && !empty($_POST["site"])){
                $url = $_POST["site"];                
            }
            else {
                echo "<h3>Please add the url from which site you need to pick data</h3><a href='".$_SERVER['HTTP_REFERER']."'>Go Back </a>";
                exit;
            }
            if((stristr($url,'http://') || stristr($url,'https://') ) === false)
            {
                echo '<h3>Please add "http://" or "https://" before the domain name.</h3><a href="'.$_SERVER['HTTP_REFERER'].'">Go Back </a>';
                exit;
            }            
            if( (@file_get_contents($url)) === false ) {
                echo "<h3>Web page doesnt exist with the given URL.Please check the URL again</h3>.<a href='".$_SERVER['HTTP_REFERER']."'>Go Back </a>";
                exit;
            }            
            $this->tempfile = @file_get_contents($url);                
            if(empty($this->tempfile)) {
                echo "<h3>Not able to read the content from the page.Please check the URL given.</h3><a href='".$_SERVER['HTTP_REFERER']."'>Go Back </a>";
                exit;
            }
            $this->insert_pageContent();           
        }
        function insert_pageContent()
        {
            set_magic_quotes_runtime(0);
            $this->get_post();
            $this->find_images();
        }

        function find_images() {
            echo '<h2>'.__( 'Importing images...').'</h2>';
            $results = '';
            foreach ($this->filearr as $id => $path) {
                $results .= $this->import_images($id, $path);
            }
            if (!empty($results)) {
                echo $results;
            }
            echo '<h3>';
            printf(__('All done. <a href="%s">Go to the Media Library.</a>'), 'media.php');
            echo '</h3>';        
        }

                        // largely borrowed from the Add Linked Images to Gallery plugin, except we do a simple str_replace at the end
        function import_images($id, $path) {
            $post = get_post($id);		
            $result = array();
            $srcs = array();
            $content = $post->post_content;
            $title = $post->post_title;
            if (empty($title)) $title = __('(no title)', 'html-import');
            $update = false;

            // find all src attributes
            preg_match_all('/<img[^>]* src=[\'"]?([^>\'" ]+)/', $post->post_content, $matches);
            for ($i=0; $i<count($matches[0]); $i++) {
                    $srcs[] = $matches[1][$i];
            }

            // also check custom fields
            $custom = get_post_meta($id, '_ise_old_sidebar', true);
            preg_match_all('/<img[^>]* src=[\'"]?([^>\'" ]+)/', $custom, $matches);
            for ($i=0; $i<count($matches[0]); $i++) {
                    $srcs[] = $matches[1][$i];
            }

            if (!empty($srcs)) {
                $count = count($srcs);

                echo "<p>";
                printf(_n('Found %d image in <a href="%s">%s</a>. Importing... ', 'Found %d images in <a href="%s">%s</a>. Importing... ', $count), $count, get_permalink($post->ID), $title);
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
                        echo '<span class="attachment_error">'.$imgid->get_error_message().'</span>';
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
                        update_post_meta($id, '_ise_old_sidebar', $custom); 
                }

                _e('done.');
                echo '</p>';
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
                        return new WP_Error('upload_error', sprintf(__('Could not find the right path to %s (tried %s). It could not be imported. Please upload it manually.', 'html-import-pages'), basename($file), $file));
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
                $type = $return['type'];

                $title = preg_replace('!\.[^.]+$!', '', basename($file));
                $content = '';

                // use image exif/iptc data for title and caption defaults if possible
                if ( $image_meta = @wp_read_image_metadata($new_file) ) {
                        if ( '' != trim($image_meta['title']) ) {
                                $title = trim($image_meta['title']);
                        }
                        if ( '' != trim($image_meta['caption']) ) {
                                $content = trim($image_meta['caption']);
                        }
                }

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

        function handle_accents() {            
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
        function cleanHTML($string){
            $string = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $string);
            // reduce line breaks and remove empty tags
            $string = str_replace( '\n', ' ', $string ); 
            $string = preg_replace( "/<[^\/>]*>([\s]?)*<\/[^>]*>/", ' ', $string );
            // get rid of remaining newlines; basic HTML cleanup
            $string = str_replace('&#13;', ' ', $string); 
            $string = ereg_replace("[\n\r]", " ", $string); 
            $string = preg_replace_callback('|<(/?[A-Z]+)|', create_function('$match', 'return "<" . strtolower($match[1]);'), $string);
            $string = str_replace('<br>', '<br />', $string);
            $string = str_replace('<hr>', '<hr />', $string);
            return $string;
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
            $content = $this -> cleanHTML($content);
            @$doc->loadHTML($content);        
            $xml = @simplexml_import_dom($doc);
            // avoid asXML errors when it encounters character range issues
            libxml_clear_errors();
            libxml_use_internal_errors(false);
            echo '<h2>'.__( 'Importing Data from WebPage...').'</h2>';
            // start building the WP post object to insert
            $my_post = array();			
            //getting title
            $titletag = "title";				
            $titlequery = '//'.$titletag;				
            $title = $xml->xpath($titlequery);
            if (isset($title[0])) {
                $title = $title[0]->asXML(); // asXML() preserves HTML in content
            }
            else { // fallback
                //$title = $xml->xpath('//title');
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
            $my_post['post_title'] = trim(strip_tags($title));
            $my_post['post_name'] = trim(strip_tags($title));		
            // post type
            $my_post['post_type'] = "post";
            //$my_post['post_parent'] lets make this as 0
            $my_post['post_date'] = date("Y-m-d H:i:s", time());
            $my_post['post_date_gmt'] = date("Y-m-d H:i:s", time());
            $my_post['post_content'] = $content;

            // status
            $my_post['post_status'] = "publish";
            // author
            $currentuser = wp_get_current_user();
            $post_author = $currentuser->ID;
            $my_post['post_author'] = $post_author;
            $post_id = wp_insert_post($my_post);

            // handle errors
            if ( is_wp_error( $post_id ) ) {
                $this->table[] = "<tr><th class='error'>--</th><td colspan='3' class='error'> " . $post_id /* error msg */ . "</td></tr>";
            }
            if (!$post_id) {
                $this->table[] = "<tr><th class='error'>--</th><td colspan='3' class='error'> " . sprintf(__("Could not import the data %s. Something went wrong", 'blog-import')) . "</td></tr>";
            } else {
                echo '<h2>'.__( 'Done with content importing').'</h2>';
            }
            //add_post_meta($post_id, '_wp_page_template', 0, true);
            $this->filearr[$post_id] = $this->tempfile;       

        }

        function dispatch() 
        {	
            if (empty ($_GET['step'])){
                $step = 0;
            }
            else {
                $step = (int) $_GET['step'];
            }

            $this->header();			
            switch ($step) {
                case 0:
                $this->greet();
                break;

                case 1:
                $this->get_blog_content();
                break;

            }
            $this->footer();
        }

        function BlogImporter()  {	//nothing	
        }
    }
}
$blog_import = new BlogImporter();	
register_importer('import_blog', __('Import Page From Blog'), 'Import Page from any blog as post into word press', array ($blog_import, 'dispatch'));
?>