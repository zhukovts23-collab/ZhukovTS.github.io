<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('X-Content-Type-Options: nosniff');
try {
 session_init();$action=$_GET['action']??'';$post=$_SERVER['REQUEST_METHOD']==='POST';
 if($post)csrf();
 if(!in_array($action,['login','logout'],true))session_write_close();
 $writes=['login','logout','news_save','message','moderate','block_word','block_host','rate','vote','visit'];
 if(in_array($action,$writes,true)&&!$post)fail('Используйте POST',405);
 switch($action){
 case 'session':json_out(['csrf'=>$_SESSION['csrf'],'admin'=>!empty($_SESSION['admin'])&&time()-($_SESSION['login_at']??0)<=7200]);
 case 'login':rate_limit('login',8,900);$d=input();$row=query('SELECT * FROM admins WHERE username=?',[field($d,'username',80)])->fetch();if(!$row||!password_verify(field($d,'password',200),$row['password_hash']))fail('Неверный логин или пароль',401);session_regenerate_id(true);$_SESSION['admin']=$row['id'];$_SESSION['login_at']=time();json_out(['ok'=>true]);
 case 'logout':$_SESSION=[];session_destroy();json_out(['ok'=>true]);
 case 'games':
  $sort=['title'=>'title ASC','year'=>'year DESC, title ASC','rating'=>'rating DESC,title ASC'][$_GET['sort']??'title']??'title ASC';
  $q=mb_substr((string)($_GET['q']??''),0,100);$g=mb_substr((string)($_GET['genre']??''),0,60);
  json_out(query("SELECT * FROM games WHERE (?='' OR title LIKE ?) AND (?='' OR genre=?) ORDER BY $sort",[$q,'%'.$q.'%',$g,$g])->fetchAll());
 case 'analytics':json_out(['summary'=>query('SELECT COUNT(*) total,ROUND(AVG(rating),2) average,MIN(year) oldest,MAX(year) newest FROM games')->fetch(),'genres'=>query('SELECT genre,COUNT(*) total,ROUND(AVG(rating),2) average FROM games GROUP BY genre ORDER BY total DESC,genre')->fetchAll(),'years'=>query('SELECT year,COUNT(*) total FROM games GROUP BY year ORDER BY year')->fetchAll(),'top'=>query('SELECT title,rating FROM games ORDER BY rating DESC,title LIMIT 5')->fetchAll()]);
 case 'news':json_out(query('SELECT id,title,body AS text,published_at AS date FROM news ORDER BY published_at DESC,id DESC LIMIT 100')->fetchAll());
 case 'news_save':admin();$d=input();query('INSERT INTO news(title,body) VALUES (?,?)',[field($d,'title',200),field($d,'text',10000)]);json_out(['ok'=>true]);
 case 'messages':
  $kind=($_GET['kind']??'guest')==='forum'?'forum':'guest';
  $rows=query("SELECT id,kind,parent_id,author,title,body,created_at FROM messages WHERE visible=1 AND (kind=? OR (kind='reply' AND parent_id IN (SELECT id FROM messages WHERE kind=? AND visible=1))) ORDER BY created_at DESC,id DESC LIMIT 200",[$kind,$kind])->fetchAll();json_out($rows);
 case 'message':rate_limit('message',6,300);$d=input();$kind=$d['kind']??'guest';if(!in_array($kind,['guest','forum','reply'],true))fail('Неизвестный тип');$author=field($d,'author',60);$body=field($d,'text',2000);$title=$kind==='forum'?field($d,'title',160):'';$parent=$kind==='reply'?(int)($d['parent_id']??0):null;if($kind==='reply'&&!query("SELECT id FROM messages WHERE id=? AND kind='forum' AND visible=1",[$parent])->fetch())fail('Тема не найдена',404);moderate($author.' '.$title.' '.$body);query('INSERT INTO messages(kind,parent_id,author,title,body,host_hash) VALUES (?,?,?,?,?,?)',[$kind,$parent,$author,$title,$body,ip_hash()]);json_out(['ok'=>true],201);
 case 'admin_data':admin();json_out(['messages'=>query('SELECT * FROM messages ORDER BY id DESC LIMIT 200')->fetchAll(),'words'=>query('SELECT * FROM blocked_words')->fetchAll(),'hosts'=>query('SELECT host_hash FROM blocked_hosts')->fetchAll()]);
 case 'moderate':admin();$d=input();query('UPDATE messages SET visible=? WHERE id=?',[!empty($d['visible'])?1:0,(int)($d['id']??0)]);json_out(['ok'=>true]);
 case 'block_word':admin();$d=input();$word=field($d,'phrase',100);if(!empty($d['remove']))query('DELETE FROM blocked_words WHERE phrase=?',[$word]);else query('INSERT IGNORE INTO blocked_words(phrase) VALUES (?)',[$word]);json_out(['ok'=>true]);
 case 'block_host':admin();$d=input();$hash=field($d,'host_hash',64);if(!preg_match('/^[a-f0-9]{64}$/',$hash))fail('Некорректный идентификатор');if(!empty($d['remove']))query('DELETE FROM blocked_hosts WHERE host_hash=?',[$hash]);else query('INSERT IGNORE INTO blocked_hosts VALUES (?)',[$hash]);json_out(['ok'=>true]);
 case 'ratings':json_out(query('SELECT g.id,g.title,COUNT(r.score) votes,ROUND(AVG(r.score),2) average FROM games g LEFT JOIN ratings r ON r.game_id=g.id GROUP BY g.id,g.title ORDER BY g.title')->fetchAll());
 case 'rate':rate_limit('rate',30,300);$d=input();$s=filter_var($d['score']??null,FILTER_VALIDATE_INT);if($s===false||$s<1||$s>5)fail('Оценка от 1 до 5');$id=field($d,'game_id',80);if(!query('SELECT id FROM games WHERE id=?',[$id])->fetch())fail('Игра не найдена',404);query('INSERT INTO ratings VALUES (?,?,?) ON DUPLICATE KEY UPDATE score=VALUES(score)',[$id,$_SESSION['visitor'],$s]);json_out(['ok'=>true]);
 case 'poll':json_out(query('SELECT choice,COUNT(*) total FROM votes GROUP BY choice ORDER BY total DESC')->fetchAll());
 case 'vote':rate_limit('vote',10,300);$choice=field(input(),'choice',30);if(!in_array($choice,['RPG','Стратегия','Шутер','Симулятор'],true))fail('Выберите жанр');query('INSERT INTO votes VALUES (?,?) ON DUPLICATE KEY UPDATE choice=VALUES(choice)',[$_SESSION['visitor'],$choice]);json_out(['ok'=>true]);
 case 'visit':rate_limit('visit',120,300);$p=field(input(),'page',180);$pages=json_decode(file_get_contents(__DIR__.'/../search-index.json'),true);if(!in_array($p,array_column($pages,'url'),true))fail('Страница не найдена',404);query('INSERT INTO visits(page,visitor) VALUES (?,?)',[$p,$_SESSION['visitor']]);json_out(['ok'=>true]);
 case 'stats':json_out(['summary'=>query('SELECT COUNT(*) views,COUNT(DISTINCT visitor) visitors FROM visits')->fetch(),'pages'=>query('SELECT page,COUNT(*) views,MAX(visited_at) last FROM visits GROUP BY page ORDER BY views DESC')->fetchAll(),'daily'=>query('SELECT DATE(visited_at) day,COUNT(*) views FROM visits WHERE visited_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(visited_at) ORDER BY day')->fetchAll()]);
 case 'rates':json_out(rates());
 case 'budget':$amount=filter_var($_GET['amount']??null,FILTER_VALIDATE_FLOAT);$fee=filter_var($_GET['fee']??0,FILTER_VALIDATE_FLOAT);$code=strtoupper((string)($_GET['currency']??'USD'));if($amount===false||$amount<0||$amount>10000000||$fee===false||$fee<0||$fee>100)fail('Проверьте сумму и комиссию');$r=rates();if(!isset($r['rates'][$code]))fail('Неизвестная валюта');json_out(['amount'=>$amount,'currency'=>$code,'rate'=>$r['rates'][$code],'fee_percent'=>$fee,'rub'=>round($amount*$r['rates'][$code]*(1+$fee/100),2),'date'=>$r['date'],'source'=>$r['source'],'stale'=>$r['stale']]);
 case 'weather':
  $v=external('weather','https://meteoinfo.ru/rss/moscow',1800);$x=safe_xml($v['body']);$items=[];foreach($x->channel->item as $n){$items[]=['title'=>strip_tags((string)$n->title),'text'=>trim(html_entity_decode(strip_tags((string)$n->description),ENT_QUOTES,'UTF-8')),'date'=>(string)$n->pubDate];if(count($items)>=3)break;}if(!$items)throw new RuntimeException('Нет прогноза');json_out(['items'=>$items,'source'=>'Гидрометцентр России — Москва и область','url'=>'https://meteoinfo.ru/','fetched_at'=>$v['updated_at'],'stale'=>$v['stale']]);
 case 'feed':json_out(feed_items());
 default:fail('Маршрут не найден',404);
 }
}catch(Throwable $e){error_log($e->__toString());json_out(['error'=>'Сервис временно недоступен. Повторите запрос позже.'],503);}
