$(document).ready(function () {
	var $addBtn = $('#addUrls');
	$addBtn.on('click',function (addEvent) {
		var $abt = $(addEvent.target);
		$abt.find('.actionButton').removeClass('button-add');
		$abt.find('.actionButton').addClass('button-wait');
		window.setTimeout(function () {
			$abt.prop('disabled', 'disabled')
		}, 50);
	});
	$('#addForm').on('submit',function (submitEvent) {
		var urls = $('#urls').val().trim();
		if (urls === '') {
			window.setTimeout(function () {
				var $abt = $('#addUrls');
				$abt.find('.actionButton').addClass('button-add');
				$abt.find('.actionButton').removeClass('button-wait');
				$abt.removeProp('disabled');
				$('#urls').focus();
			},55);
			submitEvent.preventDefault();
			return false;
		}
	});
	var $ytSearch = $('#ytSearch');
	if (apiKey) {

		$ytSearch.show();
		$ytSearch.on('click mouseover', function (e) {
			if (searchInitRunning) {
				return; // do not preventDefault!
			}
			searchInit(e.type === 'click');
		});
	} else {
		$ytSearch.hide();
	}
	$('.ytSearchLoaded').on('submit', function (e) {
		search();
		e.preventDefault();
		return false;
	});

	$('.lazy').Lazy({
		enableThrottle: true,
		combined: true,
		delay: 2000
	});
	$('.timeago').timeago();
	$('.rowStatus').on('dblclick', function (dblClickEvt) {
		var $rs = $(dblClickEvt.target);
		var md = $rs.data('outfilename');
		if (md) {
			window.open(md);
		}
	});
	$('.rowDate').on('dblclick', function (dblClickEvt) {
		var $rs = $(dblClickEvt.target);
		var md = $rs.data('metadatafilename');
		if (md) {
			window.open(md);
		}
	});
	$('button').on('dblclick', function () {
		return false;
	});
	//searchInit();
});

// After the API loads, call a function to enable the search box.
function enableYtSearchUi(upDown, focusSearch) {
	$ytsb = $('#yt-search-button');
	var $ab = $ytsb.find('.actionButton');
	if (upDown) {
		$ab.addClass('button-youtube');
		$ab.removeClass('button-wait');
		$ytsb.removeProp('disabled');
		if (focusSearch === true) {
			$('#yt-query').focus();
		}
	} else {
		$ab.removeClass('button-youtube');
		$ab.addClass('button-wait');
		$ytsb.prop('disabled','disabled');
	}
}

function share_native_inpage(clickEvent) {
	var url = null;
	if (clickEvent.target) {
		var $link = $(clickEvent.target);
		url = $link.prop('href');
		$link.addClass('dl-processing')
		url += '&isScript=1';
		getNotyf().info(texts.messageRequestStarting + ' ' + $link.data('video-url'));
		xhr_call_inpage__iggwrurefj(
			url,
			function (xhrEvent) {
				xhr_call_inpage_result__iggwrurefj(
					xhrEvent,
					getNotyf(),
					url,
					window.location.href,
					texts,
					false,
					function(notyf, requestedUrl, link, response, success) {
						$link.removeClass('dl-processing');
						if (success) {
							$link.addClass('dl-complete');
						} else {
							$link.addClass('dl-error');
						}
					}
				);
			});
		clickEvent.stopPropagation();
		return false;
	}
}

function share_native_inpage_finalCallback(notyf, requestedUrl, link, response, success) {
console.log(notyf, requestedUrl, link, response, success);
}

var searchQueued = null;

// Search for a specified string.
function search() {
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
		maxResults: Math.ceil(ytMaxResults * 1.2), /* we need a buffer - not all are usable results! */
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
		var counter = 0;
		for (var c = 0; c < items.length; c++) {
			var item = items[c];
			if (item.id.kind === "youtube#video") {
				counter++;
				$ytsc.append('<li class="yt-result" id="yt-result-' + counter + '"><a href="https://youtu.be/' + item.id.videoId + '" target="_blank" rel="noopener noreferrer">'
					+ '<img class="yt-thumbnail" src="' + item.snippet.thumbnails.default.url + '"></a>'
					+ '<div class="yt-texts"><div><a class="yt-found-item" href="?do=add&urls=' + encodeURIComponent('https://youtu.be/' + item.id.videoId) + '" data-video-url="https://youtu.be/' + item.id.videoId + '"><span class="statusLabel"></span>' + item.snippet.title
					+ '</a></div><div class="yt-description">' + item.snippet.description
					+ '</div></div></li>');
				if (counter >= ytMaxResults) {
					break;
				}
			}
		}
		$ytsc.append('<li class="yt-more-results"><a href="https://www.youtube.com/results?search_query=' + q + '" target="_blank" rel="noopener noreferrer">Další výsledky z <span class="actionButton button-youtube" title="YouTube"></span> (přibližně ' + response.result.pageInfo.totalResults + ')</a></li>');
		$ytsc.find('.yt-found-item').on('click', share_native_inpage);
	}
	shortcutInit();

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

function searchInit(focusSearch) {
	searchInitRunning = true;
	$('#ytSearch').hide();
	$('.ytSearchLoaded').show();
	if (typeof gapi === 'undefined') {
		return;
	}
	if (!searchQueued) {
		enableYtSearchUi(true, !!focusSearch);
	}
	// 1. Load the JavaScript client library.
	gapi.load('client', gapiStart);
}

var shortcutsUp = false;
function shortcutInit() {
	if (shortcutsUp) {
		return;
	} else {
		shortcutsUp = true;
	}

	var shortcutOpts = {
		'type':'keydown',
		'propagate':false,
		'target':document,
		'disable_in_input':true
	};
	if (shortcut) {
		for (var c=1; c <= 9; c++) {
			var cn = c.toString(10);
			shortcut.add(cn, shortcutDigit, shortcutOpts);
			shortcut.add('Shift+' + cn, shortcutDigit, shortcutOpts);
		}
		shortcut.add('y', searchYtFocus, shortcutOpts);
		shortcut.add('Ctrl+Enter', addItemsToQueue, {
			'type':'keydown',
			'propagate':true,
			'target':document,
			'disable_in_input':false
		});

	}
}
function shortcutDigit(kbdEvent) {
	if (kbdEvent.code && kbdEvent.code.indexOf('Digit') === 0) {
		var id = '#yt-result-' + kbdEvent.code.replace('Digit','');
		var $result = $(id);
		if ($result.length) {
			$result.find('.yt-found-item').trigger('click');
		}
	}
}
function searchYtFocus(e) {
	var $ytSearch = $('#ytSearch:visible');
	if ($ytSearch.length) {
		$ytSearch.trigger('click');
	} else {
		$('#yt-query').focus().select();
	}
	e.preventDefault();
}
function addItemsToQueue(e) {
	var $addBtn = $('#addUrls');
	$addBtn.trigger('click');
	e.stopPropagation();
}
shortcutInit();
