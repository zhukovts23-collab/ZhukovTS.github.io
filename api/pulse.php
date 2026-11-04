<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
// Only these public apps may be queried; the visitor cannot choose an upstream URL.
const PULSE_GAMES=[730=>'Counter-Strike 2',570=>'Dota 2',578080=>'PUBG: BATTLEGROUNDS',440=>'Team Fortress 2'];
function pulse_fetch(int $id): int {
 $ch=curl_init('https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid='.$id);
 $body='';
 curl_setopt_array($ch,[CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_USERAGENT=>'GameVerse/1.1 SteamPulse',CURLOPT_WRITEFUNCTION=>function($ch,$s)use(&$body){if(strlen($body)+strlen($s)>65536)return 0;$body.=$s;return strlen($s);}]);
 $ok=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
 if($ok===false||$status!==200)throw new RuntimeException('Steam unavailable');
 $data=json_decode($body,true,16,JSON_THROW_ON_ERROR)['response']??[];
 if(($data['result']??null)!==1||!isset($data['player_count'])||!is_int($data['player_count'])||$data['player_count']<0||$data['player_count']>100000000)throw new RuntimeException('Invalid Steam response');
 return $data['player_count'];
}
function pulse_game(int $id): array {
 $row=query('SELECT * FROM steam_pulse_sources WHERE app_id=?',[$id])->fetch();
 $fresh=$row&&$row['sampled_at']&&time()-strtotime($row['sampled_at'])<300;
 if(!$fresh && (!$row||!$row['attempted_at']||time()-strtotime($row['attempted_at'])>=300)) {
  $lock='gv_steam_pulse_'.$id;
  if((int)query('SELECT GET_LOCK(?,0)',[$lock])->fetchColumn()===1){
   try {
    // Recheck after locking to prevent parallel requests from polling Steam twice.
    $row=query('SELECT * FROM steam_pulse_sources WHERE app_id=?',[$id])->fetch();
    if(!$row||!$row['attempted_at']||time()-strtotime($row['attempted_at'])>=300){
     query('INSERT INTO steam_pulse_sources(app_id,attempted_at) VALUES (?,NOW()) ON DUPLICATE KEY UPDATE attempted_at=NOW()',[$id]);
     try {
      $n=pulse_fetch($id);$time=date('Y-m-d H:i:s');
      db()->beginTransaction();
      query('UPDATE steam_pulse_sources SET players=?,sampled_at=? WHERE app_id=?',[$n,$time,$id]);
      query('INSERT IGNORE INTO steam_pulse_samples(app_id,sampled_at,players) VALUES (?,?,?)',[$id,$time,$n]);
      query('DELETE FROM steam_pulse_samples WHERE app_id=? AND sampled_at<DATE_SUB(NOW(),INTERVAL 72 HOUR)',[$id]);
      db()->commit();
     }catch(Throwable $e){if(db()->inTransaction())db()->rollBack();error_log('SteamPulse '.$id.': '.$e->getMessage());}
    }
   }finally{query('SELECT RELEASE_LOCK(?)',[$lock]);}
  }
 }
 $row=query('SELECT * FROM steam_pulse_sources WHERE app_id=?',[$id])->fetch();
 $history=query('SELECT sampled_at,players FROM steam_pulse_samples WHERE app_id=? AND sampled_at>=DATE_SUB(NOW(),INTERVAL 72 HOUR) ORDER BY sampled_at DESC LIMIT 288',[$id])->fetchAll();
 $history=array_reverse($history);
 $history=array_map(fn($h)=>['at'=>date(DATE_ATOM,strtotime($h['sampled_at'])),'players'=>(int)$h['players']],$history);
 return ['id'=>$id,'name'=>PULSE_GAMES[$id],'players'=>$row&&$row['players']!==null?(int)$row['players']:null,'sampled_at'=>$row&&$row['sampled_at']?date(DATE_ATOM,strtotime($row['sampled_at'])):null,'stale'=>!$row||!$row['sampled_at']||time()-strtotime($row['sampled_at'])>=300,'history'=>$history,'source_url'=>'https://api.steampowered.com/ISteamUserStats/GetNumberOfCurrentPlayers/v1/?appid='.$id];
}
try {
 if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')fail('Используйте GET',405);
 $ids=[];foreach(['left'=>730,'right'=>570] as $key=>$default){$v=$_GET[$key]??(string)$default;if(!is_string($v)||!ctype_digit($v)||!isset(PULSE_GAMES[(int)$v]))fail('Выберите игру из списка',400);$ids[$key]=(int)$v;}
 if($ids['left']===$ids['right'])fail('Для дуэли выберите две разные игры',400);
 $games=[];foreach(array_unique(array_values($ids)) as $id)$games[$id]=pulse_game($id);
 json_out(['games'=>array_values($games),'selected'=>$ids,'catalog'=>PULSE_GAMES,'cache_seconds'=>300,'history_note'=>'Замеры при обращениях к сервису; не непрерывная статистика. До 288 замеров за последние 72 часа.','source'=>'Steam Web API','generated_at'=>date(DATE_ATOM)]);
}catch(Throwable $e){error_log('SteamPulse: '.$e->getMessage());fail('Пульс игр временно недоступен. Повторите позже.',503);}
