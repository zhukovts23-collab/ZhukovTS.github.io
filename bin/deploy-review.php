<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
umask(0077);
$dir=__DIR__;$live=dirname($dir).'/public_html';$payload=$dir.'/payload';$result=$dir.'/result.json';
if(is_file($result))exit;
$lock=fopen($dir.'/install.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))exit;
$report=['status'=>'running','stage'=>'backup','time'=>date(DATE_ATOM)];$backup=dirname($dir).'/private-backup-20261108-'.date('His');
function review_copy(string $src,string $dst): void{
 if(is_link($src))return;
 if(is_dir($src)){if(!is_dir($dst)&&!mkdir($dst,0755,true))throw new RuntimeException('mkdir');foreach(new DirectoryIterator($src) as $item)if(!$item->isDot())review_copy($item->getPathname(),$dst.'/'.$item->getFilename());}
 else {if(!copy($src,$dst))throw new RuntimeException('copy');chmod($dst,fileperms($src)&0777);}
}
function review_atomic(string $src,string $dst): void {if(!is_dir(dirname($dst)))mkdir(dirname($dst),0755,true);$temp=tempnam(dirname($dst),'.review-');if(!copy($src,$temp)||!chmod($temp,0644)||!rename($temp,$dst))throw new RuntimeException('write');}
try{
 if(!is_dir($live.'/app')||basename(dirname($live))!=='gameverse-zhukov.duckdns.org')throw new RuntimeException('site_path');
 mkdir($backup,0700);review_copy($live,$backup.'/public_html');chmod($backup,0700);
 $settings=require $live.'/app/config.local.php';$pdo=new PDO($settings['dsn'],$settings['user'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $f=fopen($backup.'/database.sql','xb');chmod($backup.'/database.sql',0600);fwrite($f,"SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n");
 $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){$ident='`'.str_replace('`','``',$table).'`';$create=$pdo->query('SHOW CREATE TABLE '.$ident)->fetch(PDO::FETCH_NUM)[1];fwrite($f,$create.";\n");$types=[];foreach($pdo->query('SHOW FULL COLUMNS FROM '.$ident) as $col)$types[$col['Field']]=$col['Type'];
  foreach($pdo->query('SELECT * FROM '.$ident,PDO::FETCH_ASSOC) as $row){$values=[];foreach($row as $k=>$v)$values[]=$v===null?'NULL':(preg_match('/blob|binary/i',$types[$k])?($v===''?"''":'0x'.bin2hex($v)):$pdo->quote((string)$v));fwrite($f,'INSERT INTO '.$ident.' VALUES ('.implode(',',$values).");\n");}
 }
 $pdo->commit();fwrite($f,"COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n");fclose($f);
 $report['backup']=$backup;$report['stage']='preflight';$work=$dir.'/qa';mkdir($work,0700);
 $cmd=[PHP_BINARY,$payload.'/bin/review-preflight.php',$live,$payload,$work];$process=proc_open($cmd,[0=>['pipe','r'],1=>['file',$dir.'/preflight.log','a'],2=>['file',$dir.'/preflight-error.log','a']],$pipes);fclose($pipes[0]);$code=proc_close($process);$report['preflight']=json_decode(file_get_contents($work.'/preflight-result.json'),true);if($code!==0)throw new RuntimeException('preflight_failed');
 $report['stage']='migration';
 $pdo->exec('CREATE TABLE IF NOT EXISTS rss_feeds(id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(120) NOT NULL,url VARCHAR(500) NOT NULL,url_hash CHAR(64) NOT NULL UNIQUE,active BOOLEAN NOT NULL DEFAULT 1,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARACTER SET utf8mb4');
 $url='https://www.ixbt.com/export/news/rss.xml';$stmt=$pdo->prepare('INSERT IGNORE INTO rss_feeds(title,url,url_hash) VALUES (?,?,?)');$stmt->execute(['iXBT — технологии',$url,hash('sha256',$url)]);
 $stmt=$pdo->prepare('INSERT IGNORE INTO cache(cache_key,payload,updated_at) SELECT ?,payload,updated_at FROM cache WHERE cache_key=?');$stmt->execute(['rss-'.substr(hash('sha256',$url),0,48),'ixbt']);
 $report['stage']='files';
 $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($payload,FilesystemIterator::SKIP_DOTS));
 foreach($iterator as $file){if(!$file->isFile()||$file->isLink())continue;$relative=substr($file->getPathname(),strlen($payload)+1);if(in_array($relative,['app/config.local.php','app/setup.local.php','.htaccess'],true))throw new RuntimeException('private_payload');review_atomic($file->getPathname(),$live.'/'.$relative);}
 $access=file_get_contents($live.'/.htaccess');if(!str_contains($access,'# November review redirects'))$access=str_replace('RewriteEngine On',"RewriteEngine On\n# November review redirects\nRewriteRule ^games\\.html$ /database-search.html [R=301,L]\nRewriteRule ^services/news\\.html$ /rss.html [R=301,L]\nRewriteRule ^games\\.xml$ /glossary.xml [R=301,L]\nRewriteRule ^games\\.xsl$ /glossary.xsl [R=301,L]",$access);
 file_put_contents($dir.'/new-htaccess',$access);review_atomic($dir.'/new-htaccess',$live.'/.htaccess');
 foreach(['games.xml','games.xsl'] as $old)if(is_file($live.'/'.$old))rename($live.'/'.$old,$backup.'/retired-'.$old);
 $report['stage']='seo';$process=proc_open([PHP_BINARY,$live.'/bin/configure-url.php'],[0=>['pipe','r'],1=>['file',$dir.'/seo.log','a'],2=>['file',$dir.'/seo-error.log','a']],$pipes);fclose($pipes[0]);if(proc_close($process)!==0)throw new RuntimeException('seo_failed');
 $report['stage']='verification';$process=proc_open([PHP_BINARY,$live.'/bin/verify-review.php'],[0=>['pipe','r'],1=>['file',$dir.'/verify.log','a'],2=>['file',$dir.'/verify-error.log','a']],$pipes);fclose($pipes[0]);$code=proc_close($process);$report['verification']=json_decode(file_get_contents($live.'/app/review-verification.result.json'),true);if($code!==0)throw new RuntimeException('verification_failed');
 $report['status']='complete';$report['stage']='done';
}catch(Throwable $e){$report['status']='failed';$report['error_type']=get_class($e);if($e instanceof RuntimeException&&preg_match('/^[a-z_]+$/D',$e->getMessage()))$report['error']=$e->getMessage();}
file_put_contents($result,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($result,0600);
echo json_encode(['status'=>$report['status'],'stage'=>$report['stage'],'preflight'=>$report['preflight']['passed']??null,'verification'=>$report['verification']['passed']??null])."\n";
