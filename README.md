# webFetchFace
Web and cron interface to `youtube-dl` and `ffmpeg`, with some convenient extras.

youtube-dl is required in $PATH

This is a pile of hacks that I've accumulated for my own use; it #worksForMe on Linux, but some tweaking is probably required to make it work elsewhere. Also, PHP like it's 2005 :(

The whole thing is built around a SQLite database, `downloads.list`, and table `files`: each row has an `URL` and a `FileStatus` (everything else in the table are bells and whistles). See the class DownloadStatus for possible states.

There are three ways to interact with the database: 
- list the rows (implemented as a PHP webserver script, `index.php`)
- insert/delete row(s) (implemented in the same PHP webserver script)
- process rows (implemented as a BASH cron script)

No access control is implemented - I run this behind a reverse proxy which does that.

Cron script is invoked every few minutes - it does have some checks, so it doesn't start again if it's still running.

`*/10 * * * * bash /some/path/downloader/fetch-urls.sh &>> /tmp/fetch-urls-cron.log`


The webpage also accepts URLs via GET/POST - in the format `?do=add&isScript=1&plaintext=1&urls={url}`, where `{url}` is the shared URL. 
This is usable e.g. through cURL: `curl https://example.com/downloader/?do=add&isScript=1&plaintext=1&urls={{url}} -X POST -u name:password` or as a share action for https://play.google.com/store/apps/details?id=ch.rmy.android.http_shortcuts

The same mechanism is used by the browser extension, in the `Download_through_downloader` directory. Note that the domain is hardcoded, you'd need to replace it with your own. Then you can load this as an unpacked extension into Firefox or Chrome, and start queuing for download by right-clicking.
