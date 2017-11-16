<?php

namespace WebFetchFace;

class DbConnection
{

    /** @var \PDO */
    private $db;

    public function __construct($filename)
    {
        $dsn = 'sqlite:' . realpath(basename($filename));
        $this->db = new \PDO($dsn);
        $this->createTables();
    }

    public function query($sql)
    {
        return $this->db->query($sql);
    }

    public function exec($sql)
    {
        return $this->db->exec($sql);
    }

    private function createTables()
    {
        return $this->exec("
          CREATE TABLE IF NOT EXISTS files (
            Id INTEGER PRIMARY KEY,
            Url TEXT,
            FileStatus INTEGER DEFAULT '0',
            FileName TEXT,
            FileNameConverted TEXT,
			FilePath TEXT,
            Title TEXT,
            Duration INTEGER,
            Extractor TEXT,
            ThumbFileName TEXT,
            TinyFileName TEXT,
            UrlDomain TEXT,
            DomainId TEXT,
            CreatedAt DATETIME,
            MetadataDownloadedAt DATETIME,
            QueuedAt DATETIME,
            DownloadStartedAt DATETIME,
            DownloadedAt DATETIME,
            DownloadAttempts INTEGER DEFAULT '0',
            MetadataFilename TEXT,
            MetadataAttempts INTEGER DEFAULT '0',
            DownloaderPid INTEGER,
            PriorityPercent INT DEFAULT 100 NULL
          )"
        );
    }

    public function prepare($string)
    {
        return $this->db->prepare($string);
    }

    public function lastInsertId()
    {
        return $this->db->lastInsertId();
    }

}
