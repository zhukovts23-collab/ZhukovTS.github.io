<?php
declare(strict_types=1);
// HTTPS only, no credentials, redirects or private-network targets; pin validated DNS.
function rss_target(string $url): array {
 $p=parse_url($url);
 if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($p)||($p['scheme']??'')!=='https'||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||(isset($p['port'])&&$p['port']!==443))throw new RuntimeException('Укажите HTTPS-адрес RSS без пароля и нестандартного порта.');
 $host=strtolower($p['host']??'');
 if(!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,63}$/D',$host)||preg_match('/\.(?:local|localhost|internal|test|invalid)$/D',$host))throw new RuntimeException('Нужен публичный домен источника.');
 $ips=gethostbynamel($host);if(!$ips)throw new RuntimeException('Не удалось определить адрес источника.');
 foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)||preg_match('/^(?:0|127|169\.254|100\.(?:6[4-9]|[78][0-9]|9[0-9]|1[01][0-9]|12[0-7])|192\.0\.0|198\.(?:18|19))\./',$ip))throw new RuntimeException('Внутренние и служебные адреса источников запрещены.');}
 return [$host,$ips[0]];
}
function rss_entries(SimpleXMLElement $x): array {
 if($x->getName()==='rss'&&isset($x->channel))return iterator_to_array($x->channel->item,false);
 if($x->getName()==='feed')return iterator_to_array($x->children('http://www.w3.org/2005/Atom')->entry,false);
 throw new RuntimeException('Источник должен содержать RSS 2.0 или Atom.');
}
function rss_fetch(string $url): array {
 $key='rss-'.substr(hash('sha256',$url),0,48);$old=query('SELECT payload,updated_at FROM cache WHERE cache_key=?',[$key])->fetch();
 if($old&&time()-strtotime($old['updated_at'])<900)return ['body'=>$old['payload'],'updated_at'=>$old['updated_at'],'stale'=>false];
 try{
  [$host,$ip]=rss_target($url);$body='';$ch=curl_init($url);
  curl_setopt_array($ch,[CURLOPT_RESOLVE=>[$host.':443:'.$ip],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>5,CURLOPT_USERAGENT=>'GameVerse RSS Reader/2.0',CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){if(strlen($body)+strlen($chunk)>2000000)return 0;$body.=$chunk;return strlen($chunk);}]);
  $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
  if($ok===false||$status!==200)throw new RuntimeException('Источник не ответил RSS-документом (HTTP '.$status.').');
  rss_entries(safe_xml($body));query('INSERT INTO cache(cache_key,payload,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE payload=VALUES(payload),updated_at=NOW()',[$key,$body]);
  return ['body'=>$body,'updated_at'=>date('Y-m-d H:i:s'),'stale'=>false];
 }catch(Throwable $e){if($old)return ['body'=>$old['payload'],'updated_at'=>$old['updated_at'],'stale'=>true];throw $e;}
}
function rss_handle(string $action): void {
 if($action==='rss_sources')json_out(query('SELECT id,title,url,active FROM rss_feeds ORDER BY id')->fetchAll());
 if(!in_array($action,['rss_source_save','rss_source_toggle'],true))return;
 admin();rate_limit('rss_edit',15,300);$d=input();
 if($action==='rss_source_toggle'){
  $id=(int)($d['id']??0);if(!query('SELECT id FROM rss_feeds WHERE id=?',[$id])->fetch())fail('Источник не найден.',404);
  $active=!empty($d['active'])?1:0;
  if($active&&(int)query('SELECT COUNT(*) FROM rss_feeds WHERE active=1 AND id<>?',[$id])->fetchColumn()>=3)fail('Можно включить до трёх источников одновременно.');
  query('UPDATE rss_feeds SET active=? WHERE id=?',[$active,$id]);json_out(['ok'=>true]);
 }
 $title=field($d,'title',120);$url=field($d,'url',500);
 if(query('SELECT id FROM rss_feeds WHERE url_hash=?',[hash('sha256',$url)])->fetch())fail('Такая лента уже добавлена.',409);
 if((int)query('SELECT COUNT(*) FROM rss_feeds')->fetchColumn()>=20)fail('Достигнут предел в 20 сохранённых источников.');
 try{rss_target($url);$v=rss_fetch($url);if($v['stale'])fail('Источник сейчас недоступен. Повторите добавление позже.');rss_entries(safe_xml($v['body']));}catch(Throwable $e){fail('Не удалось проверить RSS. Нужен доступный публичный HTTPS-адрес RSS 2.0 или Atom.');}
 $active=(int)query('SELECT COUNT(*) FROM rss_feeds WHERE active=1')->fetchColumn()<3?1:0;
 query('INSERT INTO rss_feeds(title,url,url_hash,active) VALUES (?,?,?,?)',[$title,$url,hash('sha256',$url),$active]);json_out(['ok'=>true,'id'=>(int)db()->lastInsertId(),'active'=>$active],201);
}
function merged_feed_items(): array {
 global $config;$items=[];
 foreach(query('SELECT * FROM news ORDER BY published_at DESC,id DESC LIMIT 100')->fetchAll() as $n)$items[]=['id'=>'local-'.$n['id'],'title'=>$n['title'],'text'=>$n['body'],'url'=>$config['base_url'].'/rss.html#news-'.$n['id'],'source'=>'GameVerse','date'=>$n['published_at']];
 $sources=[];
 foreach(query('SELECT * FROM rss_feeds WHERE active=1 ORDER BY id LIMIT 3')->fetchAll() as $feed){
  try{$v=rss_fetch($feed['url']);$x=safe_xml($v['body']);$atom=$x->getName()==='feed';
   foreach(array_slice(rss_entries($x),0,30) as $node){$n=$atom?$node->children('http://www.w3.org/2005/Atom'):$node;$url=$atom?'':trim((string)$n->link);
    if($atom)foreach($n->link as $link)if(!isset($link['rel'])||(string)$link['rel']==='alternate'){$url=(string)$link['href'];break;}
    if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'||parse_url($url,PHP_URL_USER)!==null)continue;
    $stamp=strtotime((string)($atom?($n->published?:$n->updated):$n->pubDate));$title=mb_substr(trim(strip_tags((string)$n->title)),0,300);if($title==='')continue;
    $items[]=['id'=>'rss-'.hash('sha256',$url),'title'=>$title,'text'=>'','url'=>$url,'source'=>$feed['title'],'date'=>$stamp?date('Y-m-d H:i:s',$stamp):$v['updated_at']];
   }$sources[]=['id'=>$feed['id'],'title'=>$feed['title'],'ok'=>true,'stale'=>$v['stale'],'fetched_at'=>$v['updated_at']];
  }catch(Throwable $e){$sources[]=['id'=>$feed['id'],'title'=>$feed['title'],'ok'=>false,'stale'=>false];}
 }
 $unique=[];foreach($items as $n)$unique[$n['url']]=$n;$items=array_values($unique);usort($items,fn($a,$b)=>strcmp($b['date'],$a['date']));
 return ['items'=>$items,'sources'=>$sources,'external'=>['ok'=>(bool)array_filter($sources,fn($s)=>$s['ok']),'stale'=>(bool)array_filter($sources,fn($s)=>$s['stale']),'message'=>'Внешние источники пока недоступны; показаны новости GameVerse.']];
}
