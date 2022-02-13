<?php

// define('SP_MY_WORDPRESS_FOLDER',$_SERVER['DOCUMENT_ROOT']);
// define('SP_MY_PLUGIN_FOLDER', plugin_dir_path( __FILE__ ));

add_action('admin_init','sp_sermon_meta_init');
function sp_sermon_meta_init()
{
	$sermon_words = sp_get_sermon_words();
	$singular = $sermon_words['singular'];
	$plural = $sermon_words['plural'];

	// review the function reference for parameter details
	// http://codex.wordpress.org/Function_Reference/add_meta_box

	// add a meta box for sermon pages
	add_meta_box('sp_sermon_series_meta', sprintf('%s Series', ucwords($singular)), 'sp_sermon_series_meta_setup', 'sp_sermon', 'side', 'high');
	add_meta_box('sp_sermon_youtube_meta', sprintf('%s YouTube', ucwords($singular)), 'sp_sermon_youtube_meta_setup', 'sp_sermon', 'side', 'high');
	add_meta_box('sp_sermon_media_meta', sprintf('%s Media', ucwords($singular)), 'sp_sermon_media_meta_setup', 'sp_sermon', 'side', 'high');
	
	// add a meta box for series groups
	add_meta_box('sp_series_group_meta', 'Series in This Group',  'sp_series_group_meta_setup', 'sp_series_group', 'side', 'high');

	// add podcasting meta box to sermon pages if podcasting plugin is installed;
	if (is_plugin_active('podcasting/podcasting.php') || defined('PODCASTING_VERSION'))
	{
		global $podcasting_metabox;
		add_meta_box('podcasting', 'Podcasting', array($podcasting_metabox, 'editForm'), 'sp_sermon', 'normal');
	}

	// add a callback function to save any data a user enters in
	add_action('save_post','sp_sermon_meta_save',999);
	add_action('save_post','sp_series_group_meta_save',999);
	add_action('admin_footer', 'sp_upload_button_handler');
}

// youtube auto embed meta box
function sp_sermon_youtube_meta_setup()
{
	global $post;
	$youtube_link = get_post_meta($post->ID,'youtube_link',TRUE);

	// meta HTML code
	echo '<div class="">';
	echo '<label for="youtube_link_input">YouTube Link or Video ID: </label><br />';
	echo '<input style="width:100%;" name="youtube_link" id="youtube_link_input" value="' . $youtube_link . '" /><br />';
	echo '<small>Paste a valid youtube link or video id here.</small>';
	echo '</div>';
}

function sp_sermon_series_meta_setup()
{
	global $post;
	$meta = get_post_meta($post->ID,'sermon_series',TRUE);

	// get all the sermon series
	$series_pages = get_posts(array ('numberposts'=>-1, 'post_type'=>'sp_series'));

	// meta HTML code
	echo '<div class="sp_sermon_meta_control">';
	echo '<select name="sermon_series">';
	echo '<option value="-1">-- Choose the right series title --</option>';

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

function sp_sermon_media_meta_setup()
{
	$max_upload = (int)(ini_get('upload_max_filesize'));
	$max_post = (int)(ini_get('post_max_size'));
	//$memory_limit = (int)(ini_get('memory_limit'));
	$upload_gb = min($max_upload, $max_post);

	global $post;
	// check if this post is connected to an archive.org item
	$identifier = get_post_meta($post->ID, 'sp_archive_identifier', True);
	
	
	$upload_purposes = array('video','audio','notes','manuscript','slides', 'other');
	$html = '';
	$html .= '<p class="description">Locally Hosted Files</p>';

	// handle attached files
	$attached_files = array();
	$attachment_ids = array();
	if ($post->ID)
	{
		$args = array(
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'post_status' => 'inherit'
		);
		$attached_files = get_children($args);
	}
	
	foreach($attached_files as $af)
	{
		$attachment_ids[] = $af->ID;
	}
	
	// see if enclosures are hosted locally and convert them to attachments
	// foreach (get_post_meta($post->ID, 'enclosure') as $enclosure)
	// {
	// 	print_r($enclosure);
	// 	$enclosure_data = explode("\n", $enclosure);
	// 	$enclosure_url = $enclosure_data[0];
	//
	// 	// is this enclosure also a local post?
	// 	$args = array(
	// 		'post_type' => 'attachment',
	// 		'guid' => $enclosure_url,
	// 	);
	// 	print_r($args);
	// 	// $p = get_posts($args);
	// 	// print_r($p);
	// }
	
	// $t = get_post(1108);
	// print_r($t);
	
	if (! $attached_files)
	{
		$html .= "<p>NO FILES ATTACHED TO THIS POST</p>";
		if (! empty($identifier))
		{
			$html .= "<p class=\"sp-archive-link\">Files for this item are hosted at archive.org.<br /><a target=\"_blank\" href=\"http://archive.org/details/$identifier\">View Them There</a></p>";
		}
		
	}
	else
	{
		// we will compare to the files already stored in custom fields
		// so grab them now
		$specified_downloads = get_post_meta($post->ID, 'download');
		$specified_enclosures = get_post_meta($post->ID, 'enclosure');

		$html .= '<div class="sp_previous_uploads">';
		$html .= '<table class="sermon-media-table"><tr><th>FILE</th><th>PURPOSE</th><th>DELETE</th></tr>';

		foreach ($attached_files as $af)
		{
			$attachment_path = get_attached_file($af->ID, TRUE);
			$attachment_url = wp_get_attachment_url($af->ID);

			$attachment_name = basename ($attachment_path);
			$attachment_purpose = 'NONE';

			foreach ($specified_downloads as $item)
			{
				$item = str_replace("\r\n","\n", $item);
				$item_data = explode("\n", $item);
				$item_url = $item_data[0];
				if ($item_url == $attachment_url)
				{
					$attachment_purpose = $item_data[1] ? $item_data[1] : 'NONE';
				}
			}
			foreach ($specified_enclosures as $item)
			{
				$item = str_replace("\r\n","\n", $item);
				$item_data = explode("\n", $item);
				$item_url = $item_data[0];
				$item_podcast_values = unserialize($item_data[3]);
				if ($item_url == $attachment_url)
				{
					$attachment_purpose = $item_podcast_values['format'] ? $item_podcast_values['format'] : 'NONE';
				}

			}



			if (strlen($attachment_name) <= 30) $attachment_shortname = $attachment_name;
			else $attachment_shortname = substr($attachment_name, 0, 20) . '...' . substr($attachment_name, -7);
			$html .= '<tr>';
			$html .= '<td><span class="sp-media-name" title="'.$attachment_name.'">' . $attachment_shortname . '</span></td>';
			$html .= '<td><span class="sp-media-purpose">' . $attachment_purpose . '</span></td>';
			$html .= '<td><input name="sp_media_delete[' . $af->ID . ']" value="yes" type="checkbox" onclick="return ! this.checked || confirm(\'The file &quot;'.$attachment_shortname.'&quot; will be deleted. Are you sure?\');"  /></td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';
	}
	$html .= '<hr />';
	$html .= '<h4>Upload New Files</h4><p class="description">Upload audio, video, or pdf files <strong>up to '.$upload_gb.'GB</strong> in size.</p>';
	$html .= '<select name="sp_media_purpose">';
	$html .= '<option value="other">-- Describe Your Upload</option>';
	foreach ($upload_purposes as $purpose)
	{
		$html .= '<option value="'.$purpose.'">'.$purpose.'</option>';
	}
	$html .= '</select>';
	$html .= '<input class="" type="file" id="sp_sermon_media" name="sp_sermon_media" value="" size="25">';
	$html .= '<div><small style="color:red;"><strong>NOTE:</strong> File uploads and/or deletions do not take place until this post is saved, updated, or published.</small></div><hr />';

	// check to see if archive.org uploading is enabled
	$options = get_option('sp_options');
	if($options['send_to_archive'] && $attached_files)
	{
		$html .= "<h4>archive.org posting</h4>";

		//check to see if this media has been sent to archive.org already
		$upload_button_text = 'Upload Attached Files to Archive.org';
		if (! empty($identifier) )
		{
			$html .= "<p class=\"sp-archive-link\"><a href=\"http://archive.org/details/$identifier\">Click here to view these files at archive.org</a></p>";
			$upload_button_text = 'Re-Upload Files to Archive.org';
		}
		else
		{
			$html .= "<p>The above media files may be sent to the <span class=\"sp-collection-name collection_name\">" . $options['archive_collection'] . "</span> collection at the Internet Archive.";
		}

		if ($post->post_status == 'publish' || $post->post_status == 'draft')
		{
			$html .= '<div id="sp-archive-submit-button-container">';
			$html .= '<div><button class="button button-primary" id="sp-button-upload" style="width:100%;">'.$upload_button_text.'</button><br /><small>This button will send all the files attached to this post to archive.org. If successful, the page will reload, so make sure all changes have been saved before clicking this button.</small></div>';
			$html .= '<div>&nbsp</div>';
			if( ! empty($identifier))
			{
				$html .= '<div><button class="button button-primary" id="sp-button-remove-local" style="width:100%;">Host Files from Archive.org</button><br /><small>This button will switch hosting from your site to archive.org and update the links here so they refer to the files there.</small></div>';
			}
			$html .= '<div class="sp-warning" style="display:none;"><span class="throbber"></span><div class="sp-warning-msg"></div></div></div>';
		}
		else
		{
			$html .= '<div id="sp-archive-submit-button-container">Once this post is saved, you will be able to transfer uploaded files to archive.org for permanent, free hosting.</div>';
		}
	}
	echo $html;
}

function sp_upload_button_handler()
{
	if (empty(get_the_ID())) return;
	
	?>

	<script type="text/javascript">
	// sp_upload_button_handler

	jQuery(document).ready(function($){
		
		var post_id = '<?php echo get_the_ID(); ?>';
		
		// var sp_upload_data = {
		// 	'action': 'sp_upload',
		// 	'post_id': <?php echo get_the_ID(); ?>
		// };
		
		
		// REMOVE LOCAL FILES
		$('#sp-button-remove-local').click(function(e)
		{

			// UNUSED NOW - trigger the remove_local action
			// sp_upload_data.action = 'sp_host_remotely';
			// sp_upload_data.remove_local = 1;

			e.preventDefault();
			if ( ! confirm('Are you sure?\n\nIf you click OK, I will make sure all files uploaded to archive.org are hosted from there and removed from your Wordpress Media Library.')) return false;


			elem = $(this);
			$('.button').addClass('disabled');
			$('#sp-archive-submit-button-container .throbber').show();
			$('#sp-archive-submit-button-container .sp-warning').slideDown();
			$('#sp-archive-submit-button-container .sp-warning-msg').html('Sending files to Archive.org. Do not close this browser window until the uploads are done.');
			

			$.ajax({
				url: ajaxurl,
				data: {
					'action': 'sp_host_remotely',
					'post_id': post_id
				},
				method: 'POST',
				success: function(response){
					console.log(response);
					if (! response.error)
					{
						setTimeout(function(){document.location.reload()}, 1000);
						$('#sp-archive-submit-button-container .sp-warning-msg').html('Success! Reloading page!')
					}
					else
					{
						$('#sp-archive-submit-button-container .sp-warning-msg').html('Error Message:<br />' . response.msg);
					}
				},
				error: function(response){
					console.log(response);
				},
				complete: function(){
					$('.button').removeClass('disabled');
					$('#sp-archive-submit-button-container .throbber').hide();
				}
			});
		});
		
		
		// DO UPLOAD
		$('#sp-button-upload').click(function(e)
		{
			// sp_upload_data.action = 'sp_upload';
			
			e.preventDefault();
			if ( ! confirm('If you have unsaved changes, you should hit CANCEL now and hit the "Update" button or they won\'t get reflected in the archive.org item.\n\nIf you are ready to upload, click OK now.')) return false;

			elem = $(this)
			$('.button').addClass('disabled');
			$('#sp-archive-submit-button-container .throbber').show();
			$('#sp-archive-submit-button-container .sp-warning').slideDown();
			$('#sp-archive-submit-button-container .sp-warning-msg').html('Sending files to Archive.org. Do not close this browser window until the uploads are done.');


			// since 2.8 ajaxurl is always defined in the wordpress admin header and points to admin-ajax.php
			$.ajax({
				url: ajaxurl,
				data: {
					'action': 'sp_upload',
					'post_id': post_id
				},
				method: 'POST',
				success: function(response)
				{
					console.log(response);
					
					// response can be an object representing one file upload
					// an array representing the results of multiple uploads
					// or an empty array if something else goes wrong
					var had_error = false;
					var err_msg = [];
					if (response.length == 0)
					{
						had_error = true;
						err_msg.push('Unknown upload error');
					}
					if (response.error)
					{
						had_error = true;
						err_msg.push(response.msg);
					}
					if (response.length > 0)
					{
						for (var i in response)
						{
							if (response[i].error)
							{
								had_error = true;
								err_msg.push(response[i].msg);
							}
						}
					}
					
					if (had_error)
					{
						$('#sp-archive-submit-button-container .sp-warning-msg').html('There was an error uploading to archive.org:<br />' + err_msg.join('<br />'));
					}
					else
					{
						setTimeout(function(){document.location.reload()}, 3000);
						$('#sp-archive-submit-button-container .sp-warning-msg').html('SUCCESS! Reloading page in 3 seconds');
					}
					// setTimeout(function(){document.location.reload()}, 3000);
				},
				error: function(response)
				{
					console.log(response);
					$('#sp-archive-submit-button-container .sp-warning-msg').html('Error');
				},
				complete: function()
				{
					$('.button').removeClass('disabled');
					$('#sp-archive-submit-button-container .throbber').hide();
				}
			});
		});
	});

	</script>

	<?php
}


function sp_sermon_meta_save($post_id)
{
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

	// authentication checks
	// make sure data came from our meta box
	if (! isset($_POST['sp_sermon_meta_noncename']) || !wp_verify_nonce($_POST['sp_sermon_meta_noncename'],__FILE__)) return $post_id;

	// check user permissions
	if (!current_user_can('edit_post', $post_id)) return $post_id;
	
	// only pay attention to posts generated by this plugin
	if ($_POST['post_type'] != 'sp_sermon') return $post_id;

	// authentication passed, save data
	// var types
	// single: sermon_series[var]
	// array: sermon_series[var][]
	// grouped array: sermon_series[var_group][0][var_1], sermon_series[var_group][0][var_2]

	// SERMON SERIES METADATA
	// $current_data = get_post_meta($post_id, 'sermon_series', TRUE);
	$new_data = $_POST['sermon_series'];
	
	if (-1 == $new_data) delete_post_meta($post_id, 'sermon_series');
	elseif (! update_post_meta($post_id,'sermon_series',$new_data))
		add_post_meta($post_id,'sermon_series',$new_data,TRUE);


	// YOUTUBE LINK
	$youtube_link = $_POST['youtube_link'] or '';
	if ( !empty($youtube_link) )
		if (! update_post_meta($post_id,'youtube_link',$youtube_link))
				add_post_meta($post_id,'youtube_link',$youtube_link,TRUE);


	// SERMON MEDIA DELETIONS
	if (isset($_POST['sp_media_delete']))
	{
		foreach ($_POST['sp_media_delete'] as $id => $yesno)
		{
			if ($yesno == 'yes')
			{
				// time to delete the attachment with id = $id
				// first, we remove the related download/enclosure metadata fields
				$attachment_url = wp_get_attachment_url($id);
				$post_meta = get_post_meta($post_id);
				$die_text = '';
				foreach ($post_meta as $key => $values)
				{
					if ($key == 'download' || $key == 'enclosure')
					{
						foreach ($values as $value)
						{
							$encdata = explode("\n", $value);
							$url = trim($encdata[0]);
							$die_text .= '<pre>*' . print_r($attachment_url, true) ."*\n*". print_r($url, true) . '*</pre>' . "\n";
							$die_text .= ($attachment_url === $url) ? 'matched' : 'did not match';
							if ($attachment_url === $url) delete_post_meta($post_id, $key, $value);
						}
					}
				}
				// next, we actually delete the attachment
				wp_delete_attachment($id, TRUE);
			}
		}
	}


	// SERMON MEDIA UPLOAD
	// Make sure the file array isn't empty
	if(!empty($_FILES['sp_sermon_media']['name']))
	{
		// Setup the array of supported file types.
		$supported_types = array('application/pdf','video/mp4','video/quicktime','video/ogg','video/webm','audio/mp3','audio/mpeg','audio/ogg');

		// Get the file type of the upload
		// $arr_file_type = wp_check_filetype(basename($_FILES['sp_sermon_media']['name']));
		// $uploaded_type = $arr_file_type['type'];
		$uploaded_type = $_FILES['sp_sermon_media']['type'];
		$uploaded_size = $_FILES['sp_sermon_media']['size'];
		$purpose = $_POST['sp_media_purpose'];

		// Check if the type is supported. If not, throw an error.
		if(in_array($uploaded_type, $supported_types))
		{
			
			// high level api attaches files improperly
			// $attachment_id = media_handle_upload('sp_sermon_media', $post_id);
			
			// Use the WordPress API to upload the file
			$upload = wp_handle_upload($_FILES['sp_sermon_media'], array('test_form'=>false));

			if(isset($upload['error']) && $upload['error'] != 0)
			{
				wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
			}
			else
			{
				// $attachment = get_post($attachment_id);
				// $file_path = get_attached_file($attachment_id);

				// the file was uploaded successfully
				// queue the archive.org upload if needed
				
				// currently not used
				// $options = get_option('sp_options');
				// if (! empty($options['archive_upload']) && $options['archive_upload'] == '1') sp_queue_archive_upload($post_id, $upload['file']);

				
				// /* USED ONLY WHEN USING THE LOWER LEVEL UPLOAD APIS
				// add an attachment
				$attachment = array(
					'guid'           => $upload['url'],
					'post_mime_type' => $upload['type'],
					'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $upload['file'] ) ),
					'post_content'   => '',
					'post_status'    => 'inherit',
					'post_type'      => 'attachment'
				);
				$attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);
				error_log(json_encode($attachment));
				error_log(json_encode($attachment_id));
				// */
				
				// THESE FUNCTIONS ARE INTENDED ONLY FOR UPLOADING IMAGES
				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				// require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// Generate image metadata for the attachment, and update the database record.
				// $attach_data = wp_generate_attachment_metadata( $attachment_id, $filename);
				// wp_update_attachment_metadata( $attachment_id, $attach_data );
				
				$pdf_purposes = Array('manuscript','notes','slides');
				$enclosure_purposes = Array('video','audio');
				
				if (in_array($purpose, $enclosure_purposes))
				{
					// the first three lines are used by wordpress in feed generation
					$field_data = $upload['url'] . "\n";
					$field_data .= $uploaded_size . "\n";
					$field_data .= $uploaded_type . "\n";

					// this line is used by the podcasting plugin for custom feed generation
					$field_data .= serialize(array('format'=>$purpose, 'keywords'=>'', 'author'=>'','length'=>'','explicit'=>''));

					// this final line is used by this plugin to help us know the associated attachment
					$field_data .= "\n" . $attachment_id;
					add_post_meta($post_id, 'enclosure', $field_data);
				}
				else add_post_meta($post_id, 'download', $upload['url'] . "\n" . $purpose . "\n" . $attachment_id);
			} // end if/else
		}
		else
		{
			wp_die("The file type that you've uploaded (". $uploaded_type .") is not allowed.");
		} // end if/else
	} // end if
	
	sp_sermon_fix_attachments($post_id);
	return $post_id;
}

function sp_allow_file_uploads()
{
	echo ' enctype="multipart/form-data"';
} // end update_edit_form
add_action('post_edit_form_tag', 'sp_allow_file_uploads');

// uses vuejs
function sp_series_group_meta_setup()
{
	global $post;

	// using an underscore prevents the meta variable
	// from showing up in the custom fields section
	$selected_series = get_post_meta($post->ID,'series_group_data',TRUE);
	if (!empty($selected_series)) $selected_series = json_decode($selected_series, TRUE);
	else $selected_series = [];

	// get all the sermon series
	$series_pages = get_posts(array ('numberposts'=>-1, 'post_type'=>'sp_series'));
	
	// add the images to the series page data
	$images_by_id=[];
	foreach($series_pages as $key=>$series)
	{
		$images_by_id[$series->ID] = get_the_post_thumbnail_url($series->ID, 'sp_poster');
	}
	
	?>
	<style>
		.series_select_row:hover {background:rgba(0,0,50,.1);}
		.series_select_link {display:block;text-transform:uppercase;cursor:pointer;}
		.series_select_buttons {position:absolute;right:5px;}
		.series_select_buttons a {cursor:pointer;padding:2px 1px;}
		.series_select_buttons a:hover {background:rgba(0,0,100,.9);color:white;}
	</style>
	<div class="sermon_meta_control">
		<div id="vueapp">
			<h3>Series in this Group</h3>
			<div class="series_select_row" v-for="series, index in selected_series">
				<div class="series_select_buttons">
					<a v-if="index < selected_series.length-1" class="series_select_button" @click="move_to_bottom(series)">&dArr;</a>
					<a v-if="index < selected_series.length-1" class="series_select_button" @click="move_down(series)">&darr;</a>
					<a v-if="index > 0" class="series_select_button" @click="move_up(series)">&uarr;</a>
					<a v-if="index > 0" class="series_select_button" @click="move_to_top(series)">&uArr;</a>
				</div>
				<a class="series_select_link" @click="deselect_series(series)">{{series.post_title}}</a>
			</div>
			<h3>Available Series</h3>
			<div class="series_select_row" v-for="series in available_series">
				<a class="series_select_link" @click="select_series(series)">{{series.post_title}}</a>
			</div>
			<!-- <input type="hidden" id="series_group_data" name="series_group_data" v-model="group_data_json"/> -->
			<textarea name="series_group_data" v-model="group_data_json"></textarea>
		</div>
		<input type="hidden" name="series_group_meta_noncename" value="<?php echo wp_create_nonce(__FILE__); ?>" />
	</div>
	
	<script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
	<script>
		var series_pages = <?php echo json_encode($series_pages); ?>;
		var selected_series = <?php echo json_encode($selected_series); ?>;
		var images_by_id = <?php echo json_encode($images_by_id); ?>;
		
		var app = new Vue({
			el: '#vueapp',
			data: {
				series_pages: series_pages,
				selected_series: selected_series,
			},
			computed: {
				available_series: function(){
					return this.series_pages.filter((e) => this.selected_ids.indexOf(e.ID) == -1);
				},
				group_data_json: function(){
					return JSON.stringify(this.selected_series)
				},
				selected_ids: function(){
					return this.selected_series.map((e)=>e.ID);
				},
			},
			methods: {
				select_series: function(series) {
					this.selected_series.push(series);
					// this.selected_ids.push(series.ID);
				},
				deselect_series: function(series) {
					var index = this.selected_series.indexOf(series);
					if (index == -1) return;
					// splice the series out of the array
					this.selected_series.splice(index,1);
					
					// also remove from the selected_ids array
					// index = this.selected_ids.indexOf(series.ID);
					// if (index == -1 ) return;
					// this.selected_ids.splice(index,1);
				},
				move_up: function(series) {
					var index = this.selected_series.indexOf(series);
					if (index <= 0) return;
					// splice the item out of the array
					var item = this.selected_series.splice(index,1);
					// item is now an array with one element;
					item = item[0];
					// insert it back into the array at an earlier position
					this.selected_series.splice(index - 1, 0, item);
				},
				move_down: function(series) {
					var index = this.selected_series.indexOf(series);
					if (index == -1 || index >= this.selected_series.length-1) return;
					// splice the item out of the array
					var item = this.selected_series.splice(index,1);
					// item is now an array with one element;
					item = item[0];
					// insert it back into the array at a later position
					this.selected_series.splice(index + 1, 0, item);
				},
				move_to_bottom: function(series) {
					var index = this.selected_series.indexOf(series);
					if (index == -1 || index >= this.selected_series.length-1) return;
					// splice the item out of the array
					var item = this.selected_series.splice(index,1);
					// item is now an array with one element;
					item = item[0];
					// insert it back into the array at the bottom
					this.selected_series.push(item);
				},
				move_to_top: function(series) {
					var index = this.selected_series.indexOf(series);
					if (index <= 0) return;
					// splice the item out of the array
					var item = this.selected_series.splice(index,1);
					// item is now an array with one element;
					item = item[0];
					// insert it back into the array at the top
					this.selected_series.unshift(item);
				},
			},
			mounted: function(){
				// make sure the titles and details are all in sync
				var series_by_id = [];
				series_pages.forEach(function(e){
					series_by_id[e.ID] = e;
					series_by_id[e.ID].image = images_by_id[e.ID];
				});
				var real_selected_series = [];
				selected_series.forEach(function(e){
					real_selected_series.push(series_by_id[e.ID]);
				});
				Vue.set(this,'selected_series',real_selected_series);
			},
			updated: function() {
			}
		});
		
	</script>
	
	
	<?php
}

function sp_series_group_meta_save($post_id)
{
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	
	// authentication checks

	// make sure data came from our meta box
	if ( ! isset($_POST['series_group_meta_noncename']) || !wp_verify_nonce($_POST['series_group_meta_noncename'],__FILE__)) return $post_id;

	// check user permissions
	if ($_POST['post_type'] == 'sp_series_group')
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

	$current_data = get_post_meta($post_id, 'series_group_data', TRUE);
	$new_data = $_POST['series_group_data'];
	
	sp_sermon_meta_clean($new_data);
	
	delete_post_meta($post_id,'series_group_data');
	if (!empty($new_data))
	{
		sp_log('ADDING NEW SERIES GROUP DATA');
		sp_log($new_data);
		add_post_meta($post_id,'series_group_data',$new_data,TRUE);
	}
	// if ($current_data)
	// {
	// 	sp_log('UPDATING SERIES GROUP DATA');
	// 	sp_log($new_data);
	// 	if (empty($new_data)) delete_post_meta($post_id,'series_group_data');
	// 	else update_post_meta($post_id,'series_group_data',$new_data);
	// }
	// elseif (!is_null($new_data))
	// {
	// 	sp_log('ADDING NEW SERIES GROUP DATA');
	// 	sp_log($new_data);
	// 	add_post_meta($post_id,'series_group_data',$new_data,TRUE);
	// }

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
