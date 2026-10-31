<?php
// Copy to config.local.php only on the host. Never commit the filled-in file.
return [
 'dsn'=>'mysql:host=localhost;dbname=HOST_DATABASE;charset=utf8mb4',
 'user'=>'HOST_DATABASE_USER',
 'password'=>'REPLACE_ON_HOST',
 'base_url'=>'https://YOUR_HOST_NAME',
 'secret'=>'REPLACE_WITH_RANDOM_SECRET_AT_LEAST_32_CHARS',
];
