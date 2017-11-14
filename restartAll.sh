#!/usr/bin/env bash
sqlite3 downloads.sqlite 'UPDATE files SET FileStatus=2 WHERE FileStatus = 904'
