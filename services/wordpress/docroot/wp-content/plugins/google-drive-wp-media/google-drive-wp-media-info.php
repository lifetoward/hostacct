<?php
if ( !is_admin() ) {
     wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
if (!class_exists('Google_Client')) {
	require_once 'gdwpm-api/Google_Client.php';
	require_once 'gdwpm-api/contrib/Google_DriveService.php';
}
if(!$gdwpm_opt_akun){
	$gdwpm_opt_akun = get_option('gdwpm_akun_opt'); // imel, client id, gdwpm_service akun, private key
	$cek_kunci = 'false';
}
$gdwpm_apiConfig['use_objects'] = true;
if(!$gdwpm_service){$gdwpm_service = new GDWPMBantuan( $gdwpm_opt_akun[1], $gdwpm_opt_akun[2], $gdwpm_opt_akun[3] );}
if(!isset($ebot)){	$ebot = $gdwpm_service->getAbout(); }
?>
<table id="gdwpm_info_akun">
	<tr>
		<td>Service Account Name</td><td>: </td>
		<td title="The name of the current user."><?php echo $ebot->getName();?></td>
	</tr>
	<tr>
		<td>Total quota</td><td>: </td>
		<td title="The total number of quota bytes."><?php echo size_format($ebot->getQuotaBytesTotal(), 2) . ' ('. $ebot->getQuotaBytesTotal() . ' bytes)';?></td>
	</tr>
	<tr>
		<td>Quota Used</td><td>: </td>
		<td title="The number of quota bytes used by Google Drive."><?php echo size_format($ebot->getQuotaBytesUsed(), 2) . ' ('. $ebot->getQuotaBytesUsed() . ' bytes)';?></td>
	</tr>
	<tr>
		<td>Available Quota</td><td>: </td>
		<td title="Remaining Quota"><?php $sisakuota = $ebot->getQuotaBytesTotal() - $ebot->getQuotaBytesUsed(); echo size_format($sisakuota, 2) . ' ('. $sisakuota . ' bytes)';?></td>
	</tr>
	<tr>
		<td>Quota used by all Google apps</td><td>: </td>
		<td title="The number of quota bytes used by all Google apps (Drive, Picasa, etc.)."><?php echo size_format($ebot->getQuotaBytesUsedAggregate(), 2) . ' ('. $ebot->getQuotaBytesUsedAggregate() . ' bytes)';?></td>
	</tr>
	<tr>
		<td>Quota in Trash</td><td>: </td>
		<td title="The number of quota bytes used by trashed items."><?php echo size_format($ebot->getQuotaBytesUsedInTrash(), 2) . ' ('. $ebot->getQuotaBytesUsedInTrash() . ' bytes)';?></td>
	</tr>
	<tr>
		<td>Quota type</td><td>: </td>
		<td title="The type of the user's storage quota."><?php echo $ebot->quotaType;?></td>
	</tr>
	<tr>
		<td>Root folder ID</td><td>: </td>
		<td title="The id of the root folder."><?php echo $ebot->getRootFolderId();?></td>
	</tr>
	<tr>
		<td>Domain Sharing Policy</td><td>: </td>
		<td title="The domain sharing policy for the current user."><?php echo $ebot->getDomainSharingPolicy();?></td>
	</tr>
	<tr>
		<td>Permission Id</td><td>: </td>
		<td title="The current user's ID as visible in the permissions collection."><?php echo $ebot->getPermissionId();?></td>
	</tr>
	<tr>
		<td>User's language</td><td>: </td>
		<td title="The user's language or locale code, as defined by BCP 47, with some extensions from Unicode's LDML format (http://www.unicode.org/reports/tr35/)."><?php echo $ebot->languageCode;?></td>
	</tr>
	<tr>
		<td>Link</td><td>: </td>
		<td title="Link to about page."><?php echo $ebot->getSelfLink();?></td>
	</tr>
	<tr>
		<td>ETag</td><td>: </td>
		<td title="The ETag of the item."><?php echo $ebot->getEtag();?></td>
	</tr>
	<tr>
		<td>Largest Change ID</td><td>: </td>
		<td title="The largest change id."><?php echo $ebot->getLargestChangeId();?></td>
	</tr>
	<tr>
		<td>Remaining Change IDs</td><td>: </td>
		<td title="The number of remaining change ids."><?php echo $ebot->getRemainingChangeIds();?></td>
	</tr>
</table>
<br/>
Quota by Service:<br/>
<?php $quotaBytesByService = $ebot->quotaBytesByService;
	foreach($quotaBytesByService as $k => $v){
		echo 'Service Name: ' . $v['serviceName'] . ', Quota Used: ' . size_format($v['bytesUsed'], 2) . ' (' . $v['bytesUsed'] . ' bytes)<br/>';
	}
 //print_r($ebot);
?>
<br/>
Max Upload Sizes:<br/>
<?php $maxUploadSizes = $ebot->getMaxUploadSizes();
	foreach($maxUploadSizes as $k => $v){
		echo 'MIME Type: ' . $v->type . ', Max: ' . size_format($v->size, 2) . ' (' . $v->size . ' bytes)<br/>';
	}
?>
<br/>
Enabled Features:<br/>
<?php $features = $ebot->getFeatures();
	foreach($features as $k => $v){
		echo 'Feature Name: ' . $v->featureName . ', Feature Rate: ' . $v->featureRate . ' queries per second<br/>';
	}
?>
<br/>
Folder Color Palette:<br/>
<?php $folderColorPalette = $ebot->folderColorPalette;
	echo implode(', ', $folderColorPalette);
?>
<script>
jQuery(function() {	  
	jQuery("#gdwpm_info_akun").tooltip({ 
		items: "[title]",
		track: true,
		show: { effect: 'slideDown' },
		open: function (event, ui) { setTimeout(function () {
				jQuery(ui.tooltip).hide('explode');
			}, 4000); }
	});
})
</script>