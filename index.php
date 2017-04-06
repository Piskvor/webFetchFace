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

try {

$db = new DbConnection($filesDb);

$prepStatus = $db->prepare('UPDATE files SET FileStatus=? WHERE Id=?');
$prepAttempts = $db->prepare('UPDATE files SET DownloadAttempts=DownloadAttempts+1 WHERE Id=?');
$prepMetadataAttempts = $db->prepare('UPDATE files SET FileStatus=?, MetadataAttempts=MetadataAttempts+1 WHERE Id=?');
$prepStatusJson = $db->prepare('UPDATE files SET FileStatus=?, FileName=?, Title=?, Duration=?, Extractor=?, ThumbFileName=?, DomainId=?, MetadataDownloadedAt=?, QueuedAt=? WHERE Id=?');

if (isset($_REQUEST['do']) && $_REQUEST['do'] === 'add') {
    $now = date('Y-m-d H:i:s');

    $prepNew = $db->prepare('INSERT INTO files (Url, UrlDomain, CreatedAt, FileStatus) VALUES (?,?,?,?)');
    foreach (explode("\n", $_REQUEST['urls']) as $url) {
        $url = trim($url);
        if (empty($url)) {
            continue;
        }
        $host = '';
        $parsingResult = DownloadStatus::STATUS_INVALID;
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
                /** @noinspection MkdirRaceConditionInspection */
                mkdir($dir, 0777);
            } else {
                // URL is somewhat-valid, but not on our whitelist
                $parsingResult = DownloadStatus::STATUS_INVALID;
            }
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
                        '/_jpe?g$/i', '.jpg',
                        preg_replace('/[^A-Za-z0-9_-]/', '_', $id . '_' . $jsonData['id'] . '_' . basename($jsonData['thumbnail']))
                    );
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
    header('Location: ?do=list');
    exit;
}

?>
<html>
<head>
    <meta charset="utf-8">
    <title>dl.piskvor.org</title>
    <style>
        .isError {
            color: maroon;
        }
        label {
            display: block;
        }
    </style>
</head>
<body><?php
print '<table border=0>';
#    print "<tr><th>Id</th><th>Title</th><th>Thumbnail</th><th>Url</th><th>FileStatus</th><th>Actions</th></tr>";
$result = $db->query('SELECT * FROM files ORDER BY Id ASC');

$changedFiles = 0;
$toDownload = array();
foreach ($result as $row) {
    if ($row['FileStatus'] == DownloadStatus::STATUS_QUEUED) {
        $toDownload[$row['Id']] = $row['Url'];
        $changedFiles++;
    }

    print '<tr><td>';
    if ($row['FileStatus'] != DownloadStatus::STATUS_INVALID) {
        print '<a href="' . $row['Url'] . '">' . $row['Title'] . '</a>';
    } else {
        print $row['Title'];
    }
    print '</td><td>';
    if ($row['ThumbFileName']) {
        print '<img style="max-width: 200px" src="' . $row['ThumbFileName'] . '" />';
    }
    print '</td><td';
    if (DownloadStatus::isError($row['FileStatus'])) {
        print ' class="isError"';
    }
    print '>';
    print DownloadStatus::getTextStatus($row['FileStatus']) . '</td>';
    print '<td>';

    if ($row['FileStatus'] == DownloadStatus::STATUS_INVALID) {
        echo "<a href='?do=delete&id=" . $row['Id'] . "'>X</a>";
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
           onclick="var that=this;window.setTimeout(function(){that.disabled='disabled'},100)"
           ondblclick="return false"/>
    <input type="hidden" name="wakaWakaWaka" value="·····•····· ᗤ ᗣᗣᗣᗣ" /><!-- @Darth Android: https://superuser.com/questions/194195/is-there-a-pac-man-like-character-in-ascii-or-unicode#comment1260666_357916 -->
</form>
</body>
</html>

