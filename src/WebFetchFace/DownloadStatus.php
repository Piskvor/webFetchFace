<?php

namespace WebFetchFace;

class DownloadStatus
{
    const STATUS_INVALID = -1; // bad URL; FAIL
    const STATUS_NEW = 0; // START HERE: just inserted into database; go to 1 or fail to -1
    const STATUS_DOWNLOADING_METADATA = 1; // currently being evaluated; go to 3 or fail to 2
    const STATUS_METADATA_ERROR = 2; // invalid metadata received; FAIL
    const STATUS_QUEUED = 3; // metadata received correctly, ready to download; go to 4
    const STATUS_DOWNLOADING = 4; // currently being downloaded; go to 5 or fail to 6
    const STATUS_FINISHED = 5; // download finished; DONE
    const STATUS_ERROR = 6; // download error; FAIL
}
