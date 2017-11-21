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
$relDir = 'tmp';
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;


$db = new DbConnection($filesDb);

$sqlDate = 'Y-m-d H:i:s';
$isoDate = 'c';
$humanDate = 'j.n.Y H:i:s';

$result = $db->query('SELECT Id,Title,FileName,FilePath,MetadataFileName,DisplayId FROM files WHERE FileNameConverted IS NULL AND DownloadedAt IS NOT NULL AND FileStatus=100 ORDER BY Id DESC');

$changedFiles = 0;
$toDownload = array();
$thumbnailWidth = 120;


$prepConverted = $db->prepare('UPDATE files SET FileNameConverted=? WHERE Id=?');

foreach ($result as $row) {

	$id = $row['Id'];
	$filepath = $row['FilePath'];
	$filenameOld = $row['FileName'];
	
	$parts = getFilenameParts($filenameOld);
	$filenameCandidate = preg_replace('~/+~', '/', $filepath . DIRECTORY_SEPARATOR . $parts[0]);
	$filePathOld = preg_replace('~/+~', '/', $filepath . DIRECTORY_SEPARATOR . $filenameOld);
	$path = glob($filenameCandidate .'*');
	if (!count($path)) {
		continue;
	}
	$filename = '';
	foreach ($path as $foundFile) {
		if (preg_match('/\.(jpg|mp3)$/',$foundFile)) {
			continue;
		}
		$filename = $foundFile;
	}
	if (!$filename) {
		echo "no candidates: $filePathOld\n";
		continue;
	}
	/*
	if ($filePathOld != $filename) {
		echo $filePathOld , "\n",$filename , "\n\n";
	}
	*/
	
	if($row['DisplayId']) {
		$did = $row['DisplayId'];
	} else if ($row['MetadataFileName']) {
		$data = json_decode(file_get_contents($row['MetadataFileName']),true,20);
		$did = getDisplayId($data);
	} else {
		//echo "No displayId, no metadata: $id";
		continue;
	}
	$did .= '__' . $id;
	$newFilename = getSanitizedName($did,$row['Title'],$filename);
	$newFilenamePath = preg_replace('~/+~', '/',$filepath . DIRECTORY_SEPARATOR . $newFilename);
	
	if ($filename != $newFilenamePath) {
		if(rename($filename,$newFilenamePath)) {
			$prepConverted->execute(array($newFilename,$id));
		} else {
			echo "cannot update $id\n";
		}
	} else {
		$prepConverted->execute(array($newFilename,$id));
	}
}
$result = $db->query('SELECT Id,Title,FileNameConverted,FilePath,MetadataFileName,DisplayId FROM files'
. ' WHERE FilePath ="files/"  AND DownloadedAt IS NOT NULL AND FileNameConverted IS NOT NULL AND FileStatus=100 ORDER BY Id DESC');

$dirs = getDirs();
	
$prepFilepath  = $db->prepare('UPDATE files SET FilePath=? WHERE Id=?');

foreach ($result as $row) {

	$id = $row['Id'];
	$filepath = $row['FilePath'];
	$filename = $row['FileNameConverted'];
	$lcfn = strtolower($filename);
	foreach ($dirs as $match => $dir) {
		if (strpos($lcfn, $match) !== false) {
			$newdir = preg_replace('~/+~', '/',$filepath . DIRECTORY_SEPARATOR 
				. 'pohadky' . DIRECTORY_SEPARATOR . 'rpi' . DIRECTORY_SEPARATOR . $dir);
				
			if (is_dir($newdir)) {
				$newfilename = preg_replace('~/+~', '/',$newdir . DIRECTORY_SEPARATOR . $filename);
				$oldfilename = preg_replace('~/+~', '/',$filepath . DIRECTORY_SEPARATOR . $filename);
				if (rename($oldfilename,$newfilename)) {
					$prepFilepath->execute(array($newdir,$id));
				}
			}
			continue;
		}
	}
}