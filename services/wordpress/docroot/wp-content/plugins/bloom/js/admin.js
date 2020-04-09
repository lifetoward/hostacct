(function($){
	$( document ).ready( function() {
		var url = window.location.href,
			tab_link = url.split( '#tab_' )[1],
			premade_grid_cache = '';

		//Set the current tab to home by default
		if ( typeof tab_link === 'undefined' ) {
			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_home', 'header' );
			$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_hidden_nav' );
		} else {
			var link_to_highlight = $( '#toplevel_page_et_bloom_options' ).find('a[href$="#tab_' + tab_link + '"]');
			window.et_dashboard_set_current_tab( tab_link, 'header' );

			$( '#toplevel_page_et_bloom_options ul li' ).removeClass( 'current' );

			if ( link_to_highlight.length ) {
				link_to_highlight.parent().addClass( 'current' );
			} else {
				$( '#toplevel_page_et_bloom_options .wp-first-item' ).addClass( 'current' );
			}

			if ( 'et_dashboard_tab_content_header_stats' === tab_link ) {
				refresh_stats_tab( false );
			}
		}

		/** Handle clicks in the WP navigation menu:
		 * 1) Open appropriate tab in dashboard
		 * 2) Highlihgt an appropriate link in the WP menu
		 */
		$( 'body' ).on( 'click', '#toplevel_page_et_bloom_options li a', function() {
			var this_link = $( this ),
				open_link = this_link.attr( 'href' ).split( '#tab_' )[1];
			if ( typeof open_link !== 'undefined' ) {
				window.et_dashboard_set_current_tab( open_link, 'header' );
				if ( 'et_dashboard_tab_content_header_stats' === open_link ) {
					refresh_stats_tab( false );
				}
			} else {
				window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_home', 'header' );
				$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_hidden_nav' );
			}

			$( '#toplevel_page_et_bloom_options ul li' ).removeClass( 'current' );
			this_link.parent().addClass( 'current' );

			return false;
		});

		if ( 'et_dashboard_tab_content_header_home' === tab_link || 'et_dashboard_tab_content_header_importexport' === tab_link || 'et_dashboard_tab_content_header_accounts' === tab_link || 'et_dashboard_tab_content_header_stats' === tab_link ) {
			$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_hidden_nav' );
		}

		$( 'body' ).on( 'click', '#et_dashboard_header ul li a', function() {
			var tab_link = $( this) .attr( 'href' ).split( '#tab_' )[1],
				link_to_highlight = $( '#toplevel_page_et_bloom_options' ).find('a[href$="#tab_' + tab_link + '"]');

			$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_hidden_nav' );

			//Highlight appropriate menu link in WP menu
			$( '#toplevel_page_et_bloom_options ul li' ).removeClass( 'current' );
			if ( link_to_highlight.length ) {
				link_to_highlight.parent().addClass( 'current' );
			} else {
				$( '#toplevel_page_et_bloom_options .wp-first-item' ).addClass( 'current' );
			}
		});

		$( 'body' ).on( 'click', '#et_dashboard_tab_content_header_home', function() {
			reset_home_tab();
		});

		$( 'body' ).on( 'click', '.et_dashboard_save_changes button', function() {
			var $provider = $( '.et_dashboard_select_provider select' ).val(),
				$list = $( '.et_dashboard_select_list select' ).val();

			if ( 'empty' == $provider || ( 'custom_html' !== $provider && 'empty' == $list ) ) {
				window.et_dashboard_generate_warning( bloom_settings.no_account_text, '#tab_et_dashboard_tab_content_optin_setup', bloom_settings.add_account_button, bloom_settings.save_inactive_button, '#', 'et_bloom_save_inactive' );
			} else {
				bloom_dashboard_save( $( this ) );
			}

			return false;
		});

		$( 'body' ).on( 'click', '.et_bloom_save_inactive', function() {
			$( '#et_dashboard_optin_status' ).val( 'inactive' );
			bloom_dashboard_save( $( '.et_dashboard_save_changes button' ) );
			$( '.et_dashboard_warning' ).remove();

			return false;
		});

		$( 'body' ).on( 'click', '.et_dashboard_next_design button', function() {
			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_optin_design', 'side' );
			$( 'html, body' ).animate( { scrollTop :  0 }, 400 );

			return false;
		});

		$( 'body' ).on( 'click', '.et_bloom_open_premade', function() {
			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_optin_premade', 'side' );
			$( '#et_dashboard_tab_content_optin_design' ).addClass( 'current' );

			if ( '' == premade_grid_cache ) {
				$.ajax({
					type: 'POST',
					url: bloom_settings.ajaxurl,
					data: {
						action : 'bloom_generate_premade_grid',
						bloom_premade_nonce : bloom_settings.bloom_premade_nonce
					},
					beforeSend: function( data ) {
						$( '.et_bloom_premade_spinner' ).addClass( 'et_dashboard_spinner_visible' );
					},
					success: function( data ) {
						premade_grid_cache = data;
						$( '.et_bloom_premade_grid' ).replaceWith( premade_grid_cache );
					}
				});
			} else {
				$( '.et_bloom_premade_grid' ).replaceWith( premade_grid_cache );
			}
		});

		$( 'body' ).on( 'click', '.et_dashboard_next_customize button', function() {

			$( '.et_dashboard_next_design button' ).removeClass( 'et_bloom_open_premade' );
			$( '.et_dashboard_tab_content_side_design a' ).removeClass( 'et_bloom_open_premade' );

			var selected_layout = JSON.stringify({ 'id' : $( this ).data( 'selected_layout' ) });

			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				dataType: 'json',
				data: {
					action : 'bloom_get_premade_values',
					bloom_premade_nonce : bloom_settings.bloom_premade_nonce,
					premade_data_array : selected_layout
				},
				success: function( data ) {
					if ( $.isPlainObject( data ) ) {
						$( data ).each( function( i, val ) {
							$.each( val, function( optin_name, optin_value ) {
								switch( optin_name ) {
									case 'et_dashboard_optin_title' :
									case 'et_dashboard_optin_message' :
									case 'et_dashboard_footer_text' :
									case 'et_dashboard_success_text' :
										$( '.' + optin_name ).text( optin_value );
										break;

									case 'et_dashboard_border_orientation' :
									case 'et_dashboard_image_orientation' :
									case 'et_dashboard_image_orientation_widget' :
									case 'et_dashboard_header_font' :
									case 'et_dashboard_body_font' :
									case 'et_dashboard_text_color' :
									case 'et_dashboard_corner_style' :
									case 'et_dashboard_form_orientation' :
									case 'et_dashboard_name_fields' :
									case 'et_dashboard_field_orientation' :
									case 'et_dashboard_field_corners' :
									case 'et_dashboard_form_text_color' :
									case 'et_dashboard_field_button_text_color' :
										$( '.' + optin_name + ' select' ).val( optin_value );

										if ( 'no_image' != optin_value && 'et_dashboard_image_orientation' == optin_name ) {
											$( '.et_dashboard_upload_image' ).parent().parent().removeClass( 'et_dashboard_hidden_option' );
										}

										if ( 'no_border' != optin_value && 'et_dashboard_border_orientation' == optin_name ) {
											$( '.et_dashboard_border_color' ).removeClass( 'et_dashboard_hidden_option' );
											$( '.et_dashboard_border_style' ).removeClass( 'et_dashboard_hidden_option' );
										}

										if ( 'no_name' != optin_value && 'et_dashboard_name_fields' == optin_name ) {
											$( '.et_dashboard_name_checkbox input' ).prop( 'checked', true );

											if ( $( '.et_dashboard_name_checkbox' ).hasClass( 'et_dashboard_visible_option' ) || ( 'single_name' == optin_value ) ) {
												$( '.et_dashboard_name_text_single' ).parent().removeClass( 'et_dashboard_hidden_option' );
											}

											if ( 'first_last_name' == optin_value ) {
												$( '.et_dashboard_last_name_text' ).parent().removeClass( 'et_dashboard_hidden_option' );
												$( '.et_dashboard_name_text' ).parent().removeClass( 'et_dashboard_hidden_option' );
											}
										}

										break;

									case 'et_dashboard_name_text' :
									case 'et_dashboard_last_name_text' :
									case 'et_dashboard_email_text' :
									case 'et_dashboard_button_text' :
										$( '.' + optin_name ).val( optin_value );
										break;

									case 'et_dashboard_upload_image' :
										$( '.' + optin_name ).find( '.et-dashboard-upload-field' ).val( optin_value );
										et_dashboard_generate_preview_image( $( '.' + optin_name ).find( '.et-dashboard-upload-field' ).siblings( '.et-dashboard-upload-button' ) );
										break;

									case 'et_dashboard_optin_bg' :
									case 'et_dashboard_form_bg_color' :
									case 'et_dashboard_form_button_color' :
									case 'et_dashboard_border_color' :
										$( '.' + optin_name ).find( '.et-dashboard-color-picker' ).wpColorPicker( 'color', optin_value );
										break;

									case 'et_dashboard_border_style' :
									case 'et_dashboard_optin_edge' :
										var tabs = $( '.' + optin_name ).find( 'div.et_dashboard_single_selectable' ),
											inputs = $( '.' + optin_name ).find( 'input' );

										tabs.removeClass( 'et_dashboard_selected' );
										inputs.prop( 'checked', false );
										var selected = tabs.find( 'input[value="' + optin_value + '"]' );
										selected.parent().toggleClass( 'et_dashboard_selected' );
										selected.prop( 'checked', true );
										break;
								}
							});
						});
					}

					window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_optin_design', 'side' );
					$( 'html, body' ).animate( { scrollTop :  0 }, 400 );
				}
			});

			return false;
		});

		$( 'body' ).on( 'click', '.et_dashboard_next_display button', function() {
			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_optin_display', 'side' );
			$( 'html, body' ).animate( { scrollTop :  0 }, 400 );

			return false;
		});

		$( 'body' ).on( 'click', '.et_dashboard_new_optin button', function() {
			$( '.et_dashboard_optin_select' ).addClass( 'et_dashboard_visible' );
			$( this ).addClass( 'clicked_button' );
		});

		$( 'body' ).on( 'click', '.et_dashboard_optin_add', function() {
			$( '.et_dashboard_new_optin button' ).addClass( 'et_bloom_loading' );
			reset_options( $( this ), '', true, false, '' );
		});

		$( 'body' ).on( 'click', '.et_dashboard_new_account_row button', function() {
			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_edit_account', 'header' );
			display_edit_account_tab( false, '', '' );
		});

		$( 'body' ).on( 'click', '.et_dashboard_icon_edit_account', function() {
			var this_el = $( this );

			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_edit_account', 'header' );
			display_edit_account_tab( true, this_el.data( 'service' ), this_el.data( 'account_name' ) );
		});

		$( 'body' ).on( 'click', '.et_dashboard_icon_edit', function() {
			var $this_el = $( this ),
				optin_id = $this_el.parent().parent().data( 'optin_id' ),
				parent_id = typeof $this_el.data( 'parent_id' ) !== 'undefined' ? $this_el.data( 'parent_id' ) : '',
				is_child = '' != parent_id ? true : false;

			$this_el.find( '.spinner' ).addClass( 'et_dashboard_spinner_visible' );

			reset_options( $this_el, optin_id, false, is_child, parent_id );
		});

		$( 'body' ).on( 'click', '.et_dashboard_icon_delete:not(.clicked_button)', function() {
			var this_el = $( this );

			$( '.et_dashboard_icon_delete' ).removeClass( 'clicked_button' );

			this_el.addClass( 'clicked_button' );
			$( '.et_dashboard_confirmation' ).hide();

			this_el.find( '.et_dashboard_confirmation' ).fadeToggle();
		});

		$( 'body' ).on( 'click', '.et_bloom_clear_stats', function() {
			var this_el = $( this );

			this_el.parent().find( '.et_dashboard_confirmation' ).fadeToggle();
		});

		$( 'body' ).on( 'click', '.et_dashboard_confirm_stats', function() {
			$( this ).parent().hide();

			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_clear_stats',
					bloom_stats_nonce : bloom_settings.bloom_stats,
				},
				beforeSend: function( data ){
					$( '.et_bloom_clear_stats' ).addClass( 'et_bloom_loading' );
				},
				success: function( data ){
					$( '.et_bloom_clear_stats' ).removeClass( 'et_bloom_loading' );
					refresh_stats_tab( true );
				}
			});
		});

		$( 'body' ).on( 'click', '.et_bloom_refresh_stats', function() {
			var $this = $(this),
				button_width = $this.width();

			$this.width( button_width ).addClass( 'et_bloom_loading' );
			refresh_stats_tab( true );
		});

		$( 'body' ).on( 'click', '.et_dashboard_confirm_delete', function() {

			var this_el = $( this ),
				optin_id = this_el.data( 'optin_id' ),
				need_refresh = false,
				table_row = this_el.parent().parent().parent().parent(),
				parent_id = typeof this_el.data( 'parent_id' ) !== 'undefined' ? this_el.data( 'parent_id' ) : '',
				is_account = true == this_el.data( 'remove_account' ) ? true : false,
				service = table_row.data( 'service' );

			//if we're about to remove the last item in table, then we need to refresh page after removal.
			if ( 1 === table_row.parent().find( '.et_dashboard_optins_item.et_dashboard_parent_item' ).length || true === is_account || '' != parent_id ) {
				need_refresh = true;
			} else {
				table_row.remove();
			}

			remove_optin( optin_id, need_refresh, is_account, service, parent_id );
		});

		$( 'body' ).on( 'click', '.et_dashboard_cancel_delete', function() {
			$( this ).parent().hide();
			$( this ).parent().parent().removeClass( 'clicked_button' );
		});

		$( 'body' ).on( 'click', '.et_dashboard_optin_select .et_dashboard_close_button', function() {
			var this_select = $( this ).parent();

			this_select.removeClass( 'et_dashboard_visible' ).addClass( 'et_dashboard_hidden' );
			this_select.parent().find( '.clicked_button').removeClass( 'clicked_button' );
		});

		$( 'body' ).on( 'click', '.clicked_button', function() {
			return false;
		});


		$( 'body' ).on( 'click', '.et_dashboard_icon_duplicate:not(.clicked_button)', function() {
			var this_el = $( this ),
				parent = this_el.parent().parent();

			$( '.et_dashboard_icon_duplicate' ).removeClass( 'clicked_button' );
			this_el.addClass( 'clicked_button' );

			var select_type_box = '<div class="et_dashboard_row et_dashboard_optin_select"><h3>' + bloom_settings.optin_type_title + '</h3><span class="et_dashboard_icon et_dashboard_close_button"></span><ul data-optin_id="' + parent.data( 'optin_id' ) + '"><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_popup" data-type="pop_up"><h6>pop up</h6><div class="optin_select_grey"><div class="optin_select_blue"></div></div></li><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_flyin" data-type="flyin"><h6>fly in</h6><div class="optin_select_grey"></div><div class="optin_select_blue"></div></li><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_below" data-type="below_post"><h6>below post</h6><div class="optin_select_grey"></div><div class="optin_select_blue"></div></li><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_inline" data-type="inline"><h6>inline</h6><div class="optin_select_grey"></div><div class="optin_select_blue"></div><div class="optin_select_grey"></div></li><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_locked" data-type="locked"><h6>locked content</h6><div class="optin_select_grey"></div><div class="optin_select_blue"></div><div class="optin_select_grey"></div></li><li class="et_dashboard_optin_type et_dashboard_optin_duplicate et_dashboard_optin_type_widget" data-type="widget"><h6>widget</h6><div class="optin_select_grey"></div><div class="optin_select_blue"></div><div class="optin_select_grey_small"></div><div class="optin_select_grey_small last"></div></li></ul></div>';

			$( '.et_dashboard_optins_item .et_dashboard_optin_select' ).remove();

			parent.append( select_type_box );

			setTimeout( function() {
				$( '.et_dashboard_optins_item .et_dashboard_optin_select').addClass( 'et_dashboard_visible' );
			}, 100 );
		});

		$( 'body' ).on( 'click', '.et_dashboard_optins_item .et_dashboard_optin_select .et_dashboard_close_button', function() {
			setTimeout( function() {
				$( '.et_dashboard_optins_item .et_dashboard_optin_select' ).remove();
			}, 800 );

			$( '.et_dashboard_icon_duplicate' ).removeClass( 'clicked_button' );
		});

		$( 'body' ).on( 'click', '.et_dashboard_optin_duplicate', function() {
			var this_el = $( this ),
				form_id = this_el.parent().data( 'optin_id' ),
				form_type = this_el.data( 'type' );

			$( '.et_dashboard_optin_select' ).removeClass( 'et_dashboard_visible' ).addClass( 'et_dashboard_hidden' );
			$( '.clicked_button').removeClass( 'clicked_button' );

			duplicate_optin( form_id, form_type );
		});

		$( 'body' ).on( 'click', '.et_dashboard_toggle_status', function() {
			var this_el = $( this ),
				optin_id = this_el.parent().parent().data( 'optin_id' ),
				new_status = this_el.data( 'toggle_to' );
			if ( this_el.hasClass( 'et_bloom_no_account' ) && 'active' == new_status ) {
				window.et_dashboard_generate_warning( bloom_settings.cannot_activate_text, '#', '', '', '', '' );
			} else {
				$.ajax({
					type: 'POST',
					url: bloom_settings.ajaxurl,
					data: {
						action : 'bloom_toggle_optin_status',
						toggle_status_nonce : bloom_settings.toggle_status,
						status_optin_id : optin_id,
						status_new : new_status
					},
					beforeSend: function() {
						this_el.find( '.spinner' ).addClass( 'et_dashboard_spinner_visible' );
					},
					success: function( data ){
						reset_home_tab();
					}
				});
			}
		});

		$( 'body' ).on( 'click', '.et_dashboard_icon_shortcode, .et_dashboard_next_shortcode button', function() {
			var this_el = $( this ),
				optin_id = typeof this_el.data( 'optin_id' ) !== 'undefined' ? this_el.data( 'optin_id' ) : this_el.parent().parent().data( 'optin_id' ),
				message_text = '',
				shortcode_text = '',
				shortcode_type = this_el.data( 'type' );

			if ( 'locked' == shortcode_type ) {
				shortcode_text = '[et_bloom_locked optin_id="' + optin_id + '"] content [/et_bloom_locked]';
			} else {
				shortcode_text = '[et_bloom_inline optin_id="' + optin_id + '"]';
			}

			message_text = bloom_settings.shortcode_text + shortcode_text;

			window.et_dashboard_generate_warning( message_text, '#', '', '', '', '' );

			return false;
		});

		//disable links on side nav to avoid confusion during page refresh
		$( 'body' ).on( 'click', '.et_dashboard_optin_nav', function(){
			return false;
		} );

		$( 'body' ).on( 'change', '.et_dashboard_select_account select', function() {
			var this_el = $( this );
				service = this_el.data( 'service' ),
				account_name = this_el.val();
			if ( 'add_new' == account_name ) {
				display_actual_accounts( service, true, '' );
			} else {
				if ( 'empty' != account_name ) {
					display_actual_lists( account_name, service );
				}
			}
		});


		$( 'body' ).on( 'change', '.et_dashboard_select_provider select', function() {
			var selected_provider = $( '.et_dashboard_select_provider select' ).val(),
				selected_account = 'empty';

			if ( 'empty' == selected_provider || 'custom_html' == selected_provider ) {
				$( '.et_dashboard_select_account' ).css( { 'display' : 'none' } );
			} else {
				display_actual_accounts( selected_provider, false, '' );
				selected_account = $( '.et_dashboard_select_account select' ).val();
			}

		});

		$( 'body' ).on( 'click', '.et_dashboard_new_account .authorize_service', function(){
			$( '.account_settings_fields' ).addClass( 'et_visible_settings' );
			$( this ).text( 'Authorize' );
			$( this ).addClass('clicked_button');
			return false;
		});

		$( 'body' ).on( 'click', '.authorize_service.clicked_button, .authorize_service.new_account_tab, .et_dashboard_icon_update_lists', function(){
			var this_el = $( this ),
				on_form = this_el.hasClass( 'new_account_tab' ) ? false : true,
				account_name = typeof this_el.data( 'account_name' ) !== 'undefined' ? this_el.data( 'account_name' ) : '',
				account_exists = this_el.hasClass( 'et_dashboard_icon_update_lists' ) ? true : false;

			authorize_network( this_el.data( 'service' ), this_el.parent(), on_form, account_name, account_exists );
		});

		$( 'body' ).on( 'change', '.et_dashboard_select_provider_new select', function() {
			var selected = $( this ).val();
				display_new_account_form( selected );
		});

		$( 'body' ).on( 'click', '.save_account_tab', function(){
			var fields_container = $( '.et_dashboard_new_account_fields' ),
				service = $( '.et_dashboard_tab_content_header_edit_account .et_dashboard_select_provider_new select' ).val();

			if ( fields_container.hasClass( 'et_dashboard_edit_account_fields' ) || 'empty' == service ) {
				save_account_tab( '', '', true );
			} else {
				var account_name = fields_container.find( '#name_' + service ).val();

				if ( '' == account_name ) {
					window.et_dashboard_generate_warning( bloom_settings.no_account_name_text, '#', '', '', '', '' );
				} else {
					save_account_tab( service, account_name, false );
				}
			}

			return false;
		});

		$( 'body' ).on( 'click', '.et_dashboard_icon_abtest:not(.active_child_optins)', function(){
			var table_row = $( this ).parent().parent();
			$( 'ul.et_dashboard_temp_row' ).remove();
			$( '.et_dashboard_icon_abtest' ).removeClass( 'clicked_button' );
			$( this ).addClass( 'clicked_button' );

			table_row.append('<ul class="et_dashboard_temp_row"><li class="et_dashboard_add_variant et_dashboard_optins_item"><a href="#" class="et_dashboard_add_var_button">Add variant</a></li></ul>');
		});

		$( 'body' ).on( 'click', '.et_dashboard_add_var_button', function(){
			var optin_id = $( this ).parent().parent().parent().data( 'optin_id' );

			add_variant( optin_id );
			return false;
		});

		$( 'body' ).on( 'click', '.child_buttons_right a', function(){
			var button = $( this ),
				parent_id = button.data( 'parent_id' ),
				action = '';

			if ( button.hasClass( 'et_dashboard_pause_test' ) ) {
				button.removeClass( 'et_dashboard_pause_test' );
				action = 'pause';
			} else if ( button.hasClass( 'et_dashboard_start_test' ) ) {
				button.addClass( 'et_dashboard_pause_test' );
				action = 'start';
			} else {
				action = 'end';
			}

			ab_test_controls( parent_id, action, button );
			return false;
		});

		//stats graph
		$( 'ul.et_bloom_graph' ).each( function() {
			resize ( $( this ) );
		});

		$( 'body' ).on( 'mouseenter', '.et_bloom_graph .et_bloom_graph_bar', function(){
			var $this_el = $( this ),
				value = $this_el.attr( 'value' );

			$( '<div class="et_bloom_tooltip"><strong>' + value + '</strong></div>' ).appendTo( $this_el );

		}).on( 'mouseleave', '.et_bloom_graph .et_bloom_graph_bar', function(){
			$( this ).find( 'div.et_bloom_tooltip' ).remove();
		});

		$( 'body' ).on( 'click', '.et_bloom_graph_button', function(){
			var this_el = $( this ),
				period = this_el.data( 'period' ),
				list_id = $( '.et_bloom_graph_select_list' ).val();

			if ( ! this_el.hasClass( 'et_bloom_active_button' ) ) {
				$( '.et_bloom_graph_button' ).removeClass( 'et_bloom_active_button' );
				this_el.addClass( 'et_bloom_active_button' );

				switch_graph( period, list_id, true );
			}

			return false;
		});

		$( 'body' ).on( 'change', '.et_bloom_graph_select_list', function(){
			var this_el = $( this ),
				period = $( 'a.et_bloom_graph_button.et_bloom_active_button' ).data( 'period' ),
				list_id = this_el.val();

				switch_graph( period, list_id, false );
		});

		$( 'body' ).on( 'click', '.et_dashboard_sort_button:not(.active_sorting)', function(){
			var this_el = $( this ),
				orderby = this_el.data( 'order_by' ),
				table = this_el.parent().data( 'table' );

			if ( 'lists' == table ) {
				$table_class = '.et_dashboard_lists_stats .et_dashboard_table_contents';
			}

			if ( 'optins' == table ) {
				$table_class = '.et_dashboard_optins_all_table .et_dashboard_table_contents';
			}

			refresh_stats_table( $table_class, orderby, table );

			this_el.parent().find( '.et_dashboard_sort_button' ).removeClass( 'active_sorting' );

			this_el.addClass( 'active_sorting' );
		});

		$( 'body' ).on( 'click', 'a#et_dashboard_tab_content_header_stats:not(.current)', function(){
			refresh_stats_tab( false );
		});

		$( 'body' ).on( 'click', '.end_test_table .et_dashboard_content_row', function(){
			var this_el = $( this ),
				winner_id = this_el.data( 'optin_id' ),
				parent_id = this_el.parent().data( 'parent_id' ),
				optins_set = this_el.parent().data( 'optins_set' );

			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_pick_winner_optin',
					remove_option_nonce : bloom_settings.remove_option,
					winner_id : winner_id,
					optins_set : optins_set,
					parent_id : parent_id
				},
				success: function( data ){
					reset_home_tab();
					$( '.et_dashboard_end_test' ).remove();
				}
			});
		});

		$( 'body' ).on( 'click', '.display_on_section .display_on_checkboxes_everything label', function() {
			check_display_options( $( this ).parent(), false );
		});

		$( 'body' ).on( 'click', '.et_bloom_premade_item', function() {
			var this_item = $( this );
				$( '.et_bloom_premade_item' ).removeClass( 'et_bloom_layout_selected' );
				this_item.addClass( 'et_bloom_layout_selected' );

			$( '.et_dashboard_next_customize button' ).data( 'selected_layout', this_item.data( 'layout' ) );
		});

		$( 'body' ).on( 'click', '.et_dashboard_preview button', function() {
			if ( ! $( this ).hasClass( 'bloom_preview_opened' ) ) {
				tinyMCE.triggerSave();
				var options_fromform = $( '.et_bloom #et_dashboard_options' ).serialize();
				$( this ).addClass( 'bloom_preview_opened' );
				$.ajax({
					type: 'POST',
					url: dashboardSettings.ajaxurl,
					dataType: 'json',
					data: {
						action : 'bloom_display_preview',
						preview_options : options_fromform,
						bloom_preview_nonce : bloom_settings.preview_nonce
					},
					success: function( data ){
						var $head = $( 'head' );
						$( '#wpwrap' ).append( data.popup_code );
						define_popup_position( $( '.et_bloom_preview_popup' ), true );
						display_image( $('.et_bloom_preview_popup') );
						$head.append( data.popup_css );

						$( data.fonts ).each( function( i, font_name ) {
							var font_name_converted = font_name.replace(/ /g,'+');

							if ( $head.find( 'link#' + font_name_converted ).length ) return;

							$head.append( '<link id="' + font_name_converted + '" href="http://fonts.googleapis.com/css?family=' + font_name_converted + '" rel="stylesheet" type="text/css" />' );
						});

						$( 'body' ).addClass( 'et_bloom_popup_active' );

						$( '.et_bloom_custom_html_form input[type="radio"], .et_bloom_custom_html_form input[type="checkbox"]' ).uniform();
					}
				});
			}

			return false;
		} );

		$( 'body' ).on( 'click', '.et_bloom_preview_popup .et_bloom_close_button', function() {
			$( this ).parent().parent().remove();
			$( '#et_bloom_preview_css' ).remove();
			$( '.et_dashboard_preview button' ).removeClass( 'bloom_preview_opened' );
			$( 'body' ).removeClass( 'et_bloom_popup_active' );
		});

		$( 'body' ).on( 'click', '.et_bloom_preview_popup .et_bloom_submit_subscription', function() {
			return false;
		});

		function display_image( $popup ) {
			setTimeout( function() {
				$popup.find( '.et_bloom_image' ).addClass( 'et_bloom_visible_image' );
			}, 500 );
		}

		function resize( $current_ul ) {
			var bar_array = '';

			var bar_array = $( $current_ul ).find( 'li > div' ).map( function() {
				return $( this ).attr( 'value' );
			}).get();
			var bar_height = Math.max.apply( Math, bar_array );

			$( $current_ul ).find( 'li > div' ).each( function() {
				set_bar_height( $( this ), bar_height );
			});
		}

		function set_bar_height( $element, $bar_height ) {
			var value = $( $element ).attr( 'value' );
			var li_height = value / $bar_height * 375;
			$( $element ).animate({ height: li_height }, 700);
		}

		function switch_graph( $period, $list_id, $period_changed ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_get_stats_graph_ajax',
					bloom_stats_nonce : bloom_settings.bloom_stats,
					bloom_list : $list_id,
					bloom_period : $period
				},
				success: function( data ){
					if ( true === $period_changed ) {
						$( '.et_dashboard_lists_stats_graph_container' ).replaceWith( function() {
							return $( data ).hide().fadeIn();
						} );
					} else {
						$( '.et_dashboard_lists_stats_graph_container' ).replaceWith( data );
					}

					$( 'ul.et_bloom_graph' ).each( function() {
						resize ( $( this ) );
					});
				}
			});
		}

		function refresh_stats_table( $id, $orderby, $table ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_refresh_optins_stats_table',
					bloom_stats_nonce : bloom_settings.bloom_stats,
					bloom_orderby : $orderby,
					bloom_stats_table : $table
				},
				success: function( data ){
					$( $id ).replaceWith( data );
				}
			});
		}

		function refresh_stats_tab( $force_upd ) {
			if ( ! $( '.et_dashboard_stats_ready' ).length || true == $force_upd ) {
				//make sure that graphs start loading from the 0 height to avoid weird jumping of bars
				$( '.et_dashboard_lists_stats_graph_container ul li div' ).css( 'height', '0' );

				$.ajax({
					type: 'POST',
					url: bloom_settings.ajaxurl,
					data: {
						action : 'bloom_reset_stats',
						bloom_stats_nonce : bloom_settings.bloom_stats,
						bloom_force_upd_stats : $force_upd
					},
					beforeSend: function( data ){
						if ( ! $force_upd ) {
							$( '.et_bloom_stats_spinner' ).addClass( 'et_dashboard_spinner_visible' );
						}
					},
					success: function( data ){
						if ( ! $force_upd ) {
							$( '.et_bloom_stats_spinner' ).removeClass( 'et_dashboard_spinner_visible' );
						}

						$( '.et_dashboard_stats_contents' ).replaceWith( data );

						$( 'ul.et_bloom_graph' ).each( function() {
							resize ( $( this ) );
						});

						$( '.et_bloom_refresh_stats' ).removeClass( 'et_bloom_loading' );
					}
				});
			} else {
				$( '.et_dashboard_lists_stats_graph_container ul li div' ).css( 'height', '0' );
				$( 'ul.et_bloom_graph' ).each( function() {
					resize ( $( this ) );
				});
			}
		}

		/**
		 * Restore all jQuery events after dashboard regeneration
		 */
		function restore_events() {
			if ( $( '.et-dashboard-upload-button' ).length ) {
				var upload_button = $( '.et-dashboard-upload-button' );

				et_dashboard_image_upload( upload_button );

				upload_button.siblings( '.et-dashboard-upload-field' ).on( 'input', function() {
					et_dashboard_generate_preview_image( $( this ).siblings( '.et-dashboard-upload-button' ) );
					$(this).siblings( '.et-dashboard-upload-id' ).val('');
				} );

				upload_button.siblings( '.et-dashboard-upload-field' ).each( function() {
					et_dashboard_generate_preview_image( $( this ).siblings( '.et-dashboard-upload-button' ) );
				} );
			}

			$( '.et-dashboard-color-picker' ).wpColorPicker();

			if ( $( '.et_dashboard_conditional' ).length ) {
				$( '.et_dashboard_conditional' ).each( function() {
					window.et_dashboard_check_conditional_options( $( this ), true );
				});
			}

			//restore email services selections
			var selected_provider = $( '.et_dashboard_select_provider select' ).val(),
				selected_account = 'empty';

			if ( 'empty' == selected_provider || 'custom_html' == selected_provider ) {
				$( '.et_dashboard_select_account' ).css( { 'display' : 'none' } );
			} else {
				display_actual_accounts( selected_provider, false, '' );
				selected_account = $( '.et_dashboard_select_account select' ).val();
			}

			$( '.et_dashboard_select_list' ).css( { 'display' : 'none' } );

			if ( 'empty' !== selected_account && 'add_new' !== selected_account && ! ( 'empty' == selected_provider || 'custom_html' == selected_provider ) ) {
				display_actual_lists( selected_account, selected_provider );
			}

			check_display_options( $( '.display_on_checkboxes_everything' ), true );

			//fix the removing of tinymce editors in FireFox

			tinymce.init({
				mode : 'specific_textareas',
				editor_selector : 'et_dashboard_optin_title',
				menubar : false,
				plugins: "textcolor",
				forced_root_block : "h2",
				toolbar: [
					"forecolor | bold italic | alignleft aligncenter alignright"
				]
			});

			tinymce.init({
				mode : 'specific_textareas',
				editor_selector : 'et_dashboard_optin_message',
				menubar : false,
				plugins: "textcolor",
				toolbar: [
					"forecolor | bold italic | alignleft aligncenter alignright"
				]
			});
		}

		function reset_options( $this_el, $form_id, $new_form, $is_child, $parent_id ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'reset_options_page',
					reset_options_nonce : bloom_settings.reset_options,
					reset_optin_id : $form_id
				},
				success: function( data ){
					$( '#et_dashboard_wrapper_outer' ).replaceWith(data);
					open_optin_settings( $this_el, $new_form, $is_child, $parent_id );

					if ( true == $new_form ) {
						$( '.et_dashboard_next_design button' ).addClass( 'et_bloom_open_premade' );
						$( '.et_dashboard_tab_content_side_design a' ).addClass( 'et_bloom_open_premade' );
					}
				}
			});
		}

		function reset_home_tab() {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_home_tab_tables',
					home_tab_nonce : bloom_settings.home_tab,
				},
				success: function( data ){
					$( '.et_dashboard_home_tab_content' ).replaceWith( data );
					try {
						tinymce.remove();
					} catch (e) {}
				}
			});
		}

		function reset_accounts_tab() {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_reset_accounts_table',
					accounts_tab_nonce : bloom_settings.accounts_tab,
				},
				success: function( data ){
					$( '.et_dashboard_accounts_content' ).replaceWith( data );
				}
			});
		}

		function remove_optin( $form_id, $need_refresh, $is_account, $service, $parent_id ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_remove_optin',
					remove_option_nonce : bloom_settings.remove_option,
					remove_optin_id : $form_id,
					is_account : $is_account,
					service : $service,
					parent_id : $parent_id
				},

				success: function( data ){
					if ( true === $need_refresh ) {
						if ( true === $is_account ) {
							reset_accounts_tab();
						} else {
							reset_home_tab();
						}
					}
				}
			});
		}

		function duplicate_optin( $form_id, $form_type ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_duplicate_optin',
					duplicate_option_nonce : bloom_settings.duplicate_option,
					duplicate_optin_id : $form_id,
					duplicate_optin_type : $form_type
				},
				beforeSend: function() {
					$( '.duplicate_id_' + $form_id ).find( '.spinner' ).addClass( 'et_dashboard_spinner_visible' );
				},
				success: function( data ){
					reset_home_tab();
				}
			});
		}

		function open_optin_settings( $this_el, $new_form, $is_child, $parent_id ) {
			restore_events();
			if ( true === $new_form ) {
				$( '#et_dashboard_optin_type' ).val( $this_el.data( 'type' ) );
				$( '#et_dashboard_optin_status' ).val( 'active' );
				$type = $this_el.data( 'type' );

				if ( 'flyin' == $type ) {
					$( '.et_dashboard_field_orientation select' ).val( 'stacked' );
					$( '.et_bloom_load_in_animation select' ).val( 'slideup' );
				}
			} else {
				$type = $( '#et_dashboard_optin_type' ).val();
			}

			$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_visible_nav' );
			$( '#et_dashboard_options' ).removeAttr( 'class' ).addClass( 'current_optin_type_' + $type );
			$( '#et_dashboard_navigation > ul' ).removeAttr( 'class' ).addClass( 'nav_current_optin_type_' + $type );
			$( '#et_dashboard_navigation' ).removeAttr( 'class' ).addClass( 'current_optin_type_' + $type );
			$( '#et_dashboard_wrapper' ).removeClass( 'et_dashboard_edit_child' );

			if ( 'locked' == $type || 'inline' == $type ) {
				$( '.et_dashboard_next_shortcode button' ).data( 'type', $type );
				$( '.et_dashboard_next_shortcode button' ).data( 'optin_id', $( '.et_dashboard_save_changes button' ).data( 'subtitle' ) );
			}

			if ( true === $is_child ) {
				$( '#et_dashboard_child_of' ).val( $parent_id );
				$( '#et_dashboard_wrapper' ).addClass( 'et_dashboard_edit_child' );
			}

			window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_optin_setup', 'side' );
		}

		function clear_account_confirmation() {
			$( '.et_dashboard_confirmation_add_account' ).remove();
			$( '.et_dashboard_account_new.clicked_button' ).removeClass( 'clicked_button' );
		}

		function authorize_network( $service, $container, $on_form, $account_name, $account_exists ) {
			var key_field = $( $container ).find( '#api_key_' + $service ),
				token_field = $( $container ).find( '#token_' + $service ),
				username_field = $( $container ).find( '#username_' + $service ),
				client_field = $( $container ).find( '#client_id_' + $service ),
				password_field = $( $container ).find( '#password_' + $service ),
				account_name = $( $container ).find( '#name_' + $service ),
				account_name_val = '' == $account_name ? $( $container ).find( '#name_' + $service ).val() : $account_name;

			$( $container ).find( 'input' ).css( { 'border' : 'none' } );

			if ( ( key_field.length && '' == key_field.val() ) || ( token_field.length && '' == token_field.val() ) || ( username_field.length && '' == username_field.val() ) || ( client_field.length && '' == client_field.val() ) || ( password_field.length && '' == password_field.val() ) || ( account_name.length && '' == account_name_val ) ) {
				if ( '' == key_field.val() ) {
					key_field.css( { 'border' : '1px solid red' } );
				}
				if ( '' == token_field.val() ) {
					token_field.css( { 'border' : '1px solid red' } );
				}
				if ( '' == username_field.val() ) {
					username_field.css( { 'border' : '1px solid red' } );
				}
				if ( '' == client_field.val() ) {
					client_field.css( { 'border' : '1px solid red' } );
				}
				if ( '' == password_field.val() ) {
					password_field.css( { 'border' : '1px solid red' } );
				}
				if ( '' == account_name_val ) {
					account_name.css( { 'border' : '1px solid red' } );
				}
			} else {
				$.ajax({
					type: 'POST',
					url: bloom_settings.ajaxurl,
					data: {
						action : 'bloom_authorize_account',
						get_lists_nonce : bloom_settings.get_lists,
						bloom_api_key : key_field.val(),
						bloom_upd_service : $service,
						bloom_upd_name : account_name_val,
						bloom_constant_token : token_field.val(),
						bloom_username : username_field.val(),
						bloom_client_id : client_field.val(),
						bloom_password : password_field.val(),
						bloom_account_exists : $account_exists
					},
					beforeSend: function( data ) {
						$( $container ).find( 'span.spinner' ).addClass( 'et_dashboard_spinner_visible' );
					},

					success: function( data ){
						$( $container ).find( 'span.spinner' ).removeClass( 'et_dashboard_spinner_visible' );

						if ( 'success' == data || '' == data ) {
							reset_accounts_tab();

							if ( true === $on_form ) {
								hide_account_form( account_name_val );
							} else {
								$( '.et_dashboard_select_provider_new select' ).prop( 'disabled', true ).addClass( 'et_dashboard_disabled_input' );
								account_name.prop( 'disabled', true ).addClass( 'et_dashboard_disabled_input' );

								$( '.authorize_service.new_account_tab' ).text( bloom_settings.reauthorize_text );
								append_lists( $service, account_name_val );
							}
						} else {
							window.et_dashboard_generate_warning( data, '#', '', '', '', '' );
						}
					}
				});
			}

			return false;
		}

		function append_lists( $service, $name ){
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_generate_current_lists',
					accounts_tab_nonce : bloom_settings.accounts_tab,
					bloom_service : $service,
					bloom_upd_name : $name
				},
				success: function( data ){
					$( '.et_dashboard_new_account_lists' ).remove();
					$( '.et_dashboard_new_account_fields' ).after( function() {
						return $( data ).hide().fadeIn();
					} );
				}
			});
		}

		function hide_account_form( $account_name ) {
			$account_fields = $( '.account_settings_fields.et_visible_settings' );
			$account_fields.removeClass( 'et_visible_settings' );
			setTimeout( function() {
				display_actual_accounts( $account_fields.data( 'service' ), false, $account_name );
			}, 100 );
		}

		function display_actual_accounts( $service, $new_account, $set_to ) {
			var optin_id = $( '.et_dashboard_save_changes button' ).data( 'subtitle' ),
				new_account = true == $new_account ? true : '';
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_generate_accounts_list',
					retrieve_lists_nonce : bloom_settings.retrieve_lists,
					bloom_service : $service,
					bloom_optin_id : optin_id,
					bloom_add_account : new_account
				},
				success: function( data ){
					$( 'li.et_dashboard_select_account' ).replaceWith( function() {
						return $( data ).hide().fadeIn();
					} );

					$( 'li.et_dashboard_select_list' ).hide();

					if ( '' !== $set_to ) {
						$( 'li.et_dashboard_select_account select' ).val( $set_to );
					}

					if ( $( 'li.et_dashboard_select_account select' ).length && 'empty' !== $( 'li.et_dashboard_select_account select' ).val() && 'add_new' !== $( 'li.et_dashboard_select_account select' ).val() ){
						display_actual_lists( $( 'li.et_dashboard_select_account select' ).val(), $service );
					}
				}
			});
		}

		function display_actual_lists( $account_name, $service ) {
			var optin_id = $( '.et_dashboard_save_changes button' ).data( 'subtitle' );
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_generate_mailing_lists',
					retrieve_lists_nonce : bloom_settings.retrieve_lists,
					bloom_account_name : $account_name,
					bloom_service : $service,
					bloom_optin_id : optin_id
				},
				success: function( data ){
					$( 'li.et_dashboard_select_list' ).replaceWith( function() {
						return $( data ).hide().fadeIn();
					} );
				}
			});
		}

		function display_new_account_form( $service ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_generate_new_account_fields',
					accounts_tab_nonce : bloom_settings.accounts_tab,
					bloom_service : $service
				},
				success: function( data ){
					$( 'ul.et_dashboard_new_account_fields' ).replaceWith( function() {
							return $( data ).hide().fadeIn();
						} );
					$( '.account_settings_fields' ).addClass( 'et_visible_settings' );
				}
			});
		}

		function display_edit_account_tab( $edit_account, $service, $name ) {
			$( '#et_dashboard_edit_account_tab' ).css( { 'display' : 'none' } );
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_generate_edit_account_page',
					accounts_tab_nonce : bloom_settings.accounts_tab,
					bloom_service : $service,
					bloom_edit_account : $edit_account,
					bloom_account_name : $name
				},
				success: function( data ){
					$( '#et_dashboard_edit_account_tab' ).replaceWith( function() {
							return $( data ).hide().fadeIn();
					} );
				}
			});
		}

		function save_account_tab( $service, $account_name, $force_exit ) {
			if ( true == $force_exit ) {
				window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_accounts', 'header' );
			} else {
				$.ajax({
					type: 'POST',
					url: bloom_settings.ajaxurl,
					data: {
						action : 'bloom_save_account_tab',
						accounts_tab_nonce : bloom_settings.accounts_tab,
						bloom_service : $service,
						bloom_account_name : $account_name
					},
					success: function( data ){
						reset_accounts_tab();
						window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_accounts', 'header' );
					}
				});
			}
		}

		function add_variant( $form_id ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_add_variant',
					duplicate_option_nonce : bloom_settings.duplicate_option,
					duplicate_optin_id : $form_id
				},
				success: function( data ){
					reset_options( '', data, false, true, $form_id );
				}
			});
		}

		function ab_test_controls( $parent_id, $action, $button ) {
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'bloom_ab_test_actions',
					ab_test_nonce : bloom_settings.ab_test,
					parent_id : $parent_id,
					test_action : $action
				},
				success: function( data ){
					if ( 'start' == $action ) {
						$button.text( bloom_settings.ab_test_pause_text );
					}

					if ( 'pause' == $action ) {
						$button.text( bloom_settings.ab_test_start_text );
					}

					if ( 'end' == $action ) {
						$( '#wpwrap' ).append( data );
					}
				}
			});
		}

		function check_display_options( current_li, is_load ) {
			if ( ( current_li.find( 'input' ).prop( 'checked' ) && false == is_load ) || ( true != current_li.find( 'input' ).prop( 'checked' ) && true == is_load ) ) {
				current_li.siblings().removeClass( 'et_bloom_hidden_option' );
				$( '.categories_include_section' ).removeClass( 'et_bloom_hidden_option' );
			} else {
				current_li.siblings().addClass( 'et_bloom_hidden_option' );
				$( '.categories_include_section' ).addClass( 'et_bloom_hidden_option' );
			}
		}

		function bloom_dashboard_save( $button ) {
			tinyMCE.triggerSave();
			var options_fromform = $( '.' + dashboardSettings.plugin_class + ' #et_dashboard_options' ).serialize();
			$spinner = $button.parent().find( '.spinner' );
			$options_subtitle = $button.data( 'subtitle' );
			$.ajax({
				type: 'POST',
				url: bloom_settings.ajaxurl,
				data: {
					action : 'et_bloom_save_settings',
					options : options_fromform,
					options_sub_title : $options_subtitle,
					save_settings_nonce : bloom_settings.save_settings
				},
				beforeSend: function ( xhr ) {
					$spinner.addClass( 'et_dashboard_spinner_visible' );
				},
				success: function( data ) {
					$spinner.removeClass( 'et_dashboard_spinner_visible' );
					window.et_dashboard_display_warning( data );
					window.et_dashboard_set_current_tab( 'et_dashboard_tab_content_header_home', 'header' );
					$( '#et_dashboard_wrapper' ).removeClass( 'et_dashboard_visible_nav' );
					reset_home_tab();
				}
			});
		}

		function define_popup_position( $this_popup, $just_loaded ) {
			setTimeout( function() {
				var this_popup = $this_popup.find( '.et_bloom_form_container' ),
				popup_max_height = this_popup.hasClass( 'et_bloom_popup_container' ) ? $( window ).height() - 40 : $( window ).height() - 20,
				real_popup_height = 0,
				percentage = this_popup.parent().hasClass( 'et_bloom_flyin' ) ? 0.03 : 0.05,
				percentage = this_popup.hasClass( 'et_bloom_with_border' ) ? percentage + 0.03 : percentage,
				breakout_offset = this_popup.hasClass( 'breakout_edge' ) ? 0.95 : 1,
				dashed_offset = this_popup.hasClass( 'et_bloom_border_dashed' ) ? 4 : 0,
				form_height = this_popup.find( 'form' ).innerHeight(),
				form_add = true == $just_loaded ? 5 : 0;

				if ( this_popup.find( '.et_bloom_form_header' ).hasClass('split' ) ) {
					var image_height = this_popup.find( '.et_bloom_form_header img' ).innerHeight(),
						text_height = this_popup.find( '.et_bloom_form_header .et_bloom_form_text' ).innerHeight(),
						header_height = image_height < text_height ? text_height + 30 : image_height + 30;
				} else {
					var header_height = this_popup.find( '.et_bloom_form_header img' ).innerHeight() + this_popup.find( '.et_bloom_form_header .et_bloom_form_text' ).innerHeight() + 30;
				}

				this_popup.css( { 'max-height' : popup_max_height } );

				if ( this_popup.hasClass( 'et_bloom_popup_container' ) ) {
					var top_position = $( window ).height() / 2 - this_popup.innerHeight() / 2;
					this_popup.css( { 'top' : top_position + 'px' } );
				}

				this_popup.find( '.et_bloom_form_container_wrapper' ).css( { 'max-height' : popup_max_height - 20 } );


				if ( ( 768 > $( 'body' ).outerWidth() + 15 ) || this_popup.hasClass( 'et_bloom_form_bottom' ) ) {
					if ( this_popup.hasClass( 'et_bloom_form_right' ) || this_popup.hasClass( 'et_bloom_form_left' ) ) {
						this_popup.find( '.et_bloom_form_header' ).css( { 'height' : 'auto' } );
					}

					real_popup_height = this_popup.find( '.et_bloom_form_header' ).innerHeight() + this_popup.find( '.et_bloom_form_content' ).innerHeight() + 30 + form_add;

					if ( this_popup.hasClass( 'et_bloom_form_right' ) || this_popup.hasClass( 'et_bloom_form_left' ) ) {
						this_popup.find( '.et_bloom_form_container_wrapper' ).css( { 'height' : real_popup_height - 30 + dashed_offset } );
					}
				} else {
					if ( header_height < form_height ) {
						real_popup_height = this_popup.find( 'form' ).innerHeight() + 30;
					} else {
						real_popup_height = header_height + 30;
					}

					if ( this_popup.hasClass( 'et_bloom_form_right' ) || this_popup.hasClass( 'et_bloom_form_left' ) ) {
						this_popup.find( '.et_bloom_form_header' ).css( { 'height' : real_popup_height * breakout_offset - dashed_offset } );
						this_popup.find( '.et_bloom_form_content' ).css( { 'min-height' : real_popup_height - dashed_offset } );
						this_popup.find( '.et_bloom_form_container_wrapper' ).css( { 'height' : real_popup_height } );
					}
				}

				if ( real_popup_height > popup_max_height ) {
					this_popup.find( '.et_bloom_form_container_wrapper' ).addClass( 'et_bloom_vertical_scroll' );
				} else {
					this_popup.find( '.et_bloom_form_container_wrapper' ).removeClass( 'et_bloom_vertical_scroll' );
				}

				if ( $this_popup.hasClass( 'et_bloom_popup' ) ) {
					$( 'body' ).addClass( 'et_bloom_popup_active' );
				}
			}, 100 );
		}

		$( window ).scroll( function(){
			if( $( this ).scrollTop() > 200 ) {
				$( '.et_dashboard_preview' ).addClass( 'et_dashboard_fixed' );
			} else {
				$( '.et_dashboard_preview' ).removeClass( 'et_dashboard_fixed' );
			}
		});


		$( window ).resize( function(){
			if ( $( '.et_bloom_preview_popup' ).length ) {
				define_popup_position( $( '.et_bloom_preview_popup' ), false );
			}
		});
	});
})(jQuery)