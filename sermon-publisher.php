<?php
/*
Plugin Name: Jeff's Sermon Publisher
Plugin URI: none
Description: This plugin allows churches to easily publish weekly sermons to their wordpress-based site. Additionally, this plugin provides sample page templates to use in your own themes and three shortcodes to display the most recent series, a gallery of past sermons, and a "full" gallery comprised of both.<p>This plugin provides the following shortcodes: [sp_featured], [sp_gallery], [sp_full_gallery]
Author: Jeff Mikels
Version: 0.4
Author URI: http://jeff.mikels.cc
*/

/* TODO */
/*
	implement sermon audio upload with custom meta box
	implement admin options page for setting alternate upload locations like S3 servers, etc.
*/


/** Add new image sizes */
add_image_size( 'sp_banner', 1040, 400, TRUE );
add_image_size( 'sp_poster', 1280, 720, TRUE );
add_image_size( 'sp_thumb', 320, 180, TRUE );
add_filter( 'image_size_names_choose', 'sp_custom_sizes' );
function sp_custom_sizes( $sizes )
{
	return array_merge( $sizes, array(
		'sp_banner' => __('Ultra-Wide Banner'),
		'sp_poster' => __('Standard 16x9 Sermon Image'),
		'sp_thumb' => __('Small 16x9 Sermon Thumbnail')
	) );
}


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
		'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
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
		'supports' => array('title', 'editor', 'author', 'custom-fields', 'podcasting', 'excerpt'),
	) );
}
add_action( 'init', 'sp_custom_post_types' );


/** META BOX CODE */
include ("sermon_meta.php");


/* IF YOU ARE RUNNING THE PODCASTING PLUGIN, ADD THIS CODE TO IT TO ADD THE METABOX */
// add_meta_box('podcasting', 'Podcasting', array($this, 'editForm'), 'sp_sermon', 'normal');


// ADD A NEW SERIES INFO WIDGET
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

		$thumbnail_size = 'sp_thumb';

		echo $before_widget;
		// echo '<div id="sp_series_info_widget"'

		// Widget Code Goes Here

		if (sp_is_sermon())
		{
			$series_page_id = get_post_meta($post->ID, 'sermon_series', TRUE);
			$series_page = get_post($series_page_id);
			$series_permalink = get_permalink($series_page_id);
			$series_thumbnail = sp_get_image($series_page_id, $thumbnail_size);
			// $series_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($series_page_id), 'thumbnail');
			$sermons = sp_get_sermons_by_series($series_page_id);

			if ((count($sermons) - 1) == 0) $countval = 'are no other sermons';
			elseif ((count($sermons) - 1) == 1) $countval = 'is one other sermon';
			else $countval = sprintf('are %d other sermons', count($sermons) - 1);

			?>

			<img class="sp_thumb" src="<?php echo $series_thumbnail[0]; ?>"/>
			This sermon is part of a series called <a href="<?php print $series_permalink; ?>"><?php echo $series_page->post_title; ?></a>. There <?php echo $countval; ?> in this series.

			<?php
		}

		elseif (sp_is_series())
		{
			$series_page_id = $post->ID;
			$series_thumbnail = sp_get_image($series_page_id, $thumbnail_size);
			// $series_thumbnail = wp_get_attachment_image_src(get_post_thumbnail_id($series_page_id), 'thumbnail');
			$sermons = sp_get_sermons_by_series($series_page_id);

			if ((count($sermons)) == 0) $countval = 'are no sermons';
			elseif ((count($sermons)) == 1) $countval = 'is one sermon';
			else $countval = sprintf('are %d sermons', count($sermons));

			?>

			<img class="sp_thumb" src="<?php echo $series_thumbnail[0]; ?>"/>
			You are viewing the Sermon Series titled <em><strong><?php echo $post->post_title; ?></strong></em>. There <?php echo $countval; ?> posted in this series.

			<?php
		}


		echo $after_widget;
	}
}
add_action( 'widgets_init', function() {return register_widget("SeriesInfoWidget"); });


// SET UP CONTENT FILTERS FOR PLUGIN POST TYPES
function sp_series_content($content)
{
	if (! sp_is_series()) return $content;
	$content = sp_add_sermons_in_series($content);
	return $content;
}
add_filter('the_content', 'sp_series_content');


function sp_sermon_content($content)
{
	if(! sp_is_sermon()) return $content;
	$series_graphic = sp_add_series_graphic('');
	$media_player = sp_add_media_player('',false);
	$downloads = sp_add_downloads('');

	return $series_graphic . $media_player . $downloads . $content;
}
add_filter('the_content', 'sp_sermon_content');


function sp_add_sermons_to_feed($qv)
{
	if (isset($qv['feed']) && !isset($qv['post_type']))
	{
		$qv['post_type'] = array('post', 'sp_sermon', 'sp_series', 'page');
		//$qv['post_type']=get_post_types();
	}
	return $qv;
}
add_filter('request', 'sp_add_sermons_to_feed');


//add_action('loop_start', 'sp_series_archive');
function sp_series_archive()
{
	global $wp_query;
	global $first_loop_done;
	if (isset($first_loop_done) && $first_loop_done) return;
	$first_loop_done = True;
	//sp_debug($wp_query);
	if (is_admin() || ! is_archive()) return;
	if (isset($wp_query->query_vars['post_type']) && $wp_query->query_vars['post_type'] == 'sp_series')
	{
		// hijack the loop with our own series archive loop
		$posts = $wp_query->posts;
		$most_recent = $posts[0];
		$the_rest = array_slice($posts,1);

		// display the most recent series as if it were a single
		$post = $most_recent;
		setup_postdata($post);

		?>

			<div class="series most-recent">
				<a href="<?php the_permalink(); ?>">
					<?php print the_post_thumbnail('sp_poster',array('class' => 'fullwidth', 'title'=>get_the_title())); ?>
					<div class="series-description">
						<span class="series-title"><?php the_title(); ?></span>
						<span class="series-excerpt"><?php the_excerpt(); ?></span>
					</div>
				</a>
			</div>

			<div class="series series-gallery">

		<?php

			foreach ($the_rest as $post)
			{
				setup_postdata($post);
				?>

				<div class="series-item">
				<a href="<?php the_permalink(); ?>">
					<?php print the_post_thumbnail('sp_thumb',array('class' => 'fullwidth', 'title' => get_the_title())); ?>
				</a>
				</div>


				<?php



			}
			$wp_query->posts = Array();

		?>

			</div>

		<?php

		return;
	}
	return;
}

// CONTENT MODIFICATION FILTER FUNCTIONS
// SERIES MODIFICATIONS
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

// SERMON MODIFICATIONS
function sp_add_series_graphic($content)
{
	if (! sp_is_sermon()) return $content;
	if (has_post_thumbnail()) return $content;
	if (sp_has_video()) return $content;

	// if we made it this far, we want to grab the series graphic from the sermon series page
	$series_page_id = get_post_meta(get_the_ID(), 'sermon_series', TRUE);
	$series_thumbnail = get_the_post_thumbnail($series_page_id, 'sp_poster', array('class' => 'fullwidth'));
	return $series_thumbnail . $content;
}

function sp_add_downloads($content)
{
	$content = sp_make_download_links() . $content;
	return $content;
}

// add media player to sermon posts
function sp_add_media_player($content, $send_to_browser = TRUE)
{
	global $post;
	if (!sp_is_sermon()) return $content;

	if ($send_to_browser)
	{
		print "<!-- MEDIA PLAYER -->\n";
		include "media_player.php";
		print "\n<!-- END MEDIA PLAYER -->\n";
		return $content;
	}
	else
	{
		ob_start();
		include "media_player.php";
		$result = ob_get_clean();
		return $result . $content;
	}
}

// SERMON OUTPUT HELPER FUNCTIONS
function sp_most_recent_series($thumbnail_size = 'sp_poster', $before = '', $after='')
{
	$featured_series = sp_get_featured_series();
	$featured_series_id = $featured_series->ID;
	$featured_series_image = sp_get_image($featured_series_id, $thumbnail_size);
	echo $before;
	?>

	<div id="most-recent-series">
		<div class="featured-series">
			<a href="<?php echo get_permalink($featured_series_id); ?>">
				<img class="featured-series-image" src="<?php echo $featured_series_image[0]; ?>" />
				<div class="featured-series-image-overlay">
					<div class="featured-series-image-caption">
						<div class="featured-series-title"><?php echo $featured_series->post_title; ?></div>
						<div class="featured-series-excerpt"><?php echo $featured_series->post_excerpt; ?></div>
					</div>
				</div>
			</a>
		</div>
	</div>

	<?php
	echo $after;
	return $featured_series_id;

}
function sp_featured_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_poster',
		'before' => '',
		'after' => ''), $atts ) );
	sp_most_recent_series($thumbnail_size, $before, $after);
}
function sp_past_series_gallery($thumbnail_size = 'sp_thumb', $before = '', $after = '', $exclude = '')
{
	$series_posts = sp_get_all_series();
	$exclude = explode(',', str_replace(' ', '', $exclude));
	echo $before;
	?>

	<div class="series-gallery">
		<?php foreach ($series_posts as $series): ?>
		<?php if (in_array($series->ID, $exclude)) continue; ?>
		<?php $series_thumbnail = sp_get_image($series->ID, $thumbnail_size); ?>

		<div class="series-gallery-item">
			<a href="<?php print get_permalink($series->ID); ?>" >
				<img class="series-gallery-item-image" src="<?php print $series_thumbnail[0]; ?>" />
				<div class="series-gallery-item-image-overlay">
					<div class="series-gallery-item-image-caption">
						<div class="series-gallery-item-title"><?php echo $series->post_title; ?></div>
						<div class="series-gallery-item-excerpt"><?php echo $series->post_excerpt; ?></div>
						<div class="series-gallery-item-date"><?php echo get_the_time('F Y', $series->ID); ?></div>
					</div>
				</div>
			</a>
		</div>
		<?php endforeach; ?>
	</div>

	<?php
}
function sp_gallery_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_thumb',
		'before' => '',
		'after' => '',
		'exclude' => '' ), $atts ) );

	ob_start();
	sp_past_series_gallery($thumbnail_size, $before, $after, $exclude);
	return ob_get_clean();
}
function sp_full_gallery_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_poster',
		'before' => '',
		'after' => ''), $atts ) );

	ob_start();

	$fid = sp_most_recent_series($thumbnail_size, $before, $after);
	sp_past_series_gallery('sp_thumb','','',$fid);
	return ob_get_clean();
}
add_shortcode('sp_featured', 'sp_featured_helper');
add_shortcode('sp_gallery', 'sp_gallery_helper');
add_shortcode('sp_full_gallery', 'sp_full_gallery_helper');

// SERMON CONTENT HELPER FUNCTIONS
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
		$output .= '<div class="download-links">downloads: ';
		if ($enclosures) {
			foreach ($enclosures as $enclosure) {
				$encdata = explode("\n",$enclosure);
				$url = $encdata[0];
				$formatdata = unserialize($encdata[3]);
				$output .= "<a class=\"download-link\" href=\"${encdata[0]}\">${formatdata['format']}</a>";
			}
		}
		if ($downloads) {
			foreach ($downloads as $download) {
				$encdata = explode("\n",$download);
				$url = $encdata[0];
				$format = $encdata[1];
				$output .= "<a class=\"download-link\" href=\"${encdata[0]}\">${format}</a>";
			}
		}
		$output .= '</div>';
	}
	return $output;
}

function sp_has_video()
{
	$enclosures = get_post_custom_values('enclosure');
	$video_extensions = array('.mp4','.ogv','webm');
	if ($enclosures)
	{
		foreach ($enclosures as $e)
		{
			$encdata = explode("\n",$enclosure);
			$url = $encdata[0];
			if (preg_match('/mp4$|ogv$|webm$/', $url)) return true;
		}
	}
	return False;
}

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

function sp_get_image($post_id, $thumbnail_size)
{
	$image_id = get_post_thumbnail_id($post_id);
	return wp_get_attachment_image_src($image_id, $thumbnail_size);
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


function sp_get_featured_series()
{
	// returns the most recent series
	// first, we grab the most recent 'sermon' post, and make that series the featured one
	$most_recent = get_posts( array ('post_type' => 'sp_sermon', 'numberposts'=>1) );
	if ( count($most_recent) != 0 )
	{
		$featured_series_id = get_post_meta($most_recent[0]->ID, 'sermon_series', TRUE);
		if ($featured_series_id)
		{
			$featured_series = get_post($featured_series_id);
			return $featured_series;
		}
	}
	return get_posts( array ('post_type' => 'sp_series', 'numberposts'=>1) );
}

function sp_get_all_series()
{
	return get_posts( array ( 'numberposts'=>-1, 'post_type' => 'sp_series', 'orderby' => 'post_date', 'order' => 'DESC' ) );

}


// WORDPRESS MODIFICATION FUNCTIONS
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


// GENERAL HELPER FUNCTIONS
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
function sp_debug($s)
{
	print "<pre>";
	print_r($s);
	print "</pre>";
}
?>
