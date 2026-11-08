<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
// Run from a private staging directory. Controllers use isolated QA tables in the same MySQL server.
$live=$argv[1]??'';$payload=$argv[2]??'';$work=$argv[3]??'';
if(!is_dir($live.'/app')||!is_dir($payload.'/api')||!is_dir($work))throw new RuntimeException('paths');
$settings=require $live.'/app/config.local.php';
$prefix='qa_review_20261108_';
$tables=['games','news','admins','messages','blocked_words','blocked_hosts','ratings','votes','visits','rate_limits','cache','steam_pulse_sources','steam_pulse_samples','rss_feeds'];
$site=$work.'/site';if(!is_dir($site))mkdir($site,0700,true);
if(!is_dir($work.'/sessions'))mkdir($work.'/sessions',0700);putenv('TEST_SESSION_DIR='.$work.'/sessions');
foreach(['api','app','tests','database'] as $dir){mkdir($site.'/'.$dir,0700,true);foreach(glob($payload.'/'.$dir.'/*') as $path)if(is_file($path))copy($path,$site.'/'.$dir.'/'.basename($path));}
foreach(['glossary.xml','glossary.xsl','search-index.json'] as $name)copy($payload.'/'.$name,$site.'/'.$name);
// Rewrite only SQL string table identifiers, retaining the controller and validation code.
foreach(glob($site.'/app/*.php') as $file){$s=file_get_contents($file);if(in_array(basename($file),['bootstrap.php','catalog.php','rss.php'],true)){
 foreach($tables as $table)$s=preg_replace('/\b(FROM|JOIN|INTO|UPDATE)\s+'.preg_quote($table,'/').'\b/i','$1 '.$prefix.$table,$s);
 file_put_contents($file,$s);
}}
foreach(glob($site.'/api/*.php') as $file){$s=file_get_contents($file);foreach($tables as $table)$s=preg_replace('/\b(FROM|JOIN|INTO|UPDATE)\s+'.preg_quote($table,'/').'\b/i','$1 '.$prefix.$table,$s);file_put_contents($file,$s);}
$settings['base_url']='https://gameverse-zhukov.duckdns.org';$settings['secret']=bin2hex(random_bytes(32));
file_put_contents($site.'/app/config.local.php',"<?php return ".var_export($settings,true).";\n");chmod($site.'/app/config.local.php',0600);
$db=new PDO($settings['dsn'],$settings['user'],$settings['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$sql=file_get_contents($payload.'/database/schema.sql');foreach($tables as $table)$sql=preg_replace('/\b(TABLE IF NOT EXISTS|REFERENCES)\s+'.preg_quote($table,'/').'\b/i','$1 '.$prefix.$table,$sql);
foreach(explode(';',$sql) as $stmt)if(trim($stmt))$db->exec($stmt);
$db->beginTransaction();foreach(simplexml_load_file($payload.'/database/games-seed.xml')->game as $g){$s=$db->prepare('INSERT INTO '.$prefix.'games VALUES (?,?,?,?,?,?,?)');$s->execute([(string)$g['id'],(string)$g->title,(string)$g->genre,(string)$g->platforms,(int)$g->year,(float)$g->rating,(string)$g->tag]);}
$password=bin2hex(random_bytes(20));$s=$db->prepare('INSERT INTO '.$prefix.'admins(username,password_hash) VALUES (?,?)');$s->execute(['review-admin',password_hash($password,PASSWORD_DEFAULT)]);
// Deterministic, clearly marked QA RSS fixture. Network outages cannot falsify application checks.
$url='https://www.ixbt.com/export/news/rss.xml';$s=$db->prepare('INSERT INTO '.$prefix.'rss_feeds(title,url,url_hash) VALUES (?,?,?)');$s->execute(['QA feed',$url,hash('sha256',$url)]);
$fixture='<?xml version="1.0"?><rss version="2.0"><channel><title>QA feed</title><item><title>Fixture RSS</title><link>https://www.ixbt.games/news/fixture</link><pubDate>Sun, 08 Nov 2026 10:00:00 +0300</pubDate></item></channel></rss>';
$s=$db->prepare('INSERT INTO '.$prefix.'cache(cache_key,payload,updated_at) VALUES (?,?,NOW())');$s->execute(['rss-'.substr(hash('sha256',$url),0,48),$fixture]);$db->commit();
$checks=[];$sid='';$csrf='';
function check_review(bool $ok,string $name): void{global $checks;$checks[]=['test'=>$name,'pass'=>$ok];if(!$ok)throw new RuntimeException($name);}
function call_review(string $action='',?array $data=null,array $query=[],string $controller='index.php',bool $token=true): array{
 global $sid,$csrf,$site,$work;
 $env=['TEST_QUERY'=>json_encode($query+($action!==''?['action'=>$action]:[])),'TEST_METHOD'=>$data===null?'GET':'POST','TEST_BODY'=>$data===null?'':json_encode($data),'TEST_CSRF'=>$token?$csrf:'invalid','TEST_SESSION'=>$sid,'TEST_META'=>$work.'/meta.json','TEST_CONTROLLER'=>$controller];
 foreach($env as $k=>$v)putenv($k.'='.$v);
 $p=proc_open([PHP_BINARY,$site.'/tests/request.php'],[0=>['pipe','r'],1=>['pipe','w'],2=>['file',$work.'/test-errors.log','a']],$pipes,$site);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$exit=proc_close($p);if($exit!==0)throw new RuntimeException('controller_exit');$m=json_decode(file_get_contents($work.'/meta.json'),true);$sid=$m['session']?:$sid;
 return [$m['status'],$controller==='index.php'?json_decode($out,true,512,JSON_THROW_ON_ERROR):$out];
}
try{
 [$status,$s]=call_review('session');$csrf=$s['csrf'];check_review($status===200&&strlen($csrf)===64,'session_csrf');
 [$status,$g]=call_review('games');check_review($status===200&&count($g)===20,'catalog_initial_20');
 [, $a]=call_review('game_options',null,['genre'=>'RPG']);check_review(in_array('PC',$a['platforms'],true)&&!in_array('Mobile',$a['platforms'],true),'dependent_platform_options');
 [, $a]=call_review('games',null,['genre'=>'RPG','platform'=>'PC']);check_review(count($a)>0&&count(array_filter($a,fn($r)=>$r['genre']==='RPG'&&in_array('PC',explode(' / ',$r['platforms']),true)))===count($a),'two_attribute_search');
 [, $a]=call_review('games',null,['q'=>"' OR 1=1 --"]);check_review($a===[],'sql_injection_literal');
 [, $a]=call_review('games',null,['genre'=>'RPG','platform'=>'Mobile']);check_review($a===[],'impossible_platform_empty');
 [$status,$err]=call_review('game_save',['title'=>'QA']);check_review($status===401,'anonymous_game_write_blocked_http_'.$status.'_'.($err['error']??''));
 [$status]=call_review('rss_source_save',['title'=>'QA','url'=>$url]);check_review($status===401,'anonymous_rss_write_blocked');
 [$status]=call_review('news_save',['title'=>'QA','text'=>'QA']);check_review($status===401,'anonymous_news_write_blocked');
 [$status]=call_review('rate',['game_id'=>'portal-2','score'=>4],[],'index.php',false);check_review($status===403,'csrf_write_blocked');
 [$status]=call_review('rate',['game_id'=>'portal-2','score'=>6]);check_review($status===400,'rating_range');
 call_review('rate',['game_id'=>'portal-2','score'=>5]);call_review('rate',['game_id'=>'portal-2','score'=>3]);[, $ratings]=call_review('ratings');$rating=array_values(array_filter($ratings,fn($r)=>$r['id']==='portal-2'))[0];check_review((int)$rating['votes']===1&&(float)$rating['average']===3.0,'rating_saved_replaced_and_read');
 [$status]=call_review('login',['username'=>'review-admin','password'=>$password]);check_review($status===200,'editor_login');
 $row=['title'=>'QA game','genre'=>'QA genre','platforms'=>'PC / QA platform','year'=>'2026','rating'=>'4.2','tag'=>'QA entry','operation'=>'create'];[$status,$saved]=call_review('game_save',$row);$id=$saved['id'];check_review($status===201,'game_create');
 [, $found]=call_review('games',null,['q'=>'QA game']);check_review(count($found)===1&&$found[0]['id']===$id,'game_create_persists');
 $edit=$found[0];$edit['operation']='update';$edit['title']='QA game edited';[$status]=call_review('game_save',$edit);check_review($status===200,'game_edit');
 [$status]=call_review('game_save',$edit);check_review($status===409,'lost_update_protection');
 [, $found]=call_review('games',null,['q'=>'QA game edited']);check_review(count($found)===1,'game_edit_persists');
 $row['rating']='9';[$status]=call_review('game_save',$row);check_review($status===400,'invalid_editor_rating');
 [, $a]=call_review('analytics');check_review((int)$a['summary']['total']===21&&count($a['top'])===5,'statistics_after_insert');
 [$status]=call_review('rss_source_save',['title'=>'Unsafe','url'=>'https://127.0.0.1/feed']);check_review($status===400,'ssrf_ip_blocked');
 [$status]=call_review('rss_source_save',['title'=>'Unsafe','url'=>'https://user:pass@example.org/feed']);check_review($status===400,'rss_credentials_blocked');
 [$status]=call_review('rss_source_save',['title'=>'Unsafe','url'=>'http://example.org/feed']);check_review($status===400,'rss_http_blocked');
 [$status]=call_review('rss_source_save',['title'=>'Duplicate','url'=>$url]);check_review($status===409,'rss_duplicate');
 // Cached, valid RSS permits a deterministic save; DNS is still validated by the real controller.
 $newurl='https://www.ixbt.com/export/news/rss.xml?review=fixture';$s=$db->prepare('INSERT INTO '.$prefix.'cache(cache_key,payload,updated_at) VALUES (?,?,NOW())');$s->execute(['rss-'.substr(hash('sha256',$newurl),0,48),$fixture]);
 [$status,$saved]=call_review('rss_source_save',['title'=>'QA added feed','url'=>$newurl]);check_review($status===201,'rss_form_save');
 [, $sources]=call_review('rss_sources');check_review(count($sources)===2,'rss_sources_persist');
 [$status]=call_review('rss_source_toggle',['id'=>$saved['id'],'active'=>0]);check_review($status===200,'rss_disable_without_deleting');
 [$status]=call_review('news_save',['title'=>'QA news & <test>','text'=>'Text with ; and "quotes"']);check_review($status===200,'rss_news_form_save');
 [, $a]=call_review('news');check_review(count($a)===1&&$a[0]['title']==='QA news & <test>','news_persists');
 [, $a]=call_review('feed');check_review(count($a['items'])===2&&$a['external']['ok'],'rss_merges_local_and_fixture');
 foreach(['rss','xml','wrapped','csv'] as $fmt){[$status,$out]=call_review('',null,['format'=>$fmt],'export.php');check_review($status===200&&($fmt==='csv'?str_contains($out,'QA news'):simplexml_load_string($out)!==false),'export_'.$fmt);}
 [, $out]=call_review('',null,[],'xml.php');$doc=new DOMDocument();@$doc->loadHTML($out);$xp=new DOMXPath($doc);check_review($xp->query('//tbody/tr')->length===50,'xml_50_objects');
 [, $out]=call_review('',null,['q'=>'кооператив'],'xml.php');check_review(str_contains($out,'Кооператив')&&!str_contains($out,'<td>Клиент</td>'),'xml_filter');
 call_review('logout',[]);[, $fresh]=call_review('session');$csrf=$fresh['csrf'];[$status]=call_review('game_save',$edit);check_review($status===401,'logout_protects_write');
 $report=['status'=>'complete','checks'=>$checks,'passed'=>count($checks),'total'=>count($checks),'fixture'=>'Separate prefixed QA tables; actual MySQL and PHP controllers, deterministic RSS fixture'];
}catch(Throwable $e){$report=['status'=>'failed','checks'=>$checks,'error'=>$e->getMessage()];}
finally{
 // Only the disposable tables created by this script are removed. User tables are never modified.
 $db->exec('SET FOREIGN_KEY_CHECKS=0');foreach(array_reverse($tables) as $table)$db->exec('DROP TABLE IF EXISTS '.$prefix.$table);$db->exec('SET FOREIGN_KEY_CHECKS=1');
 unset($settings,$password);foreach(['TEST_BODY','TEST_QUERY','TEST_SESSION','TEST_CSRF'] as $k)putenv($k);
}
file_put_contents($work.'/preflight-result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($work.'/preflight-result.json',0600);
if($report['status']!=='complete')exit(1);
echo 'Preflight passed: '.$report['passed']."\n";
