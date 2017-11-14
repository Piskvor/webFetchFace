$(document).ready(function () {
	var $addBtn = $('#addUrls');
	$addBtn.on('click',function () {
		$addBtn.find('.actionButton').removeClass('button-youtube');
		$addBtn.find('.actionButton').addClass('button-wait');
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
			searchInit();
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
	searchInit();
});

// After the API loads, call a function to enable the search box.
function enableYtSearchUi() {
	$ytsb = $('#yt-search-button');
	$ytsb.find('.actionButton').addClass('button-youtube');
	$ytsb.removeAttr('disabled');
}

var searchQueued = null;
// Search for a specified string.
function search(e)
{
	var q = $('#yt-query').val().trim();
	if (!q) {
		e.preventDefault();
		return false;
	}

	searchQueued = q; // note that we're only keeping the last one
	if (searchUsable) {
		searchUsableCallback(q);
	}
}
function searchUsableCallback(q) {
	searchQueued = null;
	var request = gapi.client.youtube.search.list({
		q: q,
		part: 'snippet'
	});

	request.execute(searchCompletedCallback);
}

function searchCompletedCallback(response) {

	var $ytsc = $('#yt-search-container');

	var items = response.items || [];
	if (items.length > 0) {
		$ytsc.empty();
		for (var c=0; c < items.length; c++) {
			var item = items[c];
			console.log(item);
			if (item.id.kind === "youtube#video") {
				$ytsc.append('<li><a href="https://youtu.be/'+ item.id.videoId + '">'
					+ '<img class="yt-thumbnail" src="'+ item.snippet.thumbnails.default.url + '"></a>'
					+ '<div class="yt-texts"><div><a class="yt-found-item" href="?do=add&urls=' + encodeURIComponent('https://youtu.be/'+ item.id.videoId) + '" data-video-url="https://youtu.be/'+ item.id.videoId + '">'+ item.snippet.title
					+ '</a></div><div class="yt-description">'+ item.snippet.description
					+ '</div></div></li>');
			}
		}
	}

	var str = JSON.stringify(response.result);
//	$('#yt-search-container').html('<pre>' + str + '</pre>');
}

function gapiStart() {
	// 2. Initialize the JavaScript client library.
	gapi.client.init({
		'apiKey': apiKey
	}).then(function() {
		gapi.client.load('youtube', 'v3', function(){
			enableYtSearchUi();
			searchUsable = true;
			if (searchQueued) {
				searchUsableCallback(searchQueued);
			}
		});
	});
}

function searchInit() {
	searchInitRunning = true;
	$('#ytSearch').hide();
	$('.ytSearchLoaded').show();
	enableYtSearchUi();
	// 1. Load the JavaScript client library.
	gapi.load('client', gapiStart);
}