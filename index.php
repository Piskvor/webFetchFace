<?php

use WebFetchFace\DbConnection;
use WebFetchFace\DownloadStatus;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';
// TODO: refactor
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$filesDb = 'downloads.sqlite';
$ytd = '/home/honza/bin/youtube-dl --restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin';
$relDir = 'tmp';
$tmpDir = __DIR__ . DIRECTORY_SEPARATOR . $relDir;

$noImage = isset($_REQUEST['noImage']);
$isScript = isset($_REQUEST['isScript']);

try {

$db = new DbConnection($filesDb);

$prepFindUrl = $db->prepare('SELECT Id,FileStatus FROM files WHERE Url=? AND FileStatus <= 100');
$prepStatus = $db->prepare('UPDATE files SET FileStatus=? WHERE Id=?');
$prepAttempts = $db->prepare('UPDATE files SET DownloadAttempts=DownloadAttempts+1 WHERE Id=?');
$prepMetadataAttempts = $db->prepare('UPDATE files SET FileStatus=?, MetadataAttempts=MetadataAttempts+1 WHERE Id=?');
$prepStatusJson = $db->prepare('UPDATE files SET FileStatus=?, FileName=?, Title=?, Duration=?, Extractor=?, ThumbFileName=?, DomainId=?, MetadataDownloadedAt=?, QueuedAt=? WHERE Id=?');

if (isset($_REQUEST['do']) && $_REQUEST['do'] !== 'list') {
    $requestId = null;
    if (isset($_REQUEST['id'])) {
        $requestId = (int)$_REQUEST['id'];
        if ($requestId <= 0) {
            $requestId = null;
        }
    }
    $action = $_REQUEST['do'];
    if ($action === 'add') {
        $now = date('Y-m-d H:i:s');

        $prepNew = $db->prepare('INSERT INTO files (Url, UrlDomain, CreatedAt, FileStatus) VALUES (?,?,?,?)');
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
                continue;
            }
            $result = $prepNew->execute(array($url, $host, $now, $parsingResult));


            $id = $db->lastInsertId();

            if (!$id) {
                die('Insert error: ' . $url);
            }
            $jsonFilename = $dir . DIRECTORY_SEPARATOR . $id . '.json';
            if ($parsingResult === DownloadStatus::STATUS_NEW) {
                $prepMetadataAttempts->execute(array(DownloadStatus::STATUS_DOWNLOADING_METADATA, $id));
                $ytdResult = -1;
                exec($ytd . ' --dump-json' . " '" . $url . "' > " . $jsonFilename, $output, $ytdResult);
                chmod($jsonFilename, 0777);
                chgrp($jsonFilename, 'honza');
                if ($ytdResult === 0) {
                    $jsonData = json_decode(file_get_contents($jsonFilename), true, 20);
                    if (count($jsonData) > 0) {
                        $now = date('Y-m-d H:i:s');
                        $thumbFileName = preg_replace(
                            '/.jpe?g$/i', '.jpg',
                            preg_replace('/[^A-Za-z0-9_-]/', '_', $id . '_' . $jsonData['id'] . '_' . basename($jsonData['thumbnail']))
                        );
                        if (!preg_match('/\.jpg$/', $thumbFileName)) {
                            $thumbFileName .= '.jpg';
                        }
                        $thumbPath = $relDir . DIRECTORY_SEPARATOR . $host . DIRECTORY_SEPARATOR . $thumbFileName;
                        $prepStatusJson->execute(array(DownloadStatus::STATUS_QUEUED, $jsonData['_filename'], $jsonData['title'], $jsonData['duration'], $jsonData['extractor'], $thumbPath, $jsonData['id'], $now, $now, $id));

                        if (!empty($jsonData['thumbnail']) && !file_exists($thumbPath)) {
                            $DLFile = $thumbPath;
                            $DLURL = $jsonData['thumbnail'];
                            $fp = fopen($DLFile, 'wb+');
                            $ch = curl_init($DLURL);
                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                            curl_setopt($ch, CURLOPT_FILE, $fp);
                            curl_exec($ch);
                            curl_close($ch);
                            fclose($fp);
                            chmod($DLFile, 0664);
                        }
                    } else {
                        $prepStatus->execute(array(DownloadStatus::STATUS_METADATA_ERROR, $id));
                    }
                } else {
                    $prepStatus->execute(array(DownloadStatus::STATUS_METADATA_ERROR, $id));
                }
            }
        }
    } else if ($action === 'delete' && $requestId) {
        $prepStatus->execute(array(DownloadStatus::STATUS_DISCARDED, $requestId));
    } else if ($action === 'retry' && $requestId) {
        $prepStatus->execute(array(DownloadStatus::STATUS_QUEUED, $requestId));
    }
    if ($isScript) {
		header("Content-Type: application/json");
		echo '{result: "OK"}';
	} else {
		header('Location: ?do=list');
	}	
    exit;
}

?>
<html>
<head>
    <meta charset="utf-8">
    <title>⇩ dl.piskvor.org</title>
	<link rel="stylesheet" type="text/css" href="downloader.css" />
</head>
<body>

<form action="?do=add&me=𝝅🐛" method="POST" accept-charset="UTF-8" enctype="multipart/form-data">
    <fieldset>
        <legend>Jedna nebo více URL adres, každá na novém řádku</legend>
        <textarea title="URL list" rows=5 cols=100 name="urls"></textarea>
    </fieldset>
    <!--<fieldset>
        <legend>Možnosti nastavení</legend>
        <label>Po stažení konvertovat do MP3<input type="checkbox" name="toMP3" value="1" /></label>
        <label>Výsledek na RPi<input type="checkbox" name="toRPi" value="1" /></label>
    </fieldset>
    -->
    <input type="submit" value="Přidat soubory"
           onclick="var that=this;window.setTimeout(function(){that.disabled='disabled'},50)"
           ondblclick="return false"/>
    <input type="hidden" name="wakaWakaWaka" value="·····•····· ᗤ ᗣᗣᗣᗣ"/>
    <!-- @Darth Android: https://superuser.com/questions/194195/is-there-a-pac-man-like-character-in-ascii-or-unicode#comment1260666_357916 -->
</form>

<?php
print '<table border=0>';
print "<tr><th>Jméno</th><th>Náhled</th><th>Stav</th><th>Přidáno</th><th>Staženo</th><th></th></tr>";
$result = $db->query('SELECT * FROM files WHERE FileStatus != ' . DownloadStatus::STATUS_DISCARDED . ' ORDER BY FileStatus = ' . DownloadStatus::STATUS_DOWNLOADING . ' DESC, FileStatus = ' . DownloadStatus::STATUS_FINISHED . ' ASC,PriorityPercent DESC,CreatedAt DESC,DownloadedAt DESC');

$changedFiles = 0;
$toDownload = array();
foreach ($result as $row) {
    if ($row['FileStatus'] == DownloadStatus::STATUS_QUEUED) {
        $toDownload[$row['Id']] = $row['Url'];
        $changedFiles++;
    }

    print '<tr><td>';
    if (!DownloadStatus::isError($row['FileStatus'])) {
        print '<a href="' . $row['Url'] . '">' . $row['Title'] . '</a>';
    } else {
        if (!empty($row['Title'])) {
            print $row['Title'] . '<br>';
        }
        print $row['Url'];
    }
    print '</td><td>';
    if ($row['ThumbFileName'] && !$noImage) {
        print '<img style="max-width: 200px" src="' . $row['ThumbFileName'] . '" />';
    }
    print '</td><td';
    if (DownloadStatus::isError($row['FileStatus'])) {
        print ' class="isError"';
    }
    print ' title="' . $row['FileStatus'] .'"';
    print ' data-filestatus="' . $row['FileStatus'] .'"';
    print '>';
    print DownloadStatus::getTextStatus($row['FileStatus']) . '</td>';

    print '<td>';

    print $row['CreatedAt'];

    print '</td><td>';
    print $row['DownloadedAt'];
    print '</td>';

    print '<td>';

    if (DownloadStatus::isError($row['FileStatus'])) { // || $row['FileStatus'] == DownloadStatus::STATUS_FINISHED) {
        echo "<a class='actionButton' href='?do=delete&id=" . $row['Id'] . "' title='Vyřadit z fronty' onclick='return confirm(\"Opravdu vyřadit z fronty?\")'>\\</a>";

//        if (DownloadStatus::isError($row['FileStatus'])) {
            echo "<a class='actionButton' href='?do=retry&id=" . $row['Id'] . "' title='Zkusit znovu' onclick='return confirm(\"Opravdu zkusit znovu?\")'>J</a>";
//        }
    }
    print '</td></tr>';

}
print '</table>';

if ($changedFiles) {
    file_put_contents('downloads.list', implode("\n", $toDownload) . "\n");
}

// close the database connection
$db = NULL;
} catch (PDOException $e) {
    echo 'Exception : ' . $e->getMessage();
}
?>
</body>
</html>

