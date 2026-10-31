<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
try{
 $format=$_GET['format']??'rss';$rows=($_GET['source']??'local')==='merged'?feed_items()['items']:array_map(fn($n)=>['id'=>'local-'.$n['id'],'title'=>$n['title'],'text'=>$n['body'],'url'=>$config['base_url'].'/services/news.html#news-'.$n['id'],'source'=>'GameVerse','date'=>$n['published_at']],query('SELECT * FROM news ORDER BY published_at DESC LIMIT 100')->fetchAll());
 header('X-Content-Type-Options: nosniff');
 if($format==='csv') {header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="gameverse-news.csv"');$f=fopen('php://output','w');fputcsv($f,['id','title','text','url','source','date'],';','"','');foreach($rows as $r)fputcsv($f,array_map(fn($v)=>preg_match('/^[=+@\-\t\r]/',(string)$v)?"'".$v:$v,array_values($r)),';','"','');exit;}
 $doc=new DOMDocument('1.0','UTF-8');$doc->formatOutput=true;
 $add=function($parent,$tag,$text)use($doc){$e=$doc->createElement($tag);$e->appendChild($doc->createTextNode((string)$text));$parent->appendChild($e);return $e;};
 if($format==='rss'){$root=$doc->createElement('rss');$root->setAttribute('version','2.0');$doc->appendChild($root);$channel=$doc->createElement('channel');$root->appendChild($channel);$add($channel,'title','GameVerse — новости');$add($channel,'link',$config['base_url']);$add($channel,'description','Новости GameVerse и выбранных источников');$add($channel,'language','ru');foreach($rows as $n){$it=$doc->createElement('item');$channel->appendChild($it);$add($it,'title',$n['title']);$add($it,'description',$n['text']);$add($it,'link',$n['url']);$add($it,'guid',$n['id'])->setAttribute('isPermaLink','false');$add($it,'pubDate',date(DATE_RSS,strtotime($n['date'])));$add($it,'category',$n['source']);}header('Content-Type: application/rss+xml; charset=utf-8');}
 elseif($format==='xml'){$root=$doc->createElement('news');$doc->appendChild($root);foreach($rows as $n){$it=$doc->createElement('item');$root->appendChild($it);foreach($n as $k=>$v)$add($it,$k,$v);}header('Content-Type: application/xml; charset=utf-8');}
 elseif($format==='wrapped'){$root=$doc->createElement('exchange');$root->setAttribute('encoding','UTF-8');$root->setAttribute('delimiter',';');$doc->appendChild($root);$f=fopen('php://temp','r+');fputcsv($f,['id','title','text','url','source','date'],';','"','');foreach($rows as $n)fputcsv($f,array_values($n),';','"','');rewind($f);$add($root,'data',stream_get_contents($f));header('Content-Type: application/xml; charset=utf-8');}
 else fail('Неизвестный формат');echo $doc->saveXML();
}catch(Throwable $e){error_log($e->__toString());fail('Не удалось сформировать экспорт',503);}
