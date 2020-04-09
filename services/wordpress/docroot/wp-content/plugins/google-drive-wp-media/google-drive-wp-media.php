<?php
/*
Plugin Name: Google Drive WP Media
Plugin URI: http://wordpress.org/plugins/google-drive-wp-media/
Description: WordPress Google Drive integration plugin. Google Drive on Wordpress Media Publishing. Upload files to Google Drive from WordPress blog.
Author: Moch Amir
Author URI: http://www.mochamir.com/
Version: 2.4
License: GNU General Public License v2.0 or later
License URI: http://www.opensource.org/licenses/gpl-license.php
*/

/*
			  Copyright (c)2014 Moch Amir.

			  This program is free software; you can redistribute it and/or modify
			  it under the terms of the GNU General Public License as published by
			  the Free Software Foundation; either version 2 of the License, or
			  (at your option) any later version.

			  This program is distributed in the hope that it will be useful,
			  but WITHOUT ANY WARRANTY; without even the implied warranty of
			  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
			  GNU General Public License for more details.

			  You should have received a copy of the GNU General Public License
			  along with this program; if not, write to the Free Software
			  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

define( 'NAMA_GDWPM', 'Google Drive WP Media' );
define( 'ALMT_GDWPM', 'google-drive-wp-media' );
define( 'MINPHP_GDWPM', '5.3.0' );
define( 'VERSI_GDWPM', '2.4' );
define( 'MY_TEXTDOMAIN', 'gdwpm' );

$gdwpm_override_optional = get_option('gdwpm_override_dir_bawaan'); // cekbok, polder

if($gdwpm_override_optional[0] == 'checked' && !empty($gdwpm_override_optional[1])){

	add_filter('wp_handle_upload_prefilter', 'gdwpm_custom_upload_filter' );

	function gdwpm_custom_upload_filter( $file ){
		$gdwpm_opt_akun = get_option('gdwpm_akun_opt'); // imel, client id, service akun, private key
		if(!empty($gdwpm_opt_akun[1]) && !empty($gdwpm_opt_akun[2]) && !empty($gdwpm_opt_akun[3])){
		
			global $gdwpm_override_optional;
			
require_once 'gdwpm-api/Google_Client.php';
require_once 'gdwpm-api/contrib/Google_DriveService.php';

			$gdwpm_service_ride = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );
			
			$filename = $file['name'];
			$path = $file['tmp_name'];
			$mime_berkas = $file['type'];
			//$mime_berkas = sanitize_mime_type($mime_berkas);
			$folder_ortu = preg_replace("/[^a-zA-Z0-9]+/", " ", $gdwpm_override_optional[1]);
			$folder_ortu = sanitize_text_field($folder_ortu);
			
			if($folder_ortu != ''){
				$folderId = $gdwpm_service_ride->getFolderIdByName( $folder_ortu );
			}
			
			$content = '';

			if( ! $folderId ) {
				$folderId = $gdwpm_service_ride->createFolder( $folder_ortu );
				$gdwpm_service_ride->setPermissions( $folderId, $gdwpm_opt_akun[0] );
			}

			$fileParent = new Google_ParentReference();
			$fileParent->setId( $folderId );
			$fileId = $gdwpm_service_ride->createFileFromPath( $path, $filename, $content, $fileParent );
			
			$sukinfo = '';
			if($fileId){
				$gdwpm_service_ride->setPermissions( $fileId, 'me', 'reader', 'anyone' );
				if(!empty($mime_berkas) && $gdwpm_override_optional[2] == 'checked'){
					gdwpm_ijin_masuk_perpus($mime_berkas, $filename, $fileId, $content, $folder_ortu);
					$sukinfo = ' and added into your Media Library';
				}
				echo '<div class="updated"><p>Done! <strong>'.$filename.' ('.$fileId.')</strong> successfully uploaded into <strong>'.$folder_ortu.'</strong>'.$sukinfo.'.</p></div>';
				$fileku['error'] = 'Google Drive WP Media: This error message appear because your file has been deleted before uploading to the internal uploads folder. If you want to remove this error, just navigate to Media >> Google Drive WP Media >> Options and then uncheck the "Google Drive as Default Media Upload Storage." and save it.';
				$fileku['name'] = $filename;
				if(file_exists($path)){@unlink($path);}
				return $fileku;
			}else{
				echo '<div class="error"><p>Failed to upload <strong>'.$filename.'</strong> to Google Drive.</p></div>';
				return $file;
			}
		}else{
			return $file;
		}
	}
}

// SHORTCODE  ===> [gdwpm id="GOOGLE-DRIVE-FILE-ID" w="640" h="385"]
function gdwpm_iframe_shortcode($gdwpm_kode_berkas) {
	$gdwpm_ukuran_preview = get_option('gdwpm_ukuran_preview'); 
	if(isset($gdwpm_kode_berkas['video']) != 'auto' && isset($gdwpm_kode_berkas['video']) != 'manual'){
		$gdwpm_kode_berkas = shortcode_atts( array( 'id' => '', 'w' => $gdwpm_ukuran_preview[0], 'h' => $gdwpm_ukuran_preview[1]), $gdwpm_kode_berkas, 'gdwpm' );
		return '<iframe src="https://docs.google.com/file/d/' . $gdwpm_kode_berkas['id'] . '/preview" width="' . $gdwpm_kode_berkas['w'] . '" height="' . $gdwpm_kode_berkas['h'] . '"></iframe>';
	}else{
			$gdwpm_kode_berkas = shortcode_atts( array( 'id' => '', 'w' => $gdwpm_ukuran_preview[4], 'h' => $gdwpm_ukuran_preview[5], 'video' => $gdwpm_ukuran_preview[3]), $gdwpm_kode_berkas, 'gdwpm' );
			if($gdwpm_kode_berkas['video'] == 'auto'){$mode_autoplay = '1';}else{$mode_autoplay = '0';}
			return '<embed src="https://video.google.com/get_player?autoplay=' . $mode_autoplay . '&amp;docid=' . $gdwpm_kode_berkas['id'] . '&amp;ps=docs&amp;partnerid=30" type="application/x-shockwave-flash" allowfullscreen="true" allowScriptAccess="always" width="' . $gdwpm_kode_berkas['w'] . '" height="' . $gdwpm_kode_berkas['h'] . '"></embed>';
	}
}
add_shortcode('gdwpm', 'gdwpm_iframe_shortcode'); 

function gdwpm_gallery_code($atts){
	extract( shortcode_atts( array('id' => null), $atts ) );

	$terms = get_the_terms( $id, 'gdwpm_album' );		
	if ( $terms && ! is_wp_error( $terms ) ){
		$post = get_post($id);
		$content = $post->post_content;
	}else{
		$content = 'Gallery not found: wrong/invalid ID.';
	}
	return $content;
}

add_shortcode('gdwpm-gallery', 'gdwpm_gallery_code');

//////////// ADMIN INIT ///////////
add_action( 'admin_init', 'gdwpm_admin_init' );
function gdwpm_admin_init() {
	$gdwpm_theme_css_pilian = get_option('gdwpm_nama_theme_css');
	if(empty($gdwpm_theme_css_pilian)){$gdwpm_theme_css_pilian = 'smoothness';}
    wp_register_style( 'gdwpm-jqueryui-theme', '//ajax.googleapis.com/ajax/libs/jqueryui/1.11.2/themes/' . $gdwpm_theme_css_pilian . '/jquery-ui.css', false, VERSI_GDWPM );
}


function gdwpm_init() {
	$cek_taxonomy = taxonomy_exists('gdwpm_category');
	if(!$cek_taxonomy){
        $labels = array(
            'name'              => _x( 'GDWPM Categories', 'taxonomy general name' ),
            'singular_name'     => _x( 'GDWPM Category', 'taxonomy singular name' ),
            'search_items'      => __( 'Search GDWPM Categories' ),
            'all_items'         => __( 'All GDWPM Categories' ),
            'parent_item'       => __( 'Parent GDWPM Category' ),
            'parent_item_colon' => __( 'Parent GDWPM Category:' ),
            'edit_item'         => __( 'Edit GDWPM Category' ), 
            'update_item'       => __( 'Update GDWPM Category' ),
            'add_new_item'      => __( 'Add New GDWPM Category' ),
            'new_item_name'     => __( 'New GDWPM Category Name' ),
            'menu_name'         => __( 'GDWPM Categories' ),
        );

        $args = array(
            'hierarchical' => TRUE,
            'labels'       => $labels,
            'show_ui'      => TRUE,
            'show_admin_column' => TRUE,
			'update_count_callback' => '_update_generic_term_count',
            'query_var'    => TRUE,
            'rewrite'      => TRUE,
        );	
	
		register_taxonomy('gdwpm_category', 'attachment', $args);
	}
	
	//
	register_post_type( 'gdwpm_galleries',
		array(
		  'labels' => array(
			'name' => __( 'GDWPM Galleries' ),
			'singular_name' => __( 'GDWPM Gallery' )
		  ),
		  //'public' => true,
		  //'supports' => array('title', 'editor', 'excerpt', 'custom-fields', 'thumbnail'),
		  'has_archive' => true,
		  'exclude_from_search' => false,
		  'publicly_queryable' => true,
		  'rewrite' => array('slug' => 'gdwpm-gallery'),
		)
	);
	
	$cek_album_taxonomy = taxonomy_exists('gdwpm_album');
	if(!$cek_album_taxonomy){
        $labels = array(
            'name'              => _x( 'GDWPM Albums', 'taxonomy general name' ),
            'singular_name'     => _x( 'GDWPM Album', 'taxonomy singular name' ),
            'search_items'      => __( 'Search GDWPM Albums' ),
            'all_items'         => __( 'All GDWPM Albums' ),
            'parent_item'       => __( 'Parent GDWPM Album' ),
            'parent_item_colon' => __( 'Parent GDWPM Album:' ),
            'edit_item'         => __( 'Edit GDWPM Album' ), 
            'update_item'       => __( 'Update GDWPM Album' ),
            'add_new_item'      => __( 'Add New GDWPM Album' ),
            'new_item_name'     => __( 'New GDWPM Album Name' ),
            'menu_name'         => __( 'GDWPM Albums' ),
        );

        $args = array(
            'hierarchical' => TRUE,
            'labels'       => $labels,
            'show_ui'      => TRUE,
            'show_admin_column' => TRUE,
			'update_count_callback' => '_update_generic_term_count',
            'query_var'    => TRUE,
			'rewrite' => array('slug' => 'gdwpm-album'),
        );	
	
		register_taxonomy('gdwpm_album', 'gdwpm_galleries', $args);
	}
	
}
function gdwpm_filter_kategori_media_tab($query) {
	if ( 'attachment' != isset($query->query_vars['post_type']) )
		return;

		if ( isset( $_REQUEST['query']['gdwpm_category'] ) && $_REQUEST['query']['gdwpm_category']['term_slug'] )	 :
			$query->set( 'gdwpm_category', $_REQUEST['query']['gdwpm_category']['term_slug'] );
		elseif ( isset( $_REQUEST['gdwpm_category'] ) && is_numeric( $_REQUEST['gdwpm_category'] ) && 0 != intval( $_REQUEST['gdwpm_category'] ) ) :
			$term = get_term_by( 'id', $_REQUEST['gdwpm_category'], 'gdwpm_category' );
			set_query_var( 'gdwpm_category', $term->slug );
		endif;
}
function gdwpm_admin_head(){
global $wpdb;
$attachment_taxonomies = $attachment_terms = array();
$attachment_taxonomies['gdwpm_category'] = 'gdwpm_category';
$terms = get_terms( 'gdwpm_category', array(
				'orderby'       => 'name',
				'order'         => 'ASC',
				'hide_empty'    => true,
			) );

			$attachment_terms[ 'gdwpm_category' ][] = array( 'id' => 0, 'label' => 'View all GDWPM Categories', 'slug' => '' );
			foreach ( $terms as $term )
				$attachment_terms[ 'gdwpm_category' ][] = array( 'id' => $term->term_id, 'label' => $term->name, 'slug' => $term->slug );
	$gdwpm_ukuran_preview = get_option('gdwpm_ukuran_preview');
		?>
<style type="text/css">
.wp-admin .media-toolbar-secondary select {
	margin: 11px 16px 0 0;
}
</style>
<script type="text/javascript">
jQuery(function($) {
    $(document).ready(function(){
		$('#masukin-sotkode').click(open_media_window);
    });
 
    function open_media_window() {
		if (this.window === undefined) {
			this.window = wp.media({
                title: 'Insert Google Drive Single File Preview',
                multiple: false,
                button: {text: 'Insert'}
            });
 
			var self = this;
			this.window.on('select', function() {
                var first = self.window.state().get('selection').first().toJSON();
				if (first.url.indexOf(".google") > -1){
					var gdwpm_video_cekbok = '<?php echo $gdwpm_ukuran_preview[2];?>';
					if (first.mime.indexOf("video/") > -1 && gdwpm_video_cekbok == 'checked'){
						wp.media.editor.insert('[gdwpm id="' + first.filename + '" video="<?php echo $gdwpm_ukuran_preview[3];?>" w="<?php echo $gdwpm_ukuran_preview[4];?>" h="<?php echo $gdwpm_ukuran_preview[5];?>"]');
					}else{
						wp.media.editor.insert('[gdwpm id="' + first.filename + '" w="<?php echo $gdwpm_ukuran_preview[0];?>" h="<?php echo $gdwpm_ukuran_preview[1];?>"]');
					}
				}
				if (first.url.indexOf("gdwpm_images") > -1){
					var gdfileid = first.filename;
					if( gdfileid.indexOf(".") > -1 ){
						var gdfileid = gdfileid.substr(0, gdfileid.lastIndexOf("."));
					}
					wp.media.editor.insert('[gdwpm id="' + gdfileid + '" w="<?php echo $gdwpm_ukuran_preview[0];?>" h="<?php echo $gdwpm_ukuran_preview[1];?>"]');
				}
            });
		}
 
		this.window.open();
		return false;
    }
});
var mediaTaxonomies = <?php echo json_encode( $attachment_taxonomies ) ?>,
mediaTerms = <?php echo json_encode( $attachment_terms ) ?>;
</script>
<?php
}
function gdwpm_image_category_filter() {
    $screen = get_current_screen();
    if ( 'upload' == $screen->id ) {
		$args = array(
	'show_option_all'    => 'View all GDWPM Categories',
	'orderby'            => 'name', 
	'hide_empty'         => 1, 
	'selected' => ( isset( $wp_query->query['gdwpm_category'] ) ? $wp_query->query['gdwpm_category'] : '' ),
	'hierarchical'       => 1, 
	'name'               => 'gdwpm_category',
	'taxonomy'           => 'gdwpm_category',
); 
				wp_dropdown_categories($args);
	}
}

$gdwpm_opsi_kategori = get_option('gdwpm_opsi_kategori_dr_folder'); 
if($gdwpm_opsi_kategori == 'checked'){
add_action( 'init', 'gdwpm_init' );

add_action('pre_get_posts','gdwpm_filter_kategori_media_tab');

add_action('admin_head', 'gdwpm_admin_head');

add_action( 'restrict_manage_posts', 'gdwpm_image_category_filter' );
}

//////////////// HALAMAN MEDIA MENU ///////////////
add_action( 'admin_menu', 'gdwpm_menu_media' );
function gdwpm_menu_media() {
	add_media_page( NAMA_GDWPM, NAMA_GDWPM, 'activate_plugins', ALMT_GDWPM, 'gdwpm_halaman_media' );
	add_action( 'admin_enqueue_scripts', 'gdwpm_sekrip_buat_mimin' );
}
function gdwpm_halaman_media() {
	if(!current_user_can('activate_plugins')){
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
	echo '<h2>'. NAMA_GDWPM .'</h2>';
	gdwpm_halaman_utama();
	echo '</div>';
}

/////////////// HALAMAN TAB UPLOAD /////////////////////////
add_action('media_upload_gdwpm_nama_tab', 'gdwpm_halaman_media_upload');
function gdwpm_halaman_media_upload() {
	if(current_user_can('activate_plugins')){
		wp_iframe( 'gdwpm_halaman_utama' );
	}else{
		wp_iframe( 'gdwpm_forboden_halaman_utama' );
	}
}

add_filter('media_upload_tabs', 'gdwpm_tab_media_upload');
function gdwpm_tab_media_upload($tabs) {
	$tabs['gdwpm_nama_tab'] = NAMA_GDWPM;
	return $tabs;
}

function gdwpm_forboden_halaman_utama() {
	echo '<p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>You do not have sufficient permissions to access this page.</p>';
}
////////////////////////// ADMIN SEKRIP ////////////////////////////////
function gdwpm_sekrip_buat_mimin() {
	wp_enqueue_style( 'gdwpm-jqueryui-theme' );
	
	wp_enqueue_script('jquery');                    
	wp_enqueue_script('jquery-ui-core');       
	wp_enqueue_script('jquery-ui-accordion');         
	wp_enqueue_script('jquery-ui-dialog');   
	wp_enqueue_script('plupload-all');          
	wp_enqueue_script('jquery-ui-tooltip');              
	wp_enqueue_script('jquery-ui-tabs');                    
	wp_enqueue_script('jquery-ui-widget');                
	wp_enqueue_script('jquery-effects-core');                 
	wp_enqueue_script('jquery-effects-explode');                
	wp_enqueue_script('jquery-ui-sortable');           
	wp_enqueue_script( 'gdwpm-ajax-script', plugins_url( '/js/sekrip.js', __FILE__ ), array('jquery'), VERSI_GDWPM, true );	
	wp_enqueue_script( 'gdwpm-base64-script', plugins_url( '/js/base64.js', __FILE__ ), array('jquery'), VERSI_GDWPM, true );	
	//backwards compatible
	if ( version_compare( get_bloginfo('version'), '4.0', '>' ) ) {          
		wp_enqueue_script('jquery-ui-selectmenu');  
	}
	
$gdwpm_opsi_kategori = get_option('gdwpm_opsi_kategori_dr_folder'); 
	wp_localize_script( 'gdwpm-ajax-script', 'ajax_object',
            array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'opsi_kategori' => $gdwpm_opsi_kategori ) );
}

/////////////////////// KASTEM ATTACHMENT URL //////////////////   
add_filter( 'wp_get_attachment_url', 'gdwpm_filter_gbrurl');
function gdwpm_filter_gbrurl( $url ){
	$upload_dir = wp_upload_dir();
    if (strpos($url, 'G_D_W_P_M-ImageFile_ID/') !== false) {
		$url = str_replace( $upload_dir['baseurl'] . '/G_D_W_P_M-ImageFile_ID/', 'https://www.googledrive.com/host/', $url );
		if(strpos($url, 'https://www.googledrive.com/host/') === false){
			$url = 'https://www.googledrive.com/host/' . substr($url, strrpos($url, '/') + 1);
		}
    } elseif (strpos($url, 'G_D_W_P_M-file_ID/') !== false) {
		$url = str_replace( $upload_dir['baseurl'] . '/G_D_W_P_M-file_ID/', 'https://docs.google.com/uc?id=', $url ) . '&export=view';
		if(strpos($url, 'https://docs.google.com/uc?id=') === false){
			$url = 'https://docs.google.com/uc?id=' . substr($url, strrpos($url, '/') + 1);
		}
    }
	return $url; 
}


function gdwpm_halaman_utama() {
	global $cek_kunci, $gdwpm_opt_akun, $gdwpm_service, $gdwpm_apiConfig;
require_once 'gdwpm-api/Google_Client.php';
require_once 'gdwpm-api/contrib/Google_DriveService.php';

$cek_kunci = 'true'; // kosong
if(isset($_REQUEST['gdwpm_opsi_kategori_nonce'])){
	require_once(ABSPATH .'wp-includes/pluggable.php');
	if(!wp_verify_nonce( $_REQUEST['gdwpm_opsi_kategori_nonce'], 'gdwpm_override_dir' )) {
		wp_die( '<div class="error"><p>Security check not verified!</p></div>' ); 
	} else {
		if($_POST['gdwpm_cekbok_opsi_kategori'] == 1){
			update_option('gdwpm_opsi_kategori_dr_folder', 'checked');
			echo '<div class="updated"><p>GDWPM Categories has been enabled.</p></div>';
		}else{
			update_option('gdwpm_opsi_kategori_dr_folder', '');
			echo '<div class="updated"><p>GDWPM Categories has been disabled.</p></div>';
		}
	}
}

if(isset($_REQUEST['gdwpm_opsi_chunkpl_nonce'])){
	require_once(ABSPATH .'wp-includes/pluggable.php');
	if(!wp_verify_nonce( $_REQUEST['gdwpm_opsi_chunkpl_nonce'], 'gdwpm_chunkpl_nonce' )) {
		wp_die( '<div class="error"><p>Security check not verified!</p></div>' ); 
	} else {
		$input_cek = true;
		$input_chunkarr = array('gdwpm_drive_chunk_size', 'gdwpm_drive_chunk_retries', 'gdwpm_local_chunk_size', 'gdwpm_local_chunk_retries');
		foreach($input_chunkarr as $val){
			if(!ctype_digit($_POST[$val])){
				$input_cek = false;
				break;
			}
		}
		if($input_cek){
			if (isset($_POST['gdwpm_cekbok_opsi_chunkpl'])) {$ceket = 'checked';}else{$ceket = '';}
			update_option('gdwpm_opsi_chunk', array('local' => array('cekbok' => $ceket, 'chunk' => $_POST['gdwpm_local_chunk_size'], 'retries' => $_POST['gdwpm_local_chunk_retries']), 'drive' => array('cekbok' => 'checked', 'chunk' => $_POST['gdwpm_drive_chunk_size'], 'retries' => $_POST['gdwpm_drive_chunk_retries'])));
			echo '<div class="updated"><p>Chunking Settings saved.</p></div>';
		}else{
			echo '<div class="error"><p>Chunking Settings cannot be saved. You must provide Numeric value.</p></div>';
		}
	}
}
	if (isset($_POST['gdwpm_akun_nonce']))
	{
		$nonce = $_POST['gdwpm_akun_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_akun_nonce' ) ) {
			die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			
		} else {
			$gdwpm_opt_imel = sanitize_email($_POST['gdwpm_imel']);
			$gdwpm_opt_klaen_aidi = sanitize_text_field($_POST['gdwpm_klaen_aidi']);
			$gdwpm_opt_nama_service = sanitize_email($_POST['gdwpm_nama_service']);
			$gdwpm_opt_kunci_rhs = esc_url($_POST['gdwpm_kunci_rhs']);
				
			$gdwpm_opt_akun = array($gdwpm_opt_imel, $gdwpm_opt_klaen_aidi, $gdwpm_opt_nama_service, $gdwpm_opt_kunci_rhs);
			
			if (!EMPTY($gdwpm_opt_imel) && !EMPTY($gdwpm_opt_klaen_aidi) && !EMPTY($gdwpm_opt_nama_service) && !EMPTY($gdwpm_opt_kunci_rhs))
			{
				// test akun				
				if(!isset($gdwpm_service)){ 
					$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] ); 
				}
				try {
					$gdwpm_apiConfig['use_objects'] = true;
					$ebot = $gdwpm_service->getAbout();
					update_option('gdwpm_akun_opt', $gdwpm_opt_akun);
					update_option('gdwpm_img_thumbs', array('', '', '150', '150', 'false'));
					echo '<div class="updated"><p>API settings successfully saved.</p></div>';
				} catch (Exception $errorkon) {
					$cek_kunci = 'true';
					echo '<div class="error"><p>An error occurred: ' . wp_strip_all_tags($errorkon->getMessage()) . '. Your settings could not be saved.</p></div>';
				}			
			}else{
				echo '<div class="error"><p>All fields are required. Your settings could not be saved.</p></div>';
			}
		}
	}
if(!isset($gdwpm_opt_akun)){$gdwpm_opt_akun = get_option('gdwpm_akun_opt');} // imel, client id, gdwpm_service akun, private key
if($gdwpm_opt_akun){
	if (!EMPTY($gdwpm_opt_akun[0]) && !EMPTY($gdwpm_opt_akun[1]) && !EMPTY($gdwpm_opt_akun[2]) && !EMPTY($gdwpm_opt_akun[3]))
	{
		$cek_kunci = 'false';
		//$gdwpm_apiConfig['use_objects'] = true;
		if(!isset($gdwpm_service)){ 
			$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] ); 
		}
	}
}

if(isset($_POST['gdwpm_opsi_thumbs_nonce'])){
	require_once(ABSPATH .'wp-includes/pluggable.php');
	if(!wp_verify_nonce( $_POST['gdwpm_opsi_thumbs_nonce'], 'gdwpm_thumbs_nonce' )) {
		wp_die( '<div class="error"><p>Security check not verified!</p></div>' ); 
	} else {
		if(ctype_digit($_POST['gdwpm_thumbs_width']) && ctype_digit($_POST['gdwpm_thumbs_height'])){
			if (isset($_POST['gdwpm_cekbok_opsi_thumbs'])) {$ceket = 'checked';}else{$ceket = '';}
			if($_POST['gdwpm_thumbs_crop'] == 'true'){$gdwpm_thumbs_crop = 'true';}else{$gdwpm_thumbs_crop = 'false';}
			$gdwpm_opsi_thumbs = get_option('gdwpm_img_thumbs');
			if(is_array($gdwpm_opsi_thumbs) && !empty($gdwpm_opsi_thumbs[1])){
				update_option('gdwpm_img_thumbs', array($ceket, $gdwpm_opsi_thumbs[1], $_POST['gdwpm_thumbs_width'], $_POST['gdwpm_thumbs_height'], $gdwpm_thumbs_crop));		
				echo '<div class="updated"><p>Thumbnail settings successfully saved.</p></div>';					
			}else{
				$folderId_thumb = $gdwpm_service->getFolderIdByName( 'gdwpm-thumbnails' );
				if(!$folderId_thumb){
					$folderId_thumb = $gdwpm_service->createFolder( 'gdwpm-thumbnails' );
					if($folderId_thumb){
						$gdwpm_service->setPermissions( $folderId_thumb, $gdwpm_opt_akun[0] );
					}else{
						$folderId_thumb = '';
					}
				}				
				update_option('gdwpm_img_thumbs', array($ceket, $folderId_thumb, $_POST['gdwpm_thumbs_width'], $_POST['gdwpm_thumbs_height'], $gdwpm_thumbs_crop));
				echo '<div class="updated"><p>Thumbnail settings successfully saved.</p></div>';
			}
		}else{
			echo '<div class="error"><p>Thumbnail settings error, only numeric can be accepted.</p></div>';
		}
	}
} 

if (isset($_POST['repair_folder_pilian']))
{
	if (isset($_POST['gdwpm_folder_tools_nonce']))
	{
		$nonce = $_POST['gdwpm_folder_tools_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_folder_tools_nonce' ) ) {
			wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
		}else{
			$folderId = $_POST['repair_folder_pilian'];
			$gdwpm_folder_permisi = $gdwpm_service->setPermissions( $folderId, $gdwpm_opt_akun[0] );
			
			if($gdwpm_folder_permisi){
				echo '<div class="updated"><p>Folder <strong>'.$folderId.'</strong> permissions changed.</p></div>';
			}else{
				echo '<div class="error"><p>Folder '.$folderId.' permissions fail to change.</p></div>';
			}
		}
	}else{
		wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>');
	}
}

if (isset($_POST['buang_folder_pilian']))
{
	if (isset($_POST['gdwpm_folder_tools_nonce']))
	{
		$nonce = $_POST['gdwpm_folder_tools_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_folder_tools_nonce' ) ) {
			wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
		}else{
			$gdwpm_nama_folder = $gdwpm_service->getNameFromId( $_POST['buang_folder_pilian'] );
			$gdwpm_tong_sampah = $gdwpm_service->buangFile( $_POST['buang_folder_pilian'] );
			if($gdwpm_tong_sampah){
				echo '<div class="updated"><p>Folder <strong>'.$_POST['buang_folder_pilian'].' '.$gdwpm_nama_folder.'</strong> successfully deleted.</p></div>';
				sleep(3);
			}else{
				echo '<div class="error"><p>' . $_POST['buang_folder_pilian'] . ' ' . $gdwpm_nama_folder . ' fail to delete.</p></div>';
			}
		}
	}else{
		wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>');
	}
}

if (isset($_POST['gdwpm_buang_berkas_terpilih']))
{
	if (isset($_POST['gdwpm_folder_tools_nonce']))
	{
		$nonce = $_POST['gdwpm_folder_tools_nonce'];
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_folder_tools_nonce' ) ) {
			wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
		}else{
			$gdwpm_info_files = '';
			if (is_array($_POST['gdwpm_buang_berkas_terpilih'])) {
				foreach($_POST['gdwpm_buang_berkas_terpilih'] as $value){
					//$gdwpm_berkas_terpilih_array = explode(' | ', $value); // mime, name, id, desc, folder, pptis
					$gdwpm_berkas_terpilih_array = json_decode(base64_decode($value), true);
					$gdwpm_tong_sampah = $gdwpm_service->buangFile( $gdwpm_berkas_terpilih_array[2] );
					if($gdwpm_tong_sampah){
						$gdwpm_info_files .= '<strong>' . $gdwpm_berkas_terpilih_array[1] . '</strong> deleted, ';
					}else{
						$gdwpm_info_files .= '<strong>' . $gdwpm_berkas_terpilih_array[1] . '</strong> failed, ';
					}
					//sleep(0.5);
				}
			} else {
				//$gdwpm_berkas_terpilih_array = explode(' | ', $_POST['gdwpm_buang_berkas_terpilih']); // mime, name, id, desc, folder, pptis
				$gdwpm_berkas_terpilih_array = json_decode(base64_decode($_POST['gdwpm_buang_berkas_terpilih']), true);
				$gdwpm_tong_sampah = $gdwpm_service->buangFile( $gdwpm_berkas_terpilih_array[2] );
				if($gdwpm_tong_sampah){
					$gdwpm_info_files = '<strong>' . $gdwpm_berkas_terpilih_array[1] . '</strong> deleted, ';
				}else{
					$gdwpm_info_files = '<strong>' . $gdwpm_berkas_terpilih_array[1] . '</strong> failed, ';
				}
			}
			echo '<div class="updated"><p>'.$gdwpm_info_files.'.. Done!.</p></div>';
			sleep(3);
		}
	}else{
		wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>');
	}
}

if (isset($_POST['gdwpm_gawe_folder_nonce']))
{
	require_once(ABSPATH .'wp-includes/pluggable.php');
	$nonce = $_POST['gdwpm_gawe_folder_nonce'];
	if ( ! wp_verify_nonce( $nonce, 'gdwpm_gawe_folder_nonce' ) ) {
		die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			
	} else {
		if (!EMPTY($_POST['gdwpm_gawe_folder']))
		{
			$gawe_folder = preg_replace("/[^a-zA-Z0-9]+/", " ", $_POST['gdwpm_gawe_folder']);
			$gawe_folder = sanitize_text_field($gawe_folder);
			$folderId = $gdwpm_service->createFolder( $gawe_folder );
			$gdwpm_folder_permisi = $gdwpm_service->setPermissions( $folderId, $gdwpm_opt_akun[0] );

			if( $gdwpm_folder_permisi ) {
				echo '<div class="updated"><p>Great! Folder name <strong>'.$gawe_folder.'</strong> successfully created.</p></div>';
			}else{
				echo '<div class="error"><p>Folder '.$gawe_folder.' created but permission fail to change.</p></div>';
			}
		}else{
			echo '<div class="error"><p>Folder name cannot be empty!</p></div>';
		}
	}
}

?>
<script>
jQuery(function() {
    var icons = {
		header: "ui-icon-triangle-1-e",
		activeHeader: "ui-icon-lightbulb"
    };
    jQuery( "#accordion" ).accordion({
		heightStyle: "content",
		icons: icons
    });
	  
	jQuery("[title]").tooltip({ 
		track: true,
		show: { effect: 'slideDown' },
		open: function (event, ui) { setTimeout(function () {
				jQuery(ui.tooltip).hide('explode');
			}, 3000); }
	});
     
	jQuery( "#tabs" ).tabs({
		beforeLoad: function( event, ui ) {
			ui.jqXHR.error(function() {
				ui.panel.html(
				"Opening Options tab, please wait.. <p>If this take too long, there's something wrong with your internet connection.<br/>Well, don't be bad.. it's just a guess. :)</p>" );
			});
		}
    });
  
	jQuery( "#doktabs" ).tabs({
		beforeLoad: function( event, ui ) {
			ui.jqXHR.error(function() {
				ui.panel.html(
				"Opening plugin documentation tab, please wait..<p>If this take too long, there's something wrong with your internet connection.<br/>Well, don't be bad.. it's just a guess. :)</p>" );
			});
		}
    });
	
	jQuery( "#gdwpm-settingtabs" ).tabs({
		beforeLoad: function( event, ui ) {
			ui.jqXHR.error(function() {
				ui.panel.html(
				"Opening Themes Setting tab, please wait..<p>If this take too long, there's something wrong with your internet connection.:)</p>" );
			});
		}	
    });
	
	jQuery( "#gdwpm-albums" ).tabs({
		beforeLoad: function( event, ui ) {
			ui.jqXHR.error(function() {
				ui.panel.html(
				"Opening Albums tab, please wait..<p>If this take too long, there's something wrong with your internet connection.</p>" );
			});
		}	
    });
});
</script>
<style type="text/css">
a.tabeksen:link {
    color: #3399FF	;
}
a.tabeksen:hover {
    color: #FF0000;
}
#box-table-a
{
	font-family: "Lucida Sans Unicode", "Lucida Grande", Sans-Serif;
	font-size: 12px;
	width:100%;
	text-align: left;
	border-collapse: collapse;
}
#box-table-a th
{
	font-size: 13px;
	font-weight: normal;
	padding: 8px;
	background: #B6F0F6;
	border-top: 4px solid #DAF8FB;
	border-bottom: 1px solid #fff;
	color: #039;
}
#box-table-a td
{
	padding: 8px;
	background: #EAFAFC; 
	border-bottom: 1px solid #fff;
	color: #669;
	border-top: 1px solid transparent;
}
#box-table-a tr:hover td
{
	background: #FFFFCF;
	color: #339;
}

div.halpager {
    text-align: center;
    margin: 1em 0;
}

div.halpager span {
    display: inline-block;
    width: 1.8em;
    height: 1.8em;
    line-height: 1.8;
    text-align: center;
    cursor: pointer;
    background: #EAFAFC;
    color: #039;
    margin-right: 0.5em;
}

div.halpager span.active {
    background: #B6F0F6;
}
#pilihMaxRes { width: 110px; }
#pilihMaxResdel { width: 140px; }
#pilihMaxResgal { width: 140px; }
#folder_pilian{ width: 200px; }
  .overflowpil { max-height: 370px; }
#folder_pilian_aplod { width: 190px; }
  .overflowapl { max-height: 360px; }
#buang_folder_pilian { width: 230px; }
  .overflowbua { max-height: 300px; }
#repair_folder_pilian { width: 230px; }
  .overflowrep { max-height: 300px; }
#folder_pilian_file_del { width: 220px; }
  .overflowdel { max-height: 360px; }
#folder_pilian_file_gal { width: 220px; }
  .overflowgal { max-height: 370px; }
#old_album { width: 180px; }
  .overflowolal { max-height: 370px; }
#css_style, #css_style_default, #css_justified_margins, #css_justified_row, #css_justified_last { width: 120px; }
#css_effect, #gallery_jquery { width: 140px; } 
h2:before { content: ""; display: block; background: url("<?php echo plugins_url( '/images/animation/icon-32x32.png', __FILE__ );?>") no-repeat; width: 32px; height: 32px; float: left; margin: 0 6px 0 15px; }
#itemgal { margin: 5px 10px 5px 0; padding: 5px; float: left; width: 160px; text-align: center; }
#itemgal img { height: 150px; width="auto"; }
#itemgal input { margin-top: 5px; }
</style>
<?php
	$gdwpm_apiConfig['use_objects'] = true;
	
	if($gdwpm_opt_akun && !isset($errorkon)){
		if (!EMPTY($gdwpm_opt_akun[0]) && !EMPTY($gdwpm_opt_akun[1]) && !EMPTY($gdwpm_opt_akun[2]) && !EMPTY($gdwpm_opt_akun[3]))
		{
			// cek dolo sbagai awal dr smuanya :p
			try {
				$parameters = array('q' => "mimeType = 'application/vnd.google-apps.folder'", 'maxResults' => 50);
				$files = $gdwpm_service->files->listFiles($parameters);
				$folderpil = '<select id="folder_pilian" name="folder_pilian">';
				$foldercek = array();
				foreach( $files->getItems() as $item )
				{//description, title
					if('gdwpm-thumbnails' == $item->getTitle()){$selek = ' disabled';}else{$selek = '';}
					$folderpil .=  '<option value="'.$item->getId().'"'.$selek.'>'.$item->getTitle().'</option>';
					$foldercek[] = $item->getTitle(); 
				}
				$folderpil .= '</select>';
				$foldercek = array_filter($foldercek);
			
				if (empty($foldercek)) {
					$folderpil = '';
				}
			} catch (Exception $e) {
				$cek_kunci = 'true';
				echo '<div class="error"><p>An error occurred: ' . wp_strip_all_tags($e->getMessage()) . '</p></div>';
			}	
		}
	}else{
		$cek_kunci = 'true';
	}
?>
<div id="accordion" style="margin:10px 10px 5px 10px;">
<?php
//cek versi php
$gdwpm_cek_php = 'true';
if(version_compare(PHP_VERSION, MINPHP_GDWPM) >= 0) {
	$gdwpm_cek_php = 'false';
	global $cek_kunci;
if($cek_kunci == 'false'){ ?>
		<h3>My Google Drive</h3>
	<div>
		<?php
			$gdwpm_tab_opsi_nonce = wp_create_nonce( "gdwpm_tab_opsi_key" );
			$gdwpm_url_tab_opsi = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=opsyen&gdwpm_tab_opsi_nonce=') . $gdwpm_tab_opsi_nonce;
			$gdwpm_url_tab_info = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=infosyen&gdwpm_tab_info_nonce=') . $gdwpm_tab_opsi_nonce;
		?>
		<div id="tabs" style="margin:0 -12px 0 -12px;">
		<ul>
 <?php if (!empty($foldercek)) { ?>
			<li><a href="#tabs-1"><span style="float:left" class="ui-icon ui-icon-script"></span>&nbsp;File & Folder List</a></li>
			<li><a href="#tabs-2"><span style="float:left" class="ui-icon ui-icon-star"></span>&nbsp;Upload</a></li>
			<li><a href="<?php echo $gdwpm_url_tab_opsi; ?>"><span style="float:left" class="ui-icon ui-icon-clipboard"></span>&nbsp;Options</a></li>
			<li><a href="<?php echo $gdwpm_url_tab_info; ?>"><span style="float:left" class="ui-icon ui-icon-heart"></span>&nbsp;Account Information</a></li>
			<li><a href="#tabs-5"><span style="float:left" class="ui-icon ui-icon-gear"></span>&nbsp;Tools</a></li>
<?php }else{ ?>
			<li><a href="#tabs-6"><span style="float:left" class="ui-icon ui-icon-folder-collapsed"></span>&nbsp;Create Folder</a></li>
<?php } ?>
		</ul>
 <?php if (!empty($foldercek)) { ?>
			<div id="tabs-1" class="ui-helper-clearfix">
				<div id="tombol-donat" class="ui-widget-content ui-corner-all" style="width:190px; float:right; padding:1em;">	
					<p style="text-align: center;">Do you like this plugin?<br/>Please consider to:<br/><br/><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=ZZNNMX3NZM2G2" target="_blank">
					<img src="https://www.paypal.com/en_US/i/btn/btn_donateCC_LG.gif" alt="Donate Button with Credit Cards" /></a><br/>or<br/><a href="https://wordpress.org/support/view/plugin-reviews/google-drive-wp-media?filter=5" target="_blank"><img src="<?php echo plugins_url( '/images/animation/5star-rating.png', __FILE__ );?>" alt="5 Star Rating" title="5 Star Rating" /></a><br/>Your supports help the plugin keep updated & maintained.
					</p>
				</div>
				<p style="display: flex; align-items: center;">Select folder: &nbsp;<?php echo str_replace(' disabled', '', $folderpil); ?>&nbsp; <select id="pilihMaxRes">
				<?php for($i=1;$i<=10;$i++){$inum = $i * 10;?>
				<option value="<?php echo $inum;?>"><?php echo $inum;?> items/page</option>				
				<?php } ?>
				</select>&nbsp; <button id="golek_seko_folder" name="golek_seko_folder"><?php _e('Get Files') ?></button> &nbsp;&nbsp;
					<span id="gdwpm_info_folder_baru" style="display:none;">
						There's a new folder.
						<a href=""><button id="gdwpm_tombol_info_folder_baru" name="gdwpm_tombol_info_folder_baru"><?php _e('Reload Now') ?></button></a>
					</span>
				<?php add_thickbox(); $gdwpm_ukuran_preview = get_option('gdwpm_ukuran_preview');?>
				<p>
					<span class="sukses">Please select folder and click Get Files, to show all files belongs to it.<br /><br />
						<dfn>New</dfn> Auto create thumbnails and Chunking Option available, just navigate to the Options page and customize your settings to help suit your needs.<br/>
						Shortcode: <code>[gdwpm id="<strong>GOOGLE-DRIVE-FILE-ID</strong>"]</code>
						<br />
						Shortcode with specific width & height: <code>[gdwpm id="<strong>GOOGLE-DRIVE-FILE-ID</strong>" w="<strong><?php echo $gdwpm_ukuran_preview[0];?></strong>" h="<strong><?php echo $gdwpm_ukuran_preview[1];?></strong>"]</code>
						<br />
						Shortcode for embed video: <code>[gdwpm id="<strong>GOOGLE-DRIVE-FILE-ID</strong>" video="<strong><?php echo $gdwpm_ukuran_preview[3];?></strong>" w="<strong><?php echo $gdwpm_ukuran_preview[4];?></strong>" h="<strong><?php echo $gdwpm_ukuran_preview[5];?></strong>"]</code>
						<br/><?php //$gdwpm_opsi_thumbs = get_option('gdwpm_img_thumbs');
						//print_r($gdwpm_opsi_thumbs);
						?>
						Link URL: https://docs.google.com/uc?id=<code><strong>GOOGLE-DRIVE-FILE-ID</strong></code>&export=view <br/>
						or: https://www.googledrive.com/host/<code><strong>GOOGLE-DRIVE-FILE-ID</strong></code>
						<br />
						Preview: https://docs.google.com/file/d/<code><strong>GOOGLE-DRIVE-FILE-ID</strong></code>/preview
						<br/><small>
						* Replace <code><strong>GOOGLE-DRIVE-FILE-ID</strong></code> with your file ID. 
						<?php
							if(!isset($ebot)){$ebot = $gdwpm_service->getAbout();}
							echo '<br /><br />Total quota: '.size_format($ebot->getQuotaBytesTotal(), 2).'<br />
							Quota Used: '.size_format($ebot->getQuotaBytesUsed(), 2).'</small>';
						?>
					</span>
				</p>	
				<div style="display: none" id="gdwpm_loading_gbr">
				  <center><img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_256.gif', __FILE__ );?>" /><br />Please wait...</center>
				</div>
				<div id="hasil"></div>
				<div id="vaginasi" style="text-align:center;margin-top:25px;"></div>
				<div style="display: none" id="gdwpm_masuk_perpus_teks"><p>Pick a file to include it in the Media Library.</p>
					<p>
						<button id="gdwpm_berkas_masuk_perpus" name="gdwpm_berkas_masuk_perpus">Add to Media Library</button>&nbsp;&nbsp;&nbsp; 
						<span style="display: none" id="gdwpm_add_to_media_gbr">
							<img src="<?php echo plugins_url( '/images/animation/loading-bar-image.gif', __FILE__ );?>" />
						</span>
						<span id="gdwpm_info_masuk_perpus"></span>
					</p>
				</div>
				<div style="display: none" id="gdwpm_info_folder_thumbs">
					<p>These thumbnails automatically attached to the Media Library along with their original images respectively.</p>
				</div>
			</div>
			<div id="tabs-2">
				<p style="display: flex; align-items: center;">
					Select folder: &nbsp;<?php echo str_replace('folder_pilian', 'folder_pilian_aplod', $folderpil); ?>&nbsp; or create a new folder: <input type="text" id="gdwpm_folder_anyar" name="gdwpm_folder_anyar" value="" size="20" title="Ignore this field if you wish to use existing folder" placeholder="*Alphanumeric only">
				</p>
					<!--<p>Short Description: <input type="text" name="gdwpm_aplod_deskrip" value="" size="65" placeholder="Optional"></p>-->
				<p>
					<ul>
						<li id="infopraupload"><dfn>Your Uploaded files will be listed in "Shared with Me" view (https://drive.google.com/?authuser=0#shared-with-me) in the classic Google Drive UI or "Incoming" area (https://drive.google.com/drive/#incoming) in the new Google Drive UI.
						</dfn></li>
						<li><dfn>Accepted Media MIME types: */*</dfn>
						<!--	<br />&nbsp;<dfn>All Filetypes are allowed.</dfn>
						--></li>
					</ul> 
				</p>
				
<?php
			$gdwpm_opsi_chunk = get_option('gdwpm_opsi_chunk');
			if(!$gdwpm_opsi_chunk || empty($gdwpm_opsi_chunk)){
				$gdwpm_opsi_chunk = array('local' => array('cekbok' => 'checked', 'chunk' => '700', 'retries' => '3'), 'drive' => array('cekbok' => 'checked', 'chunk' => '2', 'retries' => '3'));
				update_option('gdwpm_opsi_chunk', $gdwpm_opsi_chunk);
			}
			$gdwpm_satpam_buat_nonce = wp_create_nonce( 'gdwpm_satpam_aplod_berkas' );
				?> 
				<ul id="filelist"></ul>
				<br />
				<button id="gdwpm_tombol_bersih" style="display:none;float:right;">Clear List</button>
				<br />
				<br />
				<pre id="console"></pre>
				<div id="gdwpm_upload_container"><p id="gdwpm-pilih-kt">Choose your files: 
					<a id="gdwpm_tombol_browse" href="javascript:;"><button id="gdwpm_tombol_bk_folder">Browse</button></a></p>
					<input type='checkbox' id='gdwpm_cekbok_masukperpus' name='gdwpm_cekbok_masukperpus' value='1' checked /> Add to Media Library. (just linked to, all files still remain in Google Drive)<!-- (Image only: <i>*.jpg, *.jpeg, *.png, & *.gif</i>)--><p>
					<a style="display:none;" id="gdwpm_start-upload" href="javascript:;"><button id="gdwpm_tombol_upload">Upload to Google Drive</button></a>
				</div>
				<div id="gdwpm_loding_128" style="display:none;"><center>
				<img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_128.gif', __FILE__ );?>"><br/>Uploading...<br/><small id="respon_progress"></small></center></div>
 
<script type="text/javascript"> 
	var uploader = new plupload.Uploader({
		browse_button: 'gdwpm_tombol_browse', 
		url: '<?php echo admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_nonce_aplod_berkas=') . $gdwpm_satpam_buat_nonce; ?>',
		<?php if($gdwpm_opsi_chunk['local']['cekbok'] == 'checked'){echo "chunk_size: '".$gdwpm_opsi_chunk['local']['chunk']."kb',";}?>
		max_retries: <?php echo $gdwpm_opsi_chunk['local']['retries'];?>
	});
 
	uploader.init();
 
	uploader.bind('FilesAdded', function(up, files) {
		var html = '';
		plupload.each(files, function(file) {
			html += '<li id="' + file.id + '"><code>' + file.name + '</code> (' + plupload.formatSize(file.size) + ') <span class="hasilprog"></span> <input type="text" id="' + file.id + 'gdwpm_aplod_deskrip" name="' + file.id + 'lod_deskrip" value="" size="55" placeholder="Short Description (optional) *Alphanumeric*"><hr></li>';
		});
		
		document.getElementById('filelist').innerHTML += html;
		jQuery('#console').empty();
		jQuery('#gdwpm_tombol_bersih').hide();
		jQuery('#gdwpm_start-upload').show();
		jQuery('#infopraupload').remove();
	});
 
	uploader.bind('UploadProgress', function(up, file) {
		document.getElementById(file.id).getElementsByClassName('hasilprog')[0].innerHTML = "<dfn>" + file.percent + "%</dfn> <small>" +  jQuery('#' + file.id + 'gdwpm_aplod_deskrip').val().replace(/[^\w\s-]/gi, '') + "</small>";
		
		jQuery('#' + file.id + 'gdwpm_aplod_deskrip').hide();
		jQuery('#gdwpm_upload_container').hide();
		jQuery('#gdwpm_loding_128').show();
		jQuery('#gdwpm_tombol_bersih').hide();
	});
 
	uploader.bind('Error', function(up, err) {
		document.getElementById('console').innerHTML += "\nError #" + err.code + ": " + err.message;
		
		jQuery('#gdwpm_upload_container').show();
		jQuery('#gdwpm_loding_128').hide();
		jQuery('#gdwpm_start-upload').hide();
		jQuery('#gdwpm_tombol_bersih').show();
	});
	
	document.getElementById('gdwpm_start-upload').onclick = function() {
		uploader.start();
	};
	
	uploader.bind('FileUploaded', function(up, file, response ) {
        response=response["response"];
		jQuery('#console').html(response);
		
		var totalspan = document.getElementById('filelist').getElementsByClassName('hasilprog').length;
		var totaldfn = document.getElementById('filelist').getElementsByTagName('dfn').length;
		if(totalspan == totaldfn){
			jQuery('#gdwpm_upload_container').show();
			jQuery('#gdwpm_tombol_bersih').show();
			jQuery('#gdwpm_loding_128').hide();
		}
		jQuery('#gdwpm_start-upload').hide();
		jQuery('#respon_progress').empty();
		
		if(jQuery('#gdwpm_folder_anyar').val() != ''){
			jQuery('#gdwpm_info_folder_baru').show();
		}
	});
 
	uploader.bind('BeforeUpload', function (up, file) {
		up.settings.multipart_params = {gdpwm_nm_bks: jQuery("#folder_pilian_aplod option:selected").text(), gdpwm_nm_id: jQuery('select[name=folder_pilian_aplod]').val(), 
		gdpwm_nm_br: jQuery('#gdwpm_folder_anyar').val(), gdpwm_sh_ds: jQuery('#' + file.id + 'gdwpm_aplod_deskrip').val().replace(/[^\w\s-]/gi, ''), gdpwm_med_ly: jQuery('#gdwpm_cekbok_masukperpus:checked').val(),
		gdpwm_nama_file: file.name};
	});  
	
	uploader.bind('ChunkUploaded', function(up, file, info) {
		response=info["response"];
		jQuery('#respon_progress').empty();
		jQuery('#console').html(response);
		jQuery('#respon_progress').html('[Chunked: ' + info["offset"] + ' of ' + info["total"] + ' bytes]');
		
		//jQuery('#gdwpm_upload_container').show();
		//jQuery('#gdwpm_loding_128').hide();
		//jQuery('#gdwpm_start-upload').hide();
	}); 
</script>
			</div>
			<!-- tabs-3 ajax -->
			<!-- tabs-4 ajax -->
			<div id="tabs-5">
			<?php $gdwpm_folder_tools_nonce = wp_create_nonce( "gdwpm_folder_tools_nonce" ); ?>
				<div id="gdwpm_repair_folder" class="ui-widget-content ui-corner-all " style="padding:1em;">	
				<div class="ui-corner-all ui-widget-header" style="padding:0.5em;text-align:center;">Repair Folder
				</div>
				<form id="gdwpm_form_repair_folder" name="gdwpm_form_repair_folder" method="post">
				<p style="margin-left:7px;">
					<dfn>If you found that your folder was listed here, but not in your Google Drive "Incoming" area. <br/>
					Then, this tool will fix your hidden folder by change its permissions.</dfn>
				</p><br/>
					<input type="hidden" name="gdwpm_folder_tools_nonce" value="<?php echo $gdwpm_folder_tools_nonce;?>">
					<p style="margin-left:17px;display: flex; align-items: center;">Select folder: &nbsp;<?php echo str_replace(array('folder_pilian', ' disabled'), array('repair_folder_pilian', ''), $folderpil); ?> &nbsp;<button id="gdwpm_tombol_repair_folder" name="gdwpm_tombol_repair_folder"><?php _e('Fix Now!') ?></button> &nbsp;&nbsp;
				</p>
				</form>
				</div>
				<br/>
				<div class="ui-widget-content ui-corner-all" style="padding:1em;">		
				<div class="ui-corner-all ui-widget-header" style="padding:0.5em;text-align:center;">Delete Folder and Files
				</div>
				<p>What do you want to do?</p>
				 <p style="margin-left:17px;"><a onclick="gdwpm_cekbok_opsi_buang_folder_eksen();"><input type='radio' name='gdwpm_cekbok_opsi_buang_folder' value='1' /></a> 
					Delete folder</p>
				<p style="margin-left:17px;"><a onclick="gdwpm_cekbok_opsi_buang_file_eksen();"><input type='radio' name='gdwpm_cekbok_opsi_buang_folder' value='1' /></a> 
					Delete files</p>
				<br />
				<div id="gdwpm_kotak_buang_folder" class="ui-widget-content ui-corner-all" style="padding:1em;display:none;">	
				<div class="ui-corner-all ui-widget-header" style="padding:0.5em;text-align:center;">Delete Folder
				</div>
				<form id="gdwpm_form_buang_folder" name="gdwpm_form_buang_folder" method="post">
					<input type="hidden" name="gdwpm_folder_tools_nonce" value="<?php echo $gdwpm_folder_tools_nonce;?>">
					<p style="display: flex; align-items: center;">Select folder to delete: &nbsp;<?php echo str_replace('folder_pilian', 'buang_folder_pilian', $folderpil); ?> &nbsp;<button id="gdwpm_buang_folder" name="gdwpm_buang_folder"><?php _e('Delete Now') ?></button> &nbsp;&nbsp;
						</form>
					</p>
				</div>
				<div id="gdwpm_kotak_buang_file" class="ui-widget-content ui-corner-all" style="padding:1em;display:none;">		
				<div class="ui-corner-all ui-widget-header" style="padding:0.5em;text-align:center;">Delete Files
				</div>				
				<p style="display: flex; align-items: center;">Select folder: &nbsp;<?php echo str_replace(array('folder_pilian', ' disabled'), array('folder_pilian_file_del', ''), $folderpil); ?> &nbsp;<select id="pilihMaxResdel">
				<?php for($i=1;$i<=10;$i++){$inum = $i * 10;?>
				<option value="<?php echo $inum;?>"><?php echo $inum;?> items/page</option>				
				<?php } ?>
				</select> &nbsp;<button id="gdwpm_file_dr_folder" name="gdwpm_file_dr_folder"><?php _e('Get Files') ?></button> &nbsp;&nbsp;
					
				<p>
					<span class="sukses_del">Please select folder and click Get Files, to show all files belongs to it.
				</span>
				</p>
				
				<div style="display: none" id="gdwpm_loading_gbr_del">
				  <center><img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_256.gif', __FILE__ );?>" /><br />Please wait...</center>
				</div>
				<form id="gdwpm_form_buang_berkas" name="gdwpm_form_buang_berkas" method="post">	
					<input type="hidden" name="gdwpm_folder_tools_nonce" value="<?php echo $gdwpm_folder_tools_nonce;?>">
					<div id="hasil_del"></div>
					<div style="display: none" id="gdwpm_info_del"><p>Selected file(s) will be permanently deleted. Are you ready?</p>
						<p>
							<button id="gdwpm_berkas_buang" name="gdwpm_berkas_buang">Delete Selected</button>
						</p>
					</div>
				</form>
				<div id="vaginasi_del" style="text-align:center;margin-top:25px;"></div>
				</div>
<div id="dialog-buang-folder" title="Confirm Deletion" style="display: none;">
  <p>
    <span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 50px 0;"></span>
    Are you sure want to delete this folder?
  </p>
  <p>
    All files in this folder will be permanently deleted and cannot be recovered.
  </p>
</div>
<div id="dialog-buang-berkas" title="Confirm Deletion" style="display: none;">
  <p>
    <span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 50px 0;"></span>
    Are you sure want to delete selected file(s)?
  </p>
  <p>
    All selected files will be permanently deleted and cannot be recovered.
  </p>
</div>
			</div>
			</div>
<script>
function gdwpm_cekbok_opsi_buang_folder_eksen(){
		document.getElementById("gdwpm_kotak_buang_folder").style.display = "block";
		document.getElementById("gdwpm_kotak_buang_file").style.display = "none";
}
function gdwpm_cekbok_opsi_buang_file_eksen(){
		document.getElementById("gdwpm_kotak_buang_file").style.display = "block";
		document.getElementById("gdwpm_kotak_buang_folder").style.display = "none";
}
jQuery(function(){
        jQuery('#dialog-buang-folder').dialog({
            autoOpen: false,
            modal: true,
            width: 350,
            resizable: false,
            buttons: {
                "Yes, sure!": function() {
                    document.gdwpm_form_buang_folder.submit();
                },
                "Cancel": function() {
                    jQuery(this).dialog("close");
                }
            }
        });
         
        jQuery('form#gdwpm_form_buang_folder').submit(function(e){
            e.preventDefault();
 
            jQuery('#dialog-buang-folder').dialog('open');
        });
		
        jQuery('#dialog-buang-berkas').dialog({
            autoOpen: false,
            modal: true,
            width: 350,
            resizable: false,
            buttons: {
                "Yes, I do!": function() {
                    document.gdwpm_form_buang_berkas.submit();
                },
                "Cancel": function() {
                    jQuery(this).dialog("close");
                }
            }
        });
         
        jQuery('form#gdwpm_form_buang_berkas').submit(function(e){
            e.preventDefault();
 
            jQuery('#dialog-buang-berkas').dialog('open');
        });
});
</script>
<?php }else{ ?>
			<div id="tabs-6">
				<p>
					There's no folder exists/detected in the "Incoming" or "Shared with me" area in your Google Drive or this current user (<?php echo $gdwpm_opt_akun[2];?>) have no (access rights to read) folder.<br/>
					For more info about "Incoming" or "Shared with me", please visit <dfn>https://support.google.com/drive/answer/2375057?hl=en</dfn>.
				</p>
				<p>
					Once the folder created, your folder will be listed in the "Incoming" or "Shared with me" area <dfn>https://drive.google.com/drive/#incoming</dfn>.
				</p>
				<p>
					This plugin requires at least 1 folder to store your files.
				</p>
				<form name="gdwpm_form_gawe_folder" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
					<?php $gdwpm_gawe_folder_nonce = wp_create_nonce( "gdwpm_gawe_folder_nonce" ); ?>
					<input type="hidden" name="gdwpm_gawe_folder_nonce" value="<?php echo $gdwpm_gawe_folder_nonce;?>">
					<p>
						Folder Name: <input type="text" name="gdwpm_gawe_folder" value="" placeholder="Alphanumeric only"> <button id="simpen_gawe_folder"><?php _e('Create Folder') ?></button>
					</p>
				</form>
			</div>
<?php } ?>
		</div>
	</div>
 <?php if(!empty($foldercek)) { ?>
		<h3>Galleries [Alpha: experimental]</h3>
	<div>
		<?php
			$gdwpm_url_tab_albums = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=albums&gdwpm_tabulasi_albums_nonce=') . wp_create_nonce( "gdwpm_tabulasi_albums_nonce" );
			$gdwpm_url_tab_galleries = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=galleries&gdwpm_tabulasi_galleries_nonce=') . wp_create_nonce( "gdwpm_tabulasi_galleries_nonce" );
		?>
		<div id="gdwpm-albums" style="margin:0 -12px 0 -12px;">
			<ul>
				<li><a href="<?php echo $gdwpm_url_tab_albums; ?>"><span style="float:left" class="ui-icon ui-icon-contact"></span>&nbsp;Albums</a></li>
				<li><a href="<?php echo $gdwpm_url_tab_galleries; ?>"><span style="float:left" class="ui-icon ui-icon-image"></span>&nbsp;Galleries</a></li>
				<li><a href="#gdwpm-albums-3"><span style="float:left" class="ui-icon ui-icon-cart"></span>&nbsp;Gallery Creator</a></li>
			</ul>
			<!-- <div id="gdwpm-albums-1"></div> -->
			<!-- <div id="gdwpm-albums-2"></div> -->
			<div id="gdwpm-albums-3">
				<p style="display: flex; align-items: center;">Select folder: &nbsp;<?php echo str_replace('folder_pilian', 'folder_pilian_file_gal', $folderpil); ?> &nbsp;<select id="pilihMaxResgal">
				<?php for($i=1;$i<=10;$i++){$inum = $i * 10;?>
				<option value="<?php echo $inum;?>"><?php echo $inum;?> items/page</option>				
				<?php } ?>
				</select> &nbsp;<button id="gdwpm_file_gallery" name="gdwpm_file_gallery"><?php _e('Get Files') ?></button> &nbsp;&nbsp;
				<p>		
				<p>
					<span class="sukses_gal">Please select folder and click Get Files, to show all files belongs to it.
				</span>
				</p>
				<div style="display: none" id="gdwpm_loading_gbr_gal">
				  <center><img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_256.gif', __FILE__ );?>" /><br />Please wait...</center>
				</div>
				<div id="hasil_gal"></div>
				<div id="vaginasi_gal" style="text-align:center;margin-top:25px;"></div>
				<div style="display: none" id="gdwpm_masuk_gallery_teks"><p>Collect your images to create a new Gallery.</p>
					<p><span style="display: none" id="gdwpm_masuk_gallery_anim">
							<img src="<?php echo plugins_url( '/images/animation/loading-bar-image.gif', __FILE__ );?>" />
						</span>
						<button id="gdwpm_berkas_masuk_gallery" name="gdwpm_berkas_masuk_gallery">Add to Collector</button>&nbsp;&nbsp;&nbsp; 
						<span style="display: none" id="gdwpm_add_to_gal_gbr">
							<img src="<?php echo plugins_url( '/images/animation/loading-bar-image.gif', __FILE__ );?>" />
						</span>
						<span id="gdwpm_info_masuk_gallery"></span>
					</p>
				</div>
				<br/>
				<div id="gallery_box" class="ui-widget-content ui-helper-clearfix ui-corner-all" style="padding:1em 1em 3em 1em;position:relative;">
					<div class="ui-corner-all ui-widget-header" style="margin-bottom:20px;padding:0.5em;text-align:center;">Collector</div>
					<div id="gallery_holder">
					</div>
					<span id="gallery_box_info" style="display:none;position:absolute;bottom:5px;right:15px;">
						<small style="opacity:0.5;"><i>*Drag to reorder.</i></small>
					</span>
				</div>
				<br/>
				<div id="gallery_input" style="display:none;">
					<p id="gal_intermezo" style="text-align:center;">Your Gallery is ready to be published..</p>
					 <span style="display: flex; align-items: center;"><label for="gallery_category" style="display:inline-block;width:110px;">Album </label>: &nbsp;<select id="old_album">
					<?php
						$terms = get_terms( 'gdwpm_album' );
						if ( ! empty( $terms ) && ! is_wp_error( $terms ) ){
							foreach ( $terms as $term ) {
					?>		
						<option value="<?php echo $term->name;?>"><?php echo $term->name;?></option>				
					<?php 	}
						}else{ ?>
						<option value="Uncategorized">Uncategorized</option>				
					<?php } ?>	
					</select> &nbsp;or create a new album : &nbsp;<input type="text" name="new_album" id="new_album" value="" size="16" placeholder="New Album Name" />
					</span><br/>
					<label for="gallery_title" style="display:inline-block;width:110px;">Title </label>: <input type="text" name="gallery_title" id="gallery_title" value="" size="60" placeholder="Gallery Title" />
					<br/><br/>
					<span style="display: flex; align-items: center;">
					<label for="gallery_style" style="display:inline-block;width:110px;">Images List </label>: &nbsp;<select id="css_style"><option value="default">Default</option><option value="justified">Justified</option></select></span>
					<br/>	
					<span id="css_style_opt" style="display: flex; align-items: center;">
						<label for="css_style_default" style="margin-left:140px;display:inline-block;width:150px;">Number of columns </label>: &nbsp;<select id="css_style_default">
						<?php for($kenyot=3;$kenyot<10;$kenyot++){ ?>
							<option value="<?php echo $kenyot;?>"><?php echo $kenyot;?></option><?php } ?></select>
					</span>
					
					<span id="css_style_opt1">
						<span style="display: flex; align-items: center;">
							<label for="css_justified_margins" style="margin-left:140px;display:inline-block;width:100px;">margins</label>:&nbsp;<select id="css_justified_margins"><?php for($ciblek=1;$ciblek<11;$ciblek++){ ?>
							<option value="<?php echo $ciblek;?>"><?php echo $ciblek;?> px</option><?php } ?></select>
						</span><br/>
						<span style="display: flex; align-items: center;">
							<label for="css_justified_row" style="margin-left:140px;display:inline-block;width:100px;">rowHeight</label>:&nbsp;<select id="css_justified_row"><?php for($jemek=5;$jemek<20;$jemek++){ ?>
							<option value="<?php echo $jemek * 10;?>"><?php echo $jemek * 10;?> px</option><?php } ?>
							</select>
						</span><br/>
						<span style="display: flex; align-items: center;">
							<label for="css_justified_last" style="margin-left:140px;display:inline-block;width:100px;">lastRow</label>:&nbsp;<select id="css_justified_last"><option value="justify">Justify</option><option value="nojustify">Nojustify</option><option value="hide">Hide</option></select>
						</span>
					</span>
					<br/>					
					<span style="display: flex; align-items: center;">
					<label for="css_effect" style="display:inline-block;width:110px;">Effect </label>: &nbsp;<select id="css_effect"><option value="default">Default</option><option value="blackwhite">Black & White</option><option value="lighthover">Light on Hover</option></select>
					</span><br/>
					
					<span style="display: flex; align-items: center;">
					<label for="gallery_jquery" style="display:inline-block;width:110px;">Image Viewer </label>: &nbsp;<select id="gallery_jquery"><option value="lightbox">Lightbox</option></select>
					</span><br/><br/>
					<input type="hidden" id="gallery_id_edit" value="" />
					<button id="gdwpm_new_gallery" class="gdwpm_bikin_gallery">Create Gallery</button>
					<button id="gdwpm_edit_gallery" class="gdwpm_bikin_gallery" style="display:none;">Update Gallery</button>
					<span style="margin-left:300px;" id="gdwpm_reset_gallery"><a href="#"><small>Reset / Cancel</small></a></span>
				</div>
				<div id="gdwpm_creating_128" style="display:none;text-align:center;">
				<img src="<?php echo plugins_url( '/images/animation/gdwpm_loader_128.gif', __FILE__ );?>"><br/>Please wait..</div>
				<div id="gallery_input_info">		</div>
			</div>
		</div>
	</div>
<?php } ?>
<?php } ?>
		<h3>Settings</h3>
	<div>
		<?php
			$gdwpm_tabulasi_themeset_nonce = wp_create_nonce( "gdwpm_tabulasi_themeset_nonce" );
			$gdwpm_url_tab_themeset = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=themeset&gdwpm_tabulasi_themeset_nonce=') . $gdwpm_tabulasi_themeset_nonce;
		?>
		<div id="gdwpm-settingtabs" style="margin:0 -12px 0 -12px;">
			<ul>
				<li><a href="#gdwpm-settingtabs-1"><span style="float:left" class="ui-icon ui-icon-key"></span>&nbsp;Google Drive API Key</a></li>
				<li><a href="<?php echo $gdwpm_url_tab_themeset; ?>"><span style="float:left" class="ui-icon ui-icon-video"></span>&nbsp;Themes</a></li>
			</ul>
			<div id="gdwpm-settingtabs-1">
			
				<table id="gdwpm_form_konci">
					<tr>
						<td>
				<form name="gdwpm_isi_akun" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
							Google Email
						</td>
						<td>: </td>
						<td>
							<input type="text" name="gdwpm_imel" value="<?php echo $gdwpm_opt_akun[0];?>"  title="Email Account of this Api Project. eg: yourname@gmail.com" size="25">
						</td>
					</tr>
					<tr>
						<td>
							Client ID
						</td>
						<td>: </td>
						<td>
							<input type="text" name="gdwpm_klaen_aidi" value="<?php echo $gdwpm_opt_akun[1];?>"  title="eg: 123456789.apps.googleusercontent.com" size="45">
						</td>
					</tr>
					<tr>
						<td>
							Service Account Name
						</td>
						<td>: </td>
						<td>
							<input type="text" name="gdwpm_nama_service" value="<?php echo $gdwpm_opt_akun[2];?>"  title="eg: 123456789@developer.gserviceaccount.com" size="45">
						</td>
					</tr>
					<tr>
						<td>
					Private Key Url Path
						</td>
						<td>: </td>
						<td>
							<input type="text" name="gdwpm_kunci_rhs" value="<?php echo $gdwpm_opt_akun[3];?>"  title="eg: http://yourdomain.com/path/to/123xxx-privatekey.p12" size="65">
						</td>
					</tr>
				</table>
					<br />
					
					<?php $gdwpm_akun_nonce = wp_create_nonce( "gdwpm_akun_nonce" ); ?>
			
						<input type="hidden" name="gdwpm_akun_nonce" value="<?php echo $gdwpm_akun_nonce;?>">
						<p style="margin-left:35px;">
							<button type="submit" id="simpen_gdwpm_akun"><?php _e('Save') ?></button>
						</p>		
				</form>
			</div>
		</div>
	</div>
<?php }else{$cek_kunci = 'false';} ?>
		<h3>Documentation</h3>
	<div>
		<?php
			$gdwpm_tabulasi_nonce = wp_create_nonce( "gdwpm_tabulasi_ajax" );
			$gdwpm_url_tab_dok = admin_url( 'admin-ajax.php?action=gdwpm_on_action&gdwpm_tabulasi=apidoku&gdwpm_tabulasi_nonce=') . $gdwpm_tabulasi_nonce;
		?>
		<div id="doktabs" style="margin:0 -12px 0 -12px;">
			<ul>
				<li><a href="#doktabs-1"><span style="float:left" class="ui-icon ui-icon-note"></span>&nbsp;Requirements</a></li>
				<li><a href="<?php echo $gdwpm_url_tab_dok; ?>"><span style="float:left" class="ui-icon ui-icon-suitcase"></span>&nbsp;Google Drive API</a></li>
			</ul>
			<div id="doktabs-1">
				<h3>Minimum requirements</h3>
				<p>
					PHP <?php echo MINPHP_GDWPM;?> with cURL enabled.
				</p>
				<p>
					<span style="float:left" class="ui-icon ui-icon-info"></span>&nbsp;Your PHP version is <b><?php echo phpversion();?></b> and cURL <?php if(function_exists('curl_version')){$curlver = curl_version(); echo 'version is '.$curlver['version'];}else{echo 'was disabled';} ?>.
				</p>
			</div>
		</div>
	</div>
		<h3>About</h3>
	<div>
		<p>
			<?php echo NAMA_GDWPM;?> current installed version is <?php echo VERSI_GDWPM;?>
		</p>
		<p>
			<?php echo NAMA_GDWPM;?> was created and developed by Moch Amir. <br />It is licensed as Free Software under GNU General Public License 2 (GPL 2).
			You can find more information about <?php echo NAMA_GDWPM;?> on its page in the WordPress Plugin Directory. Please rate and review the plugin in the WordPress Plugin Directory.
		</p>
		<p>
			If you have any question, there's support forum provided in its page too. Before asking for support, please carefully read the Frequently Asked Questions, where you will find answers to the most common questions, and search through the forums.
		</p>
		<p>
			If you do not find an answer there, please open a new thread in the WordPress Support Forums.
		</p>
		<p style="margin-top:57px;">
			Special thanks to everyone for the donations, you are one of my reasons why this plugin is actively maintained and updated.
		</p>
		<p>
			Thank You!
		</p>
		<small>
			Credits:
			<ul>
				<li>Google Drive API & products are owned by Google inc.</li> 
				<li>Table Style credit to R. Christie (SmashingMagazine).</li> 
				<li>DriveServiceHelper Class credit to Lukasz Kujawa.</li> 
				<li>JQuery Upload credit to PLUpload by Moxiecode Systems AB.</li> 
				<li>JQuery User Interface by JQueryUI.</li> 
				<li>Alternative openssl sign function by Rochelle Alder.</li> 
				<li>Lightbox by Lokesh Dhakar.</li> 
				<li>Justified Gallery by Miro Mannino.</li> 
			</ul>
		</small>
	</div>
</div>
<div id="dialog-message" title="Warning" style="display: none;">
  <p>
    <span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 50px 0;"></span>
    This plugin requires api key to authorize your drive.
  </p>
  <p>
    Click the documentation tab for more info.
  </p>
</div>
<div id="gdwpm_pringatan_versi_php" title="Warning" style="display: none;">
  <p>
    <span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 50px 0;"></span>
    Sorry, You can't use this plugin with your current PHP version.<br>This plugin requires <b>PHP <?php echo MINPHP_GDWPM;?></b>.<br>
	Your PHP version is <b><?php echo phpversion();?></b>.
  </p>
  <p>
    Please upgrade your PHP to <?php echo MINPHP_GDWPM;?>.
  </p>
</div>
<script>
   function gantiBaris(sel, tableid) {
    var jumlahBaris = sel.value;
    jumBaris(jumlahBaris, tableid);
   }
   function remove_itemgal(e) {
		e.parentNode.parentNode.removeChild(e.parentNode);
		if (jQuery('#gallery_holder > div').length > 1){
			jQuery('#gallery_input').show();
		}else{
			jQuery('#gallery_input').hide();
			jQuery('#gallery_box_info').hide();
		}
   }

  jQuery(function() {
    jQuery( "#gdwpm_pringatan_versi_php" ).dialog({
      autoOpen: <?php echo $gdwpm_cek_php;?>,
      modal: true,
      width: 350,
      resizable: false,
      buttons: {
        Ok: function() {
          jQuery( this ).dialog( "close" );
        }
      }
    });
    jQuery( "#dialog-message" ).dialog({
      autoOpen: <?php if(isset($errorkon)){echo 'false';}else{echo $cek_kunci;}?>,
      modal: true,
      width: 350,
      resizable: false,
      buttons: {
        Ok: function() {
          jQuery( this ).dialog( "close" );
        }
      }
    });
  
    jQuery( "#gallery_holder" ).sortable()
    jQuery( "#gallery_holder" ).disableSelection()
    jQuery( "#golek_seko_folder, #gdwpm_file_dr_folder, #gdwpm_file_gallery" )
      .button({
      icons: {
        primary: "ui-icon-circle-arrow-s"
      }
    })
  
    jQuery( "#gdwpm_buang_folder" )
      .button({
      icons: {
        primary: "ui-icon-trash"
      }
    })
	
    jQuery( "#gdwpm_berkas_buang" )
      .button({
      icons: {
        primary: "ui-icon-trash"
      }
    })
	
    jQuery( "#gdwpm_tombol_info_folder_baru" )
      .button({
      icons: {
        primary: "ui-icon-refresh"
      }
    })
	
    jQuery( "#gdwpm_berkas_masuk_perpus" )
      .button({
      icons: {
        primary: "ui-icon-circle-plus"
      }
    })
	
    jQuery( "#gdwpm_berkas_masuk_gallery" )
      .button({
      icons: {
        primary: "ui-icon-cart"
      }
    })
	
    jQuery( ".gdwpm_bikin_gallery" )
      .button({
      icons: {
        primary: "ui-icon-image"
      }
    })
	
    jQuery( "#gdwpm_tombol_bk_folder" )
      .button({
      icons: {
        primary: "ui-icon-folder-open"
      }
    })
	
    jQuery( "#gdwpm_tombol_bersih" )
      .button({
      icons: {
        primary: "ui-icon-grip-dotted-horizontal"
      }
    })
	
    jQuery( "#gdwpm_tombol_upload" )
      .button({
      icons: {
        primary: "ui-icon-arrowthickstop-1-n"
      }
    })
	
    jQuery( "#simpen_gdwpm_akun" )
      .button({
      icons: {
        primary: "ui-icon-person"
      }
    })
		
    jQuery( "#simpen_gawe_folder" )
      .button({
      icons: {
        primary: "ui-icon-folder-collapsed"
      }
    })  
    jQuery( "#gdwpm_tombol_repair_folder" )
      .button({
      icons: {
        primary: "ui-icon-wrench"
      }
    })
<?php if ( version_compare( get_bloginfo('version'), '4.0', '>' ) ) { ?>		
	jQuery( "#pilihMaxRes" )
	  .selectmenu();
	jQuery( "#pilihMaxResdel" )
	  .selectmenu();
	jQuery( "#pilihMaxResgal" )
	  .selectmenu();
	  
	jQuery( "#folder_pilian" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowpil" );
	jQuery( "#folder_pilian_aplod" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowapl" );
	jQuery( "#buang_folder_pilian" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowbua" );
	jQuery( "#repair_folder_pilian" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowrep" );
	jQuery( "#folder_pilian_file_del" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowdel" );
	jQuery( "#folder_pilian_file_gal" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowgal" );
	jQuery( "#old_album, #css_effect, #css_style_default, #css_justified_margins, #css_justified_row, #css_justified_last, #gallery_jquery" )
	  .selectmenu()
	  .selectmenu( "menuWidget" )
		.addClass( "overflowolal" );
	jQuery( "#css_style" )
	  .selectmenu({ change: function( event, ui ) { 
		if(jQuery( this ).val() == 'default'){
			jQuery('#css_style_opt').show();
			jQuery('#css_style_opt1').hide();
		}else{
			jQuery('#css_style_opt').hide();
			jQuery('#css_style_opt1').show();
		}
	}})
	  .selectmenu( "menuWidget" )
		.addClass( "overflowolal" );
<?php } ?>
	jQuery('input').addClass("ui-corner-all");
  });
</script>
<?php

}

function gdwpm_ijin_masuk_perpus($jenis_berkas, $nama_berkas, $id_berkas, $deskrip_berkas, $jeneng_folder = 'Uncategorized', $img_sizes = '', $metainfo = ''){
	// ADD TO LIBRARY
	$gdwpm_lebar_gbr = ''; $gdwpm_tinggi_gbr = '';
	$gdwpm_fol_n_id = 'G_D_W_P_M-file_ID/' . $id_berkas;
	$gdwpm_fol_n_idth = '';
	
	if(strpos($jenis_berkas, 'image') !== false){
		if(!empty($img_sizes) || $img_sizes != ''){
			// selfWidth:xx selfHeight:xx thumbId:xxx thumbWidth:xx thumbHeight:xx
			preg_match_all('/(\w+):("[^"]+"|\S+)/', $img_sizes, $matches);
			$img_meta = array_combine($matches[1], $matches[2]);
			if(array_key_exists('thumbId', $img_meta)){
				$gdwpm_fol_n_idth = $img_meta['thumbId'];
			}
			if(array_key_exists('selfWidth', $img_meta)){
				$gdwpm_lebar_gbr = $img_meta['selfWidth'];
			}
			if(array_key_exists('selfHeight', $img_meta)){
				$gdwpm_tinggi_gbr = $img_meta['selfHeight'];
			}
		}else{
			$gdwpm_ukur_gambar = getimagesize('https://docs.google.com/uc?id=' . $id_berkas. '&export=view');
			$gdwpm_lebar_gbr = $gdwpm_ukur_gambar[0];
			$gdwpm_tinggi_gbr = $gdwpm_ukur_gambar[1];
		}
		$gdwpm_fol_n_id = 'G_D_W_P_M-ImageFile_ID/' . $id_berkas;
		
		$gdwpm_dummy_fol = get_option('gdwpm_dummy_folder');
		
		if($gdwpm_dummy_fol == 'checked'){
			$gdwpm_ekst_gbr = explode('/', $jenis_berkas);
			if($gdwpm_ekst_gbr[1] == 'png' || $gdwpm_ekst_gbr[1] == 'gif' || $gdwpm_ekst_gbr[1] == 'bmp'){
				$gdwpm_fol_n_id = 'gdwpm_images/' . $id_berkas . '.' . $gdwpm_ekst_gbr[1];
				$gdwpm_fol_n_idth .= '.' . $gdwpm_ekst_gbr[1];
			}else{
				$gdwpm_fol_n_id = 'gdwpm_images/' . $id_berkas . '.jpg';
				$gdwpm_fol_n_idth .= '.jpg';
			}
		}
		if(empty($metainfo) || $metainfo == ''){
			$meta = array('aperture' => 0, 'credit' => '', 'camera' => '', 'caption' => $nama_berkas, 'created_timestamp' => 0, 'copyright' => '',  
					'focal_length' => 0, 'iso' => 0, 'shutter_speed' => 0, 'title' => $nama_berkas); 
		}else{
			$meta = json_decode($metainfo, true);
		}
		$metadata = array("image_meta" => $meta, "width" => $gdwpm_lebar_gbr, "height" => $gdwpm_tinggi_gbr, 'file' => $gdwpm_fol_n_id, "gdwpm"=>TRUE); 
		
		if(isset($img_meta['thumbId'])){
			$metadata['sizes'] = array('thumbnail' => array('file' => $gdwpm_fol_n_idth, 'width' => $img_meta['thumbWidth'], 'height' => $img_meta['thumbHeight']));
		}
	}elseif(strpos($jenis_berkas, 'video') !== false){
		$metadata = '';
		if(!empty($metainfo) || $metainfo != ''){
			$metadata = json_decode($metainfo, true);
		}
	}elseif(strpos($jenis_berkas, 'audio') !== false){
		$metadata = '';
		if(!empty($metainfo) || $metainfo != ''){
			$metadata = json_decode($metainfo, true);
		}
	}else{
		$metadata = '';
	}
	
	$attachment = array( 'post_mime_type' => $jenis_berkas, 'guid' => $gdwpm_fol_n_id,
				'post_parent' => 0,	'post_title' => $nama_berkas, 'post_content' => $deskrip_berkas);
	$attach_id = wp_insert_attachment( $attachment, $gdwpm_fol_n_id, 0 );
		
    wp_update_attachment_metadata( $attach_id,  $metadata);	
	
$gdwpm_opsi_kategori = get_option('gdwpm_opsi_kategori_dr_folder'); 
	if($attach_id != 0 && $gdwpm_opsi_kategori == 'checked')
	{//TAXONOMY GANTI MASUK LEWAT SINI AJAH, 
		wp_set_object_terms( $attach_id, $jeneng_folder, 'gdwpm_category' );
	}
}

///////////// AJAX EKSYEN /////////////// ajax admin url =====>  gdwpm_on_action
add_action( 'wp_ajax_gdwpm_on_action', 'gdwpm_action_callback' );
function gdwpm_action_callback() {
	global $wpdb, $cek_kunci, $gdwpm_opt_akun, $gdwpm_service, $gdwpm_apiConfig;
	$gdwpm_opt_akun = get_option('gdwpm_akun_opt'); // imel, client id, gdwpm_service akun, private key
	require_once 'gdwpm-api/Google_Client.php';
	require_once 'gdwpm-api/contrib/Google_DriveService.php';

	if(isset($_POST['folder_pilian'])){
	$gdwpm_apiConfig['use_objects'] = true;
	$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );
		$fld = $_POST['folder_pilian'];
		if(isset($_POST['pagetoken'])){
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres'], $_POST['pagetoken']);
		}else{
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres']);
		}
		
		//array($daftarfile, $i, $totalhal, $halterlihat)
		if($daftar_berkas[1] <= 0){ // total files < 1
			if($daftar_berkas[2] > 1){ // total halaman > 1
				if($daftar_berkas[3] == $daftar_berkas[2]){
					echo '<div class="sukses"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This page is empty.</p></div>';
					echo $daftar_berkas[0];
				}else{
					echo '<div class="sukses"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>Your request contains multiple pages, click the page number below.</p></div>';	
					echo $daftar_berkas[0];
				}
			}else{
				echo '<div class="sukses"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This folder is empty.</p></div>';
			}
		}else{		
			echo '<div class="sukses"><p>Folder ID: <strong>'.$fld.'</strong> and items on page: <strong>'.$daftar_berkas[1].'</strong>.<select style="float:right;" id="pilihBaris" onchange="gantiBaris(this, \'paginasi\');"><option value="5">5 rows/sheet</option><option value="10" selected="selected">10 rows/sheet</option>   <option value="15">15 rows/sheet</option><option value="20">20 rows/sheet</option><option value="25">25 rows/sheet</option><option value="30">30 rows/sheet</option><option value="40">40 rows/sheet</option><option value="50">50 rows/sheet</option></select></p></div>';	
			echo $daftar_berkas[0];
		}
	}elseif(isset($_POST['folder_pilian_file_gal'])){
	$gdwpm_apiConfig['use_objects'] = true;
	$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );
		$fld = $_POST['folder_pilian_file_gal'];
		if(isset($_POST['pagetoken'])){
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres'], $_POST['pagetoken'], 'gall');
		}else{
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres'], null, 'gall');
		}
		
		//array($daftarfile, $i, $totalhal, $halterlihat)
		if($daftar_berkas[1] <= 0){ // total files < 1
			if($daftar_berkas[2] > 1){ // total halaman > 1
				if($daftar_berkas[3] == $daftar_berkas[2]){
					echo '<div class="sukses_gal"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This page is empty.</p></div>';
					echo $daftar_berkas[0];
				}else{
					echo '<div class="sukses_gal"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>Your request contains multiple pages, click the page number below.</p></div>';	
					echo $daftar_berkas[0];
				}
			}else{
				echo '<div class="sukses_gal"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This folder is empty.</p></div>';
			}
		}else{		
			echo '<div class="sukses_gal"><p>Folder ID: <strong>'.$fld.'</strong> and items on page: <strong>'.$daftar_berkas[1].'</strong>.<select style="float:right;" id="pilihBaris_gal" onchange="gantiBaris(this, \'paginasi_gal\');"><option value="5">5 rows/sheet</option><option value="10" selected="selected">10 rows/sheet</option>   <option value="15">15 rows/sheet</option><option value="20">20 rows/sheet</option><option value="25">25 rows/sheet</option><option value="30">30 rows/sheet</option><option value="40">40 rows/sheet</option><option value="50">50 rows/sheet</option></select></p></div>';	
			echo $daftar_berkas[0];
		}				
	}elseif(isset($_POST['folder_pilian_file_del'])){
	$gdwpm_apiConfig['use_objects'] = true;
	$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );
		$fld = $_POST['folder_pilian_file_del'];
		if(isset($_POST['pagetoken'])){
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres'], $_POST['pagetoken'], 'checkbox');
		}else{
			$daftar_berkas = $gdwpm_service->getFilesInFolder($fld, $_POST['pilmaxres'], null, 'checkbox');
		}
		$daftarfile = $daftar_berkas[0];
		$i = $daftar_berkas[1];
		
		if($daftar_berkas[1] <= 0){
			if($daftar_berkas[2] > 1){ // total halaman > 1
				if($daftar_berkas[3] == $daftar_berkas[2]){
					echo '<div class="sukses_del"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This page is empty.</p></div>';
					echo $daftarfile;
				}else{
					echo '<div class="sukses_del"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>Your request contains multiple pages, click the page number below.</p></div>';
					echo $daftarfile;
				}
			}else{
				echo '<div class="sukses_del"><p style="text-align:center;"><img src="' . plugins_url( '/images/animation/gdwpm_breaker_256.png', __FILE__ ) . '"><br/>This folder is empty.</p></div>';
			}
		}else{		
			echo '<div class="sukses_del"><p>Folder ID: <strong>'.$fld.'</strong> and items on page: <strong>'.$i.'</strong>.</p></div>';
			echo $daftarfile;
		}			
		
	}elseif(isset($_POST['dataimages'])){
		// insert gallery
		$unikid = strtotime("now");
		$postjudul = base64_decode($_POST['judul']);
		$postkat = base64_decode($_POST['album']);
		$postkontenarr = explode(' , ', $_POST['dataimages']); 		
		//$postkonten = "<div class='gdwpmgallery'>";		//lightbox
		//$postkonten = '<div class="fotorama" data-allowfullscreen="native" data-loop="true" data-autoplay="true">';	//fotorama
		if(isset($_POST['css_effect'])){
			$css_effect = $_POST['css_effect']; //default/blackwhite/lighthover
		}else{
			$css_effect = 'default';
		}
		switch ($css_effect) {
			case "default":
				$efekgbr = '';
				break;
			case "blackwhite":
				$efekgbr = ' gdwpm-bwefek';
				break;
			case "lighthover":
				$efekgbr = ' gdwpm-lightefek';
				break;
			default:
				$efekgbr = '';
		}
		if(isset($_POST['css_style'])){
			$css_style = $_POST['css_style']; //default/blackwhite/lighthover
		}else{
			$css_style = 'default';
		}
		$jmlkolom = 3;
		$justproper = '1 | 90 | justify';
		if($css_style == 'justified'){
			if(isset($_POST['css_style_justified'])){
				$justproper = $_POST['css_style_justified'];
			}
			$postkonten = '<div id="gdwpmgal-'.$unikid.'" class="gdwpmGallery1'.$efekgbr.'" data-gal1="'.$justproper.'">'; //Justified + lightbox
			$dataSource = array();
			foreach($postkontenarr as $key => $val){// id, thumbid, flnme | captionimg
				$indidata = explode(' | ', $val); 
				$indidatadecode = json_decode(base64_decode($indidata[0]), true);
				$captionimg = base64_decode($indidata[1]);
				if($key == 0){$sampleImage = $indidatadecode[1];}
				//$dataSource[] = array($indidatadecode[0], $indidatadecode[1]) . ' | ' . $captionimg;
				//$postkonten .= '<div class="galleryItem"><a href="//www.googledrive.com/host/'.$indidatadecode[0].'" data-lightbox="'.$unikid.'" data-title="'.$captionimg.'"><img src="//www.googledrive.com/host/'.$thumbId.'" alt="'.$indidatadecode[1].'"/></a></div>';      //lightbox
				//$postkonten .= '<a href="//www.googledrive.com/host/'.$thumbId.'" data-full="//www.googledrive.com/host/'.$indidatadecode[0].'" data-caption="'.$captionimg.'"></a>';	//fotorama
				$boxttl = ''; $jusalt = '';
				if(!empty($captionimg)){$boxttl = 'data-title="'.$captionimg.'" '; $jusalt = 'alt="'.$captionimg.'" ';}
				$postkonten .= '<a href="https://www.googledrive.com/host/'.$indidatadecode[0].'" '.$boxttl.'data-lightbox="'.$unikid.'"><img '.$jusalt.'src="https://www.googledrive.com/host/'.$indidatadecode[1].'" /></a>';	//justified
			}
		}else{
			if(isset($_POST['css_style_default'])){
				$jmlkolom = $_POST['css_style_default'];
			}
			$postkonten = '<div class="gdwpmGallery0'.$efekgbr.' gallery gallery-columns-'.$jmlkolom.'">';
			$dataSource = array();
			foreach($postkontenarr as $key => $val){// id, thumbid, flnme | captionimg
				$indidata = explode(' | ', $val); 
				$indidatadecode = json_decode(base64_decode($indidata[0]), true);
				$captionimg = base64_decode($indidata[1]);
				if($key == 0){$sampleImage = $indidatadecode[1];}
				$postkonten .= '<dl style="width:' . 100/$jmlkolom . '%;" class="gallery-item"><dt class="gallery-icon"><a href="https://www.googledrive.com/host/' .$indidatadecode[0]. '" data-lightbox="'.$unikid.'" data-title="'.$captionimg.'"><img class="attachment-thumbnail img-rounded" src="https://www.googledrive.com/host/'.$indidatadecode[1].'" alt="'. $indidatadecode[2] .'" width="200" /></a></dt><dd class="wp-caption-text gallery-caption">'.$captionimg.'</dd></dl>';
				if($key > 0 && ($key + 1) % $jmlkolom == 0 || $key == (count($postkontenarr) - 1)){ $postkonten .= '<br style="clear: both" />'; }
			}
		}
		$postkonten .= '</div>';
		
		//$postkonten = base64_decode($_POST['dataimages']);
		
		$gallery = array('post_title' => wp_strip_all_tags($postjudul), 'post_name' => sanitize_title_with_dashes($postjudul), 'post_content' => $postkonten, 'post_status'   => 'publish', 'post_author' => 1, 'post_type' => 'gdwpm_galleries');
		if(empty($_POST['galid']) || $_POST['galid'] == ''){
			$galeri_id = wp_insert_post($gallery);
			$infokita = 'created';
		}else{
			$gallery['ID'] = $_POST['galid'];
			$galeri_id = wp_update_post($gallery);
			$infokita = 'saved';
		}
		if($galeri_id != 0){
			wp_set_object_terms( $galeri_id, $postkat, 'gdwpm_album' );
			update_post_meta( $galeri_id, 'sampleImage', $sampleImage );
			$baseData = base64_encode(json_encode(array($galeri_id, wp_strip_all_tags($postjudul), $postkat, $css_style, $css_effect, 'lightbox', $jmlkolom, $justproper)));
			$baseData .= ' items:' . $_POST['dataimages'];
			update_post_meta( $galeri_id, 'base64data', $baseData );
			echo '<strong>'.$postjudul.'</strong> successfully '.$infokita.'. Gallery ID: <strong>'.$galeri_id.'</strong>. Shortcode: <code>[gdwpm-gallery id="'.$galeri_id.'"]</code>. Permalink: <a href="' . get_permalink($galeri_id) . '" title="Open '.$postjudul.' in new window" target="_blank">' . get_permalink($galeri_id) . '</a>';
		}else{
			echo 'Cannot create gallery.';
		}
	}elseif(isset($_POST['masuk_perpus'])){
		//$gdwpm_berkas_terpilih_arr = explode(' | ', $_POST['masuk_perpus']);
		$gdwpm_berkas_terpilih_arr = json_decode(base64_decode($_POST['masuk_perpus']), true);
		gdwpm_ijin_masuk_perpus(sanitize_mime_type($gdwpm_berkas_terpilih_arr[0]), $gdwpm_berkas_terpilih_arr[1], $gdwpm_berkas_terpilih_arr[2], $gdwpm_berkas_terpilih_arr[3], $gdwpm_berkas_terpilih_arr[4], $gdwpm_berkas_terpilih_arr[5]);
				
		echo '<strong>'.$gdwpm_berkas_terpilih_arr[1] . '</strong> has been added to your Media Library';
		
	}elseif(isset($_POST['gdwpm_ukuran_preview_lebar']) || isset($_POST['gdwpm_ukuran_preview_tinggi'])){
		$nonce = $_REQUEST['gdwpm_override_nonce'];
		
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_override_dir' ) ) {
			wp_die('<strong>Oops... failed!</strong>'); 
		} else {
			if(!$gdwpm_ukuran_preview){$gdwpm_ukuran_preview = get_option('gdwpm_ukuran_preview');}
			if (ctype_digit($_POST['gdwpm_ukuran_preview_lebar']) && ctype_digit($_POST['gdwpm_ukuran_preview_tinggi'])) {
				if($_POST['gdwpm_ukuran_preview_lebar'] > 20 && $_POST['gdwpm_ukuran_preview_tinggi'] > 10){
					if($_POST['gdwpm_cekbok_embed_video'] == 'checked'){
						if(isset($_POST['gdwpm_video_play_style']) && $_POST['gdwpm_ukuran_video_lebar'] > 20 && $_POST['gdwpm_ukuran_video_tinggi'] > 20 && ctype_digit($_POST['gdwpm_ukuran_video_lebar']) && ctype_digit($_POST['gdwpm_ukuran_video_tinggi'])){
							$gdwpm_ukuran_prev_arr = array($_POST['gdwpm_ukuran_preview_lebar'], $_POST['gdwpm_ukuran_preview_tinggi'], $_POST['gdwpm_cekbok_embed_video'], $_POST['gdwpm_video_play_style'], $_POST['gdwpm_ukuran_video_lebar'], $_POST['gdwpm_ukuran_video_tinggi']);
							update_option('gdwpm_ukuran_preview', $gdwpm_ukuran_prev_arr);	
							echo '<div id="info">Option saved.</div><div id="hasil">[gdwpm id="<b>YOURGOOGLEDRIVEFILEID</b>" w="<b>'.$gdwpm_ukuran_prev_arr[0].'</b>" h="<b>'.$gdwpm_ukuran_prev_arr[1].'</b>"]</div><div id="hasilvid">[gdwpm id="<b>YOURGOOGLEDRIVEFILEID</b>" video="<b>'.$gdwpm_ukuran_prev_arr[3].'</b>" w="<b>'.$gdwpm_ukuran_prev_arr[4].'</b>" h="<b>'.$gdwpm_ukuran_prev_arr[5].'</b>"]</div>';
						}else{
							echo '<div id="info"><strong>Warning:</strong> Minimum value is 20.</div><div id="hasil">[gdwpm id="GOOGLEDRIVEFILEID" w="<b>'.$gdwpm_ukuran_preview[0].'</b>" h="<b>'.$gdwpm_ukuran_preview[1].'</b>"]</div>';
						}
					}else{
							$gdwpm_ukuran_prev_arr = array($_POST['gdwpm_ukuran_preview_lebar'], $_POST['gdwpm_ukuran_preview_tinggi'], $_POST['gdwpm_cekbok_embed_video'], $gdwpm_ukuran_preview[3], $gdwpm_ukuran_preview[4], $gdwpm_ukuran_preview[5]);
							update_option('gdwpm_ukuran_preview', $gdwpm_ukuran_prev_arr);	
							echo '<div id="info">Option saved.</div><div id="hasil">[gdwpm id="<b>YOURGOOGLEDRIVEFILEID</b>" w="<b>'.$gdwpm_ukuran_prev_arr[0].'</b>" h="<b>'.$gdwpm_ukuran_prev_arr[1].'</b>"]</div>';
					}
				}else{
					echo '<div id="info"><strong>Warning:</strong> Minimum value is 10.</div><div id="hasil">[gdwpm id="GOOGLEDRIVEFILEID" w="<b>'.$gdwpm_ukuran_preview[0].'</b>" h="<b>'.$gdwpm_ukuran_preview[1].'</b>"]</div>';
				}
			}else{
				echo '<div id="info"><strong>Warning:</strong> Numeric only please.</div><div id="hasil">[gdwpm id="GOOGLEDRIVEFILEID" w="<b>'.$gdwpm_ukuran_preview[0].'</b>" h="<b>'.$gdwpm_ukuran_preview[1].'</b>"]</div>';
			}
		}
	}elseif(isset($_POST['gdwpm_cekbok_opsi_value'])){
		$nonce = $_REQUEST['gdwpm_override_nonce'];
		
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_override_dir' ) ) {
			wp_die('<strong>Oops... failed!</strong>'); 
		} else {
			$folder_bawaan = preg_replace("/[^a-zA-Z0-9]+/", " ", $_POST['gdwpm_folder_opsi_value']);
			$folder_bawaan = sanitize_text_field($folder_bawaan);
			
			if(empty($folder_bawaan) && $_POST['gdwpm_cekbok_opsi_value'] == 'checked'){
				echo 'Folder name cannot be empty!';
			}else{
				$gdwpm_cekbok = array($_POST['gdwpm_cekbok_opsi_value'], $folder_bawaan, $_POST['gdwpm_cekbok_masukperpus_override']);
				update_option('gdwpm_override_dir_bawaan', $gdwpm_cekbok);	
				echo 'Option saved.';
			}
		}
	}elseif(isset($_POST['gdwpm_cekbok_opsi_dummy'])){
		$nonce = $_REQUEST['gdwpm_override_nonce'];
		
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_override_dir' ) ) {
			die('<strong>Oops... failed!</strong>'); 
		} else {			
				update_option('gdwpm_dummy_folder', $_POST['gdwpm_cekbok_opsi_dummy']);	
				echo 'Option saved.';
		}
	}elseif(isset($_REQUEST['gdwpm_tabulasi'])){
		if($_REQUEST['gdwpm_tabulasi'] == 'opsyen'){
			$nonce = $_REQUEST['gdwpm_tab_opsi_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tab_opsi_key' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			} else {
				require_once 'google-drive-wp-media-options.php';
			}
		}elseif($_REQUEST['gdwpm_tabulasi'] == 'infosyen'){
			$nonce = $_REQUEST['gdwpm_tab_info_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tab_opsi_key' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			} else {
				require_once 'google-drive-wp-media-info.php';
			}
		}elseif($_REQUEST['gdwpm_tabulasi'] == 'apidoku'){
			$nonce = $_REQUEST['gdwpm_tabulasi_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tabulasi_ajax' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			} else {
				require_once 'google-drive-wp-media-documentation.php';
			}
		}elseif($_REQUEST['gdwpm_tabulasi'] == 'themeset'){
			$nonce = $_REQUEST['gdwpm_tabulasi_themeset_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tabulasi_themeset_nonce' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			} else {
				require_once 'google-drive-wp-media-themes.php';
			}
		}elseif($_REQUEST['gdwpm_tabulasi'] == 'albums'){
			$nonce = $_REQUEST['gdwpm_tabulasi_albums_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tabulasi_albums_nonce' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			} else {
				require_once 'google-drive-wp-media-albums.php';
			}
		}elseif($_REQUEST['gdwpm_tabulasi'] == 'galleries'){
			$nonce = $_REQUEST['gdwpm_tabulasi_galleries_nonce'];
			if ( ! wp_verify_nonce( $nonce, 'gdwpm_tabulasi_galleries_nonce' ) ) {
				wp_die('<div class="error"><p>Oops.. security check is not ok.</p></div>'); 
			} else {
				if(isset($_REQUEST['paged'])){
					$paged = $_REQUEST['paged'];
				}
				if(isset($_REQUEST['delete'])){
					$gdwpmbuang_gal = wp_delete_post(trim($_REQUEST['delete']), true);
					if($gdwpmbuang_gal){
						echo '<div class="updated"><p>Gallery ID="' . $_REQUEST['delete'] . '" successfully deleted.</p></div>';
					}else{
						echo '<div class="error"><p>Gallery ID="' . $_REQUEST['delete'] . '" cannot deleted!</p></div>';
					}
				}
				require_once 'google-drive-wp-media-galleries.php';
			}
		}else{
			wp_die('<div class="error"><p>Oops.. something goes wrong!</p></div>'); 
		}
	}elseif(isset($_POST['gdwpm_opsi_theme_css'])){
		$nonce = $_REQUEST['gdwpm_theme_setting_nonce'];
		
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_theme_setting_nonce' ) ) {
			die('<strong>Oops... failed!</strong>'); 
		} else {
			
				update_option('gdwpm_nama_theme_css', $_POST['gdwpm_opsi_theme_css']);	
				
		}
	}elseif(isset($_REQUEST['gdwpm_nonce_aplod_berkas'])){
		$nonce = $_REQUEST['gdwpm_nonce_aplod_berkas'];
		if ( ! wp_verify_nonce( $nonce, 'gdwpm_satpam_aplod_berkas' ) ) {
			die('<div class="error"><p>Oops.. security check is not ok!</p></div>'); 
			
		} else {
			if (empty($_FILES) || $_FILES["file"]["error"]) {
				wp_die('<div class="error"><p>Oops.. error, upload failed! '.$_FILES["file"]["error"].'</p></div>');
			}
			if (isset($_REQUEST["gdpwm_nama_file"])) {
				$filename = $_REQUEST["gdpwm_nama_file"];
			} elseif (!empty($_FILES)) {
				$filename = $_FILES["file"]["name"];
			} else {
				$filename = uniqid("file_");
			}
			//if(CHUNK_INTERNAL){
			$gdwpm_opsi_chunk = get_option('gdwpm_opsi_chunk');
			if($gdwpm_opsi_chunk['local']['cekbok'] == 'checked'){
			$targetDir = ini_get("upload_tmp_dir");
			$maxFileAge = 5 * 3600; // Temp file age in seconds
			// Create target dir
			if (!file_exists($targetDir)) {
				//@mkdir($targetDir);
				if (!file_exists($targetDir = sys_get_temp_dir())){
					$upload_dir = wp_upload_dir();
					if (!file_exists($targetDir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'gdwpm-tmp')) {
						@mkdir($targetDir);
					}
				}
			}

			$filePath = $targetDir . DIRECTORY_SEPARATOR . $filename;

			// Chunking might be enabled
			$chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
			$chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

			if (!is_dir($targetDir) || !$dir = opendir($targetDir)) {
				die('<div class="error"><p>Oops.. error. Failed to open temp directory.</p></div>');
			}

			while (($file = readdir($dir)) !== false) {
				$tmpfilePath = $targetDir . DIRECTORY_SEPARATOR . $file;

				// If temp file is current file proceed to the next
				if ($tmpfilePath == "{$filePath}.part") {
					continue;
				}

				// Remove temp file if it is older than the max age and is not the current file
				if (preg_match('/\.part$/', $file) && (filemtime($tmpfilePath) < time() - $maxFileAge)) {
					@unlink($tmpfilePath);
				}
			}
			closedir($dir);

			// Open temp file
			if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
				die('<div class="error"><p>Oops.. error. Failed to open output stream.</p></div>');
			}

			if (!empty($_FILES)) {
				if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
					die('<div class="error"><p>Oops.. error. Failed to move uploaded file.</p></div>');
				}

				// Read binary input stream and append it to temp file
				if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
					die('<div class="error"><p>Oops.. error. Failed to open input stream.</p></div>');
				}
			} else {        
				if (!$in = @fopen("php://input", "rb")) {
					die('<div class="error"><p>Oops.. error. Failed to open input stream.</p></div>');
				}
			}

			while ($buff = fread($in, 4096)) {
				fwrite($out, $buff);
			}

			@fclose($out);
			@fclose($in);

			}else{
				$chunks = true;
			}
			// Check if file has been uploaded
			if (!$chunks || $chunk == $chunks - 1) {
				// Strip the temp .part suffix off 
				if($filePath){
					rename("{$filePath}.part", $filePath);
				}else{					
					$filePath = $_FILES["file"]["tmp_name"];
				}
				
				if(!$gdwpm_service){
					$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );
				}

					$mime_berkas_arr = wp_check_filetype($filename);
					$mime_berkas = $mime_berkas_arr['type'];
					if(empty($mime_berkas)){$mime_berkas = $_FILES['file']['type'];}
					$folder_ortu = preg_replace("/[^a-zA-Z0-9]+/", " ", $_POST['gdpwm_nm_br']);
					$folder_ortu = trim(sanitize_text_field($folder_ortu));
					$folderId = $_POST['gdpwm_nm_id'];
					$nama_polder = $_POST['gdpwm_nm_bks'];
					
					if($folder_ortu != ''){
						//cek folder array id namafolder
						$last_folder = get_option('gdwpm_new_folder_kecing');
						if($folder_ortu != $last_folder[1]){
							$folderId = $gdwpm_service->getFolderIdByName( $folder_ortu );
							if( $folderId ) { update_option('gdwpm_new_folder_kecing', array($folderId, $folder_ortu)); }
							$nama_polder = $folder_ortu;
						}else{
							$folderId = $last_folder[0];
							$nama_polder = $last_folder[1];
						}
					}
					
					$content = $_POST['gdpwm_sh_ds'];

					if( ! $folderId ) {
							$folderId = $gdwpm_service->createFolder( $folder_ortu );
							$gdwpm_service->setPermissions( $folderId, $gdwpm_opt_akun[0] );
							update_option('gdwpm_new_folder_kecing', array($folderId, $nama_polder));
					}
				
				if(strpos($mime_berkas_arr['type'], 'image') !== false){
					// cek gambar if img auto create thumb .. array('checked', '', '122', '122', 'false');
					$gdwpm_img_thumbs = get_option('gdwpm_img_thumbs');
					// ITUNG DIMENSI
					$image = wp_get_image_editor( $filePath );
					if ( !is_wp_error( $image ) ) {
						$ukuran_asli = $image->get_size(); // $ukuran_asli['width']; $ukuran_asli['height'];
					}
					$idthumb_w_h = '';
					if ($gdwpm_img_thumbs[0] == 'checked'){
						$folderId_thumb = $gdwpm_img_thumbs[1];
						if(empty($folderId_thumb) || $folderId_thumb == ''){
							//$folderId_thumb = $gdwpm_service->getFolderIdByName( 'gdwpm-thumbnails' );
							//if(!$folderId_thumb){
							$folderId_thumb = $gdwpm_service->createFolder( 'gdwpm-thumbnails' );
							$gdwpm_service->setPermissions( $folderId_thumb, $gdwpm_opt_akun[0] );
							//}
							$gdwpm_img_thumbs[1] = trim($folderId_thumb);
							update_option('gdwpm_img_thumbs', $gdwpm_img_thumbs);
						}
						if ( $ukuran_asli ) {
							if($gdwpm_img_thumbs[4] == 'true'){
								$image->resize( $gdwpm_img_thumbs[2], $gdwpm_img_thumbs[3], true );
							}else{
								$image->resize( $gdwpm_img_thumbs[2], $gdwpm_img_thumbs[3], false );
							}
							$img = $image->save(); // path, file, mime-type
							$filename_thumb = $img['file'];
							$filePath_thumb = $img['path'];
							$mime_berkas_thumb = $img['mime-type'];
							$imgwidth_thumb = $img['width'];
							$imgheight_thumb = $img['height'];
						}
						$fileParent_thumb = new Google_ParentReference();
						$fileParent_thumb->setId( $folderId_thumb );			
						$fileId_thumb = $gdwpm_service->createFileFromPath( $filePath_thumb, $filename_thumb, $content, $fileParent_thumb );
						$gdwpm_service->setPermissions( $fileId_thumb, 'me', 'reader', 'anyone' );
						$idthumb_w_h = 'thumbId:' . $fileId_thumb . ' thumbWidth:' . $imgwidth_thumb . ' thumbHeight:' . $imgheight_thumb;
					}
					$gdwpm_sizez_meta = 'selfWidth:' . $ukuran_asli['width'] . ' selfHeight:' . $ukuran_asli['height'] . ' ' . $idthumb_w_h;
					@unlink($filename_thumb);
				}else{
					$gdwpm_sizez_meta = '';
				}				
				
				$fileParent = new Google_ParentReference();
				$fileParent->setId( $folderId );				
				
				//$fileId = $gdwpm_service->createFileFromPath( $_FILES["file"]["tmp_name"], $filename, $content, $fileParent );
				$fileId = $gdwpm_service->createFileFromPath( $filePath, $filename, $content, $fileParent );
				if($fileId){
					$gdwpm_service->setPermissions( $fileId, 'me', 'reader', 'anyone' );
					if(strpos($mime_berkas_arr['type'], 'image') !== false && !empty($gdwpm_sizez_meta)){
						$gdwpm_service->insertProperty($fileId, 'gdwpm-sizes', $gdwpm_sizez_meta);
					}
					$sukinfo = '';
					$metainfo = '';
					if(!empty($mime_berkas) && isset($_POST['gdpwm_med_ly']) == '1'){
			/*			if(strpos($mime_berkas_arr['type'], 'video') !== false){
							$gdwpm_meta_arr = wp_read_video_metadata( $filePath );
							$metainfo = json_encode($gdwpm_meta_arr);
						}elseif(strpos($mime_berkas_arr['type'], 'audio') !== false){
							$gdwpm_meta_arr = wp_read_audio_metadata( $filePath );
							$metainfo = json_encode($gdwpm_meta_arr);
						}elseif(strpos($mime_berkas_arr['type'], 'image') !== false){
							$gdwpm_meta_arr = wp_read_image_metadata( $filePath );
							$metainfo = json_encode($gdwpm_meta_arr);
						}
			*/
						gdwpm_ijin_masuk_perpus($mime_berkas, $filename, $fileId, $content, $nama_polder, $gdwpm_sizez_meta, $metainfo);
						$sukinfo = ' and added into your Media Library';
					}
					echo '<div class="updated"><p>'.$filename.' (<strong>'.$fileId.'</strong>) successfully uploaded to <strong>'.$nama_polder.'</strong>'.$sukinfo.'.</p></div>';
				}else{
					echo '<div class="error"><p>Failed to upload <strong>'.$filename.'</strong> to Google Drive.</p></div>';
				}
				@unlink($filePath);
			}
			wp_die();
		}
	}
	wp_die();
}

class GDWPMBantuan {
        
    protected $scope = array('https://www.googleapis.com/auth/drive');
        
    private $_service;
        
        public function __construct( $clientId, $serviceAccountName, $key ) {
				$client = new Google_Client();
				$client->setApplicationName("Google Drive WP Media");
				$client->setClientId( $clientId );
			
				$client->setAssertionCredentials( new Google_AssertionCredentials(
									$serviceAccountName,
									$this->scope,
									$this->getKonten( $key ) )
				);
            $this->_service = new Google_DriveService($client);
        }
        
        public function __get( $name ) {
                return $this->_service->$name;
        }
        
        public function getKonten( $url ) {
			if(function_exists('curl_version')){
				$data = curl_init();
				curl_setopt($data, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($data, CURLOPT_URL, $url);
				curl_setopt($data, CURLOPT_FOLLOWLOCATION,TRUE);
				curl_setopt($data, CURLOPT_SSL_VERIFYPEER, FALSE);     
				curl_setopt($data, CURLOPT_SSL_VERIFYHOST, FALSE); 
				curl_setopt($data, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 6.1; rv:2.2) Gecko/20110201');
				$hasil = curl_exec($data);
				curl_close($data);
				return $hasil;
			}else{
				$hasil = file_get_contents(str_replace(' ', '%20', $url));
				return $hasil;
			}
        }
        
        public function getAbout( ) {
                return $this->_service->about->get();
        }
        
        public function buangFile( $fileId ) {
                $result = $this->_service->files->delete($fileId);
				if(empty($result)){
					return true;
				}else{
					return false;
				}
        }
        
        public function getNameFromId( $fileId ) {
			$file_proper = $this->_service->files->get($fileId);
			$file_name = $file_proper->title;
			return $file_name;
        }
		
		public function insertProperty($fileId, $key, $value, $visibility = 'PUBLIC') {
			if(!empty($value) || $value != ''){		
				$newProperty = new Google_Property();
				$newProperty->setKey($key);
				$newProperty->setValue($value);
				$newProperty->setVisibility($visibility);
				return $this->_service->properties->insert($fileId, $newProperty);
			}else{
				return false;
			}
		}
		
        public function createFileFromPath( $path, $fileName, $description, Google_ParentReference $fileParent = null ) {
			$mimeType = wp_check_filetype($fileName);
            $file = new Google_DriveFile();
            $file->setTitle( $fileName );
            $file->setDescription( $description );
            $file->setMimeType( $mimeType['type'] );                
            if( $fileParent ) {
                $file->setParents( array( $fileParent ) );
            }
            $gdwpm_opsi_chunk = get_option('gdwpm_opsi_chunk');
			$chunks = $gdwpm_opsi_chunk['drive']['chunk'];
			$max_retries = (int) $gdwpm_opsi_chunk['drive']['retries'];
            $chunkSize = (1024 * 1024) * (int) $chunks; // 2mb chunk
            $fileupload = new Google_MediaFileUpload($mimeType['type'], null, true, $chunkSize);
			$fileupload->setFileSize(filesize($path));
			$mkFile = $this->_service->files->insert($file, array('mediaUpload' => $fileupload));
			$status = false;
			$handle = fopen($path, "rb");
			while (!$status && !feof($handle)) {
				$max = false;
				for ($i=1; $i<=$max_retries; $i++) {
					$chunked = fread($handle, $chunkSize);
					if ($chunked) {
						$createdFile = $fileupload->nextChunk($mkFile, $chunked);
						break;
					}elseif($i == $max_retries){
						$max = true;
					}
				}
				if($max){
					if($createdFile){
						$this->_service->files->trash( $createdFile['id'] );
					}
					$createdFile = false; 
					break;
				}
			}
			fclose($handle);
			if($createdFile){
				return $createdFile['id'];
			}else{
				return false;
			}
        }
                
        public function createFolder( $name ) {
			$file = new Google_DriveFile();
            $file->setTitle( $name );
            $file->setMimeType( 'application/vnd.google-apps.folder' );
            $createdFolder = $this->_service->files->insert($file, array('mimeType' => 'application/vnd.google-apps.folder'));
            return $createdFolder['id'];
        }
		
        public function setPermissions( $fileId, $value, $role = 'writer', $type = 'user' ) {
            $perm = new Google_Permission();
            $perm->setValue( $value );
            $perm->setType( $type );
            $perm->setRole( $role );
                
            return $this->_service->permissions->insert($fileId, $perm);
        }
        
        public function getFolderIdByName( $name ) {
			$parameters = array('q' => "mimeType = 'application/vnd.google-apps.folder'", 'maxResults' => 50);
            $files = $this->_service->files->listFiles($parameters);
            foreach( $files['items'] as $item ) {
                if( $item['title'] == $name ) {
                    return $item['id'];
					break;
                }
            }
            return false;
        }
		
		public function getFilesInFolder($folderId, $maxResults, $pageToken = '', $in_type = 'radio') {
			if($in_type == 'radio'){
				$div_id = 'hasil';
				$id_max = 'maxres';
				$id_folid = 'folid';
				$id_tblpagi = 'paginasi';
				$div_hal = 'halaman';
				$div_pagi = 'vaginasi';
				$opsi_kecing = 'gdwpm_kecing_hal';
				$in_name = 'gdwpm_berkas_terpilih[]';
			}elseif($in_type == 'checkbox'){
				$div_id = 'hasil_del';
				$id_max = 'maxres_del';
				$id_folid = 'folid_del';
				$id_tblpagi = 'paginasi_del';
				$div_hal = 'halaman_del';
				$div_pagi = 'vaginasi_del';
				$opsi_kecing = 'gdwpm_kecing_hal_del';
				$in_name = 'gdwpm_buang_berkas_terpilih[]';
			}else{
				$in_type = 'checkbox';
				$div_id = 'hasil_gal';
				$id_max = 'maxres_gal';
				$id_folid = 'folid_gal';
				$id_tblpagi = 'paginasi_gal';
				$div_hal = 'halaman_gal';
				$div_pagi = 'vaginasi_gal';
				$opsi_kecing = 'gdwpm_kecing_hal_gal';
				$in_name = 'gdwpm_berkas_gallery[]';
			}
			//setup 1st pagetokn is always enpty n create pagintion butt
			$haldepan = 1;
			////$hal = '<input type="hidden" id="maxres" value="'.$maxResults.'" /><button id="halaman" value="">'.$haldepan.'</button>';
			$parameters = array('maxResults' => $maxResults);
			$pageTokenInput = $pageToken;
			$gdwpm_kecing_hal = get_option($opsi_kecing);
			if (empty($pageToken) || $pageToken == '') {
			// generate halaman
				//if($gdwpm_kecing_hal || !empty($gdwpm_kecing_hal)){
					//delete_option($opsi_kecing);
				//}
				$gdwpm_kecing_hal = array();
				$errormes = '';
				$halarr = array($haldepan => 'bantuanhalamansatu');
				do {
					$haldepan++;
					try {
						if($haldepan == 1){$pageToken = '';}  //halman prtama pokoke token kudu kosong
						$parameters['pageToken'] = $pageToken;
						$children = $this->_service->children->listChildren($folderId, $parameters);
						$pageToken = $children->getNextPageToken();
						if($pageToken){
							//$hal .= '&nbsp;<button id="halaman" value="'.$pageToken.'">'.$haldepan.'</button>';
							$halarr[$haldepan] = $pageToken;
							if($haldepan % 10 == 0){sleep(1);}
						//}elseif($haldepan > 1){
						//cek n buang halman trakir jika kosong
							//$parameters['pageToken'] = $halarr[$haldepan - 1];
							//$files = $this->_service->children->listChildren($folderId, $parameters);
							//$result = array();
							//if(count(array_merge($result, $files->getItems())) < 1){
								//unset($halarr[$haldepan - 1]);
							//}
						}
					} catch (Exception $e) {
						$errormes = "<kbd>An error occurred: " . $e->getMessage() . "</kbd>";
						$haldepan -= 1;
						$pageToken = $halarr[$haldepan]; //NULL;
						sleep(1);
					}
				} while ($pageToken);
				unset($parameters['pageToken']);
				$gdwpm_kecing_hal[$folderId] = $halarr;
				update_option($opsi_kecing, $gdwpm_kecing_hal);
			}else{
				$parameters['pageToken'] = $pageToken;
			}
			$daftarfile = '';
			if(count($halarr) <= 1 || $pageToken != ''){
				if($pageToken == 'bantuanhalamansatu'){
					unset($parameters['pageToken']);
				}
			$folder_proper = $this->_service->files->get($folderId);
			$folder_name = $folder_proper->title;
			$i = 0;
				$daftarfile =  '<div id="'.$div_id.'"><table id="box-table-a" summary="File Folder" class="'.$id_tblpagi.'"><thead><tr><th scope="col"><span class="ui-icon ui-icon-check"></span></th><th scope="col">File ID</th><th scope="col">Title</th><!--<th scope="col">Description</th>--><th scope="col">Size</th><th scope="col">Action</th></tr></thead>';
					$children = $this->_service->children->listChildren($folderId, $parameters);
					foreach ($children->getItems() as $child) {
						$i++; if($i == 1 && $in_type == 'radio'){$checked = 'checked';}else{$checked = '';}
						if($maxResults != $i && $maxResults > 30 && $i % 20 == 0){sleep(1);}
						$fileId = $child->getId(); 
						$file = $this->_service->files->get($fileId); //getDescription getMimeType
						$file_mime = $file->getMimeType();
						$file_title = $file->getTitle();
						$file_desc = $file->getDescription();
						$file_icon = $file->getIconLink();
						$file_md5 = $file->getMd5Checksum();
						$file_size = size_format($file->getFileSize(), 2);
						$file_thumb = $file->getThumbnailLink();	// str_replace('=s220', '=s300', $file->getThumbnailLink());		
						$view = '<a href="https://docs.google.com/uc?id='.$fileId.'&export=download" title="Open link in a new window" target="_blank" class="tabeksen">Download</a>';
						$file_pptis = '';
						if(strpos($file_mime, 'image') !== false){
							$view = '<a href="https://www.googledrive.com/host/'.$fileId.'" title="Open link in a new window" target="_blank" class="tabeksen">View</a>';
							$properties = $this->_service->properties->listProperties($fileId);
							$getfile_pptis = $properties->getItems();
							if(count($getfile_pptis) > 0){
								$file_pptis = $getfile_pptis[0]->getValue();
								// selfWidth:xx selfHeight:xx thumbId:xxx thumbWidth:xx thumbHeight:xx
								preg_match_all('/(\w+):("[^"]+"|\S+)/', $file_pptis, $matches);
								$img_meta = array_combine($matches[1], $matches[2]);
								if(array_key_exists('thumbId', $img_meta)){
									$file_thumb = 'https://www.googledrive.com/host/' . $img_meta['thumbId'];
								}
							}
						} 
						$valson = json_encode(array($file_mime, $file_title, $fileId, $file_desc, $folder_name, $file_pptis));
						$daftarfile .=  '<tbody><tr><td><input type="'.$in_type.'" name="'.$in_name.'" value="'.base64_encode($valson).'" ' . $checked . '></td><td class="kolom_file" title="' . $file_thumb . '">'.$fileId.'</td>';
						$daftarfile .=  '<td title="' . $file_desc . '"><img src="' . $file_icon . '" title="' . $file_mime . '"> ' . $file_title . '</td>';
						$daftarfile .=  '<!--<td>' . $file_desc . '</td>-->';
						$daftarfile .=  '<td title="md5Checksum : ' . $file_md5 . '">' . $file_size . '</td>';
						$daftarfile .=  '<td>' . $view . ' | <a href="https://docs.google.com/file/d/'.$fileId.'/preview?TB_iframe=true&width=600&height=550" title="'.$file_title.' ('.$fileId.')" class="thickbox tabeksen">Preview</a></td></tr>';
					}
			$daftarfile .=  '</tbody></table></div><br/>';
			
			}
			
			// merangkai paginasi soretempe
			$range = 5; 
			$showitems = ($range * 2)+1;  
			$hal_folderid = $gdwpm_kecing_hal[$folderId];
			$halterlihat = array_search($pageToken, $hal_folderid);
			if(empty($halterlihat)){$halterlihat = 1;}
			$totalhal = count($hal_folderid);
			 if(1 != $totalhal)
			 {
				 $halsiap = '<input type="hidden" id="'.$id_max.'" value="'.$maxResults.'" /><input type="hidden" id="'.$id_folid.'" value="'.$folderId.'" />';
				 if($halterlihat > 2 && $halterlihat > $range+1 && $showitems < $totalhal) $halsiap .= '<button id="'.$div_hal.'" value="'.$hal_folderid[1].'">&laquo;</button>';
				 if($halterlihat > 1 && $showitems < $totalhal) $halsiap .= '<button id="'.$div_hal.'" value="'.$hal_folderid[$halterlihat - 1].'">&lsaquo;</button>';
				 
				 for ($j=1; $j <= $totalhal; $j++)
				 {
					 if (1 != $totalhal &&( !($j >= $halterlihat+$range+1 || $j <= $halterlihat-$range-1) || $totalhal <= $showitems ))
					 {
						if($halterlihat == $j && $pageTokenInput != ''){$disable_butt = ' disabled';}else{$disable_butt = '';}
						$halsiap .= '<button id="'.$div_hal.'" value="'.$hal_folderid[$j].'"'.$disable_butt.'>'.$j.'</button>';
					 }
				 }

				 if ($halterlihat < $totalhal && $showitems < $totalhal) $halsiap .= '<button id="'.$div_hal.'" value="'.$hal_folderid[$halterlihat + 1].'">&rsaquo;</button>';
				 if ($halterlihat < $totalhal-1 &&  $halterlihat+$range-1 < $totalhal && $showitems < $totalhal) $halsiap .= '<button id="'.$div_hal.'" value="'.$hal_folderid[$totalhal].'">&raquo;</button>';
			 }
							
			$vaginasi =	'<div id="'.$div_pagi.'">'.$halsiap.'</div>';
			$daftarfile .= $vaginasi;
			if($i == 0 && $totalhal > 1 && $halterlihat == $totalhal){$daftarfile = $vaginasi;}
			return array($daftarfile, $i, $totalhal, $halterlihat);//, $halterlihat, $totalhal);//items, items onpage, currentpage, totalpage
		}
		
}
// new folder kecing initial kosong
update_option('gdwpm_new_folder_kecing', array('', ''));

add_action('media_buttons', 'gdwpm_preview_button', 12);
function gdwpm_preview_button() {
    echo '<a href="#" id="masukin-sotkode" class="button"><img src="' . plugins_url( '/images/animation/gdwpm-insert-shortcode.png', __FILE__ ) . '"/>GDWPM Shortcode</a>';
}
add_action('wp_print_scripts', 'gdwpm_register_scripts');
add_action('wp_print_styles', 'gdwpm_register_styles');
function gdwpm_register_scripts() {
    if (!is_admin()) {
        wp_register_script('gdwpm_lightbox-script', plugins_url('js/lightbox.js', __FILE__), array( 'jquery' ), VERSI_GDWPM, true );
		// fotorama.Js
		//wp_register_script('gdwpm_fotorama-script', "http://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.3/fotorama.js", array( 'jquery' ), VERSI_GDWPM, true );
        wp_register_script('gdwpm_justified-script', plugins_url('js/justifiedGallery.js', __FILE__), array( 'jquery' ), VERSI_GDWPM, true );
        wp_register_script('gdwpm_script-script', plugins_url('js/script.js', __FILE__), array( 'jquery' ), VERSI_GDWPM, true );
        //wp_register_script('gdwpm_swipebox-script', plugins_url('libs/swipebox/jquery.swipebox.min.js', __FILE__), array( 'jquery' ), VERSI_GDWPM, true );

		wp_enqueue_script('jquery');              
        wp_enqueue_script('gdwpm_lightbox-script');
		//wp_enqueue_script('gdwpm_fotorama-script');    
		wp_enqueue_script('gdwpm_justified-script');     
		wp_enqueue_script('gdwpm_script-script');      
		//wp_enqueue_script('gdwpm_swipebox-script');   
    }
}
function gdwpm_register_styles() {
    wp_register_style('gdwpm_styles', plugins_url('css/lightbox.css', __FILE__));
    wp_register_style('gdwpm_img_styles', plugins_url('css/images.css', __FILE__));
	// fotorama.css
	//wp_register_style('gdwpm_fotorama_styles', "http://cdnjs.cloudflare.com/ajax/libs/fotorama/4.6.3/fotorama.css");
    wp_register_style('gdwpm_justified_styles', plugins_url('css/justifiedGallery.css', __FILE__));
    //wp_register_style('gdwpm_swipebox_styles', plugins_url('libs/swipebox/swipebox.css', __FILE__));

    wp_enqueue_style('gdwpm_styles');
    wp_enqueue_style('gdwpm_img_styles');
    //wp_enqueue_style('gdwpm_fotorama_styles');
    wp_enqueue_style('gdwpm_justified_styles');
    //wp_enqueue_style('gdwpm_swipebox_styles');
}

function gdwpm_activate() {
	$gdwpm_ukuran_preview = get_option('gdwpm_ukuran_preview'); 	// default value lebar tinggi vidchecked vidauto vidlebar vidtinggi
	if(!$gdwpm_ukuran_preview || empty($gdwpm_ukuran_preview)){
		update_option('gdwpm_ukuran_preview', array('600', '700', 'checked', 'manual', '600', '370'));
	}elseif(empty($gdwpm_ukuran_preview[3]) || $gdwpm_ukuran_preview[3] == ''){
		update_option('gdwpm_ukuran_preview', array($gdwpm_ukuran_preview[0], $gdwpm_ukuran_preview[1], 'checked', 'manual', '600', '370'));
	}
	$gdwpm_opsi_kategori = get_option('gdwpm_opsi_kategori_dr_folder'); 	
	if(!$gdwpm_opsi_kategori){update_option('gdwpm_opsi_kategori_dr_folder', 'checked');}
	$gdwpm_opsi_chunk = get_option('gdwpm_opsi_chunk');
	if(empty($gdwpm_opsi_chunk)){
		update_option('gdwpm_opsi_chunk', array('local' => array('cekbok' => 'checked', 'chunk' => '700', 'retries' => '3'), 'drive' => array('cekbok' => 'checked', 'chunk' => '2', 'retries' => '3')));
	}
	$gdwpm_img_thumbs = get_option('gdwpm_img_thumbs');
	if(empty($gdwpm_img_thumbs)){
		update_option('gdwpm_img_thumbs', array('', '', '150', '150', 'false'));
	}
	// Flushing Rewrite on Activation
	gdwpm_init();
	flush_rewrite_rules();
	
	$upload_dir = wp_upload_dir();
	$nama_ploder = 'gdwpm_images';
	$fulldir = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . $nama_ploder;
	$gdwpm_images_url = $upload_dir['baseurl'] . '/' . $nama_ploder;
	$gdwpm_homebase_arr = parse_url($gdwpm_images_url);
	$gdwpm_homebase = $gdwpm_homebase_arr['scheme'] . '://' . $gdwpm_homebase_arr['host'] . '/';
	$gdwpm_images_url = str_replace($gdwpm_homebase, '', $upload_dir['baseurl'] . '/' . $nama_ploder);
$tulis_htacc = <<<HTAC
RewriteEngine on
RewriteBase /
RewriteCond %{QUERY_STRING} !^imgid= [NC]
RewriteRule ^(.*)$ {$gdwpm_images_url}/index.php?imgid=$1 [L,NC,QSA]
HTAC;
$tulis_php = <<<PHPG
<?php
if (isset(\$_GET['imgid'])){
	\$gdwpm_ekst_gbr = explode('.', \$_GET['imgid']);
	if(\$gdwpm_ekst_gbr[1] == 'png' || \$gdwpm_ekst_gbr[1] == 'gif' || \$gdwpm_ekst_gbr[1] == 'bmp'){
		header("Content-Type: image/" . \$gdwpm_ekst_gbr[1]);
	}else{
		header("Content-Type: image/jpg");
	}
	\$gdurl = "https://docs.google.com/uc?id=" . \$gdwpm_ekst_gbr[0] . "&export=view";
	@readfile(\$gdurl);
}
?>
PHPG;
	if(!is_dir($fulldir)){
		wp_mkdir_p($fulldir);
	}
	file_put_contents($fulldir . '/index.php', $tulis_php, LOCK_EX);
	file_put_contents($fulldir . '/.htaccess', $tulis_htacc, LOCK_EX);
}
register_activation_hook( __FILE__, 'gdwpm_activate' );
?>