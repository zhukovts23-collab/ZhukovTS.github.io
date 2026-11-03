<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
foreach(explode(';',file_get_contents(__DIR__.'/../database/pulse.sql')) as $sql)if(trim($sql))db()->exec($sql);
echo "Steam Pulse tables ready. Existing data preserved.\n";
