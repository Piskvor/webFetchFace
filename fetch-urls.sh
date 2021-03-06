#!/usr/bin/env bash

export LANG=C
set -euxo pipefail

BN="downloader"
FILES_DIR="files/"
TMP_DIR="tmp/"
trap "exit 1" INT

export DIR_NAME=$(dirname $0)
cd ${DIR_NAME};
DIR_NAME=$(pwd)

HTTP_PROXY=${HTTP_PROXY:-}
HTTPS_PROXY=${HTTP_PROXY}
http_proxy=${HTTP_PROXY}
https_proxy=${HTTP_PROXY}

# set proxy variables by script, if any
if [[ -f "$DIR_NAME/set_proxy.sh" ]]; then
    source "$DIR_NAME/set_proxy.sh"
fi

# pushjet IDs, if any
SP_IDS=""
if [[ -f "$DIR_NAME/sp_ids" ]]; then
    source "$DIR_NAME/sp_ids"
fi

MY_PID=$$
mkdir -p "$DIR_NAME/$TMP_DIR"
mkdir -p "$DIR_NAME/$FILES_DIR"
LOCKFILE="$DIR_NAME/$TMP_DIR/fetch-urls.lock"
LOGFILE="$DIR_NAME/$TMP_DIR/full.log"

WHAT=${1:-""}
COUNTER=${2:-0}
COUNTER=$(( $COUNTER + 1 ))
if [[ "$COUNTER" -gt 2 ]]; then
	echo "LOOP!"
	exit 3
fi
if [[ "$WHAT" != "--run-logged" && "$COUNTER" = 1 ]]; then
	$0 --run-logged ${COUNTER} 2>&1 | tee -a ${LOGFILE}
	exit $?
fi

# do not run multiple instances
exec 9>${LOCKFILE}
if ! flock -n 9  ; then
	echo "$$: another instance is running";
	exit 100
fi
echo "running $$"
touch ${LOCKFILE}

# database file
export SQLITE_DB="$DIR_NAME/downloads.sqlite"

# youtube-dl directory
YTD="$HOME/bin/youtube-dl"
if [[ ! -x "$YTD" ]]; then
    YTD="youtube-dl"
fi

# ffmpeg directory
FML="--ffmpeg-location $HOME/bin"
if [[ ! -x "$HOME/bin/ffmpeg" ]]; then
    FML=""
fi
# youtube-dl options
YTD_OPTS='--restrict-filenames --prefer-ffmpeg $FML --skip-unavailable-fragments --add-metadata --limit-rate=3M --fixup=detect_or_warn'

if [[ "$http_proxy" != "" ]]; then
    YTD_OPTS="$YTD_OPTS --proxy=$http_proxy"
fi

# fallback input - read URLs from file
cp "$DIR_NAME/downloads.list" "$DIR_NAME/downloads.tmp.list"
echo -n ''>"$DIR_NAME/downloads.list"

cd ${DIR_NAME}
# lock the queued files to our pid
sqlite3 ${SQLITE_DB} "UPDATE files SET DownloaderPid=${MY_PID},FileStatus=3 WHERE FileStatus=2 OR (FileStatus=3 and DownloaderPid IS NULL)"

cd "$DIR_NAME/$FILES_DIR"

# if pids no longer exist, clean up
cleanupDeadDownloads () {
	PIDS_RUNNING=$(sqlite3 ${SQLITE_DB} 'SELECT DISTINCT DownloaderPid FROM files WHERE FileStatus in(3,4)'||true)
	echo $PIDS_RUNNING
	for dlpid in $PIDS_RUNNING ; do
		if [[ "$dlpid" != "" ]]; then
			PIDS=$(ps --no-headers -p "$dlpid" || true)
			if [[ "$PIDS" = "" ]]; then
				sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=904 WHERE DownloaderPid=$dlpid and FileStatus=4"
				sqlite3 ${SQLITE_DB} "UPDATE files SET DownloaderPid=NULL WHERE DownloaderPid=$dlpid and FileStatus in (3,4)"
			else
				echo "Download running: $PIDS"
			fi
		fi
	done
	find $DIR_NAME/files -size 0 -delete||true 
	find $DIR_NAME/tmp -size 0 -delete||true 
}

cleanupDeadDownloads

# get and process playlist files
ROWS=$(sqlite3 ${SQLITE_DB} "SELECT Id,MetadataFileName FROM files WHERE DownloaderPid=${MY_PID} AND FileStatus=3 AND IsPlaylist=1 ORDER BY PriorityPercent DESC,Id ASC"||true)

IFS=$'\n'
SOME_SUCCESS=0
for i in ${ROWS} ; do

    export ID=$(echo ${i} | sed 's/|.*//')
    METADATA_FILENAME=$(echo ${i} | sed 's/^[0-9]\+|//')

	URLDOMAIN=$(sqlite3 ${SQLITE_DB} "SELECT UrlDomain FROM files WHERE Id=$ID"||true)
	TMP_URL_DIR="$DIR_NAME/$TMP_DIR"
	if [[ -d "$DIR_NAME/$TMP_DIR/$URLDOMAIN" ]]; then
		TMP_URL_DIR="$DIR_NAME/$TMP_DIR/$URLDOMAIN"
	fi
	OUTFILE="$TMP_URL_DIR/$ID.out.txt"

    sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=4,DownloadStartedAt=DATETIME('now', 'localtime'),DownloadAttempts=DownloadAttempts+1 WHERE Id=${ID}"
    set +e
    COMMAND="/usr/bin/php ${DIR_NAME}/index.php add ${METADATA_FILENAME}"
	touch ${OUTFILE} && chgrp www-data ${OUTFILE} && chmod 664 ${OUTFILE}
	date >> ${OUTFILE}
	echo $COMMAND >> ${OUTFILE}
    RESULT=$(eval $COMMAND 2>> ${OUTFILE})
    echo $RESULT >> ${OUTFILE}
	date >> ${OUTFILE}
	RESULT_COUNT=$(echo $RESULT |& grep -cF ',0 errors')
    set -e
    if [[ "$RESULT_COUNT" -eq 1 ]] ; then
        sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=100,DownloaderPid=NULL,DownloadedAt=DATETIME('now', 'localtime') WHERE Id=${ID}"
    else
        sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=904,DownloaderPid=NULL WHERE Id=${ID}"
    fi

done

ROWS=$(sqlite3 ${SQLITE_DB} "SELECT Id,Url FROM files WHERE DownloaderPid=${MY_PID} AND FileStatus=3 AND IsPlaylist=0 ORDER BY PriorityPercent DESC,Id ASC"||true)

IFS=$'\n'
SOME_SUCCESS=0
for i in $ROWS ; do

    export ID=$(echo $i | sed 's/|.*//')
    URL=$(echo $i | sed 's/^[0-9]\+|//')
#echo $URL;continue
    sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=4,DownloadStartedAt=DATETIME('now', 'localtime'),DownloadAttempts=DownloadAttempts+1 WHERE Id=${ID}"

    OPTS="-f bestvideo+bestaudio/best"
#    IS_CT=$(echo $URL | grep -c ceskatelevize.cz || true)
#    if [ "$IS_CT" -gt 0 ]; then
#        OPTS="-f best"
#    fi

	METADATAFILE=$(sqlite3 ${SQLITE_DB} "SELECT MetadataFileName FROM files WHERE Id=$ID"||true)
	METADATAFILE="$DIR_NAME/$METADATAFILE"
	if [[ -f "$METADATAFILE" && -s "$METADATAFILE" ]]; then
		OPTS="$OPTS --load-info-json $METADATAFILE"
	fi

	URLDOMAIN=$(sqlite3 ${SQLITE_DB} "SELECT UrlDomain FROM files WHERE Id=$ID"||true)
	TMP_URL_DIR="$DIR_NAME/$TMP_DIR"
	if [[ -d "$DIR_NAME/$TMP_DIR/$URLDOMAIN" ]]; then
		TMP_URL_DIR="$DIR_NAME/$TMP_DIR/$URLDOMAIN"
	fi
	OUTFILE="$TMP_URL_DIR/$ID.out.txt"

    COMMAND="$YTD $YTD_OPTS $OPTS $URL"
#    echo $COMMAND
#    exit
    set +e
	date >> ${OUTFILE}
	chgrp www-data ${OUTFILE}
	chmod 664 ${OUTFILE}
	echo $COMMAND >> ${OUTFILE}
	# this is where the magic actually happens - we call youtube-dl and let it do its thing
    eval $COMMAND >> ${OUTFILE} 2>&1
    RESULT=$?
    echo $RESULT >> ${OUTFILE}
	date >> ${OUTFILE}
    set -e
    if [[ "$RESULT" -eq 0 || "$RESULT" -eq 127 ]]; then
        # ok, done.
       sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=100,DownloaderPid=NULL,DownloadedAt=DATETIME('now', 'localtime'),FilePath='${FILES_DIR}' WHERE Id=${ID}"
		SOME_SUCCESS=1

	MESSAGE=$(sqlite3 ${SQLITE_DB} "SELECT filename FROM files WHERE Id=${ID}")
	if [ "$SP_IDS" != "" ]; then
        for sp in $SP_IDS; do
          /usr/local/bin/pushjet-cli -s "$sp" -t "done" -m "${MESSAGE}" || true
        done
	fi

# do not convert to audio
	continue

       sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=5,DownloaderPid=NULL,DownloadedAt=DATETIME('now', 'localtime') WHERE Id=${ID}"
	MESSAGE=$(sqlite3 ${SQLITE_DB} "SELECT filename FROM files WHERE Id=${ID}") 


        $(
            FILE_NAME=$(sqlite3 ${SQLITE_DB} "SELECT Filename FROM files WHERE Id=${ID}")
            COMMAND="$HOME/bin/ffmpeg"
	    $COMMAND -y -i "${DIR_NAME}/files/${FILE_NAME}" "${DIR_NAME}/files/${FILE_NAME}.mp3"
#            echo $COMMAND $COMMAND_OPTS
#            $COMMAND $COMMAND_OPTS
            if [[ $? -eq 0 ]]; then
                sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=100 WHERE Id=${ID}"
            else
                sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=905 WHERE Id=${ID}"
            fi
        ) &
    else
        sqlite3 ${SQLITE_DB} "UPDATE files SET FileStatus=904,DownloaderPid=NULL WHERE Id=${ID}"
		exit 2
    fi

done

# fix file names
set +eu
$(
    cd ${DIR_NAME} && \
    php set-new-name.php
) || true

# move downloaded files to respective directories
if [[ "$SOME_SUCCESS" -gt 0 ]]; then
	cd ${DIR_NAME} && \
    $(
	    if [[ -d files/pohadky ]]; then
		cd files/pohadky
	        bash rpi/.scripts/create_dirs.sh
	    fi
	)
fi
exit 0
