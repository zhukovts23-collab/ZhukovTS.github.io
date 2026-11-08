<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');
try {
 $xml=new DOMDocument();$xml->load(__DIR__.'/../glossary.xml',LIBXML_NONET);
 $xsl=new DOMDocument();$xsl->load(__DIR__.'/../glossary.xsl',LIBXML_NONET);
 $p=new XSLTProcessor();$p->setSecurityPrefs(XSL_SECPREF_READ_FILE|XSL_SECPREF_WRITE_FILE|XSL_SECPREF_CREATE_DIRECTORY|XSL_SECPREF_READ_NETWORK|XSL_SECPREF_WRITE_NETWORK);$p->importStylesheet($xsl);
 $sort=in_array($_GET['sort']??'', ['title','category','english'],true)?$_GET['sort']:'title';
 $p->setParameter('','sort',$sort);$p->setParameter('','category',mb_substr((string)($_GET['category']??''),0,60));$p->setParameter('','q',mb_strtolower(mb_substr((string)($_GET['q']??''),0,100)));$p->setParameter('','asset-root','../');echo $p->transformToXML($xml);
}catch(Throwable $e){http_response_code(503);echo '<p>Не удалось обработать справочник. Повторите запрос позже.</p>';error_log($e->__toString());}
