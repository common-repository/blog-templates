<?php
/*
	Plugin Name: Blog Templates
	Plugin URI: 
	Description: Allows the creation of templates that may be applied to any new blog.
	Version: 2.5.2
	Author: Greg Breese (gregsurname@gmail.com)
	
	Installation: Just place this file in your wp-content/mu-plugins/ directory.
	To setup templates go to Site Admin -> Blog Templates.
	
	Usage: A template selection box is placed on the signup page. Additionally, templates
	can be manually applied from the administration page. If a template called 'default'
	is created then it will be applied to all new blogs unless another template is
	specified.
	
	The gb_templates_selection_form() and gb_templates_selection_tr_form() functions can be 
	used to	add template selection to other pages. The later function adds a form within
	a table row.
	
	This plugin is fully compatable with sites running the Multi-Site Manager plugin.
	
	Acknowledgements: 
	
	Deanna Schneider's cets_blog_defaults plugin was of great help in
 	developing this plugin, both in terms of ideas and some snippits of code. Her plugin
	can be found at http://wpmudev.org/project/New-Blog-Defaults.
	
	Thanks to Nuno Morgadinho for implementing the cloning of all postmeta items.
	
	Copyright:

	    Copyright 2009 Greg Breese

	    This program is free software; you can redistribute it and/or modify
	    it under the terms of the GNU General Public License as published by
	    the Free Software Foundation; either version 2 of the License, or
	    (at your option) any later version.

	    This program is distributed in the hope that it will be useful,
	    but WITHOUT ANY WARRANTY; without even the implied warranty of
	    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	    GNU General Public License for more details.

	    You should have received a copy of the GNU General Public License
	    along with this program; if not, write to the Free Software
	    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
	
	
*/

// Define name of database table where templates are stored
define("GB_TEMPLATES_TABLE", $wpdb->base_prefix . "gb_templates");

// Initialises site options used by plugin
function gb_templates_setup() 
{
    global $wpdb;

	// Create separate table in db instead of using site_option to support multi-site
    $table_exists = $wpdb->query("SHOW TABLES LIKE '" . GB_TEMPLATES_TABLE . "'");
    if(!$table_exists) {
		
		// Todo: default values
		
		$setup_query = "CREATE TABLE ". GB_TEMPLATES_TABLE ." (
					      id int(11) unsigned NOT NULL auto_increment,
					      template_name VARCHAR(255) NOT NULL default '',
						  template_value longtext,
					      UNIQUE KEY id (id),
						  KEY template_name (template_name)
					      )";
		$results = $wpdb->query($setup_query);
	}
}

// Returns a list of the names of all saved templates
function gb_get_template_list() {	
	global $wpdb;
	
	$query = "SELECT template_name FROM " . GB_TEMPLATES_TABLE . ";";
	return $wpdb->get_col($query);
}

// Returns the array containing the details of a given template
function gb_get_template($key, $use_cache = true) {
	global $wpdb;
	
	if( $use_cache == true ) {
		$value = wp_cache_get(GB_TEMPLATES_TABLE . $key, 'gb_templates');
	} else {
		$value = false;
	}
	
	if ( false === $value ) {
		$value = $wpdb->get_var( $wpdb->prepare("SELECT template_value FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = %s", $key) );
		if ( ! is_null($value) ) {
			wp_cache_add(GB_TEMPLATES_TABLE . $key, $value, 'gb_templates');
		} else {
			wp_cache_add(GB_TEMPLATES_TABLE . $key, false, 'gb_templates');
			return false;
		}
	}
	
	$value = maybe_unserialize( $value );
	
	$value = stripslashes_deep($value);

	return $value;
}

// Creates a template with the name $key and template data $value 
function gb_create_template($key, $value = null) {
	global $wpdb;

	$exists = $wpdb->get_row( $wpdb->prepare("SELECT template_value FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = %s", $key) );
	if ( is_object( $exists ) ) {// If we already have it
		gb_update_template( $key, $value );
		return false;
	}

	$value = maybe_serialize($value);
	wp_cache_delete(GB_TEMPLATES_TABLE . $key, 'gb_templates');

	$wpdb->insert( GB_TEMPLATES_TABLE, array('template_name'=>$key, 'template_value' => $value) );
	return $wpdb->insert_id;
}

// Copies the template given, creating a new identical template with the new given name
// Error returns need tidying ... convert to wp_error instances
function gb_copy_template($key, $name) {
	global $wpdb;

	$exists = $wpdb->get_row( $wpdb->prepare("SELECT template_value FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = %s", $name) );

	if ( is_object( $exists ) ) {// If we already have it
		return "Error: Template name already exists.";
	}
	
	$value = gb_get_template($key);
	$id = gb_create_template($name,$value);
    if($id)
            return $name;
    return "Template creation failed.";
}

// Creates a new template that clones the currently active blog.
// TODO !!! Need to replace instances of SITE_URL, SITE_TITLE, BLOG_URL and BLOG_TITLE within posts/pages/links.
function gb_clone_blog($name) {
	global $wpdb, $current_site;
	
	$exists = $wpdb->get_row( $wpdb->prepare("SELECT template_value FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = %s", $name) );

	if ( is_object( $exists ) ) {// If we already have it
		return "Error: Template name already exists.";
	}
	
	// copy posts	
	$newoptions = array();
	$args = array('numberposts' => 30, 'orderby' => 'id', 'post_type' => 'post');
	$posts = get_posts($args);
	$post_array = array();
	$count = 0;
	
	foreach ($posts as $post) {
		$post_array[$count] = array();
		$post_array[$count]['post_status'] = $post->post_status;
		$post_array[$count]['post_type'] = 'post';
		$text = $post->post_content;
		$text = str_replace(clean_url("http://" . $current_site->domain . $current_site->path),"SITE_URL",$text);
		$text = str_replace(clean_url(get_option('siteurl')."/"), "BLOG_URL",$text);
		$text = str_replace(get_bloginfo('name'),"BLOG_NAME",$text);
		$text = str_replace($current_site->site_name,"SITE_NAME",$text);
		$post_array[$count]['text'] = $text;
		$post_array[$count]['title'] = $post->post_title;
		$term = wp_get_object_terms($post->ID, 'category');
		$post_array[$count]['category'] = $term[0]->name;
		$post_array[$count]['postmeta'] = get_post_custom( $post->ID );	
		$count++;
	}
	$newoptions['posts'] = $post_array;

	// copy pages	
	$args = array('numberposts' => 30, 'orderby' => 'id', 'post_type' => 'page');
	$posts = get_posts($args);
	$post_array = array();
	$count = 0;
	foreach ($posts as $post) {
		$post_array[$count] = array();
		$post_array[$count]['post_status'] = $post->post_status;
		$post_array[$count]['post_type'] = 'page';
		$text = $post->post_content;
		$text = str_replace(clean_url("http://" . $current_site->domain . $current_site->path),"SITE_URL",$text);
		$text = str_replace(clean_url(get_option('siteurl')."/"), "BLOG_URL",$text);
		$text = str_replace(get_bloginfo('name'),"BLOG_NAME",$text);
		$text = str_replace($current_site->site_name,"SITE_NAME",$text);
		$post_array[$count]['text'] = $text;
		$post_array[$count]['title'] = $post->post_title;
		$post_array[$count]['post_parent'] = get_post_field( 'post_title', $post->post_parent);
		$post_array[$count]['post_template'] = get_post_meta($post->ID, '_wp_page_template',true);
		$post_array[$count]['menu_order'] = $post->menu_order;
		$count++;
	}
	$newoptions['pages'] = $post_array;
	// copy links
	$links = get_bookmarks();
	$link_array = array();
	$count = 0;
	foreach($links as $link) {
		$link_array[$count] = array();
		$link_array[$count]['url'] = str_replace(clean_url("http://" . $current_site->domain . $current_site->path), "SITE_URL", $link->link_url);
		$link_array[$count]['title'] = str_replace($current_site->site_name,"SITE_NAME", $link->link_name);
		$term = wp_get_object_terms($link->link_id, 'link_category');
		$link_array[$count]['category'] = $term[0]->name;
		$count++;
	}
	$newoptions['links'] = $link_array;
	
	// copy post categories
	$categories = get_terms('category');
	$category_array = array();
	$count = 0;
	foreach($categories as $category) {
		if($category->term_id != "1") {
			$category_array[$count] = array();
			$category_array[$count]['name'] = $category->name;
			$count++;	
		}
	}
	$newoptions['post_categories'] = $category_array;
	
	// always delete the default post/links/page
	$newoptions['default_post'] = 'delete';
	$newoptions['default_links'] = 'delete';
	$newoptions['default_page'] = 'delete';
	
	// add blog options, should be easy to change this to grab ALL the options
	$newoptions['blog_public'] = get_option('blog_public');
	$newoptions['theme'] = get_option('template') . "|" . get_option('stylesheet');

	$newoptions['default_pingback_flag'] = get_option('default_pingback_flag');
	$newoptions['default_ping_status'] = get_option('default_ping_status');
	$newoptions['default_comment_status'] = get_option('default_comment_status');
	$newoptions['comment_registration'] = get_option('comment_registration');
	if ( version_compare( $wp_version, '2.7', '>=' ) ) {
		 $newoptions['close_comments_for_old_posts'] = get_option('close_comments_for_old_posts');
		 $newoptions['close_comments_days_old'] = get_option('close_comments_days_old');
		 $newoptions['thread_comments'] = get_option('thread_comments');
		 $newoptions['thread_comments_depth'] = get_option('thread_comments_depth');
		 $newoptions['page_comments'] = get_option('page_comments');
		 $newoptions['comments_per_page'] = get_option('comments_per_page');
		 $newoptions['default_comments_page'] = get_option('default_comments_page');
		 $newoptions['comment_order'] = get_option('comment_order');
	}
	$newoptions['comments_notify'] = get_option('comments_notify');
	$newoptions['moderation_notify'] = get_option('moderation_notify');
	$newoptions['comment_moderation'] = get_option('comment_moderation');
	$newoptions['require_name_email'] = get_option('require_name_email');
	$newoptions['comment_whitelist'] = get_option('comment_whitelist');
	$newoptions['blogdescription'] = get_option('blogdescription');
	
	if( gb_create_template($name, $newoptions) ) {
		return $name;
	} else {
		return "Error creating new template.";
	}
}

// Updates the template of the given key with the data array provided
function gb_update_template($key, $value) {
	global $wpdb;

	if ( $value == gb_get_template( $key ) )
	 	return false;

	$exists = $wpdb->get_row( $wpdb->prepare("SELECT template_value FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = %s", $key) );
	if ( false == is_object( $exists ) ) // It's a new record
		return gb_create_template( $key, $value );

	$value = maybe_serialize($value);

	$wpdb->update( GB_TEMPLATES_TABLE, array('template_value' => $value), array('template_name'=>$key) );
	wp_cache_delete( GB_TEMPLATES_TABLE . $key, 'gb_templates' );
}

// Deletes the template with the name provided
function gb_delete_template($key) {
	global $wpdb;
	
	wp_cache_delete( GB_TEMPLATES_TABLE . $key, 'gb_templates' );
	
	$query = "DELETE FROM " . GB_TEMPLATES_TABLE . " WHERE template_name = '$key' LIMIT 1";
	return $wpdb->query($wpdb->prepare($query));
}

// Parses POST information and updates the database with the new template information
function gb_templates_update_defaults(){
	global $wp_version;
	
	// create an array to hold the chosen options
	$newoptions = array();
	$newoptions['posts'] = $_POST['post'];
	$newoptions['pages'] = $_POST['page'];
	// need to error check page template names
	$newoptions['links'] = $_POST['link'];
	$newoptions['post_categories'] = $_POST['post_category'];
	$newoptions['blog_public'] = $_POST['blog_public'];
	$newoptions['theme'] = $_POST['theme'];
	$newoptions['default_post'] = $_POST['default_post'];
	$newoptions['default_links'] = $_POST['default_links'];
	$newoptions['default_page'] = $_POST['default_page'];

	$newoptions['default_pingback_flag'] = ($_POST['default_pingback_flag'] == 1) ? 1 : 0;
	$newoptions['default_ping_status'] = ($_POST['default_ping_status'] == 'open') ? 'open' : 'closed';
	$newoptions['default_comment_status'] = ($_POST['default_comment_status'] == 'open') ? 'open' : 'closed';
	$newoptions['comment_registration'] = ($_POST['comment_registration'] == 1) ? 1 : 0; 

	if ( version_compare( $wp_version, '2.7', '>=' ) ) {
		 $newoptions['close_comments_for_old_posts'] = $_POST['close_comments_for_old_posts'];
		 $newoptions['close_comments_days_old'] = $_POST['close_comments_days_old'];
		 $newoptions['thread_comments'] = $_POST['thread_comments'];
		 $newoptions['thread_comments_depth'] = $_POST['thread_comments_depth'];
		 $newoptions['page_comments'] = $_POST['page_comments'];
		 $newoptions['comments_per_page'] = $_POST['comments_per_page'];
		 $newoptions['default_comments_page'] = $_POST['default_comments_page'];
		 $newoptions['comment_order'] = $_POST['comment_order'];
	}
	$newoptions['comments_notify'] = ($_POST['comments_notify'] == 1) ? 1 : 0;
	$newoptions['moderation_notify'] = ($_POST['moderation_notify'] == 1) ? 1 : 0;
	$newoptions['comment_moderation'] = ($_POST['comment_moderation'] == 1) ? 1 : 0;
	$newoptions['require_name_email'] = ($_POST['require_name_email'] == 1) ? 1 : 0;
	$newoptions['comment_whitelist'] = ($_POST['comment_whitelist'] == 1) ? 1 : 0;
		
	gb_update_template($_POST['gb_template'], $newoptions);
}

// Updates the post_parent value for a page
function gb_update_post_parent($ID, $parent) {
	global $wpdb;
	
	// should probably error check input
	$args = array('numberposts' => 30, 'orderby' => 'id', 'post_type' => 'page');
	$posts = get_posts($args);
		
	$data = array( "post_parent" => (string)$parent);
	$where = array( "ID" => (string)$ID);
	$result = $wpdb->update($wpdb->posts,$data,$where,"%s", "%s");
	return $result;
}

// Applies the template named $template_name to blog with ID $blog_id
function gb_apply_template($blog_id, $template_name){
	global $current_site;

	switch_to_blog($blog_id);
	
	$template = gb_get_template($template_name);
	
	if($template) {
	// Cycle through the various changes to be made

	if($template['theme']) {
		$theme = explode('|', $template['theme']);
		switch_theme($theme[0], $theme[1]);
		unset($template['theme']);
	}	
	
	
	if($template['default_post'] == 'delete') {
		wp_delete_post('1');
		unset($template['default_post']);		
	}
	
	if($template['default_page'] == 'delete') {
		wp_delete_post('2');
		unset($template['default_page']);
	}
	
	if($template['default_links'] == 'delete') {
		wp_delete_link('1');
		wp_delete_link('2');
		unset($template['default_links']);
	}
	
	if($template['posts']) {
		if(is_array($template['posts'])) {
			foreach($template['posts'] as $post) {
				$post_array = array();
				$post_array['post_status'] = 'publish';
				$post_array['post_type'] = 'post';
				$post_array['post_content'] = $post['text'];
				$post_array['post_content'] = str_replace( "SITE_URL", clean_url("http://" . $current_site->domain . $current_site->path), $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "SITE_NAME", $current_site->site_name, $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "BLOG_URL", clean_url(get_option('siteurl')."/"), $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "BLOG_NAME", get_bloginfo('name'), $post_array['post_content'] );
				$post_array['post_title'] = $post['title'];
				$name = wp_specialchars( $post['category']);
				if( !$post_array['post_category'][0] = get_term_by('name', $name, 'category')->term_id ) {
					// Category needs to be created
					$post_array['post_category'][0] = wp_create_category( $post['category']);
				}				
				$post_id = wp_insert_post($post_array);
				if (0 == $post_id)
				{
				       echo "There was an error while inserting the post (function
				gb_apply_template)";
					unset($template['posts']);
				       exit;
				}

				$custom_field_keys = $post['postmeta'];
				if (is_array($custom_field_keys)) {
					foreach ( $custom_field_keys as $meta_key => $meta_value ) {
						if ( '_' == $meta_key{0} )
							continue;
						update_post_meta($post_id, $meta_key, $meta_value[0]);
					}
				}
			}
		}
		unset($template['posts']);
	}
	
	$parent_list = array();
	
	if($template['pages']) {
		if(is_array($template['pages'])) {
			foreach($template['pages'] as $page) {
				$post_array = array();
				$post_array['post_status'] = 'publish';
				$post_array['post_type'] = 'page';
				$post_array['post_content'] = $page['text'];
				$post_array['post_content'] = str_replace( "SITE_URL", clean_url("http://" . $current_site->domain . $current_site->path), $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "SITE_NAME", $current_site->site_name, $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "BLOG_URL", clean_url(get_option('siteurl')."/"), $post_array['post_content'] );
				$post_array['post_content'] = str_replace( "BLOG_NAME", get_bloginfo('name'), $post_array['post_content'] );
				$post_array['post_title'] = $page['title'];
				$post_array['menu_order'] = $page['menu_order'];
				$post_id = wp_insert_post($post_array);
				if( $page['post_template'] ) {
					update_post_meta($post_id, '_wp_page_template', $page['post_template']);
				}
				if( $page['post_parent'] && $post_id) {
					$parent_list[$post_id] = $page['post_parent'];
				}
			}
		}
		unset($template['pages']);
	}	
	
	// need to add parent information after all pages are created as parent page may be created after child
	foreach( $parent_list as $key => $value) {
		$parent = get_page_id_by_title($value);
		gb_update_post_parent($key,$parent);
	}
	
	if($template['links']) {
		if(is_array($template['links'])) {
			foreach($template['links'] as $link) {
				$link_array = array();
				$link_array['link_name'] = $link['title'];
				$link_array['link_name'] = str_replace( "SITE_NAME", $current_site->site_name, $link_array['link_name'] );
				$link_array['link_url'] = $link['url'];
				$link_array['link_url'] = str_replace( "SITE_URL", clean_url("http://" . $current_site->domain . $current_site->path), $link_array['link_url'] );
				$name = wp_specialchars( $link['category']);
				$term = get_term_by('name', $name, 'link_category', ARRAY_A);
				if( !$term ) {
					$new_term = wp_insert_term( $link['category'], "link_category");
					$link_array['link_category'][] = $new_term['term_id'];
				} else {
					$link_array['link_category'][] = $term['term_id'];
				}
				$link_id = wp_insert_link($link_array);
			}
		}
		unset($template['links']);
	}
	
	if($template['post_categories']) {
		if(is_array($template['post_categories'])) {
			foreach($template['post_categories'] as $post_category) {
				wp_create_category( $post_category['name']);
			}
		}
		unset($template['post_categories']);
	}

	
	if(is_array($template)) {
		foreach($template as $key => $value) {
			update_option($key, $value);
		}
	}
	
	}
	restore_current_blog();	
}

// Creates the Site Admin panel
function gb_templates_options_panel() {
	
	global $wp_version, $wpdb, $wp_registered_widgets, $wp_registered_widget_controls;
	
	if($_POST['gb_templates_save']) {
		
		gb_templates_update_defaults();
			
		echo "<div class='updated'><p>Template updated.</p></div>";
		
		$template = $_POST['gb_template'];
	}

	if($_POST['edit']) {
		$template = $_POST['gb_template'];
	}
	
	if($_POST['create']) {		
		if($_POST['new_template_name']) {
			// create new template
			gb_create_template($_POST['new_template_name']);
			$template = $_POST['new_template_name'];
			// set $template
		} else {
			echo "<div class='updated'><p>New template name required.</p></div>";
		}
	}
	
	if($_POST['delete']) {		
		if($_POST['confirm_delete']) {
			gb_delete_template($_POST['gb_template']);
			echo "<div class='updated'><p>Template ".$_POST['gb_template']." deleted.</p></div>";
		} else {
			echo "<div class='updated'><p>Confirmation required to delete template.</p></div>";
		}
	}
	
	if($_POST['apply']) {
		global $blog_id;
		
		gb_apply_template($blog_id, $_POST['gb_template']);
		echo "<div class='updated'><p>Template applied to blog " . $blog_id . "</p></div>";
	}

	if($_POST['copy']) {
		if($_POST['new_template_name']) {
			$new_template = gb_copy_template($_POST['gb_template'], $_POST['new_template_name']);
			if($new_template == $_POST['new_template_name']) {
			     $template = $new_template;
			} else {
			    echo "<div class='updated'><p>" . $new_template . "</p></div>";
			}
		} else {
			echo "<div class='updated'><p>New template name required.</p></div>";
		}	
	}
	
	if($_POST['clone']) {
		if($_POST['new_template_name']) {
			$new_template = gb_clone_blog($_POST['new_template_name']);
			if($new_template == $_POST['new_template_name']) {
			    $template = $new_template;
				echo "<div class='updated'><p>New Template Created.</p></div>";				
			} else {
			    echo "<div class='updated'><p>" . $new_template . "</p></div>";
			}			
		} else {
			echo "<div class='updated'><p>New template name required.</p></div>";
		}
	}

	if($_POST['dump']) {
		print variable_to_html(gb_get_template($_POST['gb_template']));
	}

	// Beginning of HTML section
	?>

	<script type="text/javascript">
	 //Add more fields dynamically.
	function addLinkField(area,limit) {
		if(!document.getElementById) return; //Prevent older browsers from getting any further.
		var field_area = document.getElementById(area);
		var all_inputs = field_area.getElementsByTagName("ul");
		var last_item = all_inputs.length - 1;
		var last = all_inputs[last_item].id;
		var count = Number(last.split("_")[1]) + 1;
		if(count > limit && limit > 0) return;
		if(document.createElement) { //WC3 Dom method
			var ul = document.createElement("ul");
			ul.innerHTML = "<ul style='margin-top: 10px' id='link_"+count+"'><li>Link URL: <input type='text' name='link["+count+"][url]' size='40' /></li><li>Link Title: <input type='text' name='link["+count+"][title]' id='linktext_"+count+"' size='40' /></li><li>Link Category: <input type='text' name='link["+count+"][category]' id='linkcategory_"+count+"' size='40' /></li><li><input type='button' value='Remove Link' onclick=\"removeItem('link_"+count+"')\"> </li></ul><br>";
			field_area.appendChild(ul);						
		} else {
		field_area.innerHTML += "<ul style='margin-top: 10px' id='link_"+count+"'><li>Link URL: <input type='text' name='link["+count+"][url]' size='40' /></li><li>Link Title: <input type='text' name='link["+count+"][title]' id='linktext_"+count+"' size='40' /></li><li>Link Category: <input type='text' name='link["+count+"][category]' id='linkcategory_"+count+"' size='40' /></li><li><input type='button' value='Remove Link' onclick=\"removeItem('link_"+count+"')\"> </li></ul><br>";
		}
	}
	function addPostCategoryField(area,limit) {
		if(!document.getElementById) return; //Prevent older browsers from getting any further.
		var field_area = document.getElementById(area);
		var all_inputs = field_area.getElementsByTagName("ul"); 
		var last_item = all_inputs.length - 1;
		var last = all_inputs[last_item].id;
		var count = Number(last.split("_")[1]) + 1;
		if(count > limit && limit > 0) return;
		if(document.createElement) { //WC3 Dom method
			var ul = document.createElement("ul");
			ul.innerHTML = "<ul style='margin-top: 10px' id='postcategory_"+count+"'><li>Category: <input type='text' name='post_category["+count+"][name]' size='40' /></li><li><input type='button' value='Remove Category' onclick=\"removeItem('postcategory_"+count+"')\"> </li></ul><br>";
			field_area.appendChild(ul);						
		} else {
		field_area.innerHTML += "<ul style='margin-top: 10px' id='postcategory_"+count+"'><li>Category: <input type='text' name='post_category["+count+"][name]' size='40' /></li><li><input type='button' value='Remove Category' onclick=\"removeItem('postcategory_"+count+"')\"> </li></ul><br>";
		}
	}
	
	function addPageField(area,limit) {
		if(!document.getElementById) return; //Prevent older browsers from getting any further.
		var field_area = document.getElementById(area);
		var all_inputs = field_area.getElementsByTagName("ul");
		var last_item = all_inputs.length - 1;
		var last = all_inputs[last_item].id;
		var count = Number(last.split("_")[1]) + 1;
		if(count > limit && limit > 0) return;
		if(document.createElement) { //WC3 Dom method
			var ul = document.createElement("ul");
			ul.innerHTML = "<ul style='margin-top: 10px' id='page_"+count+"'><li>Page Title: <input type='text' name='page["+count+"][title]' id='pagetitle_"+count+"' size='30' /><input type='button' value='Remove Page' onclick=\"removeItem('page_"+count+"')\"> </li><li><textarea name='page["+count+"][text]' type='text' id='pagetext_"+count+"' value='' rows='15' cols='60' /></textarea></li><li>Page Parent (enter title of parent page):<input type='text' name='page["+count+"][post_parent]' size='30' value=''/></li><li>Page Template (enter filename of template):<input type='text' name='page["+count+"][post_template]' size='30' value=''/></li><li>Page order (enter optional page order value):<input type='text' name='page["+count+"][menu_order]' size='3' value='0'/></li></ul><br>";
			field_area.appendChild(ul);						
		} else {
		field_area.innerHTML += "<ul style='margin-top: 10px' id='page_"+count+"'><li>Page Title: <input type='text' name='page["+count+"][title]' id='pagetitle_"+count+"' size='30' /><input type='button' value='Remove Page' onclick=\"removeItem('page_"+count+"')\"> </li><li><textarea name='page["+count+"][text]' type='text' id='pagetext_"+count+"' value='' rows='15' cols='60' /></textarea></li><li>Page Parent (enter title of parent page):<input type='text' name='page["+count+"][post_parent]' size='30' value=''/></li><li>Page Template (enter filename of template):<input type='text' name='page["+count+"][post_template]' size='30' value=''/></li><li>Page order (enter optional page order value):<input type='text' name='page["+count+"][menu_order]' size='3' value='0'/></li></ul><br>";
		}
	}

	function addPostField(area,limit) {
		if(!document.getElementById) return; //Prevent older browsers from getting any further.
		var field_area = document.getElementById(area);
		var all_inputs = field_area.getElementsByTagName("ul");
		var last_item = all_inputs.length - 1;
		var last = all_inputs[last_item].id;
		var count = Number(last.split("_")[1]) + 1;
		if(count > limit && limit > 0) return;
		if(document.createElement) { //WC3 Dom method
			var ul = document.createElement("ul");
			ul.innerHTML = "<ul style='margin-top: 10px' id='post_"+count+"'><li>Post Title: <input type='text' name='post["+count+"][title]' id='posttitle_"+count+"' size='30' /><input type='button' value='Remove Post' onclick=\"removeItem('post_"+count+"')\"> </li><li><textarea name='post["+count+"][text]' type='text' id='posttext_"+count+"' value='' rows='15' cols='60' /></textarea></li><li>Post Category: <input type='text' name='post["+count+"][category]' id='postcategory_"+count+"' size='30' /></li></ul>";
			field_area.appendChild(ul);						
		} else {
		field_area.innerHTML += "<ul style='margin-top: 10px' id='post_"+count+"'><li>Post Title: <input type='text' name='post["+count+"][title]' id='posttitle_"+count+"' size='30' /><input type='button' value='Remove Post' onclick=\"removeItem('post_"+count+"')\"> </li><li><textarea name='post["+count+"][text]' type='text' id='posttext_"+count+"' value='' rows='15' cols='60' /></textarea></li><li>Post Category: <input type='text' name='post["+count+"][category]' id='postcategory_"+count+"' size='30' /></li></ul>";
		}
	}

	function removeItem(area) {
		var item = document.getElementById(area);
		item.innerHTML = "";
	}
	</script>
	<h1>Blog Templates</h1>
	<p>This plugin allows you to create templates that can be applied when you blogs are created. The easiest way to create a new template is to set up the current blog how you would like the template to look and then clone the current blog as a new template. Alternatively, you can create the template manually by creating a blank template and adding the required settings.</p>
	
	<p>Not everything about a blog is carried across when it is cloned, only the settings that you would be able to add through a template created manually. As a consequence of this blogs created with the template will not be strictly identical as the cloned blog.</p>
	
	<p>If a template called 'default' exists then it will be applied to all new blogs unless another template has been chosen.</p>
	
	<p><strong>Warning: Cloning a blog saves more information than you can currently save by creating a template manually. If you edit a template created by cloning a blog then you may loose information from your template.</strong></p>

	<form method="post" id="gb_templates_select_template">
	<div class="wrap">
		<h2>Manage Templates</h2>
		<table class="form-table">
		<tr valign="center">
			<td width="50%">
				<h3>Create a new template</h3>
				<p><input type="submit" value="Create New Blank Template" name="create">
				<input type="submit" value="Clone Current Blog As New Template" name="clone"></p>
				<small>Note: Cloning the current blog replaces the Site URL, Site Name, Blog URL and Blog Name in page/post text and links as variable versions of these values that are re-evaluated when the template is applied.
				
			</td><td>
				New template name: <input type="text" name="new_template_name">	
			</td>
		</tr><tr valign="center">
			<td>
				<h3>Edit or delete an existing template</h3>
				<p><input type="submit" value="Edit Template" name="edit">
				<input type="submit" value="Delete Template" name="delete"></p>
				<p>Confirm delete: <input type="checkbox" value="confirm_delete" name="confirm_delete"></p>
			</td><td>
				<p>Select the template to edit/delete:
				<?php	$template_names = gb_get_template_list();
				if(is_array($template_names)) { ?>
					<select name="gb_template">
						<?php foreach($template_names as $name){
							echo('<option value="'.$name.'" ');
							if( $name == $template) echo 'selected="selected" ';
							echo('>'.$name.'</option>');
						}
						}	?>
				</select></p>
			</td>
		</tr><tr valign="top">
			<td>
				<h3>Apply template to current blog</h3>
				<input type="submit" value="Apply template" name="apply">
			</td>
			<td>
				<h3>Copy a template</h3>
				<p>Enter the name of the new template and select the template to copy above.</p>
				<input type="submit" value="Copy Template" name="copy">
			</td>
		</tr>
		</table>
	</div>
	</form>
<?php
	// If $xml is set then print xml
	if($xml){
		echo "<h1>Template XML</h1>";
		echo "<div class='wrap'>" . $xml . "</div>";
	}
	
 	// If a template name was submitted then present its current saved details
	if($template) {
		$template_attributes = gb_get_template($template);
	?>	
	<form method="post" id="gb_templates_update_template">
		<h1>Edit Template: <?php echo $template;?></h1>	
		<div class="wrap">
		<h2>Blog content</h2>
		<table class="form-table">
		<tr valign="top" class="gb_template_row">
			<th scope="row"><h3>Links</h3>
				<input type="button" value="Add Link Field" onclick="addLinkField('link_area',30);" />
				<p>SITE_URL will be replaced in url in the field and SITE_NAME will be replaced in the title field with their respective values.
				</p>
				<p>Delete default links? <input type="checkbox" value="delete" name="default_links" <?php if( $template_attributes['default_links'] == 'delete') echo "checked='checked';"?> ></p>
			</th>
			<td id="link_area">
				<ul id="linkurl_0"></ul>
				Enter the details of any default links you would like created. 
				<?php if( $template_attributes['links']) {  
					$count = 1;
					foreach ($template_attributes['links'] as $link) {    ?>
						<ul style='margin-top: 10px' id='link_<?php echo $count; ?>'>
							<li>Link Url: <input type='text' name='link[<?php echo $count; ?>][url]' id='linkurl_<?php echo $count; ?>' size='40' value='<?php echo $link['url']; ?>'></li>
							<li>Link Title: <input type='text' name='link[<?php echo $count; ?>][title]' id='linktext_<?php echo $count; ?>' size='40' value='<?php echo $link['title']; ?>' ></li>
							<li>Link Category: <input type='text' name='link[<?php echo $count; ?>][category]' id='linkcategory_<?php echo $count; ?>' size='40' value='<?php echo $link['category']; ?>' ></li>
							<li><input type='button' value='Remove Link' onclick="removeItem('link_<?php echo $count; ?>')"> </li>
						</ul><br>
					<?php
						$count++;
					}
				}?>
			</td>		
		</tr><tr valign="top" class="gb_template_row">
			<th scope="row"><h3>Pages</h3>
				<input type="button" value="Add Page Field" onclick="addPageField('page_area', 30)">
				<p>SITE_URL, SITE_NAME, BLOG_URL and BLOG_NAME will all be replaced in the post content with their respective values.</p>
				<p>Delete About Page? <input type="checkbox" value="delete" name="default_page" <?php if($template_attributes['default_page'] == 'delete') echo 'checked="checked"'; ?>></p>
			</th>
			<td id="page_area">
				Enter the details of any default pages you would like created. Page text should be html.
				<ul id="page_0"></ul>
				<?php if( $template_attributes['pages']) {  
					$count = 1;
					foreach ($template_attributes['pages'] as $page) {    ?>
						<ul style='margin-top: 10px' id='page_<?php echo $count; ?>'>
							<li>Page Title: <input type='text' name='page[<?php echo $count; ?>][title]' id='pagetitle_<?php echo $count; ?>' size='30' value="<?php echo $page['title']; ?>"/>
								<input type='button' value='Remove Page' onclick="removeItem('page_<?php echo $count; ?>')"> </li>
							<li><textarea name='page[<?php echo $count; ?>][text]' type='text' id='pagetext_<?php echo $count; ?>' value='' rows='15' cols='60' /><?php echo $page['text']; ?></textarea></li>
							<li>Page Parent (enter title of parent page):<input type='text' name='page[<?php echo $count; ?>][post_parent]' size='30' value="<?php echo $page['post_parent']; ?>"/></li>
							<li>Page Template (enter filename of template):<input type='text' name='page[<?php echo $count; ?>][post_template]' size='30' value="<?php echo $page['post_template']; ?>"/></li>
							<li>Page order (enter optional page order value):<input type='text' name='page[<?php echo $count; ?>][menu_order]' size='3' value="<?php echo $page['menu_order']; ?>"/></li>
						</ul><br>
						<?php
						$count++;
					}
				} ?>
				</td>
		</tr><tr valign="top" class="gb_template_row">
			<th scope="row"><h3>Posts</h3>
				<input type="button" value="Add Post Field" onclick="addPostField('post_area', 30)">
				<p>SITE_URL, SITE_NAME, BLOG_URL and BLOG_NAME will all be replaced in the post content with their respective values.</p>
				<p>Delete Hello World post? <input type="checkbox" value="delete" name="default_post" <?php if($template_attributes['default_post'] == 'delete') echo 'checked="checked"'; ?>></p>
			</th>
			<td id="post_area">
				Enter the details of any posts you would like created. Post text should be html.
				<ul id="post_0"></ul>
				<?php if( $template_attributes['posts']) {  
					$count = 1;
					foreach ($template_attributes['posts'] as $post) {    ?>
						<ul style='margin-top: 10px' id='post_<?php echo $count; ?>'>
							<li>Post Title: <input type='text' name='post[<?php echo $count; ?>][title]' id='posttitle_<?php echo $count; ?>' size='30' value='<? echo $post['title']; ?>' />
								<input type='button' value='Remove Post' onclick="removeItem('post_<?php echo $count; ?>')"> </li>
							<li><textarea name='post[<?php echo $count; ?>][text]' type='text' id='posttext_<?php echo $count; ?>' value='' rows='15' cols='60' /><?php echo $post['text']; ?></textarea></li>
							<li>Post Category: <input type='text' name='post[<?php echo $count; ?>][category]' id='posttitle_<?php echo $count; ?>' size='30' value='<? echo $post['category']; ?>'/>
						</ul><br>
						<?php
						$count++;
					}
				} ?>
			</td>
		</tr><tr valign="top" class="gb_template_row">
			<th scope="row"><h3>Post Categories</h3>					
				<input type="button" value="Add Post Category Field" onclick="addPostCategoryField('postcategory_area', 30);" />
			</th>
			<td id="postcategory_area">
				Enter the details of any default links you would like created.
				<ul id="postcategory_0"></ul>
				<?php if( $template_attributes['post_categories']) {  
					$count = 1;
					foreach ($template_attributes['post_categories'] as $category) {    ?>
						<ul style='margin-top: 10px' id='post_category_<?php echo $count; ?>'>
							<li>Category: <input type='text' name='post_category[<?php echo $count; ?>][name]' id='postcategory_<?php echo $count; ?>' size='40' value='<?php echo $category['name']; ?>'/></li>
							<li><input type='button' value='Remove Category' onclick="removeItem('postcategory_<?php echo $count; ?>')"> </li>
						</ul><br>
						<?php
						$count++;
					}
				} ?>
			</td>
		</tr>
		<tr valig"top" class="gb_template_row">
			<th scope="row"><h3>Widgets</h3>
				<input type="button" value="Add Widget" onclick="addWidgetField('widget_area,30);" />
			</th><td id="widget_area">
				Enter the details for any widgets that you would like registered to sidebar-1.
				<ul id="widget_0"></ul>
				<?php if( $template_attributes['widgets']) {
					$count  = 1;
					$widget_list = wp_list_widgets();
					foreach ($template_attributes['widgets'] as $widget) { ?>
						
					<?php
						
					}
				}?>
			</td>
		</table>
		</div>
		
		<div class="wrap">
		<h2>Blog Settings</h2>
		<table class="form-table">
		<tr valign="top" class="gb_template_row">
			<th scope="row"><h3>Theme</h3>					
			</th>
			<td>
				Select the theme for this default template.
				<select name="theme" size="1">
		        <?php
		
			// This section ripped straight from Deanna Schneider's cets_blog_defaults plugin
		
			$themes = get_themes();
			$ct = current_theme_info();
			$allowed_themes = get_site_allowed_themes();
			if( $allowed_themes == false )
				$allowed_themes = array();

			$blog_allowed_themes = wpmu_get_blog_allowedthemes();
			if( is_array( $blog_allowed_themes ) )
				$allowed_themes = array_merge( $allowed_themes, $blog_allowed_themes );
			if( $blog_id != 1 )
				unset( $allowed_themes[ "h3" ] );

			if( isset( $allowed_themes[ wp_specialchars( $ct->stylesheet ) ] ) == false )
				$allowed_themes[ wp_specialchars( $ct->stylesheet ) ] = true;
	
			reset( $themes );
			foreach( $themes as $key => $theme ) {
				if( isset( $allowed_themes[ wp_specialchars( $theme[ 'Stylesheet' ] ) ] ) == false ) {
					unset( $themes[ $key ] );
				}
			}
			reset( $themes );

			// get the names of the themes & sort them
			$theme_names = array_keys($themes);
			natcasesort($theme_names);
				foreach ($theme_names as $theme_name) {
				$styletemplate = $themes[$theme_name]['Template'];
				$stylesheet = $themes[$theme_name]['Stylesheet'];
				$title = $themes[$theme_name]['Title'];
				$selected = "";
				if($template_attributes['theme'] == $styletemplate . "|" . $stylesheet) {
					$selected = "selected = 'selected' ";
				}
				echo('<option value="' . $styletemplate . "|" . $stylesheet .  '"' . $selected . '>' . $title . "</option>");
				}
				?>
		        </select>
			</td>
		</tr><tr valign="top" class="gb_template_row">
			<th scope="row"><h3><?php _e('Privacy Settings') ?></h3>
			</th><td>
				<p><input id="blog-public" type="radio" name="blog_public" value="1" <?php checked('1', $template_attributes['blog_public']); ?> />
	       		<label for="blog-public"><?php _e('I would like my blog to be visible to everyone, including search engines (like Google, Sphere, Technorati) and archivers and in public listings around this site.') ?></label></p>
	        	<p><input id="blog-norobots" type="radio" name="blog_public" value="0" <?php checked('0', $template_attributes['blog_public']); ?> />
	       		<label for="blog-norobots"><?php _e('I would like to block search engines, but allow normal visitors'); ?></label></p>
	       		<?php do_action('blog_privacy_selector'); ?>
			</td>
		</tr>
		</table>
		</div>
<?php // More code ripped from Deanna Schneider's cets_blog_defaults plugin ?>
		<div class="wrap">     
    	<h2>Discussion Settings</h2>
        <table class="form-table">
        <tr valign="top">
        <th scope="row"><?php _e('Default article settings') ?></th>
        <td>
         <label for="default_pingback_flag">
		 
       <input name="default_pingback_flag" type="checkbox" id="default_pingback_flag" value="1" <?php  if ($template_attributes['default_pingback_flag'] == 1) echo('checked="checked"'); ?> /> <?php _e('Attempt to notify any blogs linked to from the article (slows down posting.)') ?> </label>
       
        <br /> 
		<label for="default_ping_status">
		
        <input name="default_ping_status" type="checkbox" id="default_ping_status" value="open" <?php if ($template_attributes['default_ping_status'] == 'open') echo('checked="checked"'); ?> /> <?php _e('Allow link notifications from other blogs (pingbacks and trackbacks.)') ?></label>
       
        <br />
        <label for="default_comment_status">
		
        <input name="default_comment_status" type="checkbox" id="default_comment_status" value="open" <?php if ($template_attributes['default_comment_status'] == 'open') echo('checked="checked"'); ?> /> <?php _e('Allow people to post comments on the article') ?></label>
    
        <br />
		<label for="comment_registration">
		<input name="comment_registration" type="checkbox" id="comment_registration" value="1" <?php checked('1', $template_attributes['comment_registration']); ?> />
		<?php _e('Users must be registered and logged in to comment') ?>
		</label>
		<br />
        <small><em><?php echo '(' . __('These settings may be overridden for individual articles.') . ')'; ?></em></small>
        </td>
        </tr>
		<?php 
		// Start of 2.7 section for comments
		if ( version_compare( $wp_version, '2.7', '>=' ) ) { ?>
		<tr valign="top">
		<th scope="row"><?php _e('Other comment settings') ?></th>
		<td><fieldset><legend class="hidden"><?php _e('Other comment settings') ?></legend>

		
		<label for="close_comments_for_old_posts">
		<input name="close_comments_for_old_posts" type="checkbox" id="close_comments_for_old_posts" value="1" <?php checked('1', $template_attributes['close_comments_for_old_posts']); ?> />
		<?php printf( __('Automatically close comments on articles older than %s days'), '</label><input name="close_comments_days_old" type="text" id="close_comments_days_old" value="' . attribute_escape($template_attributes['close_comments_days_old']) . '" class="small-text" />') ?>
		<br />
		<label for="thread_comments">
		<input name="thread_comments" type="checkbox" id="thread_comments" value="1" <?php checked('1', $template_attributes['thread_comments']); ?> />
		<?php
		
		$maxdeep = (int) apply_filters( 'thread_comments_depth_max', 10 );
		
		
		
		$thread_comments_depth = '</label><select name="thread_comments_depth" id="thread_comments_depth">';
		for ( $i = 1; $i <= $maxdeep; $i++ ) {
			$thread_comments_depth .= "<option value='$i'";
			if ( $template_attributes['thread_comments_depth'] == $i ) $thread_comments_depth .= " selected='selected'";
			$thread_comments_depth .= ">$i</option>";
		}
		$thread_comments_depth .= '</select>';
		
		printf( __('Enable threaded (nested) comments %s levels deep'), $thread_comments_depth );
		
		?><br />
		<label for="page_comments">
		<input name="page_comments" type="checkbox" id="page_comments" value="1" <?php checked('1', $template_attributes['page_comments']); ?> />
		<?php
		
		
		$default_comments_page = '</label><label for="default_comments_page"><select name="default_comments_page" id="default_comments_page"><option value="newest"';
		if ( 'newest' == $opt['default_comments_page'] ) $default_comments_page .= ' selected="selected"';
		$default_comments_page .= '>' . __('last') . '</option><option value="oldest"';
		if ( 'oldest' == $opt['default_comments_page'] ) $default_comments_page .= ' selected="selected"';
		$default_comments_page .= '>' . __('first') . '</option></select>';
		
		printf( __('Break comments into pages with %1$s comments per page and the %2$s page displayed by default'), '</label><label for="comments_per_page"><input name="comments_per_page" type="text" id="comments_per_page" value="' . attribute_escape($template_attributes['comments_per_page']) . '" class="small-text" />', $default_comments_page );
		
		?></label>
		<br />
		<label for="comment_order"><?php
		
		$comment_order = '<select name="comment_order" id="comment_order"><option value="asc"';
		if ( 'asc' == $template_attributes['comment_order'] ) $comment_order .= ' selected="selected"';
		$comment_order .= '>' . __('older') . '</option><option value="desc"';
		if ( 'desc' == $template_attributes['comment_order'] ) $comment_order .= ' selected="selected"';
		$comment_order .= '>' . __('newer') . '</option></select>';
		
		printf( __('Comments should be displayed with the %s comments at the top of each page'), $comment_order );
		
		?></label>
		</fieldset></td>
		</tr>
		
		<?php }
		// end of 2.7 block
		?>
		
		
        <tr valign="top">
        <th scope="row"><?php _e('E-mail me whenever') ?></th>
        <td>
		<label for="comments_notify">
		
        <input name="comments_notify" type="checkbox" id="comments_notify" value="1" <?php if ($template_attributes['comments_notify'] == 1 ) echo('checked="checked"'); ?> /> <?php _e('Anyone posts a comment') ?> </label>
         
        <br />
		<label for="moderation_notify">
		
        <input name="moderation_notify" type="checkbox" id="moderation_notify" value="1" <?php if ($template_attributes['moderation_notify'] == 1) echo('checked="checked"'); ?> /> <?php _e('A comment is held for moderation') ?></label>
        </td>
        </tr>
        <tr valign="top">
        <th scope="row"><?php _e('Before a comment appears') ?></th>
        <td>
		<label for="comment_moderation">
		
        <input name="comment_moderation" type="checkbox" id="comment_moderation" value="1" <?php if ($template_attributes['comment_moderation'] == 1) echo('checked="checked"'); ?> /> <?php _e('An administrator must always approve the comment') ?></label>
    
        <br />
		<label for="require_name_email">
		
        <input type="checkbox" name="require_name_email" id="require_name_email" value="1" <?php if ($template_attributes['require_name_email'] == 1) echo('checked="checked"'); ?> /> <?php _e('Comment author must fill out name and e-mail') ?></label>
        
        <br />
		<label for="comment_whitelist">
        <input type="checkbox" name="comment_whitelist" id="comment_whitelist" value="1" <?php if ($template_attributes['comment_whitelist'] == 1) echo('checked="checked"'); ?> /> <?php _e('Comment author must have a previously approved comment') ?></label>
       
        </td>
        </tr>
        </table>
        
        </div>
		<input type="hidden" name="gb_template" value="<?php echo $template; ?>">	
		<p class="submit"><input type="submit" name="gb_templates_save" value="Save" /></p>

	</form>
<?php }  // end of edit template if 
?>
	</div>
<?php }

/*
 * Applies template to newly created blog from signup page
 */
function gb_templates_apply_to_new_blog($blog_id) {
	if($_POST['gb_template']) {
		gb_apply_template($blog_id, $_POST['gb_template']);
	} else {
		gb_apply_template($blog_id, 'default');
	}
}

/*
 * Adds template selection to form
 */
function gb_templates_selection_form() {

	$template_names = gb_get_template_list();
	if(is_array($template_names)) { ?>
		<p><label for="gb_template_selector"><?php _e('Blog Template[Optional]:') ?></label>
		Select a template to apply: 
		<select name="gb_template" id="gb_template_selector">
			<option value=""></option>
			<?php foreach($template_names as $name){ 
				$selected = ($name == 'default') ? ('selected="selected" ') : ('');
				echo('<option '. $selected . 'value="'.$name.'" >'.$name.'</option>');
				} ?>
		</select>
		</p>
<?php }	
}

/*
 * Create template selector within a table row
 */
function gb_templates_selection_tr_form() { 
	$template_names = gb_get_template_list();
	if(is_array($template_names)) { ?>
		<tr><th scope='row'>
			<?php _e('Blog Template') ?>
		</th><td>
			Select a blog template to apply: 
			<select name="gb_template" id="gb_template_selector">
			<option value=""></option>
			<?php foreach($template_names as $name){ 
				$selected = ($name == 'default') ? ('selected="selected" ') : ('');
				echo('<option '. $selected . 'value="'.$name.'" >'.$name.'</option>');
				} ?>
			</select>
		</td></tr>
	<?php
	}
}
// Bulk apply template to blogs, $blogs should be an array of blog id's
function gb_templates_bulk_apply_template($template, $blogs) {
	if(!is_array($blogs)) return;
	
	foreach($blogs as $blog) {
		gb_apply_template($template, $blog);
	}	
}

/**
 * Adds a sub menu to the Site Admin panel.
 *
 * @return null - does not actively return a value
 */
function gb_templates_addmenu() {
	$objCurrUser = wp_get_current_user();
	$objUser = wp_cache_get($objCurrUser->id, 'users');
	if (function_exists('add_submenu_page') && is_site_admin($objUser->user_login)) {
		// does not use add_options_page, because it is site-wide configuration,
		//  not blog-specific config, but side-wide
		add_submenu_page('wpmu-admin.php', 'Blog Templates', 'Blog Templates', 9, basename(__FILE__), 'gb_templates_options_panel');
	}
}

/**
 * Returns a page id from title
 */

function get_page_id_by_title($page_title) {
	global $wpdb;
	$page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_title = '%s' AND post_type='page'", $page_title ));
	if ( $page )
		return $page;

	return null;
}

if( array_search( GB_TEMPLATES_TABLE, $wpdb->global_tables) == false) {
	gb_templates_setup();
}

add_action('admin_menu', 'gb_templates_addmenu');
add_action('signup_blogform', 'gb_templates_selection_form');
add_action('wpmu_new_blog', 'gb_templates_apply_to_new_blog');

// Hook into Bulk Create Blogs template
add_action('gb_bulk_create_blogs_import_blog', 'gb_templates_apply_to_new_blog');
add_action('gb_bulk_create_blogs_form', 'gb_templates_selection_tr_form');


?>
