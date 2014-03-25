<?php
/*
Plugin Name: Jeff's Sermon Publisher
Plugin URI: none
Description: This plugin allows churches to easily publish weekly sermons to their wordpress-based site.
Author: Jeff Mikels
Version: 0.2
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


function sp_is_sermon($post_id = '')
{
	global $post;
	if (! isset($post) and ! $post_id) return False;
	if ($post_id)
	{
		$p = get_post($post_id);
		return $p->post_type;
	}
	else
	{
		return ($post->post_type == 'sp_sermon' && is_single());
	}
}
function sp_is_series($post_id = '')
{
	global $post;
	if (! isset($post) and ! $post_id) return False;
	if ($post_id)
	{
		$p = get_post($post_id);
		return $p->post_type;
	}
	else
	{
		return ($post->post_type == 'sp_series' && is_single());
	}
}

// meta boxes for sermon pages
include ("sermon_meta.php");



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


// related Sermons Widget
class SeriesInfoWidget extends WP_Widget
{
	function SeriesInfoWidget()
	{
		$widget_ops = array('classname' => 'SeriesInfoWidget', 'description' => 'Series information as a widget on series pages and sermon pages. Displays nothing otherwise.');
		$this->WP_Widget('SeriesInfoWidget', 'Series Information and Thumbnail', $widget_ops);
	}

	function widget($args, $instance)
	{
		// check to see if this is a sermon or series page.
		// if it is neither, don't output anything at all.
		if (! sp_is_sermon() and ! sp_is_series()) return;

		extract($args, EXTR_SKIP);
		global $post;

		echo $before_widget;
		// echo '<div id="sp_series_info_widget"'

		// Widget Code Goes Here

		if (sp_is_sermon())
		{
			$series_page_id = get_post_meta($post->ID, 'sermon_series', TRUE);
			$series_page = get_post($series_page_id);
			$series_permalink = get_permalink($series_page_id);
			$series_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($series_page_id), 'thumbnail');
			$sermons = sp_get_sermons_by_series($series_page_id);

			if ((count($sermons) - 1) == 0) $countval = 'are no other sermons';
			elseif ((count($sermons) - 1) == 1) $countval = 'is one other sermon';
			else $countval = sprintf('are %d other sermons', count($sermons) - 1);

			?>

			<img class="thumbnail alignleft" src="<?php echo $series_thumbnail[0]; ?>"/>
			This sermon is part of a series called <a href="<?php print $series_permalink; ?>"><?php echo $series_page->post_title; ?></a>. There <?php echo $countval; ?> in this series.

			<?php
		}

		elseif (sp_is_series())
		{
			$series_page_id = $post->ID;
			$series_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($series_page_id), 'thumbnail');
			$sermons = sp_get_sermons_by_series($series_page_id);

			if ((count($sermons)) == 0) $countval = 'are no sermons';
			elseif ((count($sermons)) == 1) $countval = 'is one sermon';
			else $countval = sprintf('are %d sermons', count($sermons));

			?>

			<img class="thumbnail alignleft" src="<?php echo $series_thumbnail[0]; ?>" />You are viewing the Sermon Series titled <em><strong><?php echo $post->post_title; ?></strong></em>. There <?php echo $countval; ?> posted in this series.

			<?php
		}


		echo $after_widget;
	}
}
add_action( 'widgets_init', function() {return register_widget("SeriesInfoWidget"); });


// add downloads links to posts
function sp_make_download_links()
{
	if (! sp_is_sermon()) return '';
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


function sp_add_sermons_in_series($content)
{
	if (! sp_is_series()) return $content;
	else
	{
		global $post;
		$series_id = $post->ID;
		$series_slug = $post->post_name;
		$sermons = sp_get_sermons_by_series($series_id);

		$sermons_html = '<div class="sermon-listing series-'.$series_slug.'">';
		$sermons_html = '<h2>Sermons in this Series</h2>';

		if (count($sermons) > 0)
		{
			foreach($sermons as $sermon)
			{
				$slug = $sermon->post_name;
				$id = $sermon->ID;
				$title = $sermon->post_title;
				$excerpt = $sermon->post_excerpt;
				$permalink = get_permalink($id);
				$date = date('M j, Y', strtotime($sermon->post_date));
				$this_html = "\n\n";
				$this_html .= '<div class="sermon sermon-'.$slug.'">';
				$this_html .= '<a href="' . $permalink . '">';
				$this_html .= '<div class="sermon-date">' . $date . '</div>';
				$this_html .= '<h3>' . $title . '</h3>';
				$this_html .= '<div class="sermon-excerpt">' . $excerpt . '</div>';
				$this_html .= '</a></div>' . "\n\n";
				$sermons_html .= $this_html;
			}
		}
		else $sermons_html .= 'NO SERMONS HAVE YET BEEN POSTED TO THIS SERIES';
	}
	return $content . $sermons_html;
}
add_filter('the_content', 'sp_add_sermons_in_series', 999);

// add media player to sermon posts
add_filter('the_content', 'sp_media_player');
function sp_media_player($content)
{
	global $post;
	if (sp_is_sermon())
	{
		print "<!-- MEDIA PLAYER -->\n";
		include "media_player.php";
		print "\n<!-- END MEDIA PLAYER -->\n";
	}
	return $content;
}



function sp_add_styles()
{
	// the next line registers our stylesheet from the plugin directory
	// we also say it is dependent on the theme stylesheet so that the plugin styles get
	// loaded after the theme styles
	wp_register_style('sp_sermon_styles', plugins_url('style.css', __FILE__) );
	wp_enqueue_style('sp_sermon_styles');

	print '<link rel="stylesheet" id="sp_sermon_publisher_css" href="'.plugins_url('style.css', __FILE__).'" type="text/css" />'."\n";
}
add_action('wp_head', 'sp_add_styles');
// add_action('wp_enqueue_scripts', 'sp_add_styles');


function sp_url_exists($url)
{
	$file = $url;
	$file_headers = get_headers($file);
	if(strpos($file_headers[0], '404 Not Found') === False) {
			$exists = true;
	}
	else {
			$exists = false;
	}
	return $exists;
}

?>
