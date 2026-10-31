<?php
// PHP development server only. Apache enforces .htaccess in production.
$p=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH));
if(preg_match('~(?:^|/)(?:\.|app(?:/|$)|bin(?:/|$)|database(?:/|$)|tests(?:/|$)|docs(?:/|$)|Dockerfile|compose\.yaml|package.*\.json)~',$p)){http_response_code(404);exit;}
return false;
