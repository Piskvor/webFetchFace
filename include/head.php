<head>
	<meta charset="utf-8">
	<title>dl.piskvor.org</title>
	<link rel="stylesheet" type="text/css" href="css/downloader.css" charset="utf-8" />
	<script src="js/jquery-latest.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.lazy.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.cs.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/featherlight.min.js" type="text/javascript" charset="utf-8"></script>
	<script async defer src="https://apis.google.com/js/api.js" type="text/javascript" charset="utf-8"></script>
	<!--suppress JSUnusedLocalSymbols - used in downloader.js -->
	<script>
		var apiKey = '<?php
			include_once __DIR__ . DIRECTORY_SEPARATOR . 'apikey.php';
			if (!empty($apikey)) { echo $apikey; } // defined in apiKey.php ?>';
		var searchInitRunning = false;
		var searchUsable = false;
	</script>
	<script src="js/downloader.js" type="text/javascript" charset="utf-8"></script>
</head>