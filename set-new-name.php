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

$result = $db->query('SELECT Id,FileName,MetadataFileName,FileNameConverted,FilePath,ThumbFileName,UrlDomain,DisplayId FROM files WHERE TinyFileName is NULL AND FileStatus=100 ORDER BY Id DESC');
$prepNewThumbnail = $db->prepare(
	'UPDATE files SET ThumbFileName=? WHERE Id=?'
);


foreach ($result as $row) {
	$id = $row['Id'];
	$displayId = $row['DisplayId'];
	$filePath = $row['FilePath'];
	$fileName = !empty($row['FileNameConverted']) ? $row['FileNameConverted'] : $row['FileName'];
	$fpn = $filePath . DIRECTORY_SEPARATOR . $fileName;
	$host = $row['UrlDomain'];
	$thumbFileName = $row['ThumbFileName'];
	$uploadDir = $moveToDir = $relDir . DIRECTORY_SEPARATOR . $host;

	if (empty($thumbFileName) && file_exists($row['MetadataFileName'])) {
		$jsonData = getJsonFile($row['MetadataFileName']);
	}

	if (!empty($thumbFileName) && file_exists($thumbFileName)) { // create from thumbfile
		$newTTN = updateTinyThumbnail(
			$db, $id,
			$thumbFileName, $thumbnailWidth,
			$thumbnailWidth, $uploadDir, $moveToDir, $prefix = '_tiny'
		);
		echo "New thumbnail: ", $newTTN, "\n";
	} else if (file_exists($fpn) && !is_dir($fpn)) {
		$dir = $relDir . DIRECTORY_SEPARATOR . $host;
		$aspectRatio = getAspectRatio($ffprobe, $id,$fpn,$dir);
		$bigThumbnailHeight = floor( $bigThumbnailWidth / $aspectRatio);

		$duration = getDuration($ffprobe, $id,$fpn,$dir);
		$remainingSeconds = $duration - $startSeconds;
		if ($remainingSeconds > $endSeconds) {
			$remainingSeconds -= $endSeconds;
		}

		$newThumbName = $dir . DIRECTORY_SEPARATOR . getThumbName($id, $displayId, '_generated_ffmpeg', true);

		$command = getResizeCommand($ffmpeg, $startSeconds, $remainingSeconds, $fpn, $bigThumbnailWidth, $bigThumbnailHeight, $newThumbName);
		echo $command,"\n";
		exec($command);
		if (file_exists($newThumbName)) {
			$prepNewThumbnail->execute(
				array(
					$newThumbName, $id
				)
			);
			$newTTN = updateTinyThumbnail(
				$db, $id,
				basename($newThumbName), $thumbnailWidth,
				$thumbnailWidth, dirname($newThumbName), $moveToDir, $prefix = '_tiny'
			);
		}
	} else {
		echo "No such file: ", $fpn , "\n";
	}
}

$result = $db->query('SELECT Id,Title,FileName,FilePath,MetadataFileName,DisplayId FROM files WHERE FileNameConverted IS NULL AND DownloadedAt IS NOT NULL AND FileStatus=100 ORDER BY Id DESC');

$changedFiles = 0;
$toDownload = array();

$prepConverted = $db->prepare('UPDATE files SET FileNameConverted=? WHERE Id=?');

foreach ($result as $row) {

	$id = $row['Id'];
	$filepath = $row['FilePath'];
	$filenameOld = $row['FileName'];
	$displayId = $row['DisplayId'];

	$parts = getFilenameParts($filenameOld);
	$filenameCandidate = preg_replace('~/+~', '/', $filepath . DIRECTORY_SEPARATOR . $parts[0]);
	$filePathOld = preg_replace('~/+~', '/', $filepath . DIRECTORY_SEPARATOR . $filenameOld);
	$path1 = glob($filenameCandidate .'*');
    $path2 = glob(preg_replace('~/+~', '/', $filepath . '/*__' . $id . '.*'));
    $path3 = glob(preg_replace('~/+~', '/', $filepath . '/*' . $displayId . '*'));
    $path = array_merge($path1, $path2, $path3);
	if (!count($path)) {
       continue;
	}
//	var_dump($path);exit;

	$filename = '';
	foreach ($path as $foundFile) {
		if (preg_match('/\.(jpg|jpeg|mp3|json|m4a)$/i',$foundFile)) {
			continue;
		}
		$filename = $foundFile;
	}
	if (!$filename || is_dir($filename)) {
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
	}
	if ($row['MetadataFileName']) {
		$data = getJsonFile($row['MetadataFileName']);
		if (!$did) {
            $did = getDisplayId($data);
        }
	} else {
		echo "No displayId, no metadata: $id\n";
		continue;
	}
	$did .= '__' . $id;
	$newFilename = getSanitizedName($did,$row['Title'],$filename);
	$newFilenamePath = preg_replace('~/+~', '/',$filepath . DIRECTORY_SEPARATOR . $newFilename);

	if ($filename !== $newFilenamePath) {
		if(!is_dir($filename) && rename($filename,$newFilenamePath)) {
			$prepConverted->execute(array($newFilename,$id));
			echo "rename: $filename -> $newFilenamePath\n";
		} else {
			echo "cannot update $id\n";
		}
	} else {
		$prepConverted->execute(array($newFilename,$id));
	}
}
$result = $db->query('SELECT Id,Title,FileNameConverted,FilePath,MetadataFileName,DisplayId FROM files'
. ' WHERE FilePath ="files/"  AND FileNameConverted IS NOT NULL AND FileStatus=100 ORDER BY Id DESC');

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
				$newfilename = str_replace('_-_','-', preg_replace('~/+~', '/',$newdir . DIRECTORY_SEPARATOR . $filename));
				$oldfilename = preg_replace('~/+~', '/',$filepath . DIRECTORY_SEPARATOR . $filename);
				if (!is_dir($filename) && rename($oldfilename,$newfilename)) {
					$prepFilepath->execute(array($newdir,$id));
				} else if (file_exists($newfilename)) {
					$prepFilepath->execute(array($newfilename, $id));
					echo "found in new location\n";
				}
			}
			continue;
		}
	}
}
