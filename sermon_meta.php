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

	// add a meta box for each of the wordpress page types: posts and pages
	foreach (array('sp_sermon') as $type)
	{
		add_meta_box('sp_sermon_series_meta', sprintf('%s Series', ucwords($singular)), 'sp_sermon_series_meta_setup', $type, 'side', 'high');
		add_meta_box('sp_sermon_media_meta', sprintf('%s Media', ucwords($singular)), 'sp_sermon_media_meta_setup', $type, 'side', 'high');
	}

	// add podcasting meta box to sermon pages if podcasting plugin is installed;
	if (is_plugin_active('podcasting/podcasting.php') || defined('PODCASTING_VERSION'))
	{
		global $podcasting_metabox;
		add_meta_box('podcasting', 'Podcasting', array($podcasting_metabox, 'editForm'), 'sp_sermon', 'normal');
	}

	// add a callback function to save any data a user enters in
	add_action('save_post','sp_sermon_meta_save');
	
	add_action('admin_footer', 'sp_upload_button_handler');
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
	$upload_mb = min($max_upload, $max_post);

	global $post;
	$previous_uploads = array();
	if ($post->ID)
	{
		$args = array(
			'post_parent' => $post->ID,
			'post_type' => 'attachment',
			'post_status' => 'inherit'
		);
		$previous_uploads = get_children($args);
	}
	$upload_purposes = array('video','audio','notes','manuscript','slides', 'other');
	$html = '';
	$html .= '<p class="description">Previously uploaded files</p>';

	if (! $previous_uploads) $html .= "<p>NONE</p>";
	else
	{
		$html .= '<div class="sp_previous_uploads">';
		$html .= '<table class="sermon-media-table"><tr><th>DEL?</th><th>FILE</th></tr>';

		foreach ($previous_uploads as $pu)
		{
			$attachment_name = basename (get_attached_file($pu->ID, TRUE));

			if (strlen($attachment_name) <= 30) $attachment_shortname = $attachment_name;
			else $attachment_shortname = substr($attachment_name, 0, 20) . '...' . substr($attachment_name, -7);
			$html .= '<tr>';
			$html .= '<td><input name="sp_media_delete[' . $pu->ID . ']" value="yes" type="checkbox" /></td>';
			$html .= '<td><span title="'.$attachment_name.'">' . $attachment_shortname . '</span></td>';
			$html .= '</tr>';
		}
		$html .= '</table>';
		$html .= '</div>';
	}
	$html .= '<hr />';
	$html .= '<p>You may upload new audio, video, or pdf files up to '.$upload_mb.'MB in size.</p>';
	$html .= '<select name="sp_media_purpose">';
	$html .= '<option value="other">-- Describe Your Upload</option>';
	foreach ($upload_purposes as $purpose)
	{
		$html .= '<option value="'.$purpose.'">'.$purpose.'</option>';
	}
	$html .= '</select>';
	$html .= '<input type="file" id="sp_sermon_media" name="sp_sermon_media" value="" size="25">';

	// check to see if archive.org uploading is enabled
	$options = get_option('sp_options');
	if($options['send_to_archive'])
	{
		$html .= "<p>Media files uploaded through here will be sent to the <span class=\"collection_name\">" . $options['archive_collection'] . "</span> collection at the Internet Archive.";
	}

	//check to see if this media has been sent to archive.org already
	$identifier = get_post_meta($post->ID, 'sp_archive_identifier', True);
	if (! empty($identifier) )
	{
		$html .= "<p>Files from this post can be accessed here: <a href=\"http://archive.org/details/$identifier\">$identifier</a>";
	}
	
	$html .= '<br /><button class="button button-primary" id="sp-button-upload">Send to Archive.org</button>';
	echo $html;
}

function sp_upload_button_handler()
{
	?>
	
	<script type="text/javascript">
	// sp_upload_button_handler
	
	jQuery(document).ready(function($){
		var data = {
			'action': 'sp_upload',
			'post_id': <?php echo get_the_ID(); ?>,
			'whatever': 1234
		};
		
		$('#sp-button-upload').click(function(e){
			
			alert
			e.preventDefault();
			console.log('button clicked');
			
			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			$.post(ajaxurl, data, function(response) {
				console.log(response);
				alert('Got this from the server: ' + response);
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

	// SERMON SERIES METADATA
	// $current_data = get_post_meta($post_id, 'sermon_series', TRUE);
	$new_data = $_POST['sermon_series'];
	// sp_sermon_meta_clean($new_data);

	if (-1 == $new_data) delete_post_meta($post_id, 'sermon_series');
	elseif (! update_post_meta($post_id,'sermon_series',$new_data))
		add_post_meta($post_id,'sermon_series',$new_data,TRUE);


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

			// Use the WordPress API to upload the file
			$upload = wp_handle_upload($_FILES['sp_sermon_media'], array('test_form'=>false));

			if(isset($upload['error']) && $upload['error'] != 0)
			{
				wp_die('There was an error uploading your file. The error is: ' . $upload['error']);
			}
			else
			{
				// the file was uploaded successfully
				// queue the archive.org upload if needed
				$options = get_option('sp_options');
				if (! empty($options['archive_upload']) && $options['archive_upload'] == '1') sp_queue_archive_upload($post->ID, $upload['file']);


				// add an attachment
				$attachment = array(
				'guid'           => $upload['url'],
				'post_mime_type' => $upload['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $upload['file'] ) ),
				'post_content'   => '',
				'post_status'    => 'inherit'
				);
				$attachment_id = wp_insert_attachment($attachment, $upload['file'], $post_id);

				// Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// Generate the metadata for the attachment, and update the database record.
				$attach_data = wp_generate_attachment_metadata( $attachment_id, $filename );
				wp_update_attachment_metadata( $attachment_id, $attach_data );

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
			wp_die("The file type that you've uploaded (". $uploaded_type .") is not allowed as a media/PDF file.");
		} // end if/else
	} // end if
	return $post_id;
}
function sp_allow_file_uploads() {
    echo ' enctype="multipart/form-data"';
} // end update_edit_form
add_action('post_edit_form_tag', 'sp_allow_file_uploads');

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
