<?php
// WIDGETS
// ADD A NEW SERIES INFO WIDGET
class SP_SeriesInfoWidget extends WP_Widget
{
	public function __construct()
	{
		$widget_ops = array('classname' => 'SP_SeriesInfoWidget', 'description' => 'Series information as a widget on series pages and sermon pages. Displays nothing otherwise.');
		parent::__construct('SP_SeriesInfoWidget', 'Series Info and Thumbnail', $widget_ops);
	}
	
	
	/**
	 * Outputs the options form on admin
	 *
	 * @param array $instance previously saved values
	 */
	public function form( $instance ) {
		// outputs the options form on admin
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( '', 'text_domain' );
		$show_title = ! empty( $instance['show_title'] ) ? $instance['show_title'] : 0;
		$show_content = ! empty( $instance['show_content'] ) ? $instance['show_content'] : 0;
		
		?>
		<!-- Widget Heading (title) -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Widget Heading:', 'text_domain' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		<br /><small>This will be ignored if the next Show Series Title checkbox is checked.</small>
		</p>
		
		<!-- Display Series Title? -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'show_title' ) ); ?>"><?php esc_attr_e( 'Show Series Title?', 'text_domain' ); ?>
		<input id="<?php echo esc_attr( $this->get_field_id( 'show_title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_title' ) ); ?>" type="checkbox" value="1" <?php if($show_title == 1) echo 'checked="checked"'; ?>>
		</label>
		<br /><small>If this box is checked, the series title will be shown instead of the widget heading.</small>
		</p>

		<!-- Display Series Page Content? -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'show_content' ) ); ?>"><?php esc_attr_e( 'Show Series Page Content?', 'text_domain' ); ?>
		<input id="<?php echo esc_attr( $this->get_field_id( 'show_content' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_content' ) ); ?>" type="checkbox" value="1" <?php if($show_content == 1) echo 'checked="checked"'; ?>>
		</label>
		</p>
		
		<?php 
		
	}


	/**
	 * Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		$instance['show_title'] = ( ! empty( $new_instance['show_title'] ) ) ? $new_instance['show_title'] : 0;
		$instance['show_content'] = ( ! empty( $new_instance['show_content'] ) ) ? $new_instance['show_content'] : 0;
		return $instance;
	}

	
	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget($args, $instance)
	{


		// check to see if this is a sermon or series page.
		// if it is neither, output nothing at all.
		if (! sp_is_sermon() and ! sp_is_series()) return;

		// Widget Code Goes Here
		extract($args, EXTR_SKIP);
		global $post;

		$sermon_words = sp_get_sermon_words();
		$singular = $sermon_words['singular'];
		$plural = $sermon_words['plural'];

		$thumbnail_size = 'sp_thumb';

		echo $before_widget;
				
		// identify the series page ID
		if (sp_is_sermon()) $series_page_id = get_post_meta($post->ID, 'sermon_series', TRUE);
		else $series_page_id = $post->ID;
		
		// get series data
		$series_thumbnail = sp_get_image($series_page_id, $thumbnail_size);
		$series_page = get_post($series_page_id);
		$series_permalink = get_permalink($series_page_id);
		$sermons = sp_get_sermons_by_series($series_page_id);
		
		// compute the sermon stats string
		if (sp_is_sermon())
		{
			if ((count($sermons) - 1) == 0) $count_text = 'are no other ' . $plural;
			elseif ((count($sermons) - 1) == 1) $count_text = 'is one other ' . $singular;
			else $count_text = sprintf('are %d other %s', count($sermons) - 1, $plural);
			$count_text = "There $count_text posted in this series.";
			
			$stats_html = <<<EOF
				<p class="sp_caption">
					This $singular is part of a series called <a href="$series_permalink" class="sp_series_link"><strong>$series_page->post_title</strong></a>. $count_text
				</p>
EOF;
		}
		else
		{
			if ((count($sermons) - 1) == 0) $count_text = 'are no ' . $plural;
			elseif ((count($sermons) - 1) == 1) $count_text = 'is one ' . $singular;
			else $count_text = sprintf('are %d %s', count($sermons) - 1, $plural);
			$count_text = "There $count_text posted in this series.";
			
			$stats_html = <<<EOF
				<p class="sp_caption">
					You are viewing the $singular series called <a href="$series_permalink" class="sp_series_link"><strong>$series_page->post_title</strong></a>. $count_text
				</p>
EOF;
		}
		
		// start showing widget results
		$widget_title = '';
		if ( ! empty($instance['title'])) $widget_title = $instance['title'];
		if ( ! empty( $instance['show_title'] ) && $instance['show_title']==1 ) $widget_title = $series_page->post_title;
		
		if (! empty($widget_title))
		{
			echo $args['before_title'] . apply_filters( 'widget_title', $widget_title ) . $args['after_title'];
		}
		
		// series image
		echo <<<EOF
			<img class="sp_thumb" src="$series_thumbnail[0]" />
EOF;
		
		// show series excerpt?
		if ( ! empty( $instance['show_content'] ) && $instance['show_content']==1 )
		{
			if (! empty($series_page->post_excerpt)) echo $series_page->post_excerpt;
			else echo '<p class="sp_caption">' . substr(strip_tags($series_page->post_content), 0, 100) . '</p>';
		}

		// series stats
		echo $stats_html;

		echo $after_widget;
	}
}
add_action( 'widgets_init', function() {return register_widget("SP_SeriesInfoWidget"); });


// ADD A NEW LATEST SERMON WIDGET
class SP_LatestSermonWidget extends WP_Widget
{
	public function __construct()
	{
		$widget_ops = array('classname' => 'SP_LatestSermonWidget featured-content', 'description' => 'Widget to display the most recent sermon with the link and graphic of its series.');
		parent::__construct('SP_LatestSermonWidget', 'Latest Sermon', $widget_ops);
	}

	/* Outputs the options form on admin
	 *
	 * @param array $instance previously saved values
	 */
	public function form( $instance ) {
		$sermon_words = sp_get_sermon_words();
		$singular = $sermon_words['singular'];
		$plural = $sermon_words['plural'];
		
		// outputs the options form on admin
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'Most Recent ' . ucfirst($singular), 'text_domain' );
		$show_image = ! empty( $instance['show_image'] ) ? $instance['show_image'] : 0;
		$show_text = ! empty( $instance['show_text'] ) ? $instance['show_text'] : 0;
		
		?>
		<!-- Widget Heading (title) -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Widget Title:', 'text_domain' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		
		<!-- Display Series Image? -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'show_image' ) ); ?>">
			<input id="<?php echo esc_attr( $this->get_field_id( 'show_image' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_image' ) ); ?>" type="checkbox" value="1" <?php if($show_image == 1) echo 'checked="checked"'; ?>>
			<?php esc_attr_e( 'Show Series Image', 'text_domain' ); ?>
		</label>
	</p>

		<!-- Display Text Links? -->
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'show_text' ) ); ?>">
			<input id="<?php echo esc_attr( $this->get_field_id( 'show_text' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_text' ) ); ?>" type="checkbox" value="1" <?php if($show_text == 1) echo 'checked="checked"'; ?>>
			<?php esc_attr_e( 'Show Text Link', 'text_domain' ); ?>
		</label>
		</p>
		
		<?php 
		
	}

	/* Processing widget options on save
	 *
	 * @param array $new_instance The new options
	 * @param array $old_instance The previous options
	 */
	public function update( $new_instance, $old_instance ) {
		// processes widget options to be saved
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : esc_html__( 'Most Recent ' . ucfirst($singular), 'text_domain' );
		$instance['show_image'] = ( ! empty( $new_instance['show_image'] ) ) ? $new_instance['show_image'] : 0;
		$instance['show_text'] = ( ! empty( $new_instance['show_text'] ) ) ? $new_instance['show_text'] : 0;
		return $instance;
	}

	public function widget($args, $instance)
	{

		extract($args, EXTR_SKIP);
		global $post;

		$sermon_words = sp_get_sermon_words();
		$singular = $sermon_words['singular'];
		$plural = $sermon_words['plural'];
		$thumbnail_size = 'sp_thumb';
		
		echo $before_widget;
		
		if (! empty($instance['title']))
		{
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		
		sp_most_recent_sermon($thumbnail_size, '', '', $instance['show_image'], $instance['show_text']);

		echo $after_widget;
	}
}
add_action( 'widgets_init', function() {return register_widget("SP_LatestSermonWidget"); });
	
?>