<head>
	<meta charset="utf-8">
	<title>dl.piskvor.org</title>
	<link rel="stylesheet" type="text/css" href="css/downloader.css" charset="utf-8" />
	<link rel="shortcut icon" href="/favicon.ico" />
	<link rel="icon" type="image/png" href="images/favicon.png" sizes="16x16" />
	<link rel="icon" type="image/png" href="images/favicon-192x192.png" sizes="192x192" />
	<link rel="apple-touch-icon" sizes="180x180" href="images/apple-touch-icon-180x180.png" />
	<script src="//code.jquery.com/jquery-latest.js" integrity="sha384-wciR07FV6RBcI+YEVsZy/bInxpyn0uefUp6Yi9R5r46Qv/yk/osR5nzY31koh9Uq" crossorigin="anonymous" type="text/javascript" charset="utf-8"></script>
	<script>
        // if we're here, load from CDN failed, fall back to local
		if (typeof jQuery === 'undefined') {
			document.write(decodeURIComponent("%3Cscript src='js/jquery-latest.js' type='text/javascript' charset='utf-8'%3E%3C/script%3E"));
		}
	</script>
    <!-- lazy load images -->
	<script src="js/jquery.lazy.min.js" type="text/javascript" charset="utf-8"></script>
    <!-- show timestamps -->
	<script src="js/jquery.timeago.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.cs.js" type="text/javascript" charset="utf-8"></script>
    <!-- lightbox for image previews -->
	<script src="js/featherlight.min.js" type="text/javascript" charset="utf-8"></script>
    <!-- keyboard shortcuts -->
	<script src="js/shortcut.js" type="text/javascript" charset="utf-8"></script>
    <!-- for adding single URLs, use the identical code that the extension does -->
	<script src="Download_through_downloader/background.js" type="text/javascript" charset="utf-8"></script>
	<!--suppress JSUnusedLocalSymbols - the variables are used in downloader.js -->
	<script>
        // Google API key - for searching YT directly from the page
		var apiKey = '<?php
			/** @noinspection UsingInclusionOnceReturnValueInspection - only include if exists, this is correct */
			@include_once __DIR__ . DIRECTORY_SEPARATOR . 'apikey.php';
			if (!empty($apikey)) { echo $apikey; } // defined in apikey.php ?>';
        // semaphore to avoid calls to uninitialized API
		var searchInitRunning = false;
		//
		var searchUsable = false;
		var ytMaxResults = 9;
		var getNotyf = notyf_setup_inpage__iggwrurefj;
	</script>
	<?php if (!empty($apikey)) { // if no apikey, no point in loading Google's API ?>
		<script async defer src="https://apis.google.com/js/api.js" type="text/javascript" charset="utf-8"></script>
	<?php } ?>
	<script src="js/downloader.js" type="text/javascript" charset="utf-8"></script>
</head>
