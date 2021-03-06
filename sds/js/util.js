import * as rwd from './rwd.js';

$(document).ready(()=>{
	initModals();

	rwd.init(); // init responsive web design (set up resize listener)
	rwd.update(); // run initial check
});

export function initModals() {
	$('.darkenscreen').click(function () {
		closeModal();
	});

	$('.modal-close').click(function () {
		$('.darkenscreen').hide();
		$('.modal').hide();
	});

	$('.bottommsg').click(function () {
		hideBottomMsg();
	})
}

export function getUrlVars() {
	var vars = {};
	var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		vars[key] = value;
	});
	return vars;
}

export function cleanURL() {
	let uri = window.location.toString();
	if (uri.indexOf("?") > 0) {
		let clean_uri = uri.substring(0, uri.indexOf("?"));
		window.history.replaceState({}, document.title, clean_uri);
	}
}

export function showModal(url, completion = null) {
	$('.darkenscreen').fadeIn(100);
	$('.modal').fadeIn(100);

	$.get(url, function (data) {
		$(".modal-padding-container").html(data).show();

		// Add event listeners to whatever gets loaded
		if (completion) {
			completion();
		}
	});
}

export function closeModal() {
	$('.darkenscreen').fadeOut(100);
	$('.modal').fadeOut(100, ()=>{
		$('.modal-padding-container').html("");
	});
	cleanURL();

}

export function showBottomMSG(msg){
	let bottomMSG = $('.bottommsg');
	bottomMSG.html("<p>" + msg + "</p>");
	bottomMSG.show();

	bottomMSG.animate({bottom:'0px'},300);

	setTimeout(function () {
		hideBottomMsg();
	}, 3000);

}

export  function hideBottomMsg() {
	$('.bottommsg').animate({bottom:'-100px'},300, "swing", function() {
		$(this).hide();
	});
}

