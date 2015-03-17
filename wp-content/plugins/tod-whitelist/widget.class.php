<?php 

class todWhitelistWidget extends WP_Widget {

	function todWhitelistWidget() {
		// Instantiate the parent object
		parent::__construct(
				// Base ID of your widget
				'todWhitelist',
	
				// Widget name will appear in UI
				__('ToD Whitelist', 'todWhitelist_domain'),
	
				// Widget description
				array( 'description' => __( 'Widget that shows the whitelisting-form.', 'todWhitelist_domain' ), )
		);
	}
	
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		// before and after widget arguments are defined by themes
		echo $args['before_widget'];
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
		?>
		<form method="POST" action="<?php echo "http://" . $_SERVER['SERVER_NAME']; ?>/registration">
			<label for="username">Minecraft username*:</label><br/>
				<input type="text" required name="username" value="<?= (isset($_POST['username']) ? $_POST['username'] : ''); ?>"><br/>
			<label for="email">Email*:</label><br/>
				<input type="text" name="email" value="<?= (isset($_POST['email']) ? $_POST['email'] : ''); ?>"><br/>
				<a id="whitelist_email_zebra_link" href="#">Why do you want my email?</a><br/>
			<label for="prevExp">Have you ever been a(optional):</label><br/>
				<input type="checkbox" name="prevExp[]" value="Builder"> Builder<br/>
				<input type="checkbox" name="prevExp[]" value="Staff"> Staff member<br/>
				<input type="checkbox" name="prevExp[]" value="Admin"> Admin<br/>
				<a id="whitelist_why_zebra_link" href="#">Why is this important?</a><br/>
			<label for="description">Other information(optional):</label><br/>
				<textarea name="description" rows="6" cols="30"><?= (isset($_POST['description']) ? $_POST['description'] : ''); ?></textarea>
			<input type="submit" name="whitelistSubmit" value="Apply">
		</form>
		<?php 
		echo $args['after_widget'];
	}

	function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
		return $instance;
	}

	function form( $instance ) {
		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		else {
			$title = __( 'New title', 'todWhitelist_domain' );
		}
		// Widget admin form
		?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
				<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php 
	}
}

function todwhitelist_register_widgets() {
	register_widget( 'todWhitelistWidget' );
}

add_action( 'widgets_init', 'todwhitelist_register_widgets' );
