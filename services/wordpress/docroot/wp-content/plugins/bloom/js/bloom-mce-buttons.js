(function() {
	tinymce.create('tinymce.plugins.bloom', {
		/**
		 * Initializes the plugin, this will be executed after the plugin has been created.
		 * This call is done before the editor instance has finished it's initialization so use the onInit event
		 * of the editor instance to intercept that event.
		 *
		 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
		 * @param {string} url Absolute URL to where the plugin is located.
		 */


		init : function(ed, url) {
			var t = this,
				optins_locked = jQuery.parseJSON( bloom.locked_optins ),
				optins_inline = jQuery.parseJSON( bloom.inline_optins ),
				$bloom_tooltip = bloom.bloom_tooltip,
				$inline_text = bloom.inline_text,
				$locked_text = bloom.locked_text,
				count = 0,
				$menu_items_locked = [],
				$menu_items_inline = [];

			jQuery(optins_locked).each(function(i,val){
				jQuery.each(val,function(optin_id,optin_title) {
					$menu_items_locked.push( {'text' : optin_title,
						'onclick' : function() {
							if ( 'empty' !== optin_id ) {
								var selected_text = ed.selection.getContent();
									return_text = '[et_bloom_locked optin_id='+ optin_id + ']' + selected_text + '[/et_bloom_locked]';

								ed.insertContent(return_text);
							}
						}
					} );
				});
			});

			jQuery(optins_inline).each(function(i,val){
				jQuery.each(val,function(optin_id,optin_title) {
					$menu_items_inline.push( {
						'text' : optin_title,
						'onclick' : function() {
							if ( 'empty' !== optin_id ) {
								return_text = '[et_bloom_inline optin_id='+ optin_id + ']';
								ed.insertContent(return_text);
							}
						}
					} );
				});
			});

			ed.addButton('bloom_button', {
				text: '',
				icon: 'et_bloom_shortcode_icon',
				type: 'menubutton',
				tooltip : $bloom_tooltip,
				menu:
					[
						{
							text: $locked_text,
							menu: $menu_items_locked
						},
						{
							text: $inline_text,
							menu: $menu_items_inline
						}
					]
			});
		},


		/**
		 * Creates control instances based in the incomming name. This method is normally not
		 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
		 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
		 * method can be used to create those.
		 *
		 * @param {String} n Name of the control to create.
		 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
		 * @return {tinymce.ui.Control} New control instance or null if no control was created.
		 */
		createControl : function(n, cm) {
			return null;
		},

		/**
		 * Returns information about the plugin as a name/value array.
		 * The current keys are longname, author, authorurl, infourl and version.
		 *
		 * @return {Object} Name/value array containing information about the plugin.
		 */
		getInfo : function() {
			return {
				longname : "Elegant Bloom Buttons",
				author : 'Elegant Themes',
				authorurl : 'http://www.elegantthemes.com/',
				infourl : 'http://www.elegantthemes.com/',
				version : "1.0"
			};
		}
	});

	// Register plugin
	tinymce.PluginManager.add( 'bloom', tinymce.plugins.bloom );
})();