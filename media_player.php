<!-- begin media_player.php -->
<!-- ITEM MEDIA PLAYERS OR LINKS OR KEY IMAGE -->
<?php
global $media_player_visible;
global $post;
$media_player_visible = FALSE;
$player_width = "100%";
$player_height = 365;

// CASES:
// is_single -> show Player with all compatible media items in playlist
// not_single & not_feed -> leave player hidden by default
// is_feed -> show nothing because download box will contain links
if ( is_feed() ) return;

if ( is_page() or is_single() ) $display = 'block';
else $display = 'none';

$media_items = Array();
$enclosures = get_post_custom_values('enclosure');
$poster = get_post_custom_values('poster');
$youtube_link = get_post_meta($post->ID, 'youtube_link', TRUE);

if (count($poster) > 0) $poster = $poster[0];
else $poster = plugin_dir_url( __FILE__ ) . "scripture.jpg";

if (is_single() or is_page()) $preload = 'metadata';
else $preload = 'none';

$has_video = 0;
$has_audio = 0;
$has_youtube = 0;

// search for youtube video id
if ($youtube_link)
{
	$has_youtube = 1;
	
	// youtube shortlinks look like this:
	// https://youtu.be/WmFvbN6jf5U
	// youtube long links look like this:
	// https://www.youtube.com/watch?v=WmFvbN6jf5U
	// or, the user has posted just the video id
	$matches = '';
	if(preg_match('#https://youtu.be/(.*)#', $youtube_link, $matches)) $video_id = $matches[1];
	elseif(preg_match('#[&?]v=([^&]*)#', $youtube_link, $matches)) $video_id = $matches[1];
	else $video_id = $youtube_link;
}

if ($enclosures)
{
	foreach ($enclosures as $enclosure)
	{

		// check each enclosure to see if it's a valid media file
		$encdata = explode("\n",$enclosure);
		$url = trim($encdata[0]);

		// check to see if url actually exists
		if (! sp_url_exists($url)) continue;

		$ext = substr($url, -4);
		if (in_array($ext, array('.flv','.ogv','.mp4','.webm'))) $has_video = 1;
		if (in_array($ext, array('.mp3','.ogg'))) $has_audio = 1;
		if ($ext == '.flv') $title = 'flash';
		elseif ($ext == '.mp4') $title = 'mp4';
		elseif ($ext == '.ogv') $title = 'ogv';
		elseif ($ext == '.mp3') $title = 'mp3';
		elseif ($ext == '.ogg') $title = 'ogg';
		elseif ($ext == 'webm') $title = 'webm';
		else continue;

		// we prefer the 512k version of the mp4 files
		// if this file is an mp4
		// and it is not named with _512k
		// and we already have another mp4 set,
		// then just move on and ignore this one
		if ($ext == '.mp4' and (strpos($url, '_512k') === False) and isset($media_items['mp4'])) continue;
		$media_items[$title] = $url;
	}
}

// Pick the default media file for flash player: medium, high, audio
if ($media_items or $has_youtube)
{
	if ($display == 'block') $media_player_visible = TRUE;

	array_multisort($media_items);
	$default_item = !empty($media_items['flash']) ? $media_items['flash'] : NULL;
	$default_title = 'flash';
	if ( ! $default_item ) {
		$default_item = !empty($media_items['mp4']) ? $media_items['mp4'] : NULL;
		$default_title = 'mp4';
	}
	if ( ! $default_item ) {
		$default_item = !empty($media_items['mp3']) ? $media_items['mp3'] : NULL;
		$default_title = 'mp3';
	}

	$media_player_id = "MediaPlayer_" . $post->ID;

	?>

	<div class="media-player">
	
	<!-- YouTube media has priority -->
	<?php if($has_youtube && $video_id): ?>
		
		<div class="media-player-title">Video Player</div>
		
		<style>.embed-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; } .embed-container iframe, .embed-container object, .embed-container embed { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }</style><div class='embed-container'><iframe src='https://www.youtube.com/embed/<?php echo $video_id; ?>' frameborder='0' allowfullscreen></iframe></div>
		
	<?php endif;?>
	
	<?php if ($has_video && ! $has_youtube): ?>

		<div class="media-player-title">Video Player</div>

		<!-- NOW WE SHOW THE HTML5 VIDEO PLAYER IF IT IS AVAILABLE -->
		<div id="player-<?php the_ID(); ?>" class="media-player-box" style="display:<?=$display;?>">

			<!-- Video for Everybody, Kroc Camen of Camen Design -->
			<video id="<?php echo $media_player_id; ?>" class="media-player-player" poster="<?php echo $poster; ?>" controls="controls" preload="<?php echo $preload; ?>" style="width: <?php echo $player_width; ?>;background:black;">

			<!-- MP4 for Safari, IE9, iPhone, iPad, Android, and Windows Phone 7 -->
				<?php if ($media_items['mp4'] ): ?>
				<source src="<?php echo $media_items['mp4'];?>"  type="video/mp4" codecs="avc1.42E01E, mp4a.40.2" />
				<?php endif; ?>

				<!-- WebM/VP8 for Firefox4, Opera, and Chrome -->
				<?php if ($media_items['webm'] ): ?>
				<source src="<?php echo $media_items['webm'];?>"  type="video/webm" codecs="vp8, vorbis" />
				<?php endif; ?>

				<!-- Ogg/Vorbis for older Firefox and Opera versions -->
				<?php if ($media_items['ogv'] ): ?>
				<source src="<?php echo $media_items['ogv'];?>"  type="video/ogg" codecs="theora, vorbis" />
				<?php endif; ?>

				<!-- Flash fallback for non-HTML5 browsers without JavaScript -->
				<object type="application/x-shockwave-flash" style="width: <?php echo $player_width; ?>; max-width: 100%; height: <?php echo $height; ?>;"
							data="<?php bloginfo('template_directory'); ?>/mediaelement/flashmediaelement.swf">
							<param name="movie" value="<?php bloginfo('template_directory'); ?>/mediaelement/flashmediaelement.swf" />
							<param name="flashvars" value="controls=true&file=<?php echo $media_items['mp4'];?>" />
							<!-- Image as a last resort -->
							<img src="<?php echo $poster; ?>" width="100%" title="No video playback capabilities" />
				</object>
			</video>
		</div>

	<?php endif; ?>


	<?php if ($has_audio): ?>

		<!-- WE SHOW THE HTML5 AUDIO PLAYER IF IT IS AVAILABLE -->
		<div class="media-player-title">Audio Player</div>

		<div id="audio-player-<?php the_ID(); ?>" class="audio-player-box" style="display:<?=$display;?>">
			<audio id="<?php echo $media_player_id; ?>" style="width: <?php echo $player_width; ?>; max-width: 100%; height: <?php echo $height; ?>;" poster="<?php echo $poster; ?>" controls="controls" preload="<?php echo $preload; ?>" >
				<?php if ($media_items['ogg'] ): ?>
				<source src="<?php echo $media_items['ogg'];?>"  type="audio/ogg" codecs="vorbis" />
				<?php endif; ?>

				<?php if ($media_items['mp3'] ): ?>
				<source src="<?php echo $media_items['mp3'];?>"  type="audio/mpeg" />
				<?php endif; ?>
			</audio>
		</div>

	<?php endif; ?>

	</div><!-- end .media-player -->

	<?php if ($display == 'none') { ?>

		<div class="video-player-link">
			<a href="#" onclick="document.getElementById('player-<?php the_ID(); ?>').style.display = 'block';this.innerHTML = ''; return false;">| Show Media Player |</a>
		</div>

	<?php }
}
?>
<!-- media_player.php end -->