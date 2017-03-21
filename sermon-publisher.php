<?php
/*
Plugin Name: Jeff's Sermon Publisher
Plugin URI: none
Description: This plugin allows churches to easily publish weekly sermons to their wordpress-based site. Additionally, this plugin provides sample page templates to use in your own themes and three shortcodes to display the most recent series, a gallery of past sermons, and a "full" gallery comprised of both.<p>This plugin provides the following shortcodes: [sp_featured], [sp_gallery], [sp_full_gallery]. NOTE: I recommend you use the "Podcasting" plugin by TSG for full podcast feed control.
Author: Jeff Mikels
Text Domain: sermon-publisher
Version: 0.4
Author URI: http://jeff.mikels.cc
*/

/* TODO */
/*
	implement admin options page for setting alternate upload locations like S3 servers, etc.
*/

define('SP_DO_LOG',1);
define('SP_SHOW_DEBUG',0);
define('SP_LOG_FILE', dirname(__FILE__) . '/sp_log.log');


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
		'taxonomies' => array('post_tag'),
		'rewrite' => array('slug' => 'series'),
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'menu_position' => 6,
		'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt'),
	));
	$sermon_words = sp_get_sermon_words();
	$singular = $sermon_words['singular'];
	$plural = $sermon_words['plural'];

	register_post_type( 'sp_sermon', array
	(
		'labels' => array
		(
			'name' => sprintf( __( '%s' ), ucwords($plural)),
			'singular_name' => sprintf( __( '%s' ), ucwords($singular)),
			'add_new' => sprintf( __( 'Add New %s' ), ucwords($singular)),
			'add_new_item' => sprintf( __( 'Add New %s' ), ucwords($singular)),
			'edit_item' => sprintf( __( 'Edit %s' ), ucwords($singular)),
			'new_item' => sprintf( __( 'New %s' ), ucwords($singular)),
			'view_item' => sprintf( __( 'View %s' ), ucwords($singular)),
			'search_items' => sprintf( __( 'Search %s' ), ucwords($plural)),
			'not_found' => sprintf( __( 'No %s Found' ), ucwords($plural)),
			'not_found_in_trash' => sprintf( __( 'No %s Found in Trash' ), ucwords($plural)),
		),
		'public' => true,
		'has_archive' => true,
		'rewrite' => array('slug' => $singular),
		'taxonomies' => array('category'),
		'publicly_queryable' => true,
		'exclude_from_search' => false,
		'menu_position' => 7,
		'supports' => array('title', 'editor', 'author', 'custom-fields', 'podcasting', 'excerpt'),
	) );
}
add_action( 'init', 'sp_custom_post_types' );


/** META BOX CODE */
include ("sermon-meta.php");

/** WIDGETS CODE */
include ("sermon-widgets.php");


// SET UP CONTENT FILTERS FOR PLUGIN POST TYPES

// add related sermons to content on series pages
function sp_series_content($content)
{
	if (! sp_is_series()) return $content;
	$content = sp_add_sermons_in_series($content);
	return $content;
}
add_filter('the_content', 'sp_series_content');

// add media player to sermon pages
function sp_sermon_content($content)
{
	if(! sp_is_sermon()) return $content;
	$series_graphic = sp_add_series_graphic('');
	$media_player = sp_add_media_player('',false);
	$downloads = sp_add_downloads('');
	
	return $series_graphic . $media_player . $downloads . $content;
}
add_filter('the_content', 'sp_sermon_content');

// add sermon posts to feeds
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


// unused function to hijack the series archive pages
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
					<?php //print the_post_thumbnail('sp_thumb',array('class' => 'fullwidth', 'title' => get_the_title())); ?>
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
// add_action('loop_start', 'sp_series_archive');


// HELPER FUNCTIONS FOR CONTENT FILTERS
// SERIES PAGE MODIFICATIONS
function sp_add_sermons_in_series($content)
{
	$sermon_words = sp_get_sermon_words();
	$singular = $sermon_words['singular'];
	$plural = $sermon_words['plural'];

	if (! sp_is_series()) return $content;
	else
	{
		global $post;
		$series_id = $post->ID;
		$series_slug = $post->post_name;
		$sermons = sp_get_sermons_by_series($series_id);

		$sermons_html = '<div class="sermon-listing series-'.$series_slug.'">';
		$sermons_html .= sprintf('<h2>%s in this Series</h2>', ucwords($plural));

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
		else $sermons_html .= sprintf('NO %s HAVE YET BEEN POSTED TO THIS SERIES', strtoupper($plural));
	}
	return $content . $sermons_html;
}

// SERMON POST MODIFICATIONS
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

// add a downloads box on sermon posts
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


// GALLERY SHORTCODE DISPLAY FUNCTIONS
function sp_most_recent_series($thumbnail_size = 'sp_poster', $before = '', $after='', $format='overlay')
{
	$featured_series = sp_get_featured_series();
	$featured_series_id = $featured_series->ID;
	$featured_series_image = sp_get_image($featured_series_id, $thumbnail_size);
	echo $before;
	?>

	<?php if ($format == 'overlay'): ?>
	<div class="most-recent-series">
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
	<?php elseif ($format == 'left' || $format == 'right'): ?>
	<div class="most-recent-series">
		<div class="featured-series-<?php echo $format; ?>">
			<a href="<?php echo get_permalink($featured_series_id); ?>">
				<img class="featured-series-image-<?php echo $format; ?>" src="<?php echo $featured_series_image[0]; ?>" />
				<div class="featured-series-image-sidebar">
					<div class="featured-series-image-caption">
						<div class="featured-series-title"><?php echo $featured_series->post_title; ?></div>
						<div class="featured-series-excerpt"><?php echo $featured_series->post_excerpt; ?></div>
					</div>
				</div>
			</a>
		</div>
	</div>
	<div class="clear">&nbsp;</div>
	
	<?php endif; ?>


	<?php
	echo $after;
	return $featured_series_id;
}

function sp_most_recent_sermon($thumbnail_size = 'sp_poster', $before = '', $after='', $show_image=1, $show_text=1)
{
	$featured_series = sp_get_featured_series();
	$featured_series_id = $featured_series->ID;
	$featured_series_image = sp_get_image($featured_series_id, $thumbnail_size);
	$most_recent = sp_get_most_recent_sermon();
	$permalink = get_permalink($most_recent->ID);
	echo $before;

	?>
	
	<?php if ($show_image == 1): ?>
	<div class="most-recent-series">
		<div class="featured-series">
			<a href="<?php echo $permalink; ?>">
				<img class="featured-series-image" src="<?php echo $featured_series_image[0]; ?>" />
				<div class="featured-series-image-overlay">
					<div class="featured-series-image-caption">
						<div class="featured-series-title"><?php echo $most_recent->post_title; ?></div>
					</div>
				</div>
			</a>
		</div>
	</div>
	<?php endif;?>
	
	<?php if ($show_text == 1): ?>
	<div class="most-recent-sermon">
		<!-- <a href="<?php echo get_permalink($featured_series_id); ?>"><?php echo $featured_series->post_title; ?> ::<br /> -->
		<a href="<?php echo $permalink; ?>">
			<?php echo $most_recent->post_title; ?>
		</a>
		<br /><?php echo get_the_date(get_option( 'date_format' ), $most_recent->ID); ?>
	</div>
	<?php endif; ?>

	<?php
	echo $after;
	return $featured_series_id;
}


function sp_past_series_gallery($thumbnail_size = 'sp_thumb', $before = '', $after = '', $exclude = '')
{
	$series_posts = sp_get_all_series();
	$exclude = explode(',', str_replace(' ', '', $exclude));
	echo $before;
	?>

	<div class="series-gallery">

		<?php
		$classes = array('first','middle','last');
		$counter = 0;
		$class = $classes[$counter];
		?>


		<?php foreach ($series_posts as $series): ?>
		<?php if (in_array($series->ID, $exclude)) continue; ?>
		<?php $series_thumbnail = sp_get_image($series->ID, $thumbnail_size); ?>

		<div class="series-gallery-item item-<?php echo $class; ?>">
			<a href="<?php print get_permalink($series->ID); ?>" >
				<!-- <img class="series-gallery-item-image" src="<?php print $series_thumbnail[0]; ?>" /> -->
				<div class="series-gallery-item-image" style="background-size:cover;background-image:url(<?php print $series_thumbnail[0]; ?>);">&nbsp;
				</div>
				<div class="series-gallery-item-image-overlay">
					<div class="series-gallery-item-image-caption">
						<div class="series-gallery-item-title"><?php echo $series->post_title; ?></div>
						<div class="series-gallery-item-excerpt"><?php echo $series->post_excerpt; ?></div>
						<div class="series-gallery-item-date"><?php echo get_the_time('F Y', $series->ID); ?></div>
					</div>
				</div>
			</a>
		</div>
		<?php $counter = ($counter + 1) % 3; ?>
		<?php endforeach; ?>
	</div>

	<?php
}

// REGISTER GALLERY SHORTCODES
function sp_featured_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_poster',
		'before' => '',
		'after' => '',
		'format' => 'overlay'), $atts ) );
	sp_most_recent_series($thumbnail_size, $before, $after, $format);
}
function sp_gallery_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_thumb',
		'before' => '',
		'after' => '',
		'exclude' => ''), $atts ) );

	ob_start();
	sp_past_series_gallery($thumbnail_size, $before, $after, $exclude);
	return ob_get_clean();
}
function sp_full_gallery_helper($atts)
{
	extract( shortcode_atts( array(
		'thumbnail_size' => 'sp_poster',
		'before' => '',
		'after' => '',
		'format' => 'overlay'), $atts ) );

	ob_start();

	$fid = sp_most_recent_series($thumbnail_size, $before, $after, $format);
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
	// check for youtube link
	$youtube_link = get_post_custom_values('youtube_link');
	if (!empty($youtube_link)) return True;
	
	$enclosures = get_post_custom_values('enclosure');
	$video_extensions = array('.mp4','.ogv','webm');
	if ($enclosures)
	{
		foreach ($enclosures as $e)
		{
			$encdata = explode("\n",$e);
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

function sp_get_sermon_series($sermon_id)
{
	$series_id = get_post_meta($sermon_id, 'sermon_series', TRUE);
	if ($series_id)
	{
		return get_post($series_id);
	}
	return NULL;
}
function sp_get_most_recent_sermon()
{
	$sermons = get_posts( array ('post_type' => 'sp_sermon', 'numberposts'=>1) );
	if (count($sermons) != 0) return $sermons[0];
	return NULL;
}

function sp_get_featured_series()
{
	// first, we grab the most recent 'sermon' post, and make that series the featured one
	// returns the most recent series
	$most_recent = sp_get_most_recent_sermon();
	if ( $most_recent )
	{
		$sermon_id = $most_recent->ID;
		return sp_get_sermon_series($sermon_id);
	}
	return get_posts( array ('post_type' => 'sp_series', 'numberposts'=>1) );
}

function sp_get_all_series()
{
	return get_posts( array ( 'numberposts'=>-1, 'post_type' => 'sp_series', 'orderby' => 'post_date', 'order' => 'DESC' ) );

}


function sp_get_sermon_words()
{
	$options = get_option('sp_options');
	$singular = empty($options['sermon_word_singular']) ? 'sermon' : $options['sermon_word_singular'];
	$plural = empty($options['sermon_word_plural']) ? 'sermons' : $options['sermon_word_plural'];
	return Array('singular' => $singular, 'plural' => $plural);
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
add_action('admin_head', 'sp_add_styles');


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
	if (SP_SHOW_DEBUG)
	{
		print "<pre>";
		print_r($s);
		print "</pre>";		
	}
	if (SP_DO_LOG) sp_log($s);
}

function sp_log($s)
{
	$h = fopen(SP_LOG_FILE, 'a');
	fwrite($h, print_r($s, 1) . "\n");
	fclose($h);
}


/* Add Administration Pages */
if ( is_admin() )
{
	add_action ('admin_menu', 'sp_admin_menu');
	add_action ('admin_init', 'sp_register_options');
}

function sp_admin_menu()
{
	add_options_page('Sermon Publisher Options', 'Sermon Publisher', 'manage_options', 'sermon-publisher-options', 'sp_options_page');
}

function sp_options_page()
{
	if ( !current_user_can('manage_options') ) wp_die( __('You do not have sufficient permissions to access this page.' ) );

	if (isset($_GET['settings-updated']) && $_GET['settings-updated']) flush_rewrite_rules();

	// HANDLE POSTED DATA
	// include "options.php";
	?>

	<div class="wrap">
		<h2>Sermon Publisher Options</h2>
		<div class="sp_thanks updated">
			<p>
				Thank you for installing the Sermon Publisher plugin by <a href="http://jeff.mikels.cc">Jeff Mikels.</a>
				It is truly my hope and prayer that this plugin allows you to proclaim the Gospel of Jesus more effectively.
			</p>
		</div>

		<?php ini_set('max_execution_time', '300'); ?>
		<?php if (ini_get('max_execution_time') < '300'): ?>
			<div class="sp_alert error">
				Your server is configured to kill PHP scripts after <?php echo ini_get('max_execution_time'); ?> seconds. Uploading files to archive.org may take longer than that. If you have errors uploading to archive.org, consider increasing this value in your php.ini file.
			</div>
		<?php endif; ?>

		<div class="sp_podcasting_information updated">
		<?php if(!defined('PODCASTING_VERSION')): ?>
			It looks like you haven't yet installed the <a href="https://wordpress.org/plugins/podcasting/">Podcasting Plugin</a> yet. The advantage of the podcasting plugin is that you can host multiple media files on your site and the plugin will automatically create separate podcast feeds for each type. It's convenient, but not needed.
		<?php else: ?>
			<?php $location = get_option('pod_player_location'); ?>
			<?php $formats = unserialize(get_option('pod_formats')); ?>
			<?php if (! array_key_exists('audio', $formats) || $location != '') : ?>
			PODCASTING: It looks like you have the Podcasting Plugin successfully installed, but make sure you do the following things on its settings page:
			<ul>
				<?php if ($location != '') echo "<li>SET podcast player location to \"Manual.\"</li>"; ?>
				<?php if (! array_key_exists('video', $formats)) echo "<li>CREATE a podcast format feed with the format slug of \"video.\" (If you want to use video files.)</li>"; ?>
				<?php if (! array_key_exists('audio', $formats)) echo "<li>CREATE a podcast format feed with the format slug of \"audio.\"</li>"; ?>
			</ul>
			<?php else: ?>
			PODCASTING: It looks like you have the Podcasting Plugin successfully installed and configured properly!
			<?php endif; ?>
		<?php endif; ?>
		</div>

		<?php $stored_options = get_option('sp_options'); ?>

		<?php

		$options = Array(
			'sermon_word_singular'=>Array(
				'type'=>'text',
				'label'=>'"Sermon" Word',
				'value'=>'sermon',
				'description'=>'What word should be used for "sermon" on public pages? The default word is "sermon."'
			),
			'sermon_word_plural'=>Array(
				'type'=>'text',
				'label'=>'"Sermons" Word',
				'value'=>'sermons',
				'description'=>'What word should be used for "sermons" on public pages? The default word is "sermons."'
			),
			'delete_uploads'=>Array(
				'type'=>'checkbox',
				'label'=>'Delete Uploads',
				'value'=>'0',
				'checkvalue'=>'1',
				'description'=>'If this box is checked, we will delete all uploads from the WordPress media library when the post is published.'
			),
			'send_to_archive'=>Array(
				'type'=>'checkbox',
				'label'=>'Send to Archive.org',
				'value'=>'0',
				'checkvalue'=>'1',
				'description'=>'If this box is checked, we will attempt to cross-post uploaded media to archive.org using the settings below.'
			),
			'archive_access_key'=>Array(
				'type'=>'text',
				'label'=>'Archive.org Access Key',
				'value'=>'',
				'description'=>'Enter your Archive.org S3 Access Key. You can find it by logging in to your archive.org account and visiting <a href="http://archive.org/account/s3.php">http://archive.org/account/s3.php</a>.'
			),
			'archive_secret_key'=>Array(
				'type'=>'text',
				'label'=>'Archive.org Secret Key',
				'value'=>'',
				'description'=>'Enter your Archive.org S3 Secret Key. You can find it by logging in to your archive.org account and visiting <a href="http://archive.org/account/s3.php">http://archive.org/account/s3.php</a>.'
			),
			'archive_collection'=>Array(
				'type'=>'text',
				'label'=>'Archive.org Collection',
				'value'=>'test_item',
				'description'=>'Enter the name of the archive.org collection you to which I should submit these files. For testing, use the "test_item" collection.'
			),
			'archive_creator'=>Array(
				'type'=>'text',
				'label'=>'Archive.org Creator',
				'value'=>'',
				'description'=>'Enter the name of the "creator" of this item. Usually, it will be the name of the teacher who delivered this sermon.'
			),
			'archive_keywords'=>Array(
				'type'=>'text',
				'label'=>'Archive.org Keywords',
				'value'=>'',
				'description'=>'Enter a list of keyword phrases separated by semicolons.'
			),
			'archive_license'=>Array(
				'type'=>'text',
				'label'=>'Creative Commons License',
				'value'=>'',
				'description'=>'Enter the url for a creative commons license. You may choose one here: <a href="http://creativecommons.org/choose/" target="_blank">http://creativecommons.org/choose/</a>.'
			),

		);


		// populate stored values
		foreach ($stored_options as $key=>$value)
		{
			$options[$key]['value'] = $value;
		}
		if (! function_exists('curl_init'))
		{
			$options['archive_upload']['value']='';
			?>

			<div class="sp_alert error">
				Your server is configured without PHP's libCURL features. Uploading to archive.org will not be possible.
			</div>

			<?php
		}
		?>


		<form method="post" action="options.php">
			<?php settings_fields('sp_options'); ?>
			<?php //do_settings_sections('sp-option-group'); ?>

			<table class="form-table">

				<?php foreach ($options as $key=>$value): ?>
				<tr valign="top">
					<th scope="row"><?php echo $value['label']; ?></th>
					<td>
						<?php if ($value['type'] == 'checkbox') : ?>
						<input name="sp_options[<?php echo $key; ?>]" type="checkbox" value="<?php echo htmlentities($value['checkvalue']); ?>" <?php checked('1', $value['value']); ?> /> <?php echo $value['label']; ?>
						<?php elseif ($value['type'] == 'text') : ?>
						<input style="width:60%;" name="sp_options[<?php echo $key; ?>]" type="text" value="<?php echo htmlentities($value['value']); ?>" />
						<?php endif; ?>
						<p><?php echo $value['description']; ?></p>
					</td>
				</tr>
				<?php endforeach; ?>

			</table>

			<?php submit_button(); ?>

		</form>
	</div>

	<?php
}

add_action( 'wp_ajax_sp_upload', 'sp_ajax_upload_listener' );
function sp_ajax_upload_listener()
{
	$post_id = $_POST['post_id'];
	$remove_local = isset($_POST['remove_local']) && $_POST['remove_local'];
	$archive_completed_uploads = get_post_meta($post_id, 'sp_archive_uploaded_file');
	$retval = [];
	$had_error = False;

	// make sure all specified files are uploaded to archive.org
	// if none are specified in a POST variable, attempt to upload all attachments
	$file_path = isset($_POST['file_path']) ? ($_POST['file_path']) : '';
	if (!empty($file_path))
	{
		$retval = sp_do_archive_upload($post_id, $file_path);
		if ($retval['error']) $had_error = True;
	}
	else
	{
		$retval = array();
		
		//attempt to upload all the files
		$previous_uploads = array();
		$args = array(
			'post_parent' => $post_id,
			'post_type' => 'attachment',
			'post_status' => 'inherit'
		);
		$previous_uploads = get_children($args);
		foreach ($previous_uploads as $pu)
		{
			$attachment_path = get_attached_file($pu->ID, TRUE);
			sp_debug('PREPARING TO UPLOAD');
			sp_debug($attachment_path);

			// check to see if this file has been uploaded to archive.org yet
			$already_uploaded = False;
			foreach ($archive_completed_uploads as $acu)
			{
				if (basename($acu) == basename($attachment_path))
				{
					$already_uploaded = True;
					sp_debug('ALREADY UPLOADED');
					$retval['error'] = 1;
					$retval['msg'] = $attachment_path . ' was already uploaded';
				}
			}
			if (! $already_uploaded)
			{
				$res = sp_do_archive_upload($post_id, $attachment_path);
				if ($res['error']) $had_error = True;
				$retval[] = $res;
			}
		}
	}
	if (! $had_error && $remove_local) sp_remove_local_files($post_id);
	sp_debug($retval);
	wp_send_json($retval);
}

// remove local files that exist on archive.org
// re-link local enclosures to remote files
function sp_remove_local_files($post_id)
{
	sp_debug('sp_remove_local_files');
	$archive_completed_uploads = get_post_meta($post_id, 'sp_archive_uploaded_file');
	
	$args = array(
		'post_parent' => $post_id,
		'post_type' => 'attachment',
		'post_status' => 'inherit'
	);
	$previous_uploads = get_children($args);
	

	foreach ($archive_completed_uploads as $acu)
	{
		// check to see if this actually exists on archive.org
		if (sp_url_exists($acu))
		{
			//change the local enclosures to refer to this file instead of the local file.
			foreach (array('enclosure','download') as $key)
			{
				$enclosures = get_post_meta($post_id, $key);

				foreach ($enclosures as $oldenc)
				{
					sp_debug($oldenc);
					# normalize line endings
					$oldenc_normalized = preg_replace("/\r\n/", "\n", $oldenc);
					$encdata = explode("\n", $oldenc_normalized);
					$oldurl = $encdata[0];

					// does this enclosure go with this archive.org file?
					// if not, move on to the next enclosure
					if (basename($oldurl) != basename($acu)) continue;

					// this enclosure url will be replaced by the archive.org url
					$encdata[0] = $acu;
					$newenc = implode("\n", $encdata);
					sp_debug($newenc);

					// replace the enclosure
					delete_post_meta($post_id, $key, $oldenc);
					add_post_meta($post_id, $key, $newenc);
				}
			}
			
			// since this file is successfully hosted on archive.org, delete local attachments that match it
			// run through all attachments to remove the one which matches $oldurl
			$basename = basename($acu);
			sp_debug('DELETING LOCAL FILES MATCHING: ' . $basename);
			
			foreach ($previous_uploads as $pu)
			{
				$upload_file = basename(wp_get_attachment_url($pu->ID));
				if ($upload_file == $basename)
				{
					sp_debug('FOUND: ' . $upload_file);
					wp_delete_attachment($pu->ID, True);
				}
			}
		}
	}
}

function sp_register_options()
{
	register_setting('sp_options','sp_options'); // one group to store all options as an array
}

function sp_queue_archive_upload($post_id, $file_path)
{
	add_action('sp_do_archive_upload', 'sp_do_archive_upload', 10, 2);
	wp_schedule_single_event(time()+3, 'sp_do_archive_upload', array($post_id, $file_path));
}

// this function is called by ajax, so we want to return json
function sp_do_archive_upload($post_id, $file_path)
{
	sp_debug('testing');
	$retval = array();
	$curl_debug = '';
	
	if (empty($file_path))
	{
		$retval['error'] = 1;
		$retval['msg'] = 'No file was specified.';
		sp_debug('ERROR: No file was specified.');
		return $retval;
	}
	sp_debug('Starting upload: ' . $file_path);
	
	ini_set('max_execution_time', 60*60);
	add_post_meta($post_id, 'sp_archive_uploading', $file_path);
	$sermon_words = sp_get_sermon_words();
	$options = get_option('sp_options');

	$url = get_bloginfo('url');
	$domain = preg_replace('#https?://#','', $url);
	$domain = preg_replace('#/.*#','', $domain);
	$series_id = get_post_meta($post_id, 'sermon_series', TRUE);
	if (empty($series_id)) $series_name = $sermon_words['singular'];
	else
	{
		$series_post = get_post($series_id);
		$series_name = $series_post->post_name;
	}
	
	// unpublished posts can have an empty slug.
	$sermon_post = get_post($post_id);
	$sermon_name = $sermon_post->post_name;
	if (empty($sermon_name)) $sermon_name = $sermon_post->post_title;
	if (empty($sermon_name)) $sermon_name = 'untitled--' . $sermon_post->post_date;
	
	// first we check to see if an identifier is already set in the post metadata
	$identifier = get_post_meta($post_id, 'sp_archive_identifier', TRUE);
	if (empty($identifier))
	{
		// no identifier is set, so we need to generate one and hope it is unique
		// identifier pattern looks like this blog_domain.series_slug.post_slug
		// identifiers are S3 buckets so they must be lowercase letters, numbers, and periods
		$identifier = $domain . '--' . $series_name . '--' . $sermon_name;
		$identifier = strtolower($identifier);
		$identifier = preg_replace('/[^a-zA-Z0-9.]/','-', $identifier);
	}
	sp_debug('Identifier: ' . $identifier);
	
	// now we compute all important archive.org fields
	$archive_server = 'http://s3.us.archive.org';
	$metadata = Array(
		'collection'=>$options['archive_collection'],
		'creator'=>$options['archive_creator'],
		'subject'=>$options['archive_keywords'],
		'license'=>$options['archive_license'],
		'description'=>apply_filters('the_content', $sermon_post->post_content),
		'title'=>$series_post->post_title . ': ' . $sermon_post->post_title,
		'date'=>$sermon_post->post_date,
		'mediatype'=>'movies'
		);

	$accesskey = $options['archive_access_key'];
	$secretkey = $options['archive_secret_key'];

	$file = fopen($file_path, 'r');
	$filesize = filesize($file_path);


	// now we setup and execute the s3 commands to upload this file
	$curl_basic_headers = Array("authorization: LOW $accesskey:$secretkey", 'x-archive-size-hint:' . $filesize);
	$curl_bucket_create_headers = Array('x-archive-auto-make-bucket:1');
	$curl_metadata_headers = Array();
	foreach($metadata as $key=>$value)
	{
		$curl_metadata_headers[] = sprintf("x-archive-meta-%s:uri(%s)", $key, rawurlencode($value));
	}

	$archive_endpoint = $archive_server . '/' . $identifier . '/' . urlencode(basename($file_path));

	$curl_headers = array_merge($curl_basic_headers, $curl_metadata_headers, $curl_bucket_create_headers);
	
	// generate curl command line for testing
    // curl --location --header 'x-amz-auto-make-bucket:1' \
    //      --header 'x-archive-meta01-collection:opensource_movies' \
    //      --header 'x-archive-meta-mediatype:movies' \
    //      --header 'x-archive-meta-title:Ben plays piano.' \
    //      --header "authorization: LOW $accesskey:$secret" \
    //      --upload-file ben-2009-05-09.avi \
    //      http://s3.us.archive.org/ben-plays-piano/ben-plays-piano.avi
	
	$curl_debug = "curl --location \\\n";
	foreach($curl_headers as $h)
	{
		$curl_debug .= "    --header '$h' \\\n";
	}
	$curl_debug .=     "    --upload-file '$file_path' \\\n";
	$curl_debug .= "'$archive_endpoint'";
	
	sp_debug('attempting to start upload');
	sp_debug('CURL COMMAND:');
	sp_debug($curl_debug);
	sp_debug('CURL HEADERS:');
	sp_debug($curl_headers);
	sp_debug('ARCHIVE_ENDPOINT');
	sp_debug($archive_endpoint);

	$ch = curl_init();
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLINFO_HEADER_OUT, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
	curl_setopt($ch, CURLOPT_PUT, True);
	curl_setopt($ch, CURLOPT_INFILE, $file);
	curl_setopt($ch, CURLOPT_INFILESIZE, $filesize);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
	curl_setopt($ch, CURLOPT_VERBOSE, True);
	curl_setopt($ch, CURLOPT_URL, $archive_endpoint);
	sp_debug('FIRST EXEC');
	$response = curl_exec($ch);
	sp_debug(curl_getinfo($ch, CURLINFO_HEADER_OUT));
	sp_debug($response);

	$count = 1;
	if (strpos($response, 'BucketAlreadyExists') !== False)
	{
		sp_debug('bucket exists trying to submit files to that bucket');
		$curl_headers = array_merge($curl_basic_headers, $curl_metadata_headers);
		sp_debug($curl_headers);
		$ch = curl_init();
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
		curl_setopt($ch, CURLOPT_PUT, True);
		curl_setopt($ch, CURLOPT_INFILE, $file);
		curl_setopt($ch, CURLOPT_INFILESIZE, $filesize);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
		curl_setopt($ch, CURLOPT_VERBOSE, True);
		curl_setopt($ch, CURLOPT_URL, $archive_endpoint);
		$response = curl_exec($ch);
		sp_debug('EXEC AGAIN');
		sp_debug(curl_getinfo($ch, CURLINFO_HEADER_OUT));
		sp_debug($response);
	}
	
	delete_post_meta($post_id, 'sp_archive_uploading', $file_path);
	
	
	// was it successful?
	$response_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	if (curl_errno($ch) )
	{
		sp_debug('CURL ERROR');
		sp_debug(curl_error($ch));
		$retval['error'] = 1;
		$retval['msg'] = 'Unknown CURL error. Contact your webmaster. <pre>'.curl_error($ch).'<br><br>'.$response.'</pre>';
	}
	elseif ($response_code != 200)
	{
		sp_debug('HTTP ERROR CODE');
		sp_debug($response_code);
		$retval['error'] = 1;
		$retval['msg'] = 'Unknown Uploading error. Contact your webmaster. <pre>'.$response.'</pre>';
	}
	else
	{
		$archive_item = "http://archive.org/details/" . $identifier;
		$archive_file = "http://archive.org/download/" . $identifier . '/' . urlencode(basename($file_path));

		// print "<li><a href=\"$archive_item\">$archive_item</a>";
		// print "<li><a href=\"$archive_file\">$archive_file</a>";

		// now we add the post metadata that is needed
		add_post_meta($post_id, 'sp_archive_identifier', $identifier, True) || update_post_meta($post_id, 'sp_archive_identifier', $identifier);
		add_post_meta($post_id, 'sp_archive_item', $archive_item, True) || update_post_meta($post_id, 'sp_archive_item', $archive_item);

		// is this file already in the post metadata
		$uploaded_files = get_post_meta($post_id, 'sp_archive_uploaded_file');
		if (! in_array($archive_file, $uploaded_files))
			add_post_meta($post_id, 'sp_archive_uploaded_file', $archive_file);
		
		$retval['error'] = 0;
		$retval['msg'] = 'Success';
		$retval['archive_item'] = $archive_item;
		$retval['archive_file'] = $archive_file;
	}
	curl_close($ch);
	sp_debug('SENDING JSON');
	sp_debug(json_encode($retval));
	return $retval;
}

?>