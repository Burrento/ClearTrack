<?php

return [

    /*
     * Where database backups live. On the live server this is /home/cleartrack/backups (set in .env
     * by deploy.sh), the same folder as the nightly and pre-deploy backups, so the admin's Backups
     * page lists all of them.
     */
    'backup_path' => env('BACKUP_PATH', storage_path('app/backups')),

    // The mysqldump program; on Windows/XAMPP e.g. C:/xampp/mysql/bin/mysqldump.exe
    'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'),

];
