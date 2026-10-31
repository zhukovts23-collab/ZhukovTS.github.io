<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../app/bootstrap.php';
$base=$config['base_url'];if(!filter_var($base,FILTER_VALIDATE_URL))exit("Invalid APP_URL\n");
$root=dirname(__DIR__);$files=array_merge(glob($root.'/*.html'),glob($root.'/services/*.html'));
$xml=new DOMDocument('1.0','UTF-8');$set=$xml->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9','urlset');$xml->appendChild($set);
foreach($files as $f){$path=substr($f,strlen($root)+1);if($path==='admin.html')continue;$url=$base.'/'.$path;$html=file_get_contents($f);$html=preg_replace('~<link rel="canonical"[^>]*>~','',$html);$html=str_replace('</head>','<link rel="canonical" href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'"></head>',$html);file_put_contents($f,$html);$n=$xml->createElement('url');$loc=$xml->createElement('loc');$loc->appendChild($xml->createTextNode($url));$n->appendChild($loc);$set->appendChild($n);}
$xml->formatOutput=true;$xml->save($root.'/sitemap.xml');file_put_contents($root.'/robots.txt',"User-agent: *\nAllow: /\nDisallow: /admin.html\nDisallow: /app/\nDisallow: /bin/\nDisallow: /tests/\nDisallow: /docs/\nDisallow: /api/index.php\nSitemap: $base/sitemap.xml\n");echo "SEO URLs configured.\n";
