<head>
	<meta charset="utf-8">
	<title>dl.piskvor.org</title>
	<link rel="stylesheet" type="text/css" href="css/downloader.css" charset="utf-8" />
	<script src="//code.jquery.com/jquery-latest.js" integrity="sha384-wciR07FV6RBcI+YEVsZy/bInxpyn0uefUp6Yi9R5r46Qv/yk/osR5nzY31koh9Uq" crossorigin="anonymous" type="text/javascript" charset="utf-8"></script>
	<script>
		if (typeof jQuery === 'undefined') {
			document.write(decodeURIComponent("%3Cscript src='js/jquery-latest.js' type='text/javascript' charset='utf-8'%3E%3C/script%3E"));
		}
	</script>
	<script src="js/jquery.lazy.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/jquery.timeago.cs.js" type="text/javascript" charset="utf-8"></script>
	<script src="js/featherlight.min.js" type="text/javascript" charset="utf-8"></script>
	<script src="Download_through_downloader/background.js" type="text/javascript" charset="utf-8"></script>
	<!--suppress JSUnusedLocalSymbols - used in downloader.js -->
	<script>
		var apiKey = '<?php
			/** @noinspection UsingInclusionOnceReturnValueInspection - only include if exists, this is correct */
			@include_once __DIR__ . DIRECTORY_SEPARATOR . 'apikey.php';
			if (!empty($apikey)) { echo $apikey; } // defined in apikey.php ?>';
		var searchInitRunning = false;
		var searchUsable = false;
		var getNotyf = notyf_setup_inpage__iggwrurefj;
	</script>
	<?php if (!empty($apikey)) { // if no apikey, no point in API ?>
		<script async defer src="https://apis.google.com/js/api.js" type="text/javascript" charset="utf-8"></script>
	<?php } ?>
	<script src="js/downloader.js" type="text/javascript" charset="utf-8"></script>
</head>
