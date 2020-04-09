<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class MonarchWidget extends WP_Widget
{
	function MonarchWidget(){
		$widget_ops = array( 'description' => __( 'Monarch plugin widget, please configure all the settings in Monarch control panel', 'Monarch' ) );
		parent::WP_Widget( false, $name = __( 'Monarch Follow', 'Monarch' ), $widget_ops );
	}

	/* Displays the Widget in the front-end */
	function widget( $args, $instance ){
		extract($args);

		$title = apply_filters( 'et_social_widget_title', empty( $instance['title'] )
			? esc_html__( 'Follow Us', 'Monarch' )
			: esc_html( $instance['title'] )
		);

		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		echo ET_Monarch::display_widget();

		echo $after_widget;
	}

	/* Saves the settings. */
	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );

		return $instance;
	}

	/* Creates the form for the widget in the back-end. */
	function form( $instance ){
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'title' => __( 'Follow Us', 'Monarch' ) ) );

		$title = $instance['title'];

		# Title
		printf(
			'<p>
				<label for="%1$s">%2$s: </label>
				<input class="widefat" id="%1$s" name="%4$s" type="text" value="%3$s" />
			</p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title', 'Monarch' ),
			esc_attr( $title ),
			esc_attr( $this->get_field_name( 'title' ) )
		);
	}
}