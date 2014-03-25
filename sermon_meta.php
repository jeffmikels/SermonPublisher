<?php

define('MY_WORDPRESS_FOLDER',$_SERVER['DOCUMENT_ROOT']);
define('MY_PLUGIN_FOLDER', plugin_dir_path( __FILE__ ));

add_action('admin_init','sp_sermon_meta_init');
function sp_sermon_meta_init()
{
	// review the function reference for parameter details
	// http://codex.wordpress.org/Function_Reference/wp_enqueue_script
	// http://codex.wordpress.org/Function_Reference/wp_enqueue_style

	//wp_enqueue_style('sermon_meta_css', MY_PLUGIN_FOLDER . 'css/sermon_meta.css');

	// review the function reference for parameter details
	// http://codex.wordpress.org/Function_Reference/add_meta_box

	// add a meta box for each of the wordpress page types: posts and pages
	foreach (array('sp_sermon') as $type)
	{
		add_meta_box('sp_sermon_meta', 'Sermon Series', 'sp_sermon_meta_setup', $type, 'side', 'high');
	}

	// add a callback function to save any data a user enters in
	add_action('save_post','sp_sermon_meta_save');
}


function sp_sermon_meta_setup()
{
	global $post;

	// using an underscore, prevents the meta variable
	// from showing up in the custom fields section
	$meta = get_post_meta($post->ID,'sermon_series',TRUE);
	//my_debug($meta);

	// get all the sermon series
	$series_pages = get_posts(array ('numberposts'=>-1, 'post_type'=>'sp_series'));

	// meta HTML code
	echo '<div class="sp_sermon_meta_control">';
	echo '<select name="sermon_series">';
	echo '<option value="">-- Choose the right series title --</option>';

	foreach ($series_pages as $series_page)
	{
		$id = $series_page->ID;
		$title = $series_page->post_title;
		$selected = '';
		if ($id == $meta) $selected='selected="selected"';
		echo '<option ' . $selected . ' value="' . $id . '">' . $title . '</option>';
	}
	echo '</selected>';



	// create a custom nonce for submit verification later
	echo '<input type="hidden" name="sp_sermon_meta_noncename" value="' . wp_create_nonce(__FILE__) . '" />';
	echo '</div>';
}


function sp_sermon_meta_save($post_id)
{
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

	// authentication checks

	// make sure data came from our meta box
	if ( ! isset($_POST['sp_sermon_meta_noncename']) || !wp_verify_nonce($_POST['sp_sermon_meta_noncename'],__FILE__)) return $post_id;

	// check user permissions
	if ($_POST['post_type'] == 'sp_sermon')
	{
		if (!current_user_can('edit_post', $post_id)) return $post_id;
	}
	else
	{
		if (!current_user_can('edit_post', $post_id)) return $post_id;
	}

	// authentication passed, save data
	// var types
	// single: sermon_series[var]
	// array: sermon_series[var][]
	// grouped array: sermon_series[var_group][0][var_1], sermon_series[var_group][0][var_2]

	$current_data = get_post_meta($post_id, 'sermon_series', TRUE);

	$new_data = $_POST['sermon_series'];

	sp_sermon_meta_clean($new_data);

	if ($current_data)
	{
		if (is_null($new_data)) delete_post_meta($post_id,'sermon_series');
		else update_post_meta($post_id,'sermon_series',$new_data);
	}
	elseif (!is_null($new_data))
	{
		delete_post_meta($post_id,'sermon_series');
		add_post_meta($post_id,'sermon_series',$new_data,TRUE);
	}

	return $post_id;
}


function sp_sermon_meta_clean(&$arr)
{
	if (is_array($arr))
	{
		foreach ($arr as $i => $v)
		{
			if (is_array($arr[$i]))
			{
				sp_sermon_meta_clean($arr[$i]);

				if (!count($arr[$i]))
				{
					unset($arr[$i]);
				}
			}
			else
			{
				if (trim($arr[$i]) == '')
				{
					unset($arr[$i]);
				}
			}
		}

		if (!count($arr))
		{
			$arr = NULL;
		}
	}
}
?>
