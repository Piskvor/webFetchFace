$(document).ready(function () {
	var $addBtn = $('#addUrls');
	$addBtn.on('click', function () {
		$addBtn.find('.actionButton').removeClass('button-youtube');
		$addBtn.find('.actionButton').addClass('button-wait');
		window.setTimeout(function () {
			$addBtn.attr('disabled', 'disabled')
		}, 50);
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
	$('.ytSearchLoaded').on('submit', function (e) {
		search();
		e.preventDefault();
		return false;
	});

	$('.lazy').Lazy();
	$('.timeago').timeago();
	$('button').on('dblclick', function () {
		return false;
	});
	//searchInit();
});

// After the API loads, call a function to enable the search box.
function enableYtSearchUi(upDown) {
	// console.log({"updown":upDown,"usable":searchUsable,"queued":searchQueued});
	// console.trace();
	$ytsb = $('#yt-search-button');
	var $ab = $ytsb.find('.actionButton');
	if (upDown) {
		$ab.addClass('button-youtube');
		$ab.removeClass('button-wait');
		$ytsb.removeProp('disabled');
	} else {
		$ab.removeClass('button-youtube');
		$ab.addClass('button-wait');
		$ytsb.prop('disabled','disabled');
	}
}

function share_native_inpage(clickEvent) {
	var url = null;
	if (clickEvent.target) {
		url = $(clickEvent.target).prop('href');
		url += '&isScript=1';
		xhr_call_inpage__iggwrurefj(
			url,
			function (xhrEvent) {
				xhr_call_inpage_result__iggwrurefj(
					xhrEvent,
					getNotyf(),
					url,
					window.location.href,
					texts,
					share_native_inpage_finalCallback
				);
			});
		clickEvent.stopPropagation();
		return false;
	}
}

function share_native_inpage_finalCallback(notyf, requestedUrl, link, response, success) {

}

var searchQueued = null;

// Search for a specified string.
function search(e) {
	var q = $('#yt-query').val().trim();
	if (q) {
		searchQueued = q; // note that we're only keeping the last one
		if (searchUsable) {
			searchUsableCallback(q);
		}
	}
}

function searchUsableCallback(q) {
	enableYtSearchUi(false);
	searchQueued = null;
	var request = gapi.client.youtube.search.list({
		q: q,
		maxResults: 10,
		part: 'snippet'
	});

	request.execute(function (response) {
		searchCompletedCallback(response, q)
	});
}

function searchCompletedCallback(response, q) {

	var $ytsc = $('#yt-search-container');

	var items = response.items || [];
	if (items.length > 0) {
		$ytsc.empty();
		for (var c = 0; c < items.length; c++) {
			var item = items[c];
			if (item.id.kind === "youtube#video") {
				$ytsc.append('<li><a href="https://youtu.be/' + item.id.videoId + '" target="_blank" rel="noopener noreferrer">'
					+ '<img class="yt-thumbnail" src="' + item.snippet.thumbnails.default.url + '"></a>'
					+ '<div class="yt-texts"><div><a class="yt-found-item" href="?do=add&urls=' + encodeURIComponent('https://youtu.be/' + item.id.videoId) + '" data-video-url="https://youtu.be/' + item.id.videoId + '">' + item.snippet.title
					+ '</a></div><div class="yt-description">' + item.snippet.description
					+ '</div></div></li>');
			}
		}
		$ytsc.append('<li class="more-results"><a href="https://www.youtube.com/results?search_query=' + q + '" target="_blank" rel="noopener noreferrer">Další výsledky z <span class="actionButton button-youtube" title="YouTube"></span> (přibližně ' + response.result.pageInfo.totalResults + ')</a></li>');
		$ytsc.find('.yt-found-item').on('click', share_native_inpage);
	}

	enableYtSearchUi(true);
}

function gapiStart() {
	// 2. Initialize the JavaScript client library.
	gapi.client.init({
		'apiKey': apiKey
	}).then(function () {
		gapi.client.load('youtube', 'v3', function () {
			searchUsable = true;
			if (searchQueued) {
				searchUsableCallback(searchQueued);
			} else {
				enableYtSearchUi(true);
			}
		});
	});
}

function searchInit() {
	searchInitRunning = true;
	$('#ytSearch').hide();
	$('.ytSearchLoaded').show();
	if (typeof gapi === 'undefined') {
		return;
	}
	enableYtSearchUi(true);
	// 1. Load the JavaScript client library.
	gapi.load('client', gapiStart);
}
