<?php
/*
Plugin Name: Jeff's Sermon Publisher
Plugin URI: none
Description: This plugin allows churches to easily publish weekly sermons to their wordpress-based site.
Author: Jeff Mikels
Version: 0.1
Author URI: http://jeff.mikels.cc
*/

/* TODO */
/*
	implement sermon and series post types with metaboxes
	implement admin options page for setting alternate upload locations like S3 servers, etc.
	implement media player display
	implement sermon series index display
*/


/** Add new image sizes */
add_image_size( 'banner', 1040, 400, TRUE );
add_image_size( 'poster', 1280, 720, TRUE );

/** CUSTOM POST TYPES */
function sp_custom_post_types()
{
	register_post_type( 'sp_series', array
	(
		'labels' => array
		(
			'name' => __( 'Series Pages' ),
			'singular_name' => __( 'Series Page' ),
			'add_new' => __( 'Add New Series Page' ),
			'add_new_item' => __( 'Add New Series Page' ),
			'edit_item' => __( 'Edit Series Page' ),
			'new_item' => __( 'New Series Page' ),
			'view_item' => __( 'View Series Page' ),
			'search_items' => __( 'Search Series Pages' ),
			'not_found' => __( 'No Series Pages found' ),
			'not_found_in_trash' => __( 'No Series Pages found in Trash' ),
		),
		'public' => true,
		'has_archive' => true,
		'rewrite' => array('slug' => 'series'),
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'menu_position' => 6,
		'supports' => array('title', 'editor', 'author', 'thumbnail'),
	));

	register_post_type( 'sp_sermon', array
	(
		'labels' => array
		(
			'name' => __( 'Sermons' ),
			'singular_name' => __( 'Sermon' ),
			'add_new' => __( 'Add New Sermon' ),
			'add_new_item' => __( 'Add New Sermon' ),
			'edit_item' => __( 'Edit Sermon' ),
			'new_item' => __( 'New Sermon' ),
			'view_item' => __( 'View Sermon' ),
			'search_items' => __( 'Search Sermons' ),
			'not_found' => __( 'No Sermons found' ),
			'not_found_in_trash' => __( 'No Sermons found in Trash' ),
		),
		'public' => true,
		'has_archive' => true,
		'rewrite' => array('slug' => 'sermon'),
		'taxonomies' => array('category'),
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'menu_position' => 7,
		'supports' => array('title', 'editor', 'author', 'custom-fields', 'podcasting'),
	) );
}

/* IF YOU ARE RUNNING THE PODCASTING PLUGIN, ADD THIS CODE TO IT TO ADD THE METABOX */
// add_meta_box('podcasting', 'Podcasting', array($this, 'editForm'), 'sp_sermon', 'normal');


function sp_add_sermons_to_feed($qv)
{
	if (isset($qv['feed']) && !isset($qv['post_type']))
	{
		$qv['post_type'] = array('post', 'sp_sermon', 'sp_series', 'page');
		//$qv['post_type']=get_post_types();
	}
	return $qv;
}
add_action( 'init', 'sp_custom_post_types' );
add_filter('request', 'sp_add_sermons_to_feed');



// meta boxes for sermon pages
include ("sermon_meta.php");


add_filter('the_content', 'sp_media_player');
function sp_media_player($content)
{
	global $post;
	print "<!-- MEDIA PLAYER -->\n";
	include "media_player.php";
	print "\n<!-- END MEDIA PLAYER -->\n";
	
	return $content;
}

add_action('wp_head','sp_media_player_helpers');
function sp_media_player_helpers()
{
?>

	<!-- HELPER FUNCTION FOR LOADING NEW FILES INTO MEDIA PLAYERS -->
	<script type="text/javascript"><!--//--><![CDATA[//><!--

		function load_media(obj, url)
		{
			// helper function written for MediaElement.js players
			obj.setSrc(url);
			obj.load();
			obj.play()
		}

		//--><!]]></script>

	<!-- MEDIAELEMENT PLAYER FILES -->
	<script src="<?php bloginfo('stylesheet_directory'); ?>/mediaelement/mediaelement-and-player.min.js" type="text/javascript"></script>
	<link rel="stylesheet" href="<?php bloginfo('stylesheet_directory'); ?>/mediaelement/mediaelementplayer.min.css" />

<?php
}


function sp_get_sermons_by_series($series_page_id)
{
	// grab all the 'sermon' posts who have this post's id in their 'sermon_series' custom field
	$args = array (
		'numberposts'=> -1,
		'post_type'=>'sp_sermon',
		'meta_query' => array (
			array (
				'key' => 'sermon_series',
				'value'=> $series_page_id,
			)
		),
		'orderby'=> 'post_date',
		'order' => 'ASC'
	);
	return get_posts($args);
}

// add downloads links to posts
function sp_make_download_links()
{
	$enclosures = '';
	$downloads = '';
	$output = '';
	$enclosures = get_post_custom_values('enclosure');
	$downloads = get_post_custom_values('download');
	if ($enclosures || $downloads) {
		$output .= '<div class="download_links">downloads: |';
		if ($enclosures) {
			foreach ($enclosures as $enclosure) {
				$encdata = explode("\n",$enclosure);
				$url = $encdata[0];
				$formatdata = unserialize($encdata[3]);
				$output .= " <a href=\"${encdata[0]}\">${formatdata['format']}</a> |";
			}
		}
		if ($downloads) {
			foreach ($downloads as $download) {
				$encdata = explode("\n",$download);
				$url = $encdata[0];
				$format = $encdata[1];
				$output .= " <a href=\"${encdata[0]}\">${format}</a> |";
			}
		}
		$output .= '</div>';
	}
	return $output;
}

function sp_add_downloads($content)
{
	$content = sp_make_download_links() . $content;
	return $content;
}
add_filter('the_content', 'sp_add_downloads');


?>
