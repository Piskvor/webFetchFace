<?php

/** @noinspection UsingInclusionOnceReturnValueInspection */
@include_once __DIR__ . DIRECTORY_SEPARATOR . 'dirs.php';

$ffmpeg = '/home/honza/bin/ffmpeg';
$dn = dirname($ffmpeg);
$ffprobe = $dn . DIRECTORY_SEPARATOR . 'ffprobe';

$filesDb = 'downloads.sqlite';
$relDir = 'tmp';
$ytd = '/home/honza/bin/youtube-dl '
	. '--restrict-filenames '
	. '--prefer-ffmpeg '
	. '--ffmpeg-location ' . $dn ;

$thumbnailWidth = 120;
$bigThumbnailWidth = 800;
$startSeconds = 60;
$endSeconds = 60;
$sqlDate = 'Y-m-d H:i:s';
$isoDate = 'c';
$humanDate = 'j.n.Y H:i:s';

if (!function_exists('getDirs')){
	function getDirs(){
		return array();
	}
}

$tz = new DateTimeZone('Europe/Prague');

function getFilenameParts($fileName, $position=-1) {
	$name = $fileName;
	$ext = '';
	$extPost = strrpos($fileName,'.');
	if ($extPost !== false) {
		$name = substr($fileName, 0, $extPost-1);
		$ext = substr($fileName, $extPost);
	}
	if ($position == 0) {
		return $name;
	} else if ($position == 1) {
		return $ext;
	} else {
		return array($name,$ext);
	}

}

function getSanitizedName($displayId, $title, $fileName) {
	$ext = getFilenameParts($fileName,1);

	$convertedName =	trim($title);
//	$convertedName = str_replace('-', '-',$convertedName);
	$convertedName = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $convertedName);
	$convertedName = str_replace('_+-+_+', '-',$convertedName);
	$convertedName = preg_replace('/[^\w_-]+/','_', $convertedName);
	$convertedName = preg_replace('/_+/','_', $convertedName);
	$convertedName .=  '-' . $displayId . $ext;
	return $convertedName;
}

function getJson($string) {
    return @json_decode($string,true,20);
}

function getJsonFile($fname) {
	return getJson(file_get_contents($fname));
}

function getDisplayId($data) {
	return isset($data['id']) ? $data['id'] : $data['display_id'];
}


function dateTag($date, $inputFormat, $machineFormat, $humanFormat) {
	global $tz; // yuck

	if (!$date) {
		return '';
	}
	$date = date_create_from_format($inputFormat, $date, $tz);
	return '<time class="timeago" datetime="' . $date->format($machineFormat) . '">' . $date->format($humanFormat) . '</time>';
}

function createThumbnail($image_name,$new_width,$new_height,$uploadDir,$moveToDir,$prefix = '')
{
	$path = $uploadDir . '/' . $image_name;

	$mime = getimagesize($path);

	if($mime['mime']=='image/png') {
		$src_img = imagecreatefrompng($path);
	}
	if($mime['mime']=='image/jpg' || $mime['mime']=='image/jpeg' || $mime['mime']=='image/pjpeg') {
		$src_img = imagecreatefromjpeg($path);
	}

	$old_x          =   imageSX($src_img);
	$old_y          =   imageSY($src_img);

	if($old_x > $old_y)
	{
		$thumb_w    =   $new_width;
		$thumb_h    =   $old_y*($new_height/$old_x);
	}

	if($old_x < $old_y)
	{
		$thumb_w    =   $old_x*($new_width/$old_y);
		$thumb_h    =   $new_height;
	}

	if($old_x == $old_y)
	{
		$thumb_w    =   $new_width;
		$thumb_h    =   $new_height;
	}

	$dst_img        =   ImageCreateTrueColor($thumb_w,$thumb_h);

	imagecopyresampled($dst_img,$src_img,0,0,0,0,$thumb_w,$thumb_h,$old_x,$old_y);


	// New save location
	$new_thumb_name = str_replace('.jpg', $prefix . '.jpg', $image_name);
	$new_thumb_loc = $moveToDir . DIRECTORY_SEPARATOR . $new_thumb_name;

	$result = false;

	if($mime['mime']=='image/png') {
		$result = imagepng($dst_img,$new_thumb_loc,8);
	}
	if($mime['mime']=='image/jpg' || $mime['mime']=='image/jpeg' || $mime['mime']=='image/pjpeg') {
		$result = imagejpeg($dst_img,$new_thumb_loc,80);
	}

	imagedestroy($dst_img);
	imagedestroy($src_img);

	return $result ? $new_thumb_loc : null;
}

function updateTinyThumbnail(
	$db, $id,
	$thumbFileName, $thumbnailMaxWidth,
	$thumbnailMaxHeight, $uploadDir, $moveToDir, $prefix = '_tiny'
)
{
	$tinyFilename = createThumbnail(
		$thumbFileName, $thumbnailMaxWidth,
		$thumbnailMaxHeight, $uploadDir, $moveToDir, $prefix
	);
	if ($tinyFilename) {
		$prepThumbnail = $db->prepare(
			'UPDATE files SET TinyFileName=? WHERE Id=?'
		);
		$prepThumbnail->execute(
			array($tinyFilename, $id)
		);
		chmod($tinyFilename, 0664);
	}
	return $tinyFilename;
}

function getThumbName($id, $jsonId, $originalFilename, $appendExtension = true)
{
	$thumbFileName = preg_replace(
		'/.jpe?g$/i', '.jpg',
		preg_replace(
			'/[^A-Za-z0-9_-]/', '_',
			$id . '_' . $jsonId . '_'
			. basename(
				$originalFilename
			)
		)
	);
	if ($appendExtension && !preg_match('/\.jpg$/', $thumbFileName)) {
		$thumbFileName .= '.jpg';
	}
	return $thumbFileName;
}

function getDuration($ffprobe, $id,$fpn,$dir) {
	$vi = getVideoInfo($ffprobe, $id,$fpn,$dir);
	$duration = null;
	foreach ($vi['streams'] as $stream) {
		if (!empty($stream['duration'])) {
			$duration = (int) $stream['duration'];
		}
	}
	return $duration;
}

function getAspectRatio($ffprobe, $id,$fpn,$dir) {
	$ratio = 4/3;
	$vi = getVideoInfo($ffprobe, $id,$fpn,$dir);
	$ar = null;
	foreach ($vi['streams'] as $stream) {
		if (!empty($stream['codec_type']) && $stream['codec_type'] == 'video') {
			$ar = trim($stream['display_aspect_ratio']);
		}
	}
	if (strpos($ar,':') !== false) {
		$ratios = explode(':', $ar);
		if (substr_count($ar,':') === 1) {
			$ratio = $ratios[0] / $ratios[1];
		}
	}
	return $ratio;
}

function getVideoInfo($ffprobe, $id, $videoFilename, $tmpdir) {
	$jsonFile = $tmpdir . DIRECTORY_SEPARATOR . $id . '_ffprobe.json';
	$json = '';
	if (!file_exists($jsonFile)) {
		exec(
			$ffprobe . ' -i "' . $videoFilename . '" -show_streams -print_format json',
			$output
		);
		$json = implode("\n",$output);
		file_put_contents($jsonFile,$json);
		chmod($jsonFile, 0664);
	} else {
		$json = file_get_contents($jsonFile);
	}
	$vi = json_decode($json, true);
	return $vi;
}

function getResizeCommand($ffmpeg, $startSeconds, $remainingSeconds, $fpn, $bigThumbnailWidth, $bigThumbnailHeight, $newThumbName) {
	return $ffmpeg . ' -ss ' . $startSeconds . ' -t ' . $remainingSeconds . ' -i "' . $fpn . '" -vf "thumbnail,scale=' . $bigThumbnailWidth . ':' . $bigThumbnailHeight .'" -frames:v 1 -vsync vfr -vf fps=fps=1/600 "' . $newThumbName . '" -y';
}
