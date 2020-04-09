<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class BloomWidget extends WP_Widget
{
	function BloomWidget(){
		$widget_ops = array( 'description' => __( 'Bloom widget, please configure all the settings in Bloom control panel', 'bloom' ) );
		parent::WP_Widget( false, $name = __( 'Bloom', 'bloom' ), $widget_ops );
	}

	/* Displays the Widget in the front-end */
	function widget( $args, $instance ){
		extract($args);

		$title = apply_filters( 'et_bloom_widget_title', empty( $instance['title'] )
			? ''
			: esc_html( $instance['title'] )
		);

		$optin_id = $instance['optin_id'];

		echo $before_widget;

		if ( $title ) {
			echo $before_title . $title . $after_title;
		}

		echo ET_Bloom::display_widget( $optin_id );

		echo $after_widget;
	}

	/* Saves the settings. */
	function update($new_instance, $old_instance){
		$instance = $old_instance;
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		$instance['optin_id'] = sanitize_text_field( $new_instance['optin_id'] );

		return $instance;
	}

	/* Creates the form for the widget in the back-end. */
	function form( $instance ){
		//Defaults
		$instance = wp_parse_args( (array) $instance, array( 'title' => __( 'Subscribe', 'bloom' ), 'optin_id' => 'empty' ) );

		$title = $instance['title'];
		$optin_id_saved = $instance['optin_id'];

		# Title
		printf(
			'<p>
				<label for="%1$s">%2$s: </label>
				<input class="widefat" id="%1$s" name="%4$s" type="text" value="%3$s" />
			</p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title', 'bloom' ),
			esc_attr( $title ),
			esc_attr( $this->get_field_name( 'title' ) )
		);

		$optins_set = ET_Bloom::widget_optins_list();
		$optins_formatted = '';
		foreach ( $optins_set as $optin_id => $name ) {
			$optins_formatted .= sprintf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $optin_id ),
				selected( $optin_id, $optin_id_saved, false ),
				esc_html( $name )
			);
		}

		printf(
			'<p>
				<label for="%1$s">%2$s: </label>
				<select class="widefat" id="%1$s" name="%4$s" type="text">%5$s</select>
			</p>',
			esc_attr( $this->get_field_id( 'optin_id' ) ),
			esc_html__( 'Select Optin', 'bloom' ),
			esc_attr( $title ),
			esc_attr( $this->get_field_name( 'optin_id' ) ),
			$optins_formatted
		);
	}
}