#!/usr/bin/env bash

set -euxo pipefail

JMA_SP="q5tDF"
MAJA_SP=""
BN="threepio download"
trap "exit 1" INT

export DIR_NAME=$(dirname $0)
cd $DIR_NAME;
DIR_NAME=$(pwd)
MY_PID=$$
export SQLITE_DB=$DIR_NAME/downloads.sqlite
YTD_OPTS='--restrict-filenames --prefer-ffmpeg --ffmpeg-location /home/honza/bin --skip-unavailable-fragments --add-metadata --limit-rate=2M'

cp $DIR_NAME/downloads.list $DIR_NAME/downloads.tmp.list
echo -n ''>$DIR_NAME/downloads.list

cd $DIR_NAME
sqlite3 $SQLITE_DB "UPDATE files SET DownloaderPid=${MY_PID},FileStatus=3 WHERE FileStatus=2"
 #AND DownloaderPid IS NULL"

cd $DIR_NAME/files

cleanupDeadDownloads () {
	PIDS_RUNNING=$(sqlite3 $SQLITE_DB 'SELECT DISTINCT DownloaderPid FROM files WHERE FileStatus in(3,4)'||true)
	echo $PIDS_RUNNING
	for dlpid in $PIDS_RUNNING ; do
		if [ "$dlpid" != "" ]; then
			PIDS=$(ps --no-headers -p "$dlpid" || true)
			if [ "$PIDS" = "" ]; then
				sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=904 WHERE DownloaderPid=$dlpid"
			else
				echo "Download running: $PIDS"
			fi
		fi
	done
	find $DIR_NAME/files -size 0 -delete||true 
	find $DIR_NAME/tmp -size 0 -delete||true 
}

cleanupDeadDownloads

ROWS=$(sqlite3 $SQLITE_DB "SELECT Id,Url FROM files WHERE DownloaderPid=${MY_PID} AND FileStatus=3 ORDER BY PriorityPercent DESC,Id ASC"||true)
#ROWS=$(sqlite3 $SQLITE_DB "SELECT Id,Url FROM files WHERE FileStatus=3 ORDER BY PriorityPercent DESC,Id ASC"||true)

IFS=$'\n'
for i in $ROWS ; do

    export ID=$(echo $i | sed 's/|.*//')
    URL=$(echo $i | sed 's/^[0-9]\+|//')
#echo $URL;continue
    sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=4,DownloadStartedAt=DATETIME('now'),DownloadAttempts=DownloadAttempts+1 WHERE Id=${ID}"

    IS_CT=$(echo $URL | grep -c ceskatelevize.cz)
    OPTS=""
    if [ "$IS_CT" -gt 0 ]; then
        OPTS="-f best"
    fi

    COMMAND="/home/honza/bin/youtube-dl $YTD_OPTS $OPTS $URL"
    set +e
    eval $COMMAND
    RESULT=$?
    set -e
    if [ $RESULT -eq 0 ]; then

       sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=100,DownloaderPid=NULL,DownloadedAt=DATETIME('now') WHERE Id=${ID}"
	MESSAGE=$(sqlite3 $SQLITE_DB "SELECT filename FROM files WHERE Id=${ID}") 
	for sp in $JMA_SP $MAJA_SP; do
	 curl -q "https://api.simplepush.io/send/$sp/$BN/$MESSAGE"
	done
	   continue

       sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=5,DownloaderPid=NULL,DownloadedAt=DATETIME('now') WHERE Id=${ID}"
	MESSAGE=$(sqlite3 $SQLITE_DB "SELECT filename FROM files WHERE Id=${ID}") 


        $(
            FILE_NAME=$(sqlite3 $SQLITE_DB "SELECT Filename FROM files WHERE Id=${ID}")
            COMMAND="/home/honza/bin/ffmpeg"
	    $COMMAND -y -i "${DIR_NAME}/files/${FILE_NAME}" "${DIR_NAME}/files/${FILE_NAME}.mp3"
#            echo $COMMAND $COMMAND_OPTS
#            $COMMAND $COMMAND_OPTS
            if [ $? -eq 0 ]; then
                sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=100 WHERE Id=${ID}"
            else
                sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=905 WHERE Id=${ID}"
            fi
        ) &
    else
        sqlite3 $SQLITE_DB "UPDATE files SET FileStatus=904,DownloaderPid=NULL WHERE Id=${ID}"
		exit 2
    fi

done

