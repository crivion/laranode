<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Laranode User Manager
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify the path to the laranode user manager
    | binary. This is used to create and delete system users.
    |
    */
    'laranode_bin_path' => base_path('laranode-scripts/bin'),

    /*
    |--------------------------------------------------------------------------
    | Laranode PHP-FPM Pools
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify the path to the laranode PHP-FPM pool
    | configuration template. This is used to create and delete PHP-FPM pools.
    |
    */
    'php_fpm_pool_template' => base_path('laranode-scripts/templates/php-fpm-pool.template'),

    /*
    |--------------------------------------------------------------------------
    | Laranode Apache Virtual Hosts
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify the path to the laranode Apache virtual
    | host configuration template. This is used to create and delete Apache
    | virtual hosts.
    */
    'apache_vhost_template' => base_path('laranode-scripts/templates/apache-vhost.template'),


    /*
    |--------------------------------------------------------------------------
    | Laranode File Manager - Editable Mime Types
    |--------------------------------------------------------------------------
    |
    | This option allows you to specify the mime types that can be edited
    | in the file manager.
    */
    'editable_mime_types' =>
    [
        'text/plain',              // .txt, .log, .ini, .env, .conf, .md, .sh, .bash, .zsh
        'text/html',               // .html, .htm
        'text/css',                // .css
        'text/x-php',              // .php
        'text/csv',                // .csv
        'application/x-empty',     // for example empty php files
        'text/javascript',         // .js
        'application/javascript',  // .js
        'application/x-javascript', // .js
        'application/java',         // .java, .js (sometimes)
        'application/json',        // .json
        'application/xml',         // .xml
        'application/x-yaml',      // .yaml, .yml
        'application/x-httpd-php', // .php
        'text/php',                // .php
        'text/x-python',           // .py
        'text/x-c',                // .c
        'text/x-c++',              // .cpp, .cc, .h
        'text/x-java-source',      // .java
        'text/x-shellscript',      // .sh, .bash, .zsh
        'text/x-sql',              // .sql
        'text/markdown',           // .md
        'text/x-typescript',       // .ts, .tsx
        'text/x-jsx',              // .jsx, .tsx
        'text/rtf',
        'application/x-sh',        // .sh
        'application/x-sql',       // .sql
    ]

];
