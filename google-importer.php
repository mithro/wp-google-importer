<?php
/*
Plugin Name: Google+ Importer
Plugin URI: http://sutherlandboswell.com/projects/google-plus-importer-for-wordpress/
Description: Automatically import your Google+ activity as WordPress posts
Author: Sutherland Boswell
Author URI: http://sutherlandboswell.com
Version: 1.1
License: GPL2
*/
/*  Copyright 2011 Sutherland Boswell  (email : sutherland.boswell@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Activation and Deactivation

register_activation_hook(__FILE__,'google_importer_activate');
register_deactivation_hook(__FILE__,'google_importer_deactivate');

function google_importer_activate() {

	// Initialize options
	add_option('google_plus_importer_api_key','');
	add_option('google_plus_importer_user_id','');
	add_option('google_plus_importer_author_id','');
	add_option('google_plus_importer_post_status','publish');
	add_option('google_plus_importer_post_type','post');
	add_option('google_plus_importer_category_id','');
	add_option('google_plus_importer_tags','');
	add_option('google_plus_importer_title_characters','40');
	add_option('google_plus_importer_via_text','');
	add_option('google_plus_importer_selective_tag','');
	add_option('google_plus_importer_hashtags','');
	
	// Add scheduled event
	wp_schedule_event(time(), 'hourly', 'google_importer_scheduled_task');
}

function google_importer_deactivate() {

	// Remove options
	delete_option('google_plus_importer_api_key');
	delete_option('google_plus_importer_user_id');
	delete_option('google_plus_importer_author_id');
	delete_option('google_plus_importer_post_status');
	delete_option('google_plus_importer_post_type');
	delete_option('google_plus_importer_category_id');
	delete_option('google_plus_importer_tags');
	delete_option('google_plus_importer_title_characters');
	delete_option('google_plus_importer_via_text');
	delete_option('google_plus_importer_selective_tag');
	delete_option('google_plus_importer_hashtags');
	
	// Remove scheduled event
	wp_clear_scheduled_hook('google_importer_scheduled_task');
}

// Add scheduled action

add_action('google_importer_scheduled_task', 'scan_google_plus_activity');

// Convert SWF to WordPress-friendly URL

function google_importer_video_url($swf_url) {
	preg_match('#http://w?w?w?.?youtube.com/[ve]/([A-Za-z0-9\-_]+).+?#s', $swf_url, $matches);
	if (isset($matches[1])) return "http://www.youtube.com/watch?v=".$matches[1];
	else {
		preg_match('#http://w?w?w?.?vimeo.com/moogaloop.swf\?clip_id=([A-Za-z0-9\-_]+)&#s', $swf_url, $matches);
		if (isset($matches[1])) return "http://www.vimeo.com/".$matches[1];
		elseif (!isset($matches[1])) return null;
	}
}
	
// Importing stuff
	
function getLatestPlusPosts($user_id, $max_results) {
	$api_key = get_option('google_plus_importer_api_key');
	$request = "https://www.googleapis.com/plus/v1/people/$user_id/activities/public?key=$api_key&maxResults=$max_results";
	$response = wp_remote_get( $request, array( 'sslverify' => false ) );
	if( is_wp_error( $response ) ) {
		echo '<p>Something went wrong using <code>wp_remote_get()</code></p>';
		$error_string = $response->get_error_message();
		echo '<p class="error">' . $error_string . '</p>';
	} else {
		$response = json_decode($response['body']);
		return $response;
	}
}

function insert_post_from_plus($post_title, $post_text, $publish_date, $activity_id, $hashtags) {
	$post_status = get_option('google_plus_importer_post_status');
	$post_type = get_option('google_plus_importer_post_type');
    $author_id = get_option('google_plus_importer_author_id');
    $category_id = get_option('google_plus_importer_category_id');
    $default_tags = get_option('google_plus_importer_tags');
    $tags = $default_tags.",".$hashtags;
    $post = array(
      'post_title' => $post_title,
      'post_author' => $author_id,
      'post_category' => array($category_id),
      'post_content' => $post_text,
      'post_date' => $publish_date,
      'post_status' => $post_status,
      'post_type' => $post_type,
      'tags_input' => $tags
    );
    $new_post_id = wp_insert_post($post);
    add_post_meta($new_post_id, 'google_plus_activity_id', $activity_id, TRUE);
}

function scan_google_plus_activity() {	
    if (get_option('google_plus_importer_api_key')=='') echo "Visit the Google+ WordPress settings page and enter your API key";
    else {
    	$results = getLatestPlusPosts(get_option('google_plus_importer_user_id'), '20');
		
		// Error checking
		if ($results->error) :
			echo $results->error->message;
		// Go ahead if there are no errors
		elseif ($results->items):
		
			// Get selective tag
			$selective_tag = strtolower(" " . get_option('google_plus_importer_selective_tag') . " ");

			foreach ($results->items as $item) {
			
				// See if item should be imported
				if ($selective_tag != '') {
					$content_and_annotation = strtolower(" " . $item->object->content . " " . $item->annotation . " ");
					$pos = strpos($content_and_annotation,$selective_tag);
					if($pos === false) {
						// Selective tag not found
						continue;
					}
					else {
						// Found the tag, go ahead normally
					}
				}
				
				// Construct content
			    $post_content = '';
			    /*echo "<pre>";
			    var_dump($item);
			    echo "</pre>";*/
			    if ($item->verb == "share" && $item->annotation) $post_content .= $item->annotation . "\n\n";
			    if ($item->verb == "share") $post_content .= "<img src=\"" . $item->object->actor->image->url . "?sz=24\" style=\"vertical-align:middle;\"> <a href=\"" . $item->object->actor->url . "\">" . $item->object->actor->displayName . "</a> originally shared this post:\n\n";
			    if ($item->object->content != "") $post_content .= $item->object->content . "\n";
			    if ($item->object->attachments) foreach ($item->object->attachments as $attachment) {
			    	if ($attachment->objectType == 'article') {
			    		$post_content .= "\n<a href=\"" . $attachment->url . "\">" . $attachment->displayName . "</a>\n";
			    		if ($item->object->attachments[1]->objectType == 'photo') $post_content .= "\n<img src=\"" . $item->object->attachments[1]->image->url . "\" class=\"alignleft\">";
			    		if (!$item->object->attachments[1]->objectType == 'photo' && $attachment->content) $post_content .= "\n";
			    		if ($attachment->content) $post_content .= $attachment->content . "\n";
			    		break;
			    	}
			    	if ($attachment->objectType == 'photo') $post_content .= "\n<a href=\"" . $attachment->url . "\"><img src=\"" . $attachment->image->url . "\" class=\"alignleft\"></a>\n";
			    	if ($attachment->objectType == 'video') {
			    		if ($video_url=google_importer_video_url($attachment->url)) $post_content .= "\n$video_url\n";
			    	}
			    }
			    if ($item->geocode)	{
			    	$coordinates = explode(' ',$item->geocode);
			    	$coordinates = implode(',',$coordinates);
			    	$post_content .= "\n<a href=\"http://maps.google.com/?ll=$coordinates&q=$coordinates\"><img src=\"http://maps.googleapis.com/maps/api/staticmap?center=$coordinates&zoom=12&size=75x75&maptype=roadmap&markers=size:small|color:red|$coordinates&sensor=false\" class=\"alignleft\"></a>\n";
			    }
			    if ($item->placeName) $post_content .= "\n" . $item->placeName . "\n";
			    if ($item->address) $post_content .= "\n<a href=\"http://maps.google.com/?ll=$coordinates&q=$coordinates\">" . $item->address . "</a>\n";
				$post_check = new WP_Query( array(
				        // http://codex.wordpress.org/Function_Reference/WP_Query#Custom_Field_Parameters
				        'meta_query' => array(
				                array(
				                        'key' => 'google_plus_activity_id',
				                        'value' => $item->id,
				                ),
				        ),
				        'post_type' => get_option('google_plus_importer_post_type'),
				        'post_status' => array( 'publish', 'pending', 'draft', 'trash' ),
				        'posts_per_page' => 1, // Just checking if it exists, we don't actually need the results
				        'no_found_rows' => true, // We don't care how many total posts match these parameters
				        'update_post_meta_cache' => false, // Don't pre-fetch all custom fields for the results
				        'update_post_term_cache' => false, // Don't pre-fetch all taxonomies for the results
				) );
				 
				if ( !$post_check->have_posts() ) {
					// Create title
					$post_title = $item->title;
					// If there's no title get the name of anything attached
					if ( $post_title == '' && $item->object->attachments[0]->displayName ) $post_title = $item->object->attachments[0]->displayName;
					// Get only the first line of the title
					$post_title = explode("\n", $post_title);
					$post_title = $post_title[0];
					// Shorten title if it's too long
					$max_characters = get_option('google_plus_importer_title_characters');
					if (strlen($post_title)>$max_characters) {
						preg_match('/(.{' . $max_characters . '}.*?)\b/', $post_title, $matches);
						if ( strlen(rtrim($matches[1])) < strlen($post_title) ) $post_title = rtrim($matches[1]) . "...";
					}
					
					// Check for hashtags
					$hashtags = null;
					if(get_option('google_plus_importer_hashtags')==1) {
						$content_to_match = html_entity_decode(" " . $item->object->content . " " . $item->annotation . " ",ENT_QUOTES);
						preg_match_all('#(\#[A-Za-z0-9_]+)#', $content_to_match, $matches);
						if(isset($matches[1])) {
							foreach($matches[1] as $match) {
								// Remove # symbol and add to array
								$hashtags[] = substr( $match, 1 );
							}
							$hashtags = implode(',', (array) $hashtags);
						}
					}
					
					// Insert post into WordPress
					insert_post_from_plus($post_title, $post_content, date("Y-m-d H:i:s",strtotime($item->published)), $item->id, $hashtags);
				}
			}
		endif;
    }
}

// Add class to posts from Google+ for easy CSS styling

function google_plus_post_class($classes) {
	global $post;
	if (get_post_meta($post->ID, 'google_plus_activity_id', true)) $classes[] = "google-plus";
	return $classes;
}
add_filter('post_class', 'google_plus_post_class');

// Adds "via Google+" to posts

add_filter( 'the_content', 'via_google_plus_filter', 20 );

function via_google_plus_filter( $content ) {

	$via_text = get_option('google_plus_importer_via_text');

    if ( $via_text && get_post_meta($GLOBALS['post']->ID, 'google_plus_activity_id', true) ) $content = $content . "\n\n<span class=\"via-google-plus\">$via_text</span>";

    return $content;
}

// Check now AJAX

if ( isset ( $_GET['page'] ) && ( $_GET['page'] == 'google-importer-options' ) ) {
	add_action('admin_head', 'check_google_activity_now_ajax_call');
}

function check_google_activity_now_ajax_call() {
?>

<!-- Check Google+ Activity Now Ajax -->
<script type="text/javascript" >
function check_google_activity() {
	var checkButton = document.getElementById('google-activity-check-button');
	checkButton.disabled = true;
	checkButton.value = 'Checking...';
	window.setInterval(function(){
		if(checkButton.value == 'Checking...'){
			checkButton.value = 'Checking';
		} else{
			checkButton.value += '.';
		}
	}, 300);
	// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
	jQuery.post(ajaxurl, { action: 'check_google_activity_now' }, function(response) {
		document.getElementById('google-activity-check').innerHTML = response;
	});
	return false;
};
</script>

<?php
}

add_action('wp_ajax_check_google_activity_now', 'check_google_activity_now_callback');

function check_google_activity_now_callback() {
	// Clear existing schedule
	wp_clear_scheduled_hook('google_importer_scheduled_task');
	
	// New schedule starts in 1 hour
	$nextHour = time() + 3600;
	wp_schedule_event($nextHour, 'hourly', 'google_importer_scheduled_task');
	
	// Manually call the scan right now
	scan_google_plus_activity();
	
?>

<p>Successfully checked Google+.</p>
<p>The importer will check again in <strong><?php $seconds = wp_next_scheduled('google_importer_scheduled_task') - time(); echo sprintf( "%02.2d:%02.2d", floor( $seconds / 60 ), $seconds % 60 ); ?></strong> minutes.</p>

<?php

	die();
}

// Admin Page

add_action('admin_menu', 'google_importer_menu');

function google_importer_menu() {

  add_options_page('Google+ Importer Options', 'Google+ Importer', 'manage_options', 'google-importer-options', 'google_importer_options');

}

function google_importer_options() {

  if (!current_user_can('manage_options'))  {
    wp_die( __('You do not have sufficient permissions to access this page.') );
  }

?>

<div class="wrap">

	<div id="icon-plugins" class="icon32"></div><h2>Google+ Importer</h2>
	
	<h3>Getting Started</h3>
	
	<p>Before using this plugin you need to get an API key from Google. Visit the <a href="https://code.google.com/apis/console/">Google API site</a>, make sure the Google+ API is switched on under services, then under API Access copy your API key from "Simple API Access" and paste it into this page.</p>
	<p>You'll also need to copy and paste your user ID into this page, which can be found by visiting your profile and copying the number (Ex: <code>https://plus.google.com/<strong>116604883211169478613</strong>/posts</code>) from the url.</p>
	<p>Lastly, you should set the author the posts will be attributed to and assign them to a category. I recommend creating a category specifically for Google+ posts.</p>
	
	<p>Say thanks by donating, any amount is appreciated!<form action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick"><input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----"><input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!"><img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1"></form></p>
	
	<div id="icon-options-general" class="icon32"></div><h2>Options</h2>

	<form method="post" action="options.php">
	<?php wp_nonce_field('update-options'); ?>

	<h3>Required Settings</h3>
	
	<?php
	// Check API configuration
	if ( !get_option('google_plus_importer_api_key') OR !get_option('google_plus_importer_user_id') ) :
		echo "<p><span style=\"color:red\">Warning!</span> Google+ importer will not function properly until an API key and user ID are set.</p>";
	else:
		$results = getLatestPlusPosts(get_option('google_plus_importer_user_id'), 1);
		if ($results->error) echo "<p><span style=\"color:red\">Warning!</span> There was an error reaching the Google+ API:</p><p><code>" . $results->error->message . "</code></p><p>Make sure you've entered the correct API key, have the Google+ API <a href=\"https://code.google.com/apis/console/\">turned on</a>, and the correct numeric user ID.</p>";
	endif;
	?>
	
	<table class="form-table">
	
	<tr valign="top">
	<th scope="row">API Key</th> 
	<td><fieldset><legend class="screen-reader-text"><span>API Key</span></legend> 
	<input name="google_plus_importer_api_key" type="text" id="google_plus_importer_api_key" value="<?php echo get_option('google_plus_importer_api_key'); ?>" />
	</fieldset></td> 
	</tr>
	<tr valign="top">
	<th scope="row">Google+ User ID</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Google+ User ID</span></legend> 
	<input name="google_plus_importer_user_id" type="text" id="google_plus_importer_user_id" value="<?php echo get_option('google_plus_importer_user_id'); ?>" />
	</fieldset></td> 
	</tr>
	
	</table>
	
	<h3>Optional Settings</h3>
	
	<table class="form-table">
	
	<tr valign="top">
	<th scope="row">Assign to Author</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Assign to Author</span></legend>
	<?php wp_dropdown_users( array( 'selected' => get_option('google_plus_importer_author_id'), 'name' => 'google_plus_importer_author_id', 'id' => 'google_plus_importer_author_id' ) ); ?>
	</fieldset></td>
	</tr>
	<tr valign="top">
	<th scope="row">Assign to Category</th>
	<td><fieldset><legend class="screen-reader-text"><span>Assign to Category</span></legend> 
	<?php $category_selection_args = array(
    'show_option_none'   => 'Don\'t assign to category',
    'hide_empty'         => 0,
    'selected'           => get_option('google_plus_importer_category_id'),
    'name'               => 'google_plus_importer_category_id',
    'id'                 => 'google_plus_importer_category_id',
    'hide_if_empty'      => false ); ?>
    <?php wp_dropdown_categories( $category_selection_args ); ?>
	</fieldset></td>
	</tr>
	<tr valign="top">
	<th scope="row">Tags (separated by commas)</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Tags (separated by commas)</span></legend> 
	<input name="google_plus_importer_tags" type="text" id="google_plus_importer_tags" value="<?php echo get_option('google_plus_importer_tags'); ?>" />
	</fieldset></td> 
	</tr>
	<tr valign="top"> 
	<th scope="row">Import Hashtags</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Import Hashtags</span></legend> 
	<label for="google_plus_importer_hashtags"><input name="google_plus_importer_hashtags" type="checkbox" id="google_plus_importer_hashtags" value="1" <?php if(get_option('google_plus_importer_hashtags')==1) echo "checked='checked'"; ?>/> Save hashtags from posts as WordPress tags</label> 
	</fieldset></td> 
	</tr>
	<tr valign="top">
	<th scope="row">Maximum Title Length</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Maximum Title Length</span></legend> 
	<input name="google_plus_importer_title_characters" type="text" id="google_plus_importer_title_characters" value="<?php echo get_option('google_plus_importer_title_characters'); ?>" size="3" />
	</fieldset></td>
	</tr>
	<tr valign="top">
	<th scope="row">Via Google+ Text (<a title="This text will be shown at the end of posts from Google+.">?</a>)</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Via Google+ Text</span></legend> 
	<input name="google_plus_importer_via_text" type="text" id="google_plus_importer_via_text" value="<?php echo get_option('google_plus_importer_via_text'); ?>" />
	</fieldset></td>
	</tr>
	<tr valign="top">
	<th scope="row">Post Status</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Post Status</span></legend> 
	<select name="google_plus_importer_post_status" id="google_plus_importer_post_status" class="postform">
		<option value="publish"<?php if ( get_option('google_plus_importer_post_status') == 'publish' ) echo ' selected="selected"'; ?>>Published</option>
		<option value="draft"<?php if ( get_option('google_plus_importer_post_status') == 'draft' ) echo ' selected="selected"'; ?>>Draft</option>
	</select>
	</fieldset></td> 
	</tr>
	<tr valign="top">
	<th scope="row">Post Type</th>
	<td><fieldset><legend class="screen-reader-text"><span>Post Type</span></legend> 
		<select name="google_plus_importer_post_type" id="google_plus_importer_post_type" class="postform">
<?php
	$post_types	= get_post_types( array( 'public' => true, 'show_ui' => true ), 'objects' );
	foreach ( $post_types as $post_type => $pt ) {
		$selected = ''; if ( esc_attr( $pt->name ) == get_option('google_plus_importer_post_type') ) $selected = ' selected="selected"';
		echo '<option value="' . esc_attr( $pt->name ) . '"' . $selected . '>' . $pt->labels->singular_name . "</option>\n";
	}
?>
		</select>
	</fieldset></td>
	</tr>
	<tr valign="top">
	<th scope="row">Selective Tag (<a title="Leave blank to import any posts or limit which posts are imported when posting to Google+ by specifying a word or tag here. Ex: #wordpress">?</a>)</th> 
	<td><fieldset><legend class="screen-reader-text"><span>Selective Tag</span></legend> 
	<input name="google_plus_importer_selective_tag" type="text" id="google_plus_importer_selective_tag" value="<?php echo get_option('google_plus_importer_selective_tag'); ?>" />
	</fieldset></td>
	</tr>
	
	</table>
	
	<input type="hidden" name="action" value="update" />
	<input type="hidden" name="page_options" value="google_plus_importer_api_key,google_plus_importer_user_id,google_plus_importer_author_id,google_plus_importer_post_status,google_plus_importer_post_type,google_plus_importer_category_id,google_plus_importer_tags,google_plus_importer_title_characters,google_plus_importer_via_text,google_plus_importer_selective_tag,google_plus_importer_hashtags" />
	
	<p class="submit">
	<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
	</p>
	
	</form>
	
	<h3>Next Import</h3>
	
	<div id="google-activity-check">
		<p>The importer will check again in <strong><?php $seconds = wp_next_scheduled('google_importer_scheduled_task') - time(); echo sprintf( "%02.2d:%02.2d", floor( $seconds / 60 ), $seconds % 60 ); ?></strong> minutes.</p>
		<p><input type="submit" id="google-activity-check-button" class="button-primary" onclick="return check_google_activity();" value="Check now" /></p>
	</div>

</div>

<?php

}

?>