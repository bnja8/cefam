<?php
// Define la configuración de backup.


return [
    'mysql_dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
    'retention_days' => env('BACKUP_RETENTION_DAYS', 30),
];
