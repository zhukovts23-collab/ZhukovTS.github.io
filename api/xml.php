<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');
try{$xml=new DOMDocument();$xml->load(__DIR__.'/../games.xml',LIBXML_NONET);$xsl=new DOMDocument();$xsl->load(__DIR__.'/../games.xsl',LIBXML_NONET);$p=new XSLTProcessor();$p->setSecurityPrefs(XSL_SECPREF_READ_FILE|XSL_SECPREF_WRITE_FILE|XSL_SECPREF_CREATE_DIRECTORY|XSL_SECPREF_READ_NETWORK|XSL_SECPREF_WRITE_NETWORK);$p->importStylesheet($xsl);$sort=$_GET['sort']??'title';if(!in_array($sort,['title','year','rating'],true))$sort='title';$p->setParameter('','sort',$sort);$p->setParameter('','genre',mb_substr((string)($_GET['genre']??''),0,60));$p->setParameter('','q',mb_strtolower(mb_substr((string)($_GET['q']??''),0,100)));echo $p->transformToXML($xml);}catch(Throwable $e){http_response_code(503);echo '<p>Не удалось обработать каталог.</p>';error_log($e->__toString());}
