jQuery(document).on('click','.add_tab',function(){
	var tab = jQuery('.tab-setting').eq(0).html();
	jQuery(this).closest('.tab-setting').after('<div class="tab-setting new_tab">'+tab+'</div>');
	jQuery('.new_tab input,.new_tab select').val('');
	var i = 0;
	jQuery('.new_tab tbody tr').each(function() {
		if (i>0) jQuery(this).remove();
		i = 1;
	});
	get_sort();
	reset_index();
	
});


jQuery(document).on('click','.add_field',function(){
	var tr = jQuery(this).closest('tr').html();		
	jQuery(this).closest('tr').after('<tr class="news_str">'+tr+'</tr>');
	jQuery('.news_str').children('td').each(function() {
		jQuery(this).children('input,select').val('');
	});
	jQuery('.news_str').removeClass('news_str');		
	reset_index()
});

jQuery(document).on('click','.remove_field',function(){
	var c = jQuery(this).closest('tbody').children('tr').length;
	if (c>1) jQuery(this).closest('tr').remove();
});

jQuery(document).on('click','.remove_tab',function(){
	jQuery(this).closest('.tab-setting').remove();
	reset_index();
});

get_sort();

function save_settings() {
	documentDirty = false;
	jQuery(document.settings).submit();
}

function get_sort()
{
	jQuery('.sort-str').each(function(i){	
		new Sortable(document.getElementsByClassName('sort-str')[i],{onEnd: function(){ reset_index(); }});
	});
	jQuery('.tab-settings').each(function(i){	
		new Sortable(document.getElementsByClassName('tab-settings')[i],{onEnd: function(){ reset_index(); }});
	});
}

function reset_index()
{
	var ind = 10;
	jQuery('.tab-setting').each(function(){
		var name = jQuery(this).find('.caption').attr('name');
		var atr = [];
		name.replace(/\[(.+?)]/g, function($1) {
			atr.push($1);
		});			
		var new_name = 'settings[tab'+ind+']'+atr[1];
		jQuery(this).find('.caption').attr({'name':new_name});			
		
		var name = jQuery(this).find('.introtext').attr('name');
		var atr = [];
		name.replace(/\[(.+?)]/g, function($1) {
			atr.push($1);
		});			
		var new_name = 'settings[tab'+ind+']'+atr[1];
		jQuery(this).find('.introtext').attr({'name':new_name});						
		
		
		var sn = 0;
		jQuery(this).find('tbody tr').each(function(){
			jQuery(this).find('input,select').each(function(){
				var name = jQuery(this).attr('name');
				var atr = [];
				name.replace(/\[(.+?)]/g, function($1) {
					atr.push($1);
				});						
				var new_name = 'settings[tab'+ind+']['+sn+']'+atr[2];
				jQuery(this).attr({'name':new_name});	
			});
			sn = sn+1;	
		});
		
		ind = ind + 10;
		
	});	
}
