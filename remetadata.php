<?php

use WebFetchFace\DbConnection;
use WebFetchFace\DownloadStatus;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'functions.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

// TODO: refactor
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$filesDb = 'downloads.sqlite';
$ytd = '/home/honza/bin/youtube-dl --restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin';
$relDir = 'tmp';
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;


$db = new DbConnection($filesDb);

$sqlDate = 'Y-m-d H:i:s';
$isoDate = 'c';
$humanDate = 'j.n.Y H:i:s';

$result = $db->query('SELECT Id,ThumbFileName FROM files WHERE MetadataFileName IS NULL ORDER BY Id DESC');

$changedFiles = 0;
$toDownload = array();
$thumbnailWidth = 120;


$prepThumbnail = $db->prepare('UPDATE files SET MetadataFileName=? WHERE Id=?');

foreach ($result as $row) {

	$thumbPath = dirname($row['ThumbFileName']);
	$thumbFileName = basename($row['ThumbFileName']);
	$id = $row['Id'];

	if (!$thumbFileName || !file_exists($row['ThumbFileName'])) {
		echo "no thumbnail: $id\n";
		continue;
	}

	$jsonFileName = dirname($row['ThumbFileName']) . '/' . $row['Id'] . '.json';
	if ($jsonFileName && file_exists($jsonFileName)) {
		$prepThumbnail->execute(array($jsonFileName, $id));
	} else {
		echo "cannot update $id\n";
	}

}

$result = $db->query('SELECT Id,MetadataFileName FROM files WHERE DisplayId IS NULL AND MetadataFileName IS NOT NULL ORDER BY Id DESC');

$changedFiles = 0;
$toDownload = array();


$prepDisplayId = $db->prepare('UPDATE files SET DisplayId=? WHERE Id=?');

foreach ($result as $row) {
	$id = $row['Id'];
	$data = json_decode(file_get_contents($row['MetadataFileName']),true, 20);

	if (!$data) {
		echo "Not found: $id";
	}
	
	$did = getDisplayId($data);
	if ($did != '') {
		$prepDisplayId->execute(array($did, $id));
	} else {
		echo "cannot update $id\n";
	}

}
