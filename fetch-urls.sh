#!/usr/bin/env bash

DIR_NAME=$(dirname $0)

cd $DIR_NAME/files

/home/honza/bin/youtube-dl -a $DIR_NAME/downloads.list

cd $DIR_NAME
sqlite3 downloads.sqlite "UPDATE files SET FileStatus=3 WHERE FileStatus=1"
echo -n ''>$DIR_NAME/downloads.list