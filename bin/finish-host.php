<?php
declare(strict_types=1);
// Hosting-console installer only; never expose installation over HTTP.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$settingsFile = $root.'/app/setup.local.php';
$resultFile = $root.'/app/setup.result.json';
$completeFile = $root.'/app/setup.completed.json';
if (is_file($completeFile)) exit;
umask(0077);
$lock = fopen($root.'/app/setup.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) exit;
function setup_write(string $path, string $contents): void {
    $temporary = tempnam(dirname($path), '.setup-');
    if ($temporary === false) throw new RuntimeException('temporary_file');
    if (file_put_contents($temporary, $contents, LOCK_EX) === false || !chmod($temporary, 0600) || !rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('write_file');
    }
}
function setup_status(string $state, array $details = []): array {
    global $resultFile;
    $result = ['status'=>$state, 'time'=>date(DATE_ATOM)] + $details;
    setup_write($resultFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    return $result;
}
$stage = 'settings';
try {
    if (!is_file($settingsFile)) { setup_status('waiting_for_settings'); exit; }
    $settings = require $settingsFile;
    if (!is_array($settings)) throw new RuntimeException('settings_format');
    $databasePassword = $settings['database_password'] ?? '';
    $adminPassword = $settings['admin_password'] ?? '';
    if (!is_string($databasePassword) || !is_string($adminPassword) || $databasePassword === '' || $databasePassword === 'PASTE_DATABASE_PASSWORD_HERE' || $adminPassword === 'CREATE_ADMIN_PASSWORD_HERE' || strlen($adminPassword) < 14) {
        setup_status('waiting_for_passwords', ['admin_minimum_characters'=>14]); exit;
    }
    if (PHP_VERSION_ID < 80300) throw new RuntimeException('php_version');
    foreach (['pdo_mysql','xsl','mbstring','curl','dom','simplexml'] as $extension) {
        if (!extension_loaded($extension)) throw new RuntimeException('missing_extension');
    }
    $database = $settings['database_name'] ?? '';
    $base = rtrim($settings['base_url'] ?? '', '/');
    if (!is_string($database) || !preg_match('/^[a-zA-Z0-9_]{1,32}$/D', $database) || !filter_var($base, FILTER_VALIDATE_URL) || !in_array(parse_url($base, PHP_URL_SCHEME), ['http','https'], true)) throw new RuntimeException('settings_values');
    $stage = 'configuration';
    $configFile = $root.'/app/config.local.php';
    $previous = is_file($configFile) ? require $configFile : [];
    $privateConfig = ['dsn'=>'mysql:host=localhost;dbname='.$database.';charset=utf8mb4', 'user'=>$database, 'password'=>$databasePassword, 'base_url'=>$base, 'secret'=>is_array($previous) && strlen($previous['secret'] ?? '') >= 32 ? $previous['secret'] : bin2hex(random_bytes(32))];
    setup_write($configFile, "<?php\nreturn ".var_export($privateConfig, true).";\n");
    $stage = 'database_installation';
    putenv('ADMIN_PASSWORD='.$adminPassword);
    ob_start();
    require __DIR__.'/install.php';
    ob_end_clean();
    $stage = 'seo';
    ob_start();
    require __DIR__.'/configure-url.php';
    ob_end_clean();
    foreach (['robots.txt','sitemap.xml','sitemap.txt'] as $file) chmod($root.'/'.$file, 0644);
    $stage = 'verification';
    $counts = [];
    foreach (['games','news','admins'] as $table) $counts[$table] = (int)query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    if ($counts['games'] !== 20 || $counts['admins'] < 1) throw new RuntimeException('installation_counts');
    $http = [];
    foreach (['/','/robots.txt','/sitemap.xml','/api/index.php?action=games','/api/xml.php','/api/pulse.php','/api/index.php?action=budget&amount=1&currency=USD&fee=0','/app/config.local.php'] as $path) {
        $ch = curl_init($base.$path);
        $body = '';
        curl_setopt_array($ch, [CURLOPT_CONNECTTIMEOUT=>4, CURLOPT_TIMEOUT=>22, CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_WRITEFUNCTION=>function($ch, $chunk) use (&$body) { if (strlen($body)+strlen($chunk)>2000000) return 0; $body.=$chunk; return strlen($chunk); }]);
        if ($path === '/app/config.local.php') curl_setopt($ch, CURLOPT_NOBODY, true);
        $success = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $http[$path] = ['http'=>$code, 'transport_ok'=>$success !== false];
        if ($path === '/api/pulse.php' && $code === 200) {
            $pulse = json_decode($body, true);
            $http[$path]['games'] = array_map(fn($g)=>['name'=>$g['name'] ?? '', 'players'=>$g['players'] ?? null, 'sampled_at'=>$g['sampled_at'] ?? null, 'stale'=>$g['stale'] ?? true], $pulse['games'] ?? []);
        }
    }
    $result = setup_status('installed', ['php'=>PHP_VERSION, 'base_url'=>$base, 'counts'=>$counts, 'http_checks'=>$http]);
    setup_write($completeFile, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    // The administrator password is no longer needed after hashing in MySQL.
    setup_write($settingsFile, "<?php\nreturn ['completed'=>true];\n");
} catch (Throwable $error) {
    while (ob_get_level()) ob_end_clean();
    setup_status('failed', ['stage'=>$stage, 'error_type'=>get_class($error)]);
} finally {
    putenv('ADMIN_PASSWORD');
    unset($databasePassword, $adminPassword, $settings, $privateConfig, $previous);
    flock($lock, LOCK_UN);
    fclose($lock);
}
