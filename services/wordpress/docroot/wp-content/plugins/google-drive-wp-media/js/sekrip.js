function jumBaris(maxBaris, tabelid){

jQuery('table.'+tabelid).each(function() {
    var halSkrg = 0;
  if (typeof maxBaris === 'undefined') { maxBaris = 20; }
    var table = jQuery(this);
    table.bind('repaginate', function() {
        table.find('tbody tr').hide().slice(halSkrg * maxBaris, (halSkrg + 1) * maxBaris).show();
    });
    table.trigger('repaginate');
    var jmlBaris = table.find('tbody tr').length;
    var jmlLaman = Math.ceil(jmlBaris / maxBaris);
	var halpgerid = 'halpager-'+tabelid;
	if (jQuery('#'+halpgerid).length) {
		jQuery('#'+halpgerid).remove();
	}
	if (jmlLaman > 1){
		var halPager = jQuery('<div id="'+halpgerid+'" class="halpager"></div>');
		for (var hal = 0; hal < jmlLaman; hal++) {
			jQuery('<span class="urut-laman"></span>').text(hal + 1).bind('click', {
				halBaru: hal
			}, function(event) {
				halSkrg = event.data['halBaru'];
				table.trigger('repaginate');
				jQuery(this).addClass('active').siblings().removeClass('active');
			}).appendTo(halPager).addClass('clickable');
		}
		halPager.insertAfter(table).find('span.urut-laman:first').addClass('active');
	}
});
}

jQuery(document).ready(function($) {
	$('#gdwpm_loading_gbr').hide();
	$('#gdwpm_masuk_perpus_teks').hide();
	$('#gdwpm_add_to_media_gbr').hide();
/////////////////////////////////////////////////
    $("#golek_seko_folder").click(function(){
        $("#gdwpm_loading_gbr").show();
	$('.sukses').empty();
	$('.sukses').hide();
	$('#tombol-donat').remove();
	$('.updated').hide();
		$('#hasil').empty();
		$('#vaginasi').empty();
		$('#gdwpm_info_folder_thumbs').hide();
		$('#gdwpm_masuk_perpus_teks').hide();
		$('#hasil').hide();
	var data = {
		action: 'gdwpm_on_action',
		folder_pilian: $('select[name=folder_pilian]').val(),
		pilmaxres: $('#pilihMaxRes').val()
	};
	
	jQuery.post(ajax_object.ajax_url, data, function(response) {
		$("#gdwpm_loading_gbr").hide();
		$('#hasil').show();
		if(response.indexOf("radio") > 1){
			if($('#folder_pilian option:selected').text() != 'gdwpm-thumbnails'){
				$('#gdwpm_masuk_perpus_teks').show();
			}else{
				$('#gdwpm_info_folder_thumbs').show();
			}
		}
		$('#gdwpm_info_masuk_perpus').empty();
		var $holder = $('<div/>').html(response);
$('.sukses').html($('.sukses', $holder).html());
$('#hasil').html($('#hasil', $holder).html());
$('#vaginasi').html($('#vaginasi', $holder).html());
$("#hasil").tooltip({
    items: "td.kolom_file",
	track: true,
	position: { my: "left+15 top-5", at: "left bottom" },
	show: { effect: 'slideDown' },
    open: function (event, ui) { setTimeout(function () {
			$(ui.tooltip).hide('slideUp');
        }, 7000); },
    content: function(){
           var src = $(this).attr('title');
			if(src == ''){
				return 'No thumbnail found';
			}else{
				return '<img src="'+ src +'" />';
			}
    }
});
	$('.sukses').show();	
		$('#vaginasi').buttonset({});
	$('#gdwpm_add_to_media_gbr').hide();
	var pilBaris = $('#pilihBaris').val();
		jumBaris(pilBaris, 'paginasi');
	});
    }); 
	$('body').on('click', '#halaman', function() {
        $("#gdwpm_loading_gbr").show();
		$('.sukses').empty();
		$('.sukses').hide();
		$('.updated').hide();
		$('#hasil').empty();
		$('#gdwpm_info_folder_thumbs').hide();
		$('#gdwpm_masuk_perpus_teks').hide();
		$('#hasil').hide();		
		var clicked = $(this);
	var data = {
		action: 'gdwpm_on_action',
		folder_pilian: $('#folid').val(),
		pilmaxres: $('#maxres').val(),
		pagetoken: clicked.val()
	};
	
	jQuery.post(ajax_object.ajax_url, data, function(response) {
		$("#gdwpm_loading_gbr").hide();
		$('#hasil').show();
		if(response.indexOf("radio") > 1){
			if($('#folder_pilian option:selected').text() != 'gdwpm-thumbnails'){
				$('#gdwpm_masuk_perpus_teks').show();
			}else{
				$('#gdwpm_info_folder_thumbs').show();
			}
		}
		$('#gdwpm_info_masuk_perpus').empty();
		$('#vaginasi').empty();
		var $holder = $('<div/>').html(response);
		$('.sukses').html($('.sukses', $holder).html());
		$('#hasil').html($('#hasil', $holder).html());
		$('#vaginasi').html($('#vaginasi', $holder).html());
		$('.sukses').show();
		$('#vaginasi').buttonset({});
		$('#gdwpm_add_to_media_gbr').hide();
		var pilBaris = $('#pilihBaris').val();
			jumBaris(pilBaris, 'paginasi');
	});
    }); 
	$("#gdwpm_berkas_masuk_perpus").click(function(){
        $("#gdwpm_add_to_media_gbr").show();
		$('#gdwpm_info_masuk_perpus').empty();
	var data = {
		action: 'gdwpm_on_action',
		masuk_perpus: $("input:radio[name='gdwpm_berkas_terpilih[]']:checked").val()
	};
	jQuery.post(ajax_object.ajax_url, data, function(dataPerpus) {
		$('#gdwpm_add_to_media_gbr').hide();
		$('#gdwpm_info_masuk_perpus').html(dataPerpus);
	});
	
	});
	

	$("#gdwpm_file_gallery").click(function(){
        $("#gdwpm_loading_gbr_gal").show();
		$('.sukses_gal').empty();
		$('.sukses_gal').hide();
		$('#hasil_gal').empty();
		$('#hasil_gal').hide();
		$('#vaginasi_gal').empty();
		$('#gdwpm_masuk_gallery_teks').hide();
		var data = {
			action: 'gdwpm_on_action',
			folder_pilian_file_gal: $('select[name=folder_pilian_file_gal]').val(),
			pilmaxres: $('#pilihMaxResgal').val()
		};
	
		jQuery.post(ajax_object.ajax_url, data, function(responsegal) {
			$("#gdwpm_loading_gbr_gal").hide();
			$('#hasil_gal').show();
			$('#gdwpm_info_masuk_gallery').empty();
			var holder = $('<div/>').html(responsegal);
			$('.sukses_gal').html($('.sukses_gal', holder).html());
			$('#hasil_gal').html($('#hasil_gal', holder).html());
			$('#vaginasi_gal').html($('#vaginasi_gal', holder).html());
			$("#hasil_gal").tooltip({
				items: "td.kolom_file",
				track: true,
				position: { my: "left+15 top-5", at: "left bottom" },
				show: { effect: 'slideDown' },
				open: function (event, ui) { setTimeout(function () {
					$(ui.tooltip).hide('slideUp');
				}, 7000); },
				content: function(){
					var src = $(this).attr('title');
					if(src == ''){
						return 'No thumbnail found';
					}else{
						return '<img src="'+ src +'" />';
					}
				}
			});
			$('.sukses_gal').show();
			$('#vaginasi_gal').buttonset({});	
			$('#gdwpm_add_to_gal_gbr').hide();
			var pilBaris = $('#pilihBaris_gal').val();
			jumBaris(pilBaris, 'paginasi_gal');	
		});
    }); 
  	
	$("#hasil_gal").click(function(){
		var len = $("#hasil_gal input[name='gdwpm_berkas_gallery[]']:checked").length;
		if (len>0){
			$('#gdwpm_masuk_gallery_teks').show();
		}else{
			$('#gdwpm_masuk_gallery_teks').hide();
		}
    }); 					
				
	$('body').on('click', '#halaman_gal', function() {
        $("#gdwpm_loading_gbr_gal").show();
		$('.sukses_gal').empty();
		$('.sukses_gal').hide();
		$('#hasil_gal').empty();
		$('#gdwpm_masuk_gallery_teks').hide();
		var clicked = $(this);
		var data = {
			action: 'gdwpm_on_action',
			folder_pilian_file_gal: $('#folid_gal').val(),
			pilmaxres: $('#maxres_gal').val(),
			pagetoken: clicked.val()
		};
	
		jQuery.post(ajax_object.ajax_url, data, function(responsegal) {
			$("#gdwpm_loading_gbr_gal").hide();
			$('#hasil_gal').show();
			$('#gdwpm_info_masuk_gallery').empty();
			$('#vaginasi_gal').empty();
			var holder = $('<div/>').html(responsegal);
			$('.sukses_gal').html($('.sukses_gal', holder).html());
			$('#hasil_gal').html($('#hasil_gal', holder).html());
			$('#vaginasi_gal').html($('#vaginasi_gal', holder).html());
			$('.sukses_gal').show();
			$('#vaginasi_gal').buttonset({});
			$('#gdwpm_add_to_gal_gbr').hide();
			var pilBaris = $('#pilihBaris_gal').val();
				jumBaris(pilBaris, 'paginasi_gal');
		});
    }); 
	
	function galerimasuk(i, arraydata) {
		setTimeout(function() {
			if(arraydata == 'entek'){
				if((i-2) > 0){
					$('#gdwpm_info_masuk_gallery').html("Done! <b>"+ (i-2) +"</b> image(s) successfully added to Collector.");
				}else{
					$('#gdwpm_info_masuk_gallery').html("Warning! No image(s) found.");
				}
			}else{
				//var arraydata = data.split(' | ');
				var imgid = arraydata[ 2 ];
				if(arraydata[5] !== 'undefined'){
					if (arraydata[5].indexOf("thumbId:") > -1){
						var sizesdata = arraydata[5].split(' ');
						var imgid = sizesdata[2].replace("thumbId:", "");
					}
				}
				var datasiap = [arraydata[ 2 ], imgid, arraydata[ 1 ]];	// array id, thumbid, flnme
				var datasiap = Base64.encode(JSON.stringify(datasiap));
				$("#gallery_holder").append( "<div id='itemgal' class='ui-widget-content ui-corner-all'><div id='galgbr' class='ui-corner-all' data-info='"+datasiap+"' style='margin: 0 auto;width:150px;height:150px;overflow:hidden;' ><img src='https://www.googledrive.com/host/"+imgid+"' title='"+arraydata[ 1 ]+"' alt='"+arraydata[ 1 ]+"' /></div><input type='text' name='customcaption[]' class='ui-corner-all' value='' size='15' placeholder='Caption this image' /><a href='javascript:void(0)' onClick='remove_itemgal(this); return false;'><small>[Remove]</small></a></div>" );
				$('#gdwpm_info_masuk_gallery').html('<b>'+arraydata[ 1 ]+'</b> (<i>'+arraydata[ 2 ]+'</i>) has been added to Collector.');
				if ($('#gallery_holder > div').length > 1){
					$('#gallery_input').show();
					if($('#gallery_id_edit').val() == ''){
						$('#gal_intermezo').html('Your New Gallery is ready to be published..');
						$('#css_style').val('default');
						$('#css_style').selectmenu("refresh");
						$('#css_style_opt').show();
						$('#css_style_opt1').hide();
					}
					$('#gallery_box_info').show();
					$('#gallery_input_info').empty();
				}else{
					$('#gallery_input').hide();
				}
			}
		}, 700*i);
	}
	
	$("#gdwpm_berkas_masuk_gallery").click(function(){
		var data = $("#hasil_gal input[name='gdwpm_berkas_gallery[]']:checked");   // mime, name, id, desc, folder, pptis
		var maks = data.length;
		var totimg = 0;
		for(var i = 0; i <= maks; i++){ 
			if(i < maks){
				var jsondata = Base64.decode(data[i].value);
				var arraydata = jQuery.parseJSON(jsondata);
				if (arraydata[0].indexOf("image") > -1){
					galerimasuk(totimg, arraydata);
					totimg++;
				}
			}else{
				galerimasuk(totimg+2, 'entek');
			}
		}
	});
	
	$(".gdwpm_bikin_gallery").click(function(){  
		if($('#gallery_title').val() == ''){
			$('#gallery_input_info').html('<div class="error"><p>Warning: Title cannot be empty.</p></div>');
		}else{
			$('#gdwpm_creating_128').show();
			$('#gallery_input').hide();
		
			var datagbrs = "";
			var captionarr = $("input[name^='customcaption']");
			$("#gallery_holder  > #itemgal > #galgbr").each(function(i) {
				if (datagbrs == '')
					datagbrs = $(this).attr('data-info') + ' | ' + Base64.encode(captionarr.eq(i).val()); // <<< string json formated base64encoded
				   // datagbrs = $(this).data('galgbr') + ' | ' + Base64.encode(captionarr.eq(i).val()); <<<<<< data object array result
				else
					datagbrs += " , " + $(this).attr('data-info') + ' | ' + Base64.encode(captionarr.eq(i).val());
			});
			if($('#new_album').val() == ''){
				var album = Base64.encode($('#old_album').val());
			}else{
				var album = Base64.encode($('#new_album').val());
			}
			var data = {
				action: 'gdwpm_on_action',
				dataimages: datagbrs,
				galid: $('#gallery_id_edit').val(),
				album: album,
				css_style: $('#css_style').val(),
				css_style_default: $('#css_style_default').val(),
				css_style_justified: $('#css_justified_margins').val() + ' | ' + $('#css_justified_row').val() + ' | ' + $('#css_justified_last').val(),
				css_effect: $('#css_effect').val(),
				judul: Base64.encode($('#gallery_title').val())
			};
			jQuery.post(ajax_object.ajax_url, data, function(responsegal) {
				$('#gallery_input_info, #galpage').html('<div class="updated"><p>'+responsegal+'</p></div>');
				$('#gallery_holder').empty();
				$('#gallery_id_edit').val('');
				$('#gdwpm_new_gallery').show();
				$('#gdwpm_edit_gallery').hide();
				$('#gdwpm_creating_128').hide();
				$('#gallery_box_info').hide();
				$('#gallery_title').val('');
				$('#gdwpm-albums').tabs({active:1});
			});
		}
		
		
	});
	
	
	$("#gdwpm_reset_gallery").click(function(){
		$("#gallery_holder").html('');
		$('#gallery_id_edit').val('');
		$('#gallery_input').hide();
		$('#gal_intermezo').html('Your New Gallery is ready to be published..');
		$('#gallery_title').val('');
		$('#gdwpm_new_gallery').show();
		$('#gdwpm_edit_gallery').hide();
		$('#gdwpm_info_masuk_gallery').empty();
		$('#gallery_box_info').hide();
	});
////////////////////////////////////////////////////////
	 $("#gdwpm_file_dr_folder").click(function(){
        $("#gdwpm_loading_gbr_del").show();
	$('.sukses_del').empty();
	$('.sukses_del').hide();
	$('#hasil_del').empty();
	$('#gdwpm_info_del').hide();
	$('#hasil_del').hide();
		$('#vaginasi_del').empty();
	var data = {
		action: 'gdwpm_on_action',
		folder_pilian_file_del: $('select[name=folder_pilian_file_del]').val(),
		pilmaxres: $('#pilihMaxResdel').val()
	};
	
	jQuery.post(ajax_object.ajax_url, data, function(responsedel) {
		$("#gdwpm_loading_gbr_del").hide();
		$('#hasil_del').show();
		var holder = $('<div/>').html(responsedel);
$('.sukses_del').html($('.sukses_del', holder).html());
$('#hasil_del').html($('#hasil_del', holder).html());
$('#vaginasi_del').html($('#vaginasi_del', holder).html());
$("#hasil_del").tooltip({
    items: "td.kolom_file",
	track: true,
	show: { effect: 'slideDown' },
    open: function (event, ui) { setTimeout(function () {
			$(ui.tooltip).hide('slideUp');
        }, 7000); },
    content: function(){
           var src = $(this).attr('title');
			if(src == ''){
				return 'No thumbnail found';
			}else{
				return '<img src="'+ src +'" />';
			}
    }
});
	$('.sukses_del').show();
		$('#vaginasi_del').buttonset({});
		
	});
    }); 
  
	
	 $("#hasil_del").click(function(){
    var len = $("#hasil_del input[name='gdwpm_buang_berkas_terpilih[]']:checked").length;
	if (len>0){
		$('#gdwpm_info_del').show();
	}else{
		$('#gdwpm_info_del').hide();
	}
    }); 
	
	$('body').on('click', '#halaman_del', function() {
        $("#gdwpm_loading_gbr_del").show();
		$('.sukses_del').empty();
		$('.sukses_del').hide();
		$('#hasil_del').empty();
		$('#hasil_del').hide();		
		$('#gdwpm_info_del').hide();
		var clicked = $(this);
	var data = {
		action: 'gdwpm_on_action',
		folder_pilian_file_del: $('#folid_del').val(),
		pilmaxres: $('#maxres_del').val(),
		pagetoken: clicked.val()
	};
	
	jQuery.post(ajax_object.ajax_url, data, function(responsedel) {
		$("#gdwpm_loading_gbr_del").hide();
		$('#hasil_del').show();
		$('#vaginasi_del').empty();
		var holder = $('<div/>').html(responsedel);
		$('.sukses_del').html($('.sukses_del', holder).html());
		$('#hasil_del').html($('#hasil_del', holder).html());
		$('#vaginasi_del').html($('#vaginasi_del', holder).html());
		$('.sukses_del').show();
		$('#vaginasi_del').buttonset({});
	});
    }); 
	
	$("#gdwpm_tombol_bersih").click(function(){
		$('#filelist').empty();	
		$('#console').empty();
		$(this).hide();
	})
	
	$("#gdwpm_aplot_masuk").click(function(){
//function uploadfile(){
	alert('Got this from the server: ');
	var data = {
		action: 'gdwpm_on_action',
		folder_pilian_aplod: $('select[name=folder_pilian_aplod]').val(), 
		gdwpm_folder_anyar: $('.gdwpm_folder_anyar').val(), 
		gdwpm_aplod_file: $("#gdwpm_aplod_file").val(), 
		gdwpm_aplod_deskrip: $("#gdwpm_aplod_deskrip").val()      // We pass php values differently!
	};
	jQuery.post(ajax_object.ajax_url, data, function(data) {
		//$('#gdwpm_add_to_media_gbr').hide();
		$('#gdwpm_hsl_aplod_gbr').html(data);
	});
	

})

if(ajax_object.opsi_kategori == 'checked') {
	var gdwpmMedia = wp.media;
	if ( gdwpmMedia ) {
		jQuery.each(mediaTaxonomies,function(key,label){

			gdwpmMedia.view.AttachmentFilters[key] = gdwpmMedia.view.AttachmentFilters.extend({
				className: key,

				createFilters: function() {
					var filters = {};

					_.each( mediaTerms[key] || {}, function( term ) {

						var query = {};

						query[key] = {
							taxonomy: key,
							term_id: parseInt( term.id, 10 ),
							term_slug: term.slug
						};

						filters[ term.slug ] = {
							text: term.label,
							props: query
						};
					});

					this.filters = filters;
				}


			});

			var gdwpmBar = gdwpmMedia.view.AttachmentsBrowser;

			gdwpmMedia.view.AttachmentsBrowser = gdwpmMedia.view.AttachmentsBrowser.extend({
				createToolbar: function() {

					gdwpmMedia.model.Query.defaultArgs.filterSource = 'filter-media-taxonomies';

					gdwpmBar.prototype.createToolbar.apply(this,arguments);

					this.toolbar.set( key, new gdwpmMedia.view.AttachmentFilters[key]({
						controller: this.controller,
						model:      this.collection.props,
						priority:   -80
						}).render()
					);
				}
			});

		});
	}
}

 });