<head>
	<meta charset="utf-8">
	<title>dl.piskvor.org</title>
	<link rel="stylesheet" type="text/css" href="css/downloader.css" charset="utf-8" />
	<script src="js/jquery-latest.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.lazy.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.cs.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/featherlight.min.js" type="text/javascript" charset="utf-8"></script>
	<script async defer src="https://apis.google.com/js/api.js"></script>
	<script>
		var apiKey = '<?php
			include_once __DIR__ . DIRECTORY_SEPARATOR . 'apikey.php';
			if (!empty($apikey)) { echo $apikey; } // defined in apiKey.php ?>';
		var searchInitRunning = false;
		var searchUsable = false;

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
		})
	</script>
	<script>
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
	</script>
</head>