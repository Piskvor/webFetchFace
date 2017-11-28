<?php
// TODO: refactor

use WebFetchFace\DbConnection;
use WebFetchFace\DownloadStatus;

require_once __DIR__ . DIRECTORY_SEPARATOR
	. 'include' . DIRECTORY_SEPARATOR . 'functions.php';

require_once __DIR__ . DIRECTORY_SEPARATOR
	. 'autoloader.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;

$noImage = isset($_REQUEST['noImage']);
$isScript = isset($_REQUEST['isScript']);

try {

$db = new DbConnection($filesDb);

$prepFindUrl = $db->prepare(
	'SELECT Id,FileStatus FROM files WHERE Url=? AND FileStatus <= 100'
);
$prepStatus = $db->prepare('UPDATE files SET FileStatus=? WHERE Id=?');
$prepAttempts = $db->prepare(
	'UPDATE files SET DownloadAttempts=DownloadAttempts+1 WHERE Id=?'
);
$prepMetadataAttempts = $db->prepare(
	'UPDATE files SET FileStatus=?, MetadataFileName=?, MetadataAttempts=MetadataAttempts+1 WHERE Id=?'
);

if (isset($_REQUEST['do']) && $_REQUEST['do'] !== 'list') {
	$requestId = null;
	if (isset($_REQUEST['id'])) {
		$requestId = (int)$_REQUEST['id'];
		if ($requestId <= 0) {
			$requestId = null;
		}
	}
	$action = $_REQUEST['do'];
	$titleAdded = array();
	$urlAdded = array();
	$urlSkipped = array();
	$urlErrors = array();
	if ($action === 'add') {
		$now = date($sqlDate);

		$prepNew = $db->prepare(
			'INSERT INTO files (Url, UrlDomain, CreatedAt, FileStatus) VALUES (?,?,?,?)'
		);
		foreach (explode("\n", $_REQUEST['urls']) as $url) {
			$url = trim($url);
			if (empty($url)) {
				continue;
			}
			$host = '';
			$parsingResult = DownloadStatus::STATUS_INVALID;

			if (stripos($url, 'http') !== 0 && stripos($url, 'http', 1) > 0) {
				if (preg_match('/(https?:[^\\s]+)/', $url, $matches)) {
					$url = $matches[1];
				}
			}

			$urlStructure = @parse_url($url);
			$dir = $tmpDir;
			if ($urlStructure === false || !isset($urlStructure['scheme'])) {
				// URL is seriously borked, do not even try
				$parsingResult = DownloadStatus::STATUS_INVALID;
			} else {
				$scheme = strtolower($urlStructure['scheme']);
				if ($scheme === 'http' || $scheme === 'https') {
					// whitelisted scheme, accept
					$parsingResult = DownloadStatus::STATUS_NEW;
					$host = $urlStructure['host'];
					$dir = $tmpDir . DIRECTORY_SEPARATOR . $host;
					if (!is_dir($dir)) {
						/** @noinspection MkdirRaceConditionInspection */
						mkdir($dir, 0777);
					}
					if (preg_match('/youtu.?be/', $host)
						&& strpos(
							$url, 'list='
						)
					) {
						$url = preg_replace('/&?list=[a-zA-Z0-9-]+/', '', $url);
					}

				} else {
					// URL is somewhat-valid, but not on our whitelist
					$parsingResult = DownloadStatus::STATUS_INVALID;
				}
			}

			$idExists = false;
			$fileStatus = 0;
			$prepFindUrl->bindColumn('Id', $idExists);
			$prepFindUrl->bindColumn('FileStatus', $fileStatus);
			$existsAlready = $prepFindUrl->execute(array($url));
			$prepFindUrl->fetch();
			if ($idExists && !DownloadStatus::isError($fileStatus)) {
				$urlSkipped[] = $url;
				continue;
			}
			$result = $prepNew->execute(
				array($url, $host, $now, $parsingResult)
			);


			$id = $db->lastInsertId();

			if (!$id) {
				$urlSkipped[] = $url;
				continue;
			}
			$jsonFilename = $dir . DIRECTORY_SEPARATOR . $id . '.json';
			if ($parsingResult === DownloadStatus::STATUS_NEW) {

				$prepMetadataAttempts->execute(
					array(
						DownloadStatus::STATUS_DOWNLOADING_METADATA, substr(
						$jsonFilename, strlen($tmpDir) - strlen($relDir)
					), $id
					)
				);
				$ytdResult = -1;
				exec(
					$ytd . ' --dump-json' . " '" . $url . "' > "
					. $jsonFilename, $output, $ytdResult
				);
				chmod($jsonFilename, 0777);
				@chgrp($jsonFilename, 'honza');
				if (file_exists($jsonFilename)) {
					$jsonData = getJsonFile($jsonFilename);
					$thumbFileName = null;
					if (count($jsonData) > 0) {
						$now = date($sqlDate);
						if (!empty($jsonData['thumbnail'])) {
							$thumbFileName = getThumbName($id, $jsonData['id'], $jsonData['thumbnail']);
							$thumbPath = $relDir . DIRECTORY_SEPARATOR . $host;
							$thumbFilePath = $thumbPath . DIRECTORY_SEPARATOR
								. $thumbFileName;
						}
						$prepStatusJson = $db->prepare(
							'UPDATE files SET FileStatus=?, FileName=?, DisplayId=?, Title=?, Duration=?, Extractor=?, ThumbFileName=?, DomainId=?, MetadataDownloadedAt=?, QueuedAt=? WHERE Id=?'
						);
						$prepStatusJson->execute(
							array(
								DownloadStatus::STATUS_QUEUED,
								$jsonData['_filename'], 
								getDisplayId($jsonData),
								$jsonData['title'],
								$jsonData['duration'], $jsonData['extractor'],
								$thumbFilePath, $jsonData['id'], $now, $now, $id
							)
						);

						$urlAdded[] = $url;
						$titleAdded[] = $jsonData['title'];
						$thumbnailUrl = !empty($jsonData['thumbnail']) ? $jsonData['thumbnail'] : null;
						if (!$thumbnailUrl) {
							if (isset($jsonData['thumbnails']) && is_array($jsonData['thumbnails'])) {
								foreach ($jsonData['thumbnails'] as $thumbnail) {
									if (!empty($thumbnail['url'])) {
										$thumbnailUrl = $thumbnail['url'];
										break;
									}
								}
							}
						}

						if (!empty($jsonData['thumbnail'])
							&& !file_exists(
								$thumbFilePath
							)
						) {
							$DLFile = $thumbFilePath;
							$DLURL = $thumbnailUrl;
							$fp = fopen($DLFile, 'wb+');
							$ch = curl_init($DLURL);
							curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
							curl_setopt($ch, CURLOPT_FILE, $fp);
							curl_exec($ch);
							curl_close($ch);
							fclose($fp);
							chmod($DLFile, 0664);

							updateTinyThumbnail($db, $id,
							$thumbFileName, $thumbnailWidth,
								$thumbnailWidth, $thumbPath, $thumbPath, '_tiny'
							);
						}
					} else {
						$prepStatus->execute(
							array(DownloadStatus::STATUS_METADATA_ERROR, $id)
						);
						$urlErrors[] = $url;
					}
				} else {
					$prepStatus->execute(
						array(DownloadStatus::STATUS_METADATA_ERROR, $id)
					);
					$urlErrors[] = $url;
				}
			}
		}
	} else {
		if ($action === 'delete' && $requestId) {
			$prepStatus->execute(
				array(DownloadStatus::STATUS_DISCARDED, $requestId)
			);
		} else {
			if ($action === 'retry' && $requestId) {
				$prepStatus->execute(
					array(DownloadStatus::STATUS_QUEUED, $requestId)
				);
			}
		}
	}
	if ($isScript) {
		header("Content-Type: application/json");
		$result = array(
			'result' => 'OK'
		);
		$result['added'] = count($titleAdded);
		$result['addedTitles'] = $titleAdded;
		$result['addedUrls'] = $urlAdded;
		$result['skipped'] = count($urlSkipped);
		$result['skippedUrls'] = $urlSkipped;
		$result['errors'] = count($urlErrors);
		$result['errorsUrls'] = $urlErrors;
		echo json_encode($result);
	} else {
		header('Location: ?do=list');
	}
	header('Expires: ' . gmdate('r'));
	exit;
}

?>
<html>
<?php
require_once __DIR__ . DIRECTORY_SEPARATOR . 'include' . DIRECTORY_SEPARATOR
	. 'head.php';
?>
<body>

<form id="addForm" action="?do=add&me=𝝅🐛" method="POST" accept-charset="UTF-8"
	  enctype="multipart/form-data">
	<fieldset>
		<legend>Jedna nebo více URL adres, každá na novém řádku</legend>
		<textarea id="urls" title="URL list" rows="5" cols="100" name="urls"></textarea>
	</fieldset>
	<!--<fieldset>
		<legend>Možnosti nastavení</legend>
		<label>Po stažení konvertovat do MP3<input type="checkbox" name="toMP3" value="1" /></label>
		<label>Výsledek na RPi<input type="checkbox" name="toRPi" value="1" /></label>
	</fieldset>
	-->
	<button type="submit" id="addUrls"><span
				class="actionButton button-add" title="klávesa Ctrl+Enter"></span> Přidat soubory
	</button>
	<input type="hidden" name="wakaWakaWaka" value="·····•····· ᗤ ᗣᗣᗣᗣ"/>
	<!-- @Darth Android: https://superuser.com/questions/194195/is-there-a-pac-man-like-character-in-ascii-or-unicode#comment1260666_357916 -->
</form>
<div class="ytSearchWrapper">
	<button class="ytSearchOn" id="ytSearch" style="display: none"><span
				class="actionButton button-youtube"></span> Hledat na YT
	</button>
	<form class="ytSearchLoaded" style="display: none">
		<input type="text" id="yt-query" title="název videa">
		<button id="yt-search-button" disabled="disabled" type="submit"><span
					class="actionButton button-wait" title="klávesa Y"></span> Hledat
		</button>
		<ol id="yt-search-container"></ol>
	</form>
</div>
<div id="lightbox"></div>

<?php
print '<table class="queue-list" border=0>';
print "<tr><th>Náhled</th><th>Jméno</th><th>Stav</th><th>Datum</th><th></th></tr>\n\n";
$result = $db->query(
	'SELECT * FROM files WHERE FileStatus != '
	. DownloadStatus::STATUS_DISCARDED . ' ORDER BY FileStatus = '
	. DownloadStatus::STATUS_DOWNLOADING . ' DESC, FileStatus = '
	. DownloadStatus::STATUS_FINISHED
	. ' ASC,PriorityPercent DESC,CreatedAt DESC,DownloadedAt DESC'
);

$changedFiles = 0;
$rowCounter = 0;
$toDownload = array();
foreach ($result as $row) {
	if ($row['FileStatus'] == DownloadStatus::STATUS_QUEUED) {
		$toDownload[$row['Id']] = $row['Url'];
		$changedFiles++;
	}

	print '<tr data-id="' . $row['Id'] . '">';
	print '<td class="rowThumb">';
	$image = null;
	if (!$noImage) {
		$image = $row['TinyFileName'];
		if (!$image) {
			$image = $row['ThumbFileName'];
		}
	}
	if ($image && file_exists($image)) {
		$class = array(
			'preview-image'
		);
		print '<a href="' . $row['ThumbFileName']
			. '" data-featherlight="image"><img'; //style="max-width: ' . $thumbnailWidth . 'px"';
		if ($rowCounter < 10) { // load first ones directly
			print ' src="';
		} else {
			print ' data-src="';
			$class[] = 'lazy';
		}
		print $image . '" class="' . implode(' ', $class) . '"/></a>';
	} else {
		print '<div class="preview-image no-image">I</div>';
	}
	print "</td>\n";
	print '<td class="rowTitle">';
	if (!DownloadStatus::isError($row['FileStatus'])) {
		print '<a href="' . $row['Url'] . '">' . $row['Title'] . '</a>';
	} else {
		if (!empty($row['Title'])) {
			print $row['Title'] . '<br>';
		}
		print $row['Url'];
	}
	print '</td>';
	print '<td class="rowStatus';
	if (DownloadStatus::isError($row['FileStatus'])) {
		print ' isError rowStatusIsError';
	}
	print '" title="' . $row['FileStatus'] . '"';
	print ' data-filestatus="' . $row['FileStatus'] . '"';
	print ' data-metadatafilename="' . $row['MetadataFileName'] . '"';
	print ">\n";
	if (DownloadStatus::isError($row['FileStatus'])) {
		print '<a href="' . $row['MetadataFileName'] . '">'
			. DownloadStatus::getTextStatus($row['FileStatus']) . '</a>';
	} else {
		print DownloadStatus::getTextStatus($row['FileStatus']);
	}
	print '</td>';

	print '<td class="rowDate">';

	print dateTag(
		$row['DownloadedAt']
			? $row['DownloadedAt'] : ($row['DownloadStartedAt']
			? $row['DownloadStartedAt'] : $row['CreatedAt']), $sqlDate,
		$isoDate, $humanDate
	);

	print '</td>';

	print '<td class="rowActions">';

	if ($row['FileStatus'] == DownloadStatus::STATUS_QUEUED
		|| DownloadStatus::isError($row['FileStatus'])
	) {
		echo "<a class='actionButton' href='?do=delete&id=" . $row['Id']
			. "' title='Vyřadit z fronty' onclick='return confirm(\"Opravdu vyřadit z fronty?\")'>\\</a>";

		if (DownloadStatus::isError($row['FileStatus'])) {
			echo "<a class='actionButton' href='?do=retry&id=" . $row['Id']
				. "' title='Zkusit znovu' onclick='return confirm(\"Opravdu zkusit znovu?\")'>J</a>";
		}
	}
	print "</td></tr>\n\n";

	$rowCounter++;
//	break;
}
print '</table>';

if ($changedFiles) {
	file_put_contents('downloads.list', implode("\n", $toDownload) . "\n");
}

// close the database connection
$db = null;
} catch (PDOException $e) {
	echo 'Exception : ' . $e->getMessage();
}
?>
</body>
</html>

