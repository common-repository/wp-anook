var $ = jQuery; // incase $ is undefined
var games_list_name = '';

$(function(){

	// rejig width on pageload
	anook_badge_width();
	// rejig width on browser resize (orientation change / as im testing squishyness)
	$('.anook-badge').resize(function(){
		anook_badge_width();
	})

	// Widget games search
	var data = '';
	$('body').on('click', '.anook_admin_games_list i.fa-search', function(){
		var query = encodeURIComponent($(this).prev('input[type=text]').val());
		games_list_name = $(this).parents('.anook_admin_games_list').data('name');
		anook_ajax_fetch('user',$(this).parents('.anook_admin').find('.anook_admin_search input[type=text]').val()+'/games','search='+query,1,$(this));
	})

	// Widget add game
	$('body').on('click','.add_game',function(){
		var row_id = $(this).parent().data('id');
		var game_id = $(this).parent().data('game-id');
		$('.anook_admin .saved').append('<div class="saved_game" data-id="'+row_id+'" data-game-id="'+$('.anook_admin .result[data-id='+row_id+'] input[type=hidden]').val()+'">'+$('.anook_admin .result[data-id='+row_id+']').html()+'</div>');
		$('.anook_admin .saved .saved_game[data-id='+row_id+']').find('.add_game').addClass('remove_game').removeClass('add_game'), $('.anook_admin .saved .saved_game[data-id='+row_id+'] .remove_game').html('-');
		
		$(this).parent().remove();
	})

	// Widget remove game
	$('body').on('click','.remove_game',function(){
		var game_id = $(this).parent().data('game-id');
		$('[data-game-id='+game_id+']').remove();
	})

})

function anook_badge_width(){
	$.each($('.anook-badge'), function(){
		if($(this).width() < 260){
			$('.anook-badge #country').css({'margin-bottom':'5%'})
			if($(this).width() < 225){
				if($(this).attr('id')!='anook-user-badge' && $(this).width() < 180){
					$(this).addClass('nook-ultra-thin');
				} else {
					$(this).removeClass('nook-ultra-thin');
				}
				$(this).addClass('thin');
			} else {
				$(this).removeClass('thin');
			}
		} else {
			$('.anook-badge #country').css({'margin-bottom':'20px'})
		}
	})
}

function anook_ajax_fetch(part,search,attr,json,elem){
	$.post(ajaxurl,{'part':part,'search':search,'attr':attr,'action':'anook_ajax','json_encode':json},function(response){
		var c = 0;
		var data = JSON.parse(response);
		$('.anook_admin .results').html('');
		for(var i in data){
			if(i!='url'&&i!='source'&&i!='timestamp'){
				c++;
				$('.anook_admin .results').append('<div class="result" data-id="'+c+'" data-game-id="'+data[i]['id']+'"><span class="add_game">+</span> <span class="game_name">'+data[i]['name']+'</span><input type="hidden" value="'+data[i]['name']+'" name="'+games_list_name+'['+data[i]['id']+']" /></div>');
			}
		}
	});
}