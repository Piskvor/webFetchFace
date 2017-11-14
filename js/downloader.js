$(document).ready(function () {
	var $addBtn = $('#addUrls');
	$addBtn.on('click',function () {
		$addBtn.find('.actionButton').html('P');
		window.setTimeout(function(){
			$addBtn.attr('disabled','disabled')
		},50);
	});
	var $ytSearch = $('#ytSearch');
	if (apiKey) {
		$ytSearch.show();
		$ytSearch.on('click mouseover', function () {
			if (searchInitRunning) {
				return; // do not preventDefault!
			}
			searchInitRunning = true;
			$ytsb = $('#yt-search-button');
			$ytsb.attr('disabled','disabled');
			searchInit();
			$ytSearch.hide();
		});
	} else {
		$ytSearch.hide();
	}
	$('.ytSearchLoaded').on('submit',function(e){search();e.preventDefault();return false;});

	$('.lazy').Lazy();
	$('.timeago').timeago();
	$('button').on('dblclick',function () {
		return false;
	});
});

// After the API loads, call a function to enable the search box.
function enableYtSearchUi() {
	$ytsb = $('#yt-search-button');
	$ytsb.find('.actionButton').html('y');
	$ytsb.removeAttr('disabled');
}

// Search for a specified string.
function search(e) {
	var q = $('#yt-query').val().trim();
	if (!q) {
		e.preventDefault();
		return false;
	}
	var request = gapi.client.youtube.search.list({
		q: q,
		part: 'snippet'
	});

	request.execute(function(response) {
		console.log(response.result);
		var str = JSON.stringify(response.result);
		$('#yt-search-container').html('<pre>' + str + '</pre>');
	});
}

function gapiStart() {
	// 2. Initialize the JavaScript client library.
	gapi.client.init({
		'apiKey': apiKey
	}).then(function() {
		gapi.client.load('youtube', 'v3', function(){
			enableYtSearchUi();
			searchUsable = true;
		});
	});
}

function searchInit() {
	$('.ytSearchLoaded').show();
	enableYtSearchUi();
	// 1. Load the JavaScript client library.
	gapi.load('client', gapiStart);
}