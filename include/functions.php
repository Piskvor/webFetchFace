<?php

/** @noinspection UsingInclusionOnceReturnValueInspection */
@include_once __DIR__ . DIRECTORY_SEPARATOR . 'dirs.php';

$thumbnailWidth = 120;
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
	$convertedName = str_replace('_-_', '-',$convertedName);
	$convertedName = preg_replace('/[^\w_-]+/','_', $convertedName);
	$convertedName = preg_replace('/_+/','_', $convertedName);
	$convertedName .=  '-' . $displayId . $ext;
	return $convertedName;
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
	$thumbFileName, $thumbnailWidth,
	$thumbnailWidth, $uploadDir, $moveToDir, $prefix = '_tiny'
)
{
	$tinyFilename = createThumbnail(
		$thumbFileName, $thumbnailWidth,
		$thumbnailWidth, $uploadDir, $moveToDir, $prefix
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
