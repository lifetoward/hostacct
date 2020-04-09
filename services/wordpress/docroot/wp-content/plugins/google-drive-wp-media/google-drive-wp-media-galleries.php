<?php 
if(!function_exists('is_admin')){
     die('You do not have sufficient permissions to access this page.');
}
if ( !is_admin() ) {
     wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
?>
<div id="galpage">
<?php
if(!isset($paged)){
	$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
}
if(getGals($paged) && $paged > 1){
$paged = 1;
getGals($paged);
}
echo '<br/><br/>Page '.$paged;
?>
&nbsp;&nbsp;&nbsp;
<button id="nextgal">Next Page >></button>
<?php
$paged++;
$gdwpm_url_tab_galleries = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=galleries&gdwpm_tabulasi_galleries_nonce=') . wp_create_nonce( "gdwpm_tabulasi_galleries_nonce" );
?>
</div>
<div id="gdwpm_galleries_128" style="display:none;text-align:center;">
	<img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_128.gif', __FILE__ );?>"><br/>Please wait..
</div>
				
<script>
jQuery(document).ready(function(){
    jQuery("#nextgal").click(function(){
		jQuery("#galpage").html('');
		jQuery("#gdwpm_galleries_128").show();
        jQuery.ajax({url: "<?php echo $gdwpm_url_tab_galleries . '&paged=' . $paged;?>", success: function(result){
			jQuery("#gdwpm_galleries_128").hide();
            jQuery("#galpage").html(result);
        }});
    });
	jQuery('.galdelet').click(function(){
		jQuery("#galpage").html('');
		jQuery("#gdwpm_galleries_128").show();
		var galid = jQuery(this).attr('data-galdelet');
        jQuery.ajax({url: "<?php echo $gdwpm_url_tab_galleries . '&delete=';?>" + galid, success: function(result){
			jQuery("#gdwpm_galleries_128").hide();
			jQuery("#galpage").html(result);
        }});
    });
	jQuery('.galedit').click(function(){
		jQuery("#gallery_holder").html('');
		var galids = jQuery(this).attr('data-galedit');
		var arraydata = galids.split(' items:');
		var galdata = jQuery.parseJSON(Base64.decode(arraydata[0]));	//array(galid, judul, album, style, effect, viewer, styleopt)
		var galitems = arraydata[1].split(' , ');  // array(id, thumb id, flnme) capt
		var jmlarr = galitems.length;
		for (var i = 0; i < jmlarr; i++) {
			var indidata = galitems[i].split(' | '); 
			var indidatadecode = jQuery.parseJSON(Base64.decode(indidata[0]));
			var captionimg = Base64.decode(indidata[1]);
			jQuery("#gallery_holder").append( "<div id='itemgal' class='ui-widget-content ui-corner-all'><div id='galgbr' class='ui-corner-all' data-info='"+indidata[0]+"' style='margin: 0 auto;width:150px;height:150px;overflow:hidden;' ><img src='https://www.googledrive.com/host/"+indidatadecode[1]+"' title='"+indidatadecode[2]+"' alt='"+indidatadecode[2]+"' /></div><input type='text' name='customcaption[]' class='ui-corner-all' value='"+captionimg+"' size='15' placeholder='Caption this image' /><a href='javascript:void(0)' onClick='remove_itemgal(this); return false;'><small>[Remove]</small></a></div>" );
		}
		jQuery('#gdwpm_new_gallery').hide();
		jQuery('#gdwpm_edit_gallery').show();
		jQuery('#gdwpm_info_masuk_gallery').empty();
		jQuery('#gallery_id_edit').val(galdata[0]);
		jQuery('#gallery_input').show();
		jQuery('#gdwpm-albums').tabs({active:2});
		jQuery('#gal_intermezo').html('Gallery ID: '+galdata[0]+' is ready for editing.');
		jQuery('#old_album').val(galdata[2]);
		jQuery('#old_album').selectmenu("refresh");
		jQuery('#gallery_title').val(galdata[1]);
		jQuery('#css_style').val(galdata[3]);
		jQuery('#css_style').selectmenu("refresh");
		jQuery('#css_effect').val(galdata[4]);
		jQuery('#css_effect').selectmenu("refresh");
		jQuery('#css_style_default').val(galdata[6]);	
		jQuery('#css_style_default').selectmenu("refresh");
		var datagalarr = galdata[7].split(' | ');
		jQuery('#css_justified_margins').val(datagalarr[0]);	
		jQuery('#css_justified_margins').selectmenu("refresh");
		jQuery('#css_justified_row').val(datagalarr[1]);	
		jQuery('#css_justified_row').selectmenu("refresh");
		jQuery('#css_justified_last').val(datagalarr[2]);	
		jQuery('#css_justified_last').selectmenu("refresh");
		
		if(galdata[3] == 'default'){
			jQuery('#css_style_opt').show();
			jQuery('#css_style_opt1').hide();
		}else{
			jQuery('#css_style_opt').hide();
			jQuery('#css_style_opt1').show();
		}
		jQuery('#gallery_box_info').show();
		jQuery('#gallery_input_info').empty();
    });
});
</script>
<?php
function getGals($paged){
	$type = 'gdwpm_galleries';
	$args=array(
	  'post_type' => $type,
	  'post_status' => 'publish',
	  'posts_per_page' => -1,
	  'posts_per_page' => 10,
	  'paged' => $paged,
	  'caller_get_posts'=> 1);
	$my_query = null;
	$my_query = new WP_Query($args);
	if( $my_query->have_posts() ) {
		echo '<table><tr><th></th><th>Title</th><th>Shortcode</th><th>Album</th><th>Action</th></tr><tr>';
		while ($my_query->have_posts()) : $my_query->the_post(); 
			echo '<tr><td><div class="ui-corner-all" style="width:32px;height:32px; overflow:hidden;"><img src="//www.googledrive.com/host/' . get_post_meta( get_the_ID(), 'sampleImage', true ) . '" title="'.get_the_ID().'" height="32px" /></div></td>
			<td style="padding:0 5px 0 3px;"><a href="' . get_permalink() . '" target="_blank" title="Permanent Link to ' . get_the_title() . '">' . get_the_title() . '</a></td>
			<td style="padding:0 3px 0 3px;"><code>[gdwpm-gallery id="' . get_the_ID() . '"]</code></td>
			<td style="padding:0 3px 0 3px;">' . get_the_term_list( get_the_ID(), 'gdwpm_album', '', ', ' ) . '</td>
			<td style="padding:0 0 0 7px;"><button id="galedit" class="galedit" title="Send to Collector" data-galedit="'.get_post_meta( get_the_ID(), 'base64data', true ).'">Edit</button> | <button id="galdelet" class="galdelet ui-state-disabled" title="Permanently deleted" data-galdelet="' . get_the_ID() . '">Delete</button></td></tr>';
		
		endwhile;
		echo '</table>';

	}else{
		if($paged > 1){
			return true;
		}else{
			echo 'No galleries found.';
		}
	}
	wp_reset_query(); 
}
?>
<script>
         jQuery(function() {
            jQuery( "#nextgal" ).button({
            });
            jQuery( ".galedit" ).button({
               icons: {
                  primary: "ui-icon-pencil"
               }
            });
            jQuery( ".galdelet" ).button({
               icons: {
                  primary: "ui-icon-trash"
               }
            });
         });
</script>