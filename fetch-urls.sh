#!/usr/bin/env bash

DIR_NAME=$(dirname $0)
cd $DIR_NAME;
DIR_NAME=$(pwd)

cp $DIR_NAME/downloads.list $DIR_NAME/downloads.tmp.list
echo -n ''>$DIR_NAME/downloads.list

cd $DIR_NAME
sqlite3 downloads.sqlite "UPDATE files SET FileStatus=4 WHERE FileStatus=3"

cd $DIR_NAME/files

for i in $(cat $DIR_NAME/downloads.tmp.list) ; do

    IS_CT=$(echo $i | grep -c ceskatelevize.cz)
    OPTS=""
    if [ "$IS_CT" -gt 0 ]; then
        OPTS="-f hls-main-meta"
    fi

    echo /home/honza/bin/youtube-dl --restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin --skip-unavailable-fragments --embed-thumbnail --add-metadata $OPTS $i
    /home/honza/bin/youtube-dl --restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin --skip-unavailable-fragments --embed-thumbnail --add-metadata $OPTS $i

done
#exit
#/home/honza/bin/youtube-dl --restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin --skip-unavailable-fragments --embed-thumbnail --add-metadata  -a $DIR_NAME/downloads.tmp.list

cd $DIR_NAME
sqlite3 downloads.sqlite "UPDATE files SET FileStatus=5 WHERE FileStatus=4"
echo -n ''>$DIR_NAME/downloads.tmp.list
