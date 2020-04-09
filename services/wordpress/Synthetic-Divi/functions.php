<?php
/* Use Monarch's social network information in MailPoet newsletters */
// Thanks to https://www.survivehive.com/adding-social-bookmark-icons-mailpoet/ for teaching us the MailPoet side!!
if (class_exists('WYSIJA'))
	add_action('media_upload_standard', 'synSetMailpoetNetworks');
function synSetMailpoetNetworks($tab) 
{
	if (!class_exists('WYSIJA') || $tab != 'bookmarks') 
		return;
	WYSIJA::get('campaigns', 'controller')->data['networks'] = synGetMonarchNetworksForMailpoet();
}
// To create our extended set of available networks, we want to draw from Monarch rather than re-enter all the data here.
// The datastructure to return is [ 'handle' => [ 'label' => 'Readable', 'url'=>'url', 'placeholder'=>null ] , ... ]
function synGetMonarchNetworksForMailpoet()
{
	$monarchNetworks = ET_Monarch::get_options_array()['follow_networks_networks_sorting'];
	foreach ($monarchNetworks['class'] as $i => $class)
		$result[$class] = [ 'label'=>$monarchNetworks['label'][$i], 'url'=>$monarchNetworks['username'][$i] ];
	return $result;
}
function synReplaceMailpoetJS() 
{
    wp_dequeue_script( 'wysija-autoinc-newsletter-admin-campaigns-bookmarks-js' );
    wp_enqueue_script( 'wysija-autoinc-newsletter-admin-campaigns-bookmarks-js', get_template_directory_uri() . "/synWysijaBookmarks.js", ['jquery'] );
    wp_dequeue_script( 'wysija-autoinc-newsletter-wysija-editor-js' );
    wp_enqueue_script( 'wysija-autoinc-newsletter-wysija-editor-js', get_template_directory_uri() . "/synth-wysija-editor.js", ['jquery'] );
}
add_action('admin_print_scripts', 'synReplaceMailpoetJS');
/* END Add own social networks to MailPoet */

// This brief section ensures the child theme styles are enqueued.
add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() 
{
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
}

/**
* Here we correct the fact that media attachments are turning out with URLs which don't 
* match their containing pages in protocol and hostname. This makes for problems in several ways.
*/
function synthetic_wp_get_attachment_url( $url, $aId )
{
	return preg_replace('|[a-z]*://[a-z.]*(:[0-9]*)?/uploads/|','/uploads/',$url);
}
add_filter('wp_get_attachment_url','synthetic_wp_get_attachment_url');

function synthetic_attachment_link( $url, $aId )
{
	return preg_replace('|[a-z]*://[a-z.]*(:[0-9]*)?/uploads/|','/uploads/',$url);
}
add_filter('attachment_link','synthetic_attachment_link');
