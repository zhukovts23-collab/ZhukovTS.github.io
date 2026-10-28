<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$pass=getenv('ADMIN_PASSWORD');if(!$pass||strlen($pass)<14||strlen($config['secret'])<32)exit("Set ADMIN_PASSWORD (14+ chars) and APP_SECRET (32+ chars).\n");
foreach(explode(';',file_get_contents(__DIR__.'/../database/schema.sql')) as $sql)if(trim($sql))db()->exec($sql);
foreach(safe_xml(file_get_contents(__DIR__.'/../games.xml'))->game as $g)query('INSERT IGNORE INTO games VALUES (?,?,?,?,?,?,?)',[(string)$g['id'],(string)$g->title,(string)$g->genre,(string)$g->platforms,(int)$g->year,(float)$g->rating,(string)$g->tag]);
if(!query('SELECT COUNT(*) FROM news')->fetchColumn())foreach(json_decode(file_get_contents(__DIR__.'/../app/news.json'),true) as $n)query('INSERT INTO news(title,body) VALUES (?,?)',[$n['title'],$n['text']]);
query('INSERT IGNORE INTO admins(username,password_hash) VALUES (?,?)',['admin',password_hash($pass,PASSWORD_DEFAULT)]);
echo "Installed. Existing content and passwords preserved.\n";
