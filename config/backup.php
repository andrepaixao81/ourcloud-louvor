<?php

return [
    'path' => env('SGML_BACKUP_PATH', '/var/backups/sgml/database'),
    'pg_dump' => env('SGML_PG_DUMP_BINARY', '/usr/bin/pg_dump'),
    'pg_restore' => env('SGML_PG_RESTORE_BINARY', '/usr/bin/pg_restore'),
    'timeout' => (int) env('SGML_BACKUP_TIMEOUT', 300),
    'max_files' => (int) env('SGML_BACKUP_MAX_FILES', 30),
    'upload_max_kb' => (int) env('SGML_BACKUP_UPLOAD_MAX_KB', 524288),
    'restore_request_path' => env('SGML_RESTORE_REQUEST_PATH', '/var/lib/sgml/restore-requests'),
    'restore_status_path' => env('SGML_RESTORE_STATUS_PATH', '/var/lib/sgml/restore-status'),
    'restore_dispatcher' => env('SGML_RESTORE_DISPATCHER', '/usr/local/sbin/sgml-web-restore-dispatch'),
];
