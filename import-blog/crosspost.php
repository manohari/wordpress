<div class="wrap">	
	
	<?php	if (!empty($this->appError)) : ?>	
		<div id="message" class="error fade">
			<p><?php echo $this->appError; ?></p>
		</div>			
	<?php	elseif (!empty($post_id)) : ?>	
		<div id="message" class="updated fade">
			<p><strong><?php _e('Content Imported.',$this->plugin_domain); ?></strong> <a href="<?php echo get_permalink($post_id); ?>"><?php _e('View post',$this->plugin_domain); ?> &raquo;</a></p>
		</div> 
        <?php endif; ?>
	
	<h2><?php _e('Cross Post From Blog',$this->plugin_domain); ?></h2>
	<form action="" method="post">
	<?php wp_nonce_field($_GET['page']); ?>
			
		<div id="poststuff">				
                    <div class="submitbox" id="submitpost">
                            <div id="previewview"></div>
                            <div class="inside"></div>
                            <p class="submit"><input name="publish" type="submit" class="button button-highlighted" tabindex="4" value="<?php if (current_user_can('publish_posts')) _e('Publish', $this->plugin_domain); else _e('Submit', $this->plugin_domain); ?>" /></p>
                    </div>
	
                    <div id="post-body">				
                        <div class="postbox ">
                            <h3><?php _e('URL',$this->plugin_domain); ?></h3>
                            <div class="inside">																				
                                <p>					  	
                                    <input style="width: 415px" type="text" tabindex="2" name="url" id="url" value="<?php echo esc_url($url); ?>" />					
                                </p>
                            </div>
                        </div>
                        <div class="postbox ">
                                <h3><?php _e('Div Id from which you need to extract content',$this->plugin_domain); ?></h3>					
                                <div class="inside">
                                    <input style="width: 415px" type="text" tabindex="2" name="contentdivid" id="contentdivid" value="<?php echo esc_attr($contentDivId); ?>" />					                                    
                                    <p>Please enter div ids to pull data.If empty total page content is pulled</p>
                                </div>
                        </div>

                    </div>
		</div>
	</form>	
</div>

