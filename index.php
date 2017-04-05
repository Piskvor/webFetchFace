<?php

use WebFetchFace\DbConnection;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'autoloader.php';
// TODO: refactor
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$filesDb = 'downloads.sqlite';

try {

    $db = new DbConnection($filesDb);

    if (isset($_REQUEST['do']) && $_REQUEST['do'] === 'add') {
        $now = date('Y-m-d H:i:s');
        foreach (explode("\n", $_REQUEST['urls']) as $url) {
            $url = trim($url);
            echo $url;
            $sql = 'INSERT INTO files (Url, CreatedAt) VALUES ("' . $url . '", "' . $now . '")';
            echo $sql;
            $db->query($sql);
        }
        header('Location: ?do=list');
        exit;
    }

    print "<table border=1>";
    print "<tr><th>Id</th><th>Url</th><th>CreatedAt</th><th>FileStatus</th><th>Actions</th></tr>";
    $result = $db->query('SELECT * FROM files ORDER BY Id ASC');

    $changedFiles = 0;
    $toDownload = array();
    foreach ($result as $row) {
        if ($row['FileStatus'] == 0 || $row['FileStatus'] == 1) {
            $toDownload[$row['Id']] = $row['Url'];
            if ($row['FileStatus'] == 0) {
                $db->query('UPDATE files SET FileStatus=1 WHERE Id=' . $row['Id']);
                $row['FileStatus'] = 1;
                $changedFiles++;
            }
        }
        print "<tr><td>" . $row['Id'] . "</td>";
        print "<td>" . $row['Url'] . "</td>";
        print "<td>" . $row['CreatedAt'] . "</td>";
        print "<td>" . $row['FileStatus'] . "</td>";
        print "<td>";
        if ($row['FileStatus'] == 0) {
            echo "<a href='?do=delete&id=" . $row['Id'] . "'>X</a>";
        }
        print "</td></tr>";

    }
    print "</table>";

    if (1 || $changedFiles) {
        file_put_contents('downloads.list', implode("\n", $toDownload) . "\n");
    }

    // close the database connection
    $db = NULL;
} catch (PDOException $e) {
    echo 'Exception : ' . $e->getMessage();
}
?>
<form action="?do=add" method="POST"><textarea rows=5 cols=100 name="urls"></textarea><br><input type="submit"/></form>
