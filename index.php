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

function xheader ($string, $replace = true, $http_response_code = null)
{
    if (PHP_SAPI !== 'cli') {
        header($string,$replace, $http_response_code);
    }
}

if (PHP_SAPI === 'cli') {
    if ($argc === 3 && $argv[1] === 'add') {
        $noImage = false;
        $isScript = true;
        $plaintext = true;
        $doAction = 'add';
        $requestId = null;

        $urls = array();
        if (preg_match('~^https?://~', $argv[2])) {
            $urlStructure = @parse_url($argv[2]);
            $scheme = $urlStructure && isset($urlStructure['scheme']) ? strtolower($urlStructure['scheme']) : '';
            if ($scheme === 'http' || $scheme === 'https') {
                $urls[] = $argv[2];
            }
        } else {
            $fileName = realpath(__DIR__.'/'.$argv[2]);
            $basePath = realpath(__DIR__);
            if (strpos($fileName, $basePath) === 0 && file_exists($fileName)) {
                $rows = file($fileName);
                foreach ($rows as $row) {
                    $data = getJson($row);
                    if ($data && $data['_type'] === 'url' && $data['ie_key'] === 'Youtube') {
                        $urls[] = 'https://youtu.be/'.$data['url'];
                    }
                }
            }
        }
        $requestUrls = implode("\n", $urls);
    } else {
        echo 'Usage: ' . __FILE__ . ' add fname.json' . "\n";
        exit;
    }
} else {
    $noImage = isset($_REQUEST['noImage']);
    $isScript = isset($_REQUEST['isScript']);
    $plaintext = isset($_REQUEST['plaintext']);
    $doAction = isset($_REQUEST['do']) ? $_REQUEST['do'] : null;
    $requestId = null;
    if (isset($_REQUEST['id'])) {
        $requestId = (int)$_REQUEST['id'];
        if ($requestId <= 0) {
            $requestId = null;
        }
    }
    $requestUrls = isset($_REQUEST['urls']) ? $_REQUEST['urls'] : '';
}

try {

$db = new DbConnection($filesDb);

$prepFindUrl = $db->prepare(
	'SELECT Id,FileStatus FROM files WHERE Url=? AND FileStatus <= 100'
);
$prepFindDuplicate = $db->prepare(
	'SELECT Id AS duplicate FROM files WHERE Extractor=? AND DomainId = ? AND Id != ? ORDER BY Id ASC'
);
$prepFindRunning = $db->prepare(
	'SELECT COUNT(Id) AS running FROM files WHERE FileStatus <= 4'
);
$prepStatus = $db->prepare('UPDATE files SET FileStatus=? WHERE Id=?');
$prepAttempts = $db->prepare(
	'UPDATE files SET DownloadAttempts=DownloadAttempts+1 WHERE Id=?'
);
$prepMetadataAttempts = $db->prepare(
	'UPDATE files SET FileStatus=?, MetadataFileName=?, MetadataAttempts=MetadataAttempts+1 WHERE Id=?'
);

if (!empty($doAction) && $doAction !== 'list') {

	$action = $doAction;
	$titleAdded = array();
	$urlAdded = array();
	$urlSkipped = array();
	$urlErrors = array();
    $actionResult = array(
        'result' => 'OK'
    );
    
	if ($action === 'add') {
		$now = date($sqlDate);

		$prepNew = $db->prepare(
			'INSERT INTO files (Url, UrlDomain, CreatedAt, FileStatus, IsPlaylist) VALUES (?,?,?,?,?)'
		);
		foreach (explode("\n", $requestUrls) as $url) {
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

            $counter = 5;
			// try decoding the URL, might arrive URLencoded.
            while ($counter > 0 && preg_match('/^https?%/', $url)) {
                $url = rawurldecode($url);
            }
			$urlStructure = @parse_url($url);
			$dir = $tmpDir;
			$isPlaylist = 0;
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
					    if (strpos($url,'/playlist') !== false) {
                            $isPlaylist = 1;
                        } else {
					        // remove reference to playlist
                            $url = preg_replace('/&?list=[a-zA-Z0-9_-]+/', '', $url);
                        }
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
				array($url, $host, $now, $parsingResult, $isPlaylist)
			);


			$id = $db->lastInsertId();

			if (!$id) {
				$urlSkipped[] = $url;
				continue;
			}
			$jsonFilename = $dir . DIRECTORY_SEPARATOR . $id . '.json';
			$jsonFilenameLog = $dir . DIRECTORY_SEPARATOR . $id . '.json.log';
			if ($parsingResult === DownloadStatus::STATUS_NEW) {

				$prepMetadataAttempts->execute(
					array(
						DownloadStatus::STATUS_DOWNLOADING_METADATA, substr(
						$jsonFilename, strlen($tmpDir) - strlen($relDir)
					), $id
					)
				);
				$ytdResult = -1;

				if ($isPlaylist) {
                    $command = $ytd.' --yes-playlist --ignore-errors --flat-playlist --dump-json'." '".$url."' > "
                    .$jsonFilename . ' 2>> ' . $jsonFilenameLog;
                    @exec(
                        $command,
                        $output,
                        $ytdResult
                    );
                    @chmod($jsonFilename, 0766);
                    @chgrp($jsonFilename, 'honza');
                    if (file_exists($jsonFilename) && filesize($jsonFilename) > 0) {
                        $prepStatus->execute(
                            array(DownloadStatus::STATUS_QUEUED, $id)
                        );
                        $urlErrors[] = $url;
                    } else {
                        $prepStatus->execute(
                            array(DownloadStatus::STATUS_METADATA_ERROR, $id)
                        );
                        $urlErrors[] = $url;
                    }
                } else {
				    // single file
                    $command = $ytd.' --dump-json'." '".$url."' > "
                        .$jsonFilename . ' 2>> ' . $jsonFilenameLog;
                    @exec(
                        $command,
                        $output,
                        $ytdResult
                    );
                    @chmod($jsonFilename, 0766);
                    @chgrp($jsonFilename, 'honza');

                    if (file_exists($jsonFilename)) {
                        $jsonData = getJsonFile($jsonFilename);
                        $thumbFileName = null;
                        if (count($jsonData) > 0) {
                            $now = date($sqlDate);
                            if (!empty($jsonData['thumbnail'])) {
                                $thumbFileName = getThumbName($id, $jsonData['id'], $jsonData['thumbnail']);
                                $thumbPath = $relDir.DIRECTORY_SEPARATOR.$host;
                                $thumbFilePath = $thumbPath.DIRECTORY_SEPARATOR
                                    .$thumbFileName;
                            }
                            $duplicateId = null;
                            $prepFindDuplicate->bindColumn('duplicate', $duplicateId);
                            $prepFindDuplicate->execute(array(
                                $jsonData['extractor'],
                                $jsonData['id'],
                                $id
                            ));
                            $prepFindDuplicate->fetch();
                            if ($duplicateId > 0) {
                                $downloadStatus = DownloadStatus::STATUS_DUPLICATE;
                                $urlSkipped[] = $url;
                            } else {
                                $downloadStatus = DownloadStatus::STATUS_QUEUED;
                            }

                            $prepStatusJson = $db->prepare(
                                'UPDATE files SET FileStatus=?, FileName=?, DisplayId=?, Title=?, Duration=?, Extractor=?, ThumbFileName=?, DomainId=?, MetadataDownloadedAt=?, QueuedAt=?, DuplicateFor=? WHERE Id=?'
                            );
                            $prepStatusJson->execute(
                                array(
                                    $downloadStatus,
                                    $jsonData['_filename'],
                                    getDisplayId($jsonData),
                                    $jsonData['title'],
                                    $jsonData['duration'],
                                    $jsonData['extractor'],
                                    $thumbFilePath,
                                    $jsonData['id'],
                                    $now,
                                    $now,
                                    $duplicateId,

                                    $id
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

                                updateTinyThumbnail(
                                    $db,
                                    $id,
                                    $thumbFileName,
                                    $thumbnailWidth,
                                    $thumbnailWidth,
                                    $thumbPath,
                                    $thumbPath,
                                    '_tiny'
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
                file_put_contents($jsonFilenameLog, $command . "\n", FILE_APPEND);
                file_put_contents($jsonFilenameLog, $output, FILE_APPEND);
                @chmod($jsonFilenameLog, 0664);
                @chgrp($jsonFilenameLog, 'honza');
			}
		}
	} elseif ($action === 'delete' && $requestId) {
        $prepStatus->execute(
            array(DownloadStatus::STATUS_DISCARDED, $requestId)
        );
    } elseif ($action === 'tail' && $requestId) {

        $resultTailRequest = $db->query(
            'SELECT * FROM files WHERE Id = ' . (int) $requestId
        );
        $rowTailRequest = $resultTailRequest->fetch();
        $outfile = getOutFileName($rowTailRequest);
        $tail = '';
        @exec('tr "\r" "\n" < ' . escapeshellarg($outfile) . ' | tail -n 1', $tail);
        $actionResult['tail'] = reset($tail);

    } elseif ($action === 'retry' && $requestId) {
        $prepStatus->execute(
            array(DownloadStatus::STATUS_QUEUED, $requestId)
        );
	}
	if ($isScript) {
		
        $runningCount = 0;
        $prepFindRunning->bindColumn('running', $runningCount);
        $prepFindRunning->execute();
        $prepFindRunning->fetch();

        $actionResult['added'] = count($titleAdded);
		$actionResult['addedTitles'] = $titleAdded;
		$actionResult['addedUrls'] = $urlAdded;
		$actionResult['skipped'] = count($urlSkipped);
		$actionResult['skippedUrls'] = $urlSkipped;
		$actionResult['errors'] = count($urlErrors);
		$actionResult['errorsUrls'] = $urlErrors;
		$actionResult['pending'] = $runningCount;

		if ($plaintext) {
            xheader('Content-Type: text/plain');
            $resultText = null;
            if ($actionResult['skipped'] || $actionResult['errors'] || !$actionResult['added']) {
                echo $actionResult['added'] . ' added,' . $actionResult['skipped'] . ' skipped,' . $actionResult['errors'] . ' errors,' . $runningCount . ' pending.';
            } else {
                echo 'OK: ' . $actionResult['added'] . ' added. ' . $runningCount . ' pending.';
            }
            echo $resultText;
        } else {
            xheader('Content-Type: application/json');
            echo json_encode($actionResult);
        }
	} else {
		xheader('Location: ?do=list');
	}
	xheader('Expires: ' . gmdate('r'));
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
    . ' LIMIT 200'
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
		if ($rowCounter < 20) { // load first ones directly
			print ' src="';
		} else {
			print ' data-src="';
			$class[] = 'lazy';
		}
		print $image . '" class="' . implode(' ', $class) . '"/></a>';
	} else {
	    if ($row['IsPlaylist']) {
            print '<div class="preview-image no-image playlist-image">M</div>';
        } else {
            print '<div class="preview-image no-image">I</div>';
        }
	}
	print "</td>\n";
	print '<td class="rowTitle">';
	if (!empty($row['Title'])) {
		$title = $row['Title'];
	} else {
		$title = $row['Url'];
	}
	if (!DownloadStatus::isError($row['FileStatus'])) {
		print '<a href="' . $row['Url'] . '">' . $title . '</a>';
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
		if ($row['FileStatus'] == DownloadStatus::STATUS_DOWNLOADING) {
		    print ' <span class="dl-tail"></span>';
        }
	}
	print '</td>';

	print '<td class="rowDate"';
	if ($row['FileStatus'] == DownloadStatus::STATUS_FINISHED || $row['FileStatus'] == DownloadStatus::STATUS_DOWNLOADING || DownloadStatus::isError($row['FileStatus'])) {
		$outfile = getOutFileName($row);
		if (file_exists($outfile)) {
			print ' data-outfilename="' . $outfile . '"';
		}
	}
	print '>';

	print dateTag(
		$row['DownloadedAt']
			? $row['DownloadedAt']
            : ($row['DownloadStartedAt']
			    ? $row['DownloadStartedAt']
                : $row['CreatedAt']),
        $sqlDate,
		$isoDate,
        $humanDate
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

