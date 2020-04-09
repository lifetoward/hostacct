jQuery(document).ready(function($) {
	$('.gdwpmGallery1').justifiedGallery(
		{margins : 1,
		rowHeight : 90,
		lastRow	  : 'justify'}
	)
	.on('jg.complete', function (e) {
		setTimeout(function(){ 
			$('.gdwpmGallery1').each(function(){
				var datagal = $(this).attr('data-gal1');
				var datagalarr = datagal.split(' | ');
				var aidi = $(this).attr('id');
				$('#'+aidi).justifiedGallery(
					{margins : parseInt(datagalarr[0]),
					rowHeight : parseInt(datagalarr[1]),
					lastRow	  : datagalarr[2]}
				);
			});
		}, 2000);    
	});
});