<?php

use WebFetchFace\DbConnection;
use WebFetchFace\DownloadStatus;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR . 'functions.php';

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';

// TODO: refactor
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;


$db = new DbConnection($filesDb);

$result = $db->query('SELECT * FROM files WHERE TinyFileName IS NULL AND FileStatus != ' . DownloadStatus::STATUS_DISCARDED . ' ORDER BY FileStatus = ' . DownloadStatus::STATUS_DOWNLOADING . ' DESC, FileStatus = ' . DownloadStatus::STATUS_FINISHED . ' ASC,PriorityPercent DESC,CreatedAt DESC,DownloadedAt DESC');

$changedFiles = 0;
$toDownload = array();

$prepThumbnail = $db->prepare('UPDATE files SET TinyFileName=? WHERE Id=?');

foreach ($result as $row) {

	$thumbPath = dirname($row['ThumbFileName']);
	$thumbFileName = basename($row['ThumbFileName']);
	$id = $row['Id'];

	if (!$thumbFileName || !file_exists($row['ThumbFileName'])) {
		echo "no thumbnail: $id\n";
		continue;
	}

	$tinyFilename = createThumbnail($thumbFileName,$thumbnailWidth, $thumbnailWidth, $thumbPath, $thumbPath, '_tiny');
	if ($tinyFilename) {
		$prepThumbnail->execute(array($tinyFilename, $id));
		chmod($tinyFilename, 0664);
		echo $tinyFilename, "\n";
	} else {
		echo "cannot resize $thumbFileName\n";
	}

}