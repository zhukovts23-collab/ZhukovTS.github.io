<?php
declare(strict_types=1);
$local = is_file(__DIR__.'/config.local.php') ? require __DIR__.'/config.local.php' : [];
return array_replace([
 'dsn'=>getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;port=3306;dbname=gameverse;charset=utf8mb4',
 'user'=>getenv('DB_USER') ?: 'gameverse', 'password'=>getenv('DB_PASSWORD') ?: '',
 'base_url'=>rtrim(getenv('APP_URL') ?: 'http://localhost:8080','/'),
 'secret'=>getenv('APP_SECRET') ?: '',
],$local);
