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

];
