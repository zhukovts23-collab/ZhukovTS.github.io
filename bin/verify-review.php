<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
$root=dirname(__DIR__);$checks=[];
function review_check(string $name,bool $ok,array $details=[]): void{global $checks;$checks[]=['test'=>$name,'pass'=>$ok]+$details;}
function review_http(string $path): array {global $config;$ch=curl_init($config['base_url'].$path);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>22]);$body=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$transport=$body!==false;curl_close($ch);return [$status,$body?:'',$transport];}
$manifest=json_decode(file_get_contents($root.'/docs/review-routes.json'),true);
foreach($manifest['content'] as $page){[$code,$body,$tls]=review_http('/'.$page);review_check('content:'.$page,$code===200&&$tls&&str_contains($body,'menu-toggle')&&str_contains($body,'id="main-menu"'),['http'=>$code]);}
foreach(['database.html','database-edit.html','database-search.html','database-statistics.html','xml.html','rss.html','services.html'] as $page){[$code,$body]=review_http('/'.$page);review_check('section:'.$page,$code===200&&str_contains($body,'js/review.js'),['http'=>$code]);}
foreach($manifest['services'] as $page){[$code]=review_http('/'.$page);review_check('service:'.$page,$code===200,['http'=>$code]);}
[$code,$body]=review_http('/glossary.xml');$x=safe_xml($body);review_check('XML_50_records',$code===200&&count($x->term)===50&&count($x->term[0]->children())===6);
[$code,$body]=review_http('/api/xml.php?q='.rawurlencode('кооператив'));review_check('XML_XSLT_filter',$code===200&&str_contains($body,'<td>Кооператив</td>')&&!str_contains($body,'<td>Клиент</td>'));
foreach(['games','ratings','analytics','db_info','game_options','rss_sources','news','feed','stats'] as $action){[$code,$body]=review_http('/api/index.php?action='.$action);$d=json_decode($body,true);review_check('API:'.$action,$code===200&&is_array($d)&&!isset($d['error']),['http'=>$code]);}
foreach(['game_save','rss_source_save','rss_source_toggle','news_save','rate'] as $action){[$code]=review_http('/api/index.php?action='.$action);review_check('GET_write_blocked:'.$action,$code===405,['http'=>$code]);}
foreach(['/app/config.local.php','/database/games-seed.xml','/bin/review-preflight.php'] as $path){[$code]=review_http($path);review_check('private_blocked:'.$path,$code===403,['http'=>$code]);}
[$code]=review_http('/api/index.php?action=admin_data');review_check('admin_data_requires_auth',$code===401,['http'=>$code]);
[$code,$body]=review_http('/services.html');$d=new DOMDocument();@$d->loadHTML($body);$xp=new DOMXPath($d);review_check('ten_services_no_news',$xp->query('//a[contains(@class,"service-card")]')->length===10&&!str_contains($body,'href="services/news.html"'));
[$code,$body]=review_http('/links.html');review_check('thematic_links_only',$code===200&&!str_contains($body,'cbr.ru')&&!str_contains($body,'meteoinfo.ru'));
[$code,$body,$tls]=review_http('/');review_check('HTTPS_home',$code===200&&$tls&&str_starts_with($config['base_url'],'https://'));
$report=['status'=>'complete','time'=>date(DATE_ATOM),'passed'=>count(array_filter($checks,fn($c)=>$c['pass'])),'total'=>count($checks),'checks'=>$checks];
file_put_contents($root.'/app/review-verification.result.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));chmod($root.'/app/review-verification.result.json',0600);echo json_encode(['passed'=>$report['passed'],'total'=>$report['total']])."\n";
exit($report['passed']===$report['total']?0:1);
