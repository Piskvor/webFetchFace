<?php

namespace WebFetchFace;

class DownloadStatus
{
    const STATUS_NEW = 0; // START HERE: just inserted into database; go to 1 or fail to 900
    const STATUS_INVALID = 900; // bad URL; FAIL
    const STATUS_DOWNLOADING_METADATA = 1; // currently being evaluated; go to 2 or fail to 901
    const STATUS_METADATA_ERROR = 901; // invalid metadata received; FAIL
    const STATUS_QUEUED = 2; // metadata received correctly, ready to download; go to 3
    const STATUS_PREDOWNLOAD = 3; // currently preparing for download; go to 4
    const STATUS_DOWNLOADING = 4; // currently being downloaded; go to 5 or 100 or fail to 904
    const STATUS_ERROR = 904; // download error; FAIL
    const STATUS_CONVERTING = 5; // currently being converted; go to 100 or fail to 905
    const STATUS_CONVERSION_ERROR = 905; // download error; FAIL
    const STATUS_INTERRUPTED = 804; // download interrupted; FAIL
    const STATUS_FINISHED = 100; // download finished; DONE
    const STATUS_DISCARDED = 910; // manually discarded from list; DONE

    private static $statusTexts = array(
        0 => 'Nový',
        900 => 'Neplatná adresa',
        1 => 'Ověřují se data',
        901 => 'Chyba ověření dat',
        2 => 'Čeká na stažení',
        3 => 'Brzy začne stahování',
        4 => 'Stahuje se',
        5 => 'Konvertuje se na MP3',
        100 => 'Dokončeno',
        804 => 'Přerušeno',
        904 => 'Chyba stahování',
        905 => 'Chyba konverze do MP3',
        910 => 'Vyřazeno'
    );

    public static function getTextStatus($status) {
        return self::$statusTexts[$status];
    }

    public static function isError($status) {
        return $status >= 500;
    }
}
