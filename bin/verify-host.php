<?php
declare(strict_types=1);
// Read-only deployment checks, run through the hosting CLI, never through HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$resultFile = $root.'/app/verification.result.json';
if (is_file($resultFile)) exit;
umask(0077);
$lock = fopen($root.'/app/verification.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
$report = ['status'=>'checking', 'time'=>date(DATE_ATOM), 'checks'=>[]];
function record_check(string $test, bool $pass, array $details=[]): void {
    global $report;
    $report['checks'][] = ['test'=>$test, 'pass'=>$pass] + $details;
}
function host_request(string $path, bool $head=false, bool $https=false): array {
    global $config;
    $base = rtrim($config['base_url'], '/');
    if ($https) $base = preg_replace('~^http:~', 'https:', $base);
    $body = '';
    $ch = curl_init($base.$path);
    curl_setopt_array($ch, [CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>24,
        CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,
        CURLOPT_NOBODY=>$head, CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body){
            if (strlen($body)+strlen($chunk)>2000000) return 0;
            $body.=$chunk; return strlen($chunk);
        }]);
    $ok = curl_exec($ch);
    $response = ['http'=>curl_getinfo($ch,CURLINFO_RESPONSE_CODE), 'transport_ok'=>$ok!==false, 'body'=>$body];
    curl_close($ch);
    return $response;
}
function host_json(string $path): array {
    $r=host_request($path);
    $r['json']=json_decode($r['body'],true);
    return $r;
}
try {
    require $root.'/app/bootstrap.php';
    $report['base_url']=$config['base_url'];
    $report['time']=date(DATE_ATOM);
    $pages=array_merge(glob($root.'/*.html'),glob($root.'/services/*.html'));
    $failedPages=[];
    foreach($pages as $file){
        $path='/'.substr($file,strlen($root)+1);
        $r=host_request($path);
        if($r['http']!==200 || !str_contains(strtolower($r['body']),'gameverse')) $failedPages[]=$path;
    }
    record_check('html_pages', !$failedPages, ['total'=>count($pages),'failed'=>$failedPages]);
    $home=host_request('/');
    $index=host_request('/index.html');
    record_check('home_is_gameverse', $home['http']===200 && $home['body']===$index['body'] && str_contains(strtolower($home['body']),'gameverse'));
    foreach(['css/pulse.css','js/pulse.js','search-index.json','games.xml','games.xsl'] as $asset){
        $r=host_request('/'.$asset);
        record_check('asset:'.$asset,$r['http']===200&&strlen($r['body'])>20,['http'=>$r['http']]);
    }
    $games=host_json('/api/index.php?action=games');
    record_check('catalog_20', $games['http']===200&&is_array($games['json'])&&count($games['json'])===20);
    $filtered=host_json('/api/index.php?action=games&q=Minecraft');
    record_check('catalog_filter', $filtered['http']===200&&count($filtered['json']??[])===1&&($filtered['json'][0]['title']??'')==='Minecraft');
    $analytics=host_json('/api/index.php?action=analytics');
    record_check('database_analytics', $analytics['http']===200&&(int)($analytics['json']['summary']['total']??0)===20&&count($analytics['json']['top']??[])===5&&count($analytics['json']['genres']??[])>1);
    foreach(['news','messages&kind=guest','messages&kind=forum','ratings','poll','stats'] as $action){
        $r=host_json('/api/index.php?action='.$action);
        record_check('api:'.$action,$r['http']===200&&is_array($r['json'])&&!isset($r['json']['error']),['http'=>$r['http']]);
    }
    $xml=host_request('/api/xml.php');
    $dom=new DOMDocument(); @$dom->loadHTML($xml['body']);
    record_check('xslt_20_rows',$xml['http']===200&&(new DOMXPath($dom))->query('//tbody/tr')->length===20);
    $xml=host_request('/api/xml.php?q=Minecraft');
    $dom=new DOMDocument(); @$dom->loadHTML($xml['body']);
    record_check('xslt_filter',$xml['http']===200&&(new DOMXPath($dom))->query('//tbody/tr')->length===1&&str_contains($xml['body'],'Minecraft'));
    foreach(['rss'=>'rss','xml'=>'news','wrapped'=>'exchange'] as $format=>$expected){
        $r=host_request('/api/export.php?format='.$format);
        $dom=new DOMDocument(); $parsed=@$dom->loadXML($r['body'],LIBXML_NONET);
        record_check('export:'.$format,$r['http']===200&&$parsed&&$dom->documentElement->tagName===$expected,['http'=>$r['http']]);
    }
    $r=host_request('/api/export.php?format=csv');
    $lines=preg_split('/\r?\n/',trim($r['body']));
    record_check('export:csv',$r['http']===200&&count(str_getcsv($lines[0]??'',';','"',''))===6&&count($lines)>=4);
    $r=host_json('/api/index.php?action=weather');
    record_check('weather_rss',$r['http']===200&&count($r['json']['items']??[])>0,['http'=>$r['http'],'stale'=>$r['json']['stale']??null]);
    $r=host_json('/api/index.php?action=budget&amount=10&currency=USD&fee=5');
    record_check('cbr_budget',$r['http']===200&&($r['json']['rub']??0)>0&&abs($r['json']['rub']-round(10*$r['json']['rate']*1.05,2))<0.001,['http'=>$r['http'],'date'=>$r['json']['date']??null,'stale'=>$r['json']['stale']??null]);
    $r=host_json('/api/index.php?action=feed');
    record_check('rss_merge',$r['http']===200&&count($r['json']['items']??[])>=3&&($r['json']['external']['ok']??false),['http'=>$r['http'],'external_ok'=>$r['json']['external']['ok']??false,'items'=>count($r['json']['items']??[])]);
    foreach([[730,570],[578080,440]] as [$left,$right]){
        $path='/api/pulse.php?left='.$left.'&right='.$right;
        $r=host_json($path); $gameResults=[];
        foreach($r['json']['games']??[] as $g) $gameResults[]=['name'=>$g['name'],'players'=>$g['players'],'stale'=>$g['stale'],'history_points'=>count($g['history'])];
        $fresh=count($gameResults)===2;
        foreach($gameResults as $g) $fresh=$fresh&&is_int($g['players'])&&$g['players']>=0&&!$g['stale'];
        record_check('steam:'.$left.':'.$right,$r['http']===200&&$fresh,['http'=>$r['http'],'games'=>$gameResults]);
        $before=(int)query('SELECT COUNT(*) FROM steam_pulse_samples WHERE app_id IN (?,?)',[$left,$right])->fetchColumn();
        $cached=host_json($path);
        $after=(int)query('SELECT COUNT(*) FROM steam_pulse_samples WHERE app_id IN (?,?)',[$left,$right])->fetchColumn();
        record_check('steam_cache:'.$left.':'.$right,$cached['http']===200&&$before===$after);
    }
    foreach(['/api/pulse.php?left=730&right=730'=>400,'/api/pulse.php?left=999'=>400,'/api/index.php?action=admin_data'=>401,'/api/index.php?action=news_save'=>405] as $path=>$expected){
        $r=host_request($path);
        record_check('guard:'.$path,$r['http']===$expected,['http'=>$r['http']]);
    }
    foreach(['/app/config.local.php','/app/setup.local.php','/database/schema.sql','/bin/finish-host.php','/bin/verify-host.php'] as $path){
        $r=host_request($path,true);
        record_check('private:'.$path,in_array($r['http'],[403,404],true),['http'=>$r['http']]);
    }
    $r=host_request('/sitemap.xml');$dom=new DOMDocument();$parsed=@$dom->loadXML($r['body'],LIBXML_NONET);
    $locs=$parsed?$dom->getElementsByTagName('loc'):[];
    record_check('sitemap_25',$r['http']===200&&count($locs)===25,['urls'=>count($locs)]);
    $report['https_probe']=array_intersect_key(host_request('/',true,true),array_flip(['http','transport_ok']));
    $report['status']='complete';
    $report['passed']=count(array_filter($report['checks'],fn($r)=>$r['pass']));
    $report['total']=count($report['checks']);
}catch(Throwable $e){ $report['status']='failed';$report['error_type']=get_class($e); }
$temporary=tempnam(dirname($resultFile),'.verify-');
file_put_contents($temporary,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
chmod($temporary,0600);rename($temporary,$resultFile);
flock($lock,LOCK_UN);fclose($lock);
