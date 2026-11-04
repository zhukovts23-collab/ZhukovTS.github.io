<?php
// CLI-only harness: exercises the actual API controllers without a web server.
if(PHP_SAPI!=='cli')exit;
class TestInput {public $context;private int $pos=0;function stream_open($p,$m,$o,&$opened){return $p==='php://input';}function stream_read($n){$s=substr(getenv('TEST_BODY')?:'', $this->pos,$n);$this->pos+=strlen($s);return $s;}function stream_eof(){return $this->pos>=strlen(getenv('TEST_BODY')?:'');}function stream_stat(){return [];}}
$_GET=json_decode(getenv('TEST_QUERY')?:'{}',true);$_SERVER['REQUEST_METHOD']=getenv('TEST_METHOD')?:'GET';$_SERVER['HTTP_X_CSRF_TOKEN']=getenv('TEST_CSRF')?:'';$_SERVER['REMOTE_ADDR']=getenv('TEST_IP')?:'127.0.0.1';
if(getenv('TEST_SESSION'))session_id(getenv('TEST_SESSION'));
register_shutdown_function(function(){file_put_contents(getenv('TEST_META'),json_encode(['session'=>session_id(),'status'=>http_response_code()?:200]));});
if($_SERVER['REQUEST_METHOD']==='POST'){stream_wrapper_unregister('php');stream_wrapper_register('php',TestInput::class);}
require __DIR__.'/../api/'.(getenv('TEST_CONTROLLER')?:'index.php');
