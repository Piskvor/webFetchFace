<?php

namespace WebFetchFace;

class DbConnection
{

    /** @var \PDO */
    private $db;

    public function __construct($filename)
    {
        //open the database
        $this->db = new \PDO('sqlite:' . basename($filename));
        $this->createTables();
    }

    public function query($sql)
    {
        return $this->db->query($sql);
    }

    public function exec($string)
    {
        return $this->db->exec($string);
    }

    private function createTables()
    {
        $this->exec("
          CREATE TABLE files (
            Id INTEGER PRIMARY KEY,
            Url TEXT,
            FileStatus INTEGER DEFAULT '0',
            FileName TEXT,
            Title TEXT,
            Duration INTEGER,
            Extractor TEXT,
            ThumbFileName TEXT,
            UrlDomain TEXT,
            CreatedAt DATETIME,
            MetadataDownloadedAt DATETIME,
            QueuedAt DATETIME,
            DownloadedAt DATETIME,
            DownloadAttempts INTEGER DEFAULT '0',
            MetadataAttempts INTEGER DEFAULT '0'
          )"
        );
    }

}
