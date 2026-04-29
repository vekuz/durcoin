<?php
session_start();

// ============ ⚙️ НАСТРОЙКИ ============
$SITE_TITLE              = 'DjDurcoin.ru - My Media Hosting';
$SITE_DESCRIPTION        = 'Music, Videos, and Files · Pay via DURCOIN';
$DEFAULT_THEME           = 'dark';
$DEFAULT_LANG            = 'en';
$WAVES_SYSTEM_ADDRESS    = '3P95dfoJHC6dP6GaeCYGEMRYg7o4UAXE1w6';
$WAVES_MIN_AMOUNT        = 10.00;
$WAVES_SUBSCRIPTION_DAYS = 1;

$INDEX_CACHE_FILE        = '_index.cache.json';
$INDEX_CACHE_TTL         = 3600;
$PAGE_SIZE               = 60;
$MAX_SHARE_LIST_SIZE     = 300;

// ============ 🔒 ЗАЩИЩЁННОЕ ЯДРО ============
function _core_asset_id() { return pack('H*', '4631486F414C7943446E764D624D785A63765745566474505854593942' . '4C396E62486E7A53796A524C547438'); }
function _core_asset_decimals() { return 2; }
function _core_node() { return pack('H*', '68747470733A2F2F6E6F6465732E77617665736E6F6465732E636F6D'); }
$WAVES_ASSET_ID = _core_asset_id(); $WAVES_ASSET_DECIMALS = _core_asset_decimals(); $WAVES_NODE = _core_node();

function base58_decode($input) {
    if ($input === '') return '';
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $bytes = [0];
    for ($i = 0; $i < strlen($input); $i++) {
        $c = strpos($alphabet, $input[$i]); if ($c === false) return false;
        $carry = $c;
        for ($j = 0; $j < count($bytes); $j++) { $carry += $bytes[$j] * 58; $bytes[$j] = $carry & 0xff; $carry >>= 8; }
        while ($carry > 0) { $bytes[] = $carry & 0xff; $carry >>= 8; }
    }
    for ($i = 0; $i < strlen($input) && $input[$i] === '1'; $i++) $bytes[] = 0;
    return implode('', array_map('chr', array_reverse($bytes)));
}
function httpGet($url, $timeout = 12) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>$timeout, CURLOPT_CONNECTTIMEOUT=>8, CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_FOLLOWLOCATION=>true]);
        $resp = curl_exec($ch); curl_close($ch); return $resp === false ? null : $resp;
    }
    $ctx = stream_context_create(['http'=>['timeout'=>$timeout,'ignore_errors'=>true]]);
    $resp = @file_get_contents($url, false, $ctx); return $resp === false ? null : $resp;
}
function curve25519ToEd25519(string $curvePk, int $signBit): ?string {
    $p = gmp_init('57896044618658097711785492504343953926634992332820282019728792003956564819949', 10);
    $bytes = $curvePk; $bytes[31] = chr(ord($bytes[31]) & 0x7F);
    $u = gmp_init(bin2hex(strrev($bytes)), 16); $u = gmp_mod($u, $p);
    $uPlus1 = gmp_mod(gmp_add($u, 1), $p);
    if (gmp_cmp($uPlus1, 0) === 0) return null;
    $inv = gmp_invert($uPlus1, $p); if ($inv === false) return null;
    $y = gmp_mod(gmp_mul(gmp_sub($u, 1), $inv), $p);
    if (gmp_cmp($y, 0) < 0) $y = gmp_mod(gmp_add($y, $p), $p);
    $hex = gmp_strval($y, 16); if (strlen($hex) % 2) $hex = '0' . $hex;
    $beBytes = str_pad(hex2bin($hex), 32, "\x00", STR_PAD_LEFT); $leBytes = strrev($beBytes);
    $last = ord($leBytes[31]) & 0x7F; if ($signBit) $last |= 0x80; $leBytes[31] = chr($last); return $leBytes;
}
function wavesVerifySignature(string $message, string $sigBytes, string $pubBytes): array {
    if (strlen($sigBytes) !== 64)  return ['valid'=>false,'error'=>'bad sig len'];
    if (strlen($pubBytes) !== 32)  return ['valid'=>false,'error'=>'bad pub len'];
    if (!function_exists('sodium_crypto_sign_verify_detached')) return ['valid'=>false,'error'=>'no sodium'];
    if (!function_exists('gmp_init')) return ['valid'=>false,'error'=>'no gmp'];
    $signByte = ord($sigBytes[63]); $signBit = ($signByte >> 7) & 1;
    $stdSig = $sigBytes; $stdSig[63] = chr($signByte & 0x7F);
    $edPub = curve25519ToEd25519($pubBytes, $signBit);
    if ($edPub === null) return ['valid'=>false,'error'=>'pubkey conv failed'];
    try { $ok = sodium_crypto_sign_verify_detached($stdSig, $message, $edPub); return ['valid'=>(bool)$ok, 'error'=>$ok?null:'sodium false']; }
    catch (Throwable $e) { return ['valid'=>false,'error'=>'sodium: '.$e->getMessage()]; }
}

function ext_of($n) { return strtolower(pathinfo($n, PATHINFO_EXTENSION)); }
function human_size($b) { $u=['B','KB','MB','GB','TB']; $i=0; while ($b>=1024 && $i<4){$b/=1024;$i++;} return round($b,2).' '.$u[$i]; }

$CATEGORIES = [
  'audio' => ['ext'=>['mp3','wav','ogg','flac','m4a','aac','opus','wma']],
  'video' => ['ext'=>['mp4','webm','mov','mkv','avi','m4v','3gp','flv']],
  'image' => ['ext'=>['jpg','jpeg','png','gif','webp','svg','bmp','avif','ico','tiff']],
  'doc'   => ['ext'=>['pdf','doc','docx','xls','xlsx','ppt','pptx','txt','rtf','odt','md','csv']],
  'arch'  => ['ext'=>['zip','rar','7z','tar','gz','bz2','xz']],
];
function catOfExt($ext) {
    global $CATEGORIES;
    foreach ($CATEGORIES as $k => $v) if (in_array($ext, $v['ext'])) return $k;
    return 'other';
}
function mimeType($ext) {
    static $map = ['mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','flac'=>'audio/flac','m4a'=>'audio/mp4','aac'=>'audio/aac','opus'=>'audio/opus','wma'=>'audio/x-ms-wma','mp4'=>'video/mp4','webm'=>'video/webm','mov'=>'video/quicktime','mkv'=>'video/x-matroska','avi'=>'video/x-msvideo','m4v'=>'video/mp4','3gp'=>'video/3gpp','flv'=>'video/x-flv','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','bmp'=>'image/bmp','avif'=>'image/avif','ico'=>'image/x-icon','tiff'=>'image/tiff','pdf'=>'application/pdf','txt'=>'text/plain; charset=utf-8'];
    return $map[$ext] ?? 'application/octet-stream';
}
function serveFile($path, $mime, $disposition = 'inline') {
    $size = filesize($path); $start = 0; $end = $size - 1;
    header('Content-Type: ' . $mime); header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, max-age=3600');
    if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d+)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        $start = (int)$m[1]; if ($m[2] !== '') $end = (int)$m[2];
        if ($end >= $size) $end = $size - 1;
        if ($start > $end) { http_response_code(416); exit; }
        http_response_code(206); header("Content-Range: bytes $start-$end/$size");
    }
    $length = $end - $start + 1; header('Content-Length: ' . $length);
    if ($disposition === 'attachment') header('Content-Disposition: attachment; filename="' . rawurlencode(basename($path)) . '"');
    @set_time_limit(0);
    $fp = fopen($path, 'rb'); if ($start > 0) fseek($fp, $start);
    $remaining = $length;
    while ($remaining > 0 && !feof($fp) && !connection_aborted()) {
        $read = min(8192, $remaining); echo fread($fp, $read); $remaining -= $read;
        @ob_flush(); @flush();
    }
    fclose($fp);
}
function isSubscribed() { return !empty($_SESSION['subscribed']) && ($_SESSION['expires'] ?? 0) > time() * 1000; }
function isStreamable($file) { $cat = catOfExt(ext_of($file)); return $cat === 'audio' || $cat === 'video'; }
function findActiveSubscription($address, $sysAddr, $minAmount, $days) {
    $assetId = _core_asset_id(); $decimals = _core_asset_decimals(); $node = _core_node();
    $cutoffMs = (time() - $days * 86400) * 1000;
    $raw = httpGet($node . '/transactions/address/' . urlencode($address) . '/limit/200');
    if ($raw === null) return null;
    $data = json_decode($raw, true);
    $txs = $data[0] ?? (is_array($data) ? $data : []);
    $needAsset = ($assetId === 'WAVES') ? null : $assetId;
    $minUnits  = (int) round($minAmount * pow(10, $decimals));
    $lastTs = 0;
    foreach ($txs as $tx) {
        if (($tx['type'] ?? 0) !== 4) continue;
        if (($tx['recipient'] ?? '') !== $sysAddr) continue;
        if (($tx['sender'] ?? '') !== $address) continue;
        if (($tx['assetId'] ?? null) !== $needAsset) continue;
        if (($tx['amount'] ?? 0) < $minUnits) continue;
        $ts = $tx['timestamp'] ?? 0;
        if ($ts >= $cutoffMs && $ts > $lastTs) $lastTs = $ts;
    }
    return $lastTs > 0 ? $lastTs + $days * 86400 * 1000 : 0;
}

$self = basename(__FILE__);
function getIndex($force = false) {
    global $INDEX_CACHE_FILE, $INDEX_CACHE_TTL;
    if (!$force && file_exists($INDEX_CACHE_FILE)) {
        if ((time() - filemtime($INDEX_CACHE_FILE)) < $INDEX_CACHE_TTL) {
            $raw = @file_get_contents($INDEX_CACHE_FILE);
            if ($raw) { $data = json_decode($raw, true);
                if ($data && isset($data['files']) && is_array($data['files'])) return $data; }
        }
    }
    return rebuildIndex();
}
function rebuildIndex() {
    global $INDEX_CACHE_FILE, $self;
    $files = [];
    $dh = @opendir('.');
    if (!$dh) return ['files'=>[], 'total'=>0, 'counts'=>[], 'generated'=>time()];
    while (($f = readdir($dh)) !== false) {
        if ($f === '.' || $f === '..' || $f === $self || $f === $INDEX_CACHE_FILE) continue;
        if ($f[0] === '.') continue;
        if (in_array($f, ['.htaccess', '.htpasswd'])) continue;
        if (!is_file($f)) continue;
        $size = @filesize($f) ?: 0;
        $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
        $files[] = ['n'=>$f, 's'=>$size, 'c'=>catOfExt($ext)];
    }
    closedir($dh);
    shuffle($files);
    $counts = ['all'=>count($files)];
    foreach ($files as $f) $counts[$f['c']] = ($counts[$f['c']] ?? 0) + 1;
    $data = ['v'=>1, 'generated'=>time(), 'total'=>count($files), 'counts'=>$counts, 'files'=>$files];
    @file_put_contents($INDEX_CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
    return $data;
}
function reshuffleIndex() {
    global $INDEX_CACHE_FILE;
    $idx = getIndex(); shuffle($idx['files']); $idx['generated'] = time();
    @file_put_contents($INDEX_CACHE_FILE, json_encode($idx, JSON_UNESCAPED_UNICODE));
    return $idx;
}
function filterIndex($index, $tab, $search) {
    $search = trim(strtolower($search));
    $useTab = $tab !== 'all'; $useSearch = $search !== '';
    if (!$useTab && !$useSearch) return $index['files'];
    $result = [];
    foreach ($index['files'] as $f) {
        if ($useTab && $f['c'] !== $tab) continue;
        if ($useSearch && stripos($f['n'], $search) === false) continue;
        $result[] = $f;
    }
    return $result;
}
function enrichItem(array &$item) {
    $item['sh'] = human_size($item['s']);
    $item['e']  = strtolower(pathinfo($item['n'], PATHINFO_EXTENSION));
}

function resolveFile($raw) {
    global $self, $INDEX_CACHE_FILE;
    $file = basename($raw);
    if ($file === $self || $file === $INDEX_CACHE_FILE || $file === '' || !file_exists($file) || is_dir($file)) return null;
    if ($file[0] === '.') return null;
    return $file;
}
if (isset($_GET['stream'])) {
    $file = resolveFile($_GET['stream']);
    if (!$file) { http_response_code(404); exit('Not found'); }
    if (!isStreamable($file)) { http_response_code(403); exit('Subscription required'); }
    serveFile($file, mimeType(ext_of($file)), 'inline'); exit;
}
if (isset($_GET['open'])) {
    if (!isSubscribed()) { http_response_code(403); exit('Subscription required'); }
    $file = resolveFile($_GET['open']);
    if (!$file) { http_response_code(404); exit('Not found'); }
    serveFile($file, mimeType(ext_of($file)), 'inline'); exit;
}
if (isset($_GET['download'])) {
    if (!isSubscribed()) { http_response_code(403); exit('Subscription required'); }
    $file = resolveFile($_GET['download']);
    if (!$file) { http_response_code(404); exit('Not found'); }
    serveFile($file, 'application/octet-stream', 'attachment'); exit;
}

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    if ($_GET['action'] === 'list') {
        $index = getIndex();
        $tab = $_GET['tab'] ?? 'all'; $search = $_GET['search'] ?? '';
        $page = max(1, (int)($_GET['page'] ?? 1));
        $filtered = filterIndex($index, $tab, $search);
        $total = count($filtered);
        $offset = ($page - 1) * $PAGE_SIZE;
        $items = array_slice($filtered, $offset, $PAGE_SIZE);
        foreach ($items as &$it) enrichItem($it); unset($it);
        echo json_encode(['total'=>$total,'page'=>$page,'pageSize'=>$PAGE_SIZE,'hasMore'=>($offset+count($items))<$total,'items'=>$items,'counts'=>$index['counts']]); exit;
    }
    if ($_GET['action'] === 'playlist') {
        $index = getIndex();
        $cat = $_GET['cat'] ?? 'audio'; $search = $_GET['search'] ?? '';
        $filtered = filterIndex($index, $cat, $search);
        echo json_encode(['items'=>array_column($filtered, 'n'), 'total'=>count($filtered)]); exit;
    }
    if ($_GET['action'] === 'names') {
        $index = getIndex();
        $tab = $_GET['tab'] ?? 'all'; $search = $_GET['search'] ?? '';
        $filtered = filterIndex($index, $tab, $search);
        $audio = []; $video = [];
        foreach ($filtered as $f) { if ($f['c'] === 'audio') $audio[] = $f['n']; elseif ($f['c'] === 'video') $video[] = $f['n']; }
        echo json_encode(['audio'=>$audio, 'video'=>$video, 'totalFiltered'=>count($filtered)]); exit;
    }
    if ($_GET['action'] === 'reshuffle') { reshuffleIndex(); echo json_encode(['ok'=>true]); exit; }
    if ($_GET['action'] === 'reindex') { rebuildIndex(); echo json_encode(['ok'=>true]); exit; }
    if ($_GET['action'] === 'nonce') {
        if (empty($_SESSION['nonce'])) $_SESSION['nonce'] = 'SUB-' . strtoupper(bin2hex(random_bytes(4)));
        echo json_encode(['nonce' => $_SESSION['nonce']]); exit;
    }
    if ($_GET['action'] === 'status') {
        echo json_encode(['subscribed'=>isSubscribed(),'address'=>$_SESSION['address']??null,'expires'=>$_SESSION['expires']??null]); exit;
    }
    if ($_GET['action'] === 'logout') { $_SESSION = []; session_destroy(); echo json_encode(['ok' => true]); exit; }
    if ($_GET['action'] === 'keeperAuth') {
        $addr = trim($_POST['address'] ?? ''); $pub = trim($_POST['publicKey'] ?? ''); $sig = trim($_POST['signature'] ?? '');
        if (empty($_SESSION['nonce'])) { echo json_encode(['ok'=>false,'error'=>'no nonce']); exit; }
        if (!preg_match('/^3[A-Za-z0-9]{34}$/', $addr) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $pub) || !preg_match('/^[1-9A-HJ-NP-Za-km-z]+$/', $sig)) { echo json_encode(['ok'=>false,'error'=>'bad data']); exit; }
        if (!function_exists('sodium_crypto_sign_verify_detached')) { echo json_encode(['ok'=>false,'error'=>'no ext-sodium']); exit; }
        if (!function_exists('gmp_init')) { echo json_encode(['ok'=>false,'error'=>'no ext-gmp']); exit; }
        $pubBytes = base58_decode($pub); $sigBytes = base58_decode($sig);
        if (strlen($pubBytes) !== 32 || strlen($sigBytes) !== 64) { echo json_encode(['ok'=>false,'error'=>'bad length']); exit; }
        $raw = httpGet(_core_node() . '/addresses/publicKey/' . urlencode($pub));
        if ($raw === null) { echo json_encode(['ok'=>false,'error'=>'node down']); exit; }
        $derivedData = json_decode($raw, true); $derivedAddr = $derivedData['address'] ?? '';
        if ($derivedAddr !== $addr) { echo json_encode(['ok'=>false,'error'=>'addr mismatch']); exit; }
        $nonce = $_SESSION['nonce']; $message = "\xff\xff\xff\x01" . $nonce;
        $v = wavesVerifySignature($message, $sigBytes, $pubBytes);
        if (!$v['valid']) { echo json_encode(['ok'=>false,'error'=>'bad signature','debug'=>$v]); exit; }
        $expires = findActiveSubscription($addr, $WAVES_SYSTEM_ADDRESS, $WAVES_MIN_AMOUNT, $WAVES_SUBSCRIPTION_DAYS);
        if ($expires === null) { echo json_encode(['ok'=>false,'error'=>'node down']); exit; }
        session_regenerate_id(true);
        if ($expires > 0) { $_SESSION['subscribed'] = true; $_SESSION['address'] = $addr; $_SESSION['expires'] = $expires; echo json_encode(['ok'=>true,'subscribed'=>true,'address'=>$addr,'expires'=>$expires]); }
        else { $_SESSION['subscribed'] = false; $_SESSION['address'] = $addr; echo json_encode(['ok'=>true,'subscribed'=>false,'address'=>$addr]); }
        exit;
    }
    if ($_GET['action'] === 'checkPayment') {
        $nonce = $_SESSION['nonce'] ?? ''; if (!$nonce) { echo json_encode(['ok'=>false,'error'=>'no nonce']); exit; }
        $raw = httpGet(_core_node() . '/transactions/address/' . urlencode($WAVES_SYSTEM_ADDRESS) . '/limit/200');
        if (!$raw) { echo json_encode(['ok'=>false,'error'=>'node down']); exit; }
        $data = json_decode($raw, true); $txs = $data[0] ?? (is_array($data) ? $data : []);
        $needAsset = (_core_asset_id() === 'WAVES') ? null : _core_asset_id();
        $minUnits = (int) round($WAVES_MIN_AMOUNT * pow(10, _core_asset_decimals()));
        foreach ($txs as $tx) {
            if (($tx['type'] ?? 0) !== 4) continue;
            if (($tx['recipient'] ?? '') !== $WAVES_SYSTEM_ADDRESS) continue;
            if (($tx['assetId'] ?? null) !== $needAsset) continue;
            if (($tx['amount'] ?? 0) < $minUnits) continue;
            $att = $tx['attachment'] ?? ''; if ($att === '') continue;
            $decoded = base58_decode($att); if ($decoded === false) continue;
            if (trim($decoded) !== $nonce) continue;
            $sender = $tx['sender'] ?? '';
            if (!preg_match('/^3[A-Za-z0-9]{34}$/', $sender)) continue;
            $expires = ($tx['timestamp'] ?? 0) + $WAVES_SUBSCRIPTION_DAYS * 86400 * 1000;
            session_regenerate_id(true);
            $_SESSION['subscribed'] = true; $_SESSION['address'] = $sender; $_SESSION['expires'] = $expires;
            unset($_SESSION['nonce']);
            echo json_encode(['ok'=>true,'subscribed'=>true,'address'=>$sender,'expires'=>$expires]); exit;
        }
        echo json_encode(['ok'=>true,'subscribed'=>false]); exit;
    }
}

$index = getIndex();
$counts = $index['counts'] ?? ['all'=>0];
$totalFiles = $counts['all'] ?? 0;
$initialItems = array_slice($index['files'], 0, $PAGE_SIZE);
foreach ($initialItems as &$it) enrichItem($it); unset($it);

$tabKeys = ['all'];
foreach ($CATEGORIES as $k => $c) if (!empty($counts[$k])) $tabKeys[] = $k;
if (!empty($counts['other'])) $tabKeys[] = 'other';

$isSubscribed = isSubscribed();
$userAddress  = $_SESSION['address'] ?? null;
$userExpires  = $_SESSION['expires'] ?? null;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($DEFAULT_LANG) ?>">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($SITE_TITLE) ?></title>
<meta name="description" content="<?= htmlspecialchars($SITE_DESCRIPTION) ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Yandex.Metrika counter -->
<script type="text/javascript">
    (function(m,e,t,r,i,k,a){
        m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};
        m[i].l=1*new Date();
        for (var j = 0; j < document.scripts.length; j++) {if (document.scripts[j].src === r) { return; }}
        k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)
    })(window, document,'script','https://mc.yandex.ru/metrika/tag.js?id=108772545', 'ym');

    ym(108772545, 'init', {ssr:true, webvisor:true, clickmap:true, ecommerce:"dataLayer", referrer: document.referrer, url: location.href, accurateTrackBounce:true, trackLinks:true});
</script>
<noscript><div><img src="https://mc.yandex.ru/watch/108772545" style="position:absolute; left:-9999px;" alt="" /></div></noscript>
<!-- /Yandex.Metrika counter -->



<style>
/* ============ ТЁМНАЯ ТЕМА (цветная) ============ */
:root{color-scheme:dark;--bg:#0f1115;--surface:#1a1d24;--surface-2:#20232c;--border:#262a33;--border-hi:#2a2433;--text:#e6e6e6;--text-strong:#fff;--muted:#8a93a3;--dim:#6b7280;--input-bg:#1a1d24;--btn-bg:#2b3140;--btn-bg-hover:#374151;--overlay:rgba(0,0,0,.8);--player-bg:rgba(16,19,26,.95);--auth-grad:linear-gradient(135deg,#1a1d24,#1f1a2e);--shadow-lg:0 -8px 30px rgba(0,0,0,.4);--accent:#3b82f6;--accent-hover:#2563eb;--success:#10b981;--success-hover:#059669;--danger:#ef4444;--danger-hover:#dc2626;--warning:#f59e0b;--purple:#8b5cf6;--pink:#ec4899}

/* ============ СВЕТЛАЯ ТЕМА (ч/б минимализм) ============ */
[data-theme=light]{
  color-scheme:light;
  --bg:#fafafa;
  --surface:#ffffff;
  --surface-2:#f5f5f5;
  --border:#e5e5e5;
  --border-hi:#d4d4d4;
  --text:#171717;
  --text-strong:#000000;
  --muted:#737373;
  --dim:#a3a3a3;
  --input-bg:#ffffff;
  --btn-bg:#f5f5f5;
  --btn-bg-hover:#e5e5e5;
  --overlay:rgba(0,0,0,.4);
  --player-bg:rgba(255,255,255,.96);
  --auth-grad:linear-gradient(135deg,#ffffff,#f5f5f5);
  --shadow-lg:0 -8px 30px rgba(0,0,0,.06);
  --accent:#171717;
  --accent-hover:#000000;
  --success:#171717;
  --success-hover:#000000;
  --danger:#525252;
  --danger-hover:#171717;
  --warning:#737373;
  --purple:#171717;
  --pink:#171717;
}
/* Перекрываем цветные градиенты в светлой теме */
[data-theme=light] .wbtn.primary{background:#171717;color:#fff}
[data-theme=light] .wbtn.primary:hover{background:#000}
[data-theme=light] .wbtn.pay{background:#171717;color:#fff}
[data-theme=light] .wbtn.pay:hover{background:#000}
[data-theme=light] .mode-btn{background:#171717;color:#fff;box-shadow:none}
[data-theme=light] .mode-btn:hover{background:#000;transform:translateY(-1px);box-shadow:none}
[data-theme=light] .mode-btn.video{background:#ffffff;color:#171717;border:1px solid #171717}
[data-theme=light] .mode-btn.video:hover{background:#f5f5f5}
[data-theme=light] .mode-btn.shuffle{background:#ffffff;color:#171717;border:1px solid #d4d4d4}
[data-theme=light] .mode-btn.shuffle:hover{background:#f5f5f5}
[data-theme=light] .mode-btn:disabled{background:#f5f5f5;color:#a3a3a3;border-color:#e5e5e5}
[data-theme=light] .progress-fill{background:#171717}
[data-theme=light] mark{background:#171717;color:#fff}
[data-theme=light] .nonce-big{background:#f5f5f5;color:#000;border:2px dashed #171717}
[data-theme=light] .toast{background:#171717;color:#fff}
[data-theme=light] .info-banner{background:#f5f5f5;border-color:#e5e5e5;color:#525252}
[data-theme=light] .warn{background:#fafafa;border-color:#d4d4d4;color:#525252}
[data-theme=light] .card.playing{border-color:#171717;box-shadow:0 0 0 2px rgba(0,0,0,.08)}
[data-theme=light] .card.playing.video-playing{border-color:#171717;box-shadow:0 0 0 2px rgba(0,0,0,.15)}
[data-theme=light] .tab .num{background:#e5e5e5;color:#525252}
[data-theme=light] .tab.active .num{background:#171717;color:#fff}
[data-theme=light] .pbtn.main{background:#171717}
[data-theme=light] .pbtn.active{background:#171717}
[data-theme=light] .pbtn.stop{background:#737373}
[data-theme=light] .pbtn.share{background:#171717}
[data-theme=light] .btn{background:#171717;color:#fff}
[data-theme=light] .btn:hover{background:#000}
[data-theme=light] .btn.play-from{background:#171717;color:#fff}
[data-theme=light] .btn.play-from:hover{background:#000}
[data-theme=light] .btn.play-from.video{background:#ffffff;color:#171717;border:1px solid #171717}
[data-theme=light] .btn.play-from.video:hover{background:#f5f5f5}
[data-theme=light] .auth-status .ok{color:#171717}
[data-theme=light] .auth-status .bad{color:#737373}
[data-theme=light] .copy-btn.copied{background:#171717}
[data-theme=light] .share-option:hover{border-color:#171717}
[data-theme=light] .lang-menu button.active{background:#171717;color:#fff}
[data-theme=light] .icon-btn:hover{background:#f5f5f5;border-color:#171717}
[data-theme=light] .search-box input:focus{border-color:#171717}

/* ============ ОБЩИЕ СТИЛИ ============ */
*{box-sizing:border-box}body{font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:var(--bg);color:var(--text);margin:0;padding:24px 24px 140px;transition:background .2s,color .2s}
[dir=rtl] body{direction:rtl}
.topbar{display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px}
.title-block{flex:1;min-width:240px}
h1{font-weight:500;margin:0 0 4px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;color:var(--text-strong)}
h1 .total{font-size:14px;color:var(--muted);font-weight:400}
.site-desc{color:var(--muted);font-size:13px;margin:0}
.prefs{display:flex;gap:8px;align-items:center}
.icon-btn{width:38px;height:38px;background:var(--surface);border:1px solid var(--border);border-radius:10px;color:var(--text);cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;transition:background .15s,border-color .15s}
.icon-btn:hover{background:var(--btn-bg-hover);border-color:var(--accent)}
.lang-wrap{position:relative}.lang-btn{padding:0 12px;width:auto;gap:6px;font-size:13px;font-weight:500}
.lang-menu{position:absolute;top:calc(100% + 6px);right:0;background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:6px;min-width:160px;z-index:300;display:none;box-shadow:0 8px 20px rgba(0,0,0,.15)}
[dir=rtl] .lang-menu{right:auto;left:0}.lang-menu.open{display:block}
.lang-menu button{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;background:transparent;border:none;color:var(--text);font-size:13px;cursor:pointer;border-radius:6px;text-align:left}
[dir=rtl] .lang-menu button{text-align:right}.lang-menu button:hover{background:var(--surface-2)}.lang-menu button.active{background:var(--accent);color:#fff}
.auth-bar{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px;margin-bottom:18px;background:var(--auth-grad);border:1px solid var(--border-hi);border-radius:12px}
.auth-status{flex:1;min-width:200px;font-size:13px}
.auth-status .addr{color:var(--text-strong);font-family:monospace;font-size:12px}
.auth-status .ok{color:var(--success);font-weight:500}.auth-status .bad{color:var(--warning)}.auth-status .muted{color:var(--muted)}
.wbtn{background:var(--btn-bg);color:var(--text);border:none;padding:8px 14px;border-radius:8px;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;transition:background .15s}
.wbtn:hover{background:var(--btn-bg-hover)}
.wbtn.primary{background:linear-gradient(135deg,#0055ff,#00c2ff);color:#fff}
.wbtn.pay{background:linear-gradient(135deg,#10b981,#059669);color:#fff}
.tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px;border-bottom:1px solid var(--border)}
.tab{background:transparent;border:none;color:var(--muted);padding:10px 16px;font-size:14px;cursor:pointer;border-radius:8px 8px 0 0;border-bottom:2px solid transparent;margin-bottom:-1px;transition:color .15s,background .15s}
.tab:hover{color:var(--text);background:var(--surface)}
.tab.active{color:var(--text-strong);border-bottom-color:var(--accent)}
.tab .num{font-size:12px;opacity:.85;margin-left:6px;background:var(--border);padding:1px 7px;border-radius:10px}
[dir=rtl] .tab .num{margin-left:0;margin-right:6px}
.tab.active .num{background:var(--accent);color:#fff;opacity:1}
.modes{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px}
.mode-btn{background:linear-gradient(135deg,#3b82f6,#8b5cf6);color:#fff;border:none;padding:12px 18px;border-radius:10px;font-size:14px;font-weight:500;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(59,130,246,.25);transition:transform .15s,background .15s}
.mode-btn:hover{transform:translateY(-2px)}.mode-btn.video{background:linear-gradient(135deg,#ec4899,#f43f5e)}
.mode-btn.shuffle{background:linear-gradient(135deg,#10b981,#059669)}
.mode-btn:disabled{opacity:.35;cursor:not-allowed;transform:none;box-shadow:none}
.mode-btn .count{opacity:.85;font-size:12px;font-weight:400}
.toolbar{display:flex;gap:12px;align-items:center;margin-bottom:20px;flex-wrap:wrap}
.search-box{position:relative;flex:1;min-width:220px;max-width:500px}
.search-box input{width:100%;padding:10px 36px 10px 38px;background:var(--input-bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;outline:none;transition:border-color .15s}
.search-box input:focus{border-color:var(--accent)}
.search-box::before{content:"🔍";position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:14px;opacity:.6;pointer-events:none}
[dir=rtl] .search-box::before{left:auto;right:12px}
.clear-btn{position:absolute;right:8px;top:50%;transform:translateY(-50%);background:var(--btn-bg);border:none;color:var(--text);width:24px;height:24px;border-radius:50%;cursor:pointer;font-size:14px;display:none;line-height:1}
[dir=rtl] .clear-btn{right:auto;left:8px}
.counter{font-size:13px;color:var(--muted);white-space:nowrap}
mark{background:#facc15;color:#0f1115;border-radius:3px;padding:0 2px}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px}
.card{background:var(--surface);border:1px solid var(--border);border-radius:10px;padding:12px;display:flex;flex-direction:column;gap:8px;overflow:hidden;position:relative;transition:border-color .15s}
.card:hover{border-color:var(--border-hi)}
.card.playing{border-color:var(--accent);box-shadow:0 0 0 2px rgba(59,130,246,.3)}
.card.playing.video-playing{border-color:var(--pink);box-shadow:0 0 0 2px rgba(236,72,153,.3)}
.cat-icon{opacity:.7;margin-right:6px;font-size:14px;vertical-align:middle}
.name{font-size:14px;word-break:break-all;color:var(--text-strong);line-height:1.35}
.meta{font-size:12px;color:var(--muted)}
.preview img,.preview video{max-width:100%;max-height:200px;border-radius:6px;display:block;margin:0 auto}
.preview audio{width:100%}
.btn{display:inline-flex;justify-content:center;align-items:center;gap:4px;text-decoration:none;background:var(--accent);color:#fff;padding:8px 12px;border-radius:6px;font-size:13px;text-align:center;border:none;cursor:pointer;transition:background .15s}
.btn:hover{background:var(--accent-hover)}
.btn.secondary{background:var(--btn-bg);color:var(--text)}.btn.secondary:hover{background:var(--btn-bg-hover)}
.btn.play-from{background:var(--success);color:#fff}.btn.play-from:hover{background:var(--success-hover)}
.btn.play-from.video{background:var(--pink);color:#fff}.btn.play-from.video:hover{background:#db2777}
.row{display:flex;gap:6px;flex-wrap:wrap}.row .btn{flex:1;min-width:70px}
.empty,.no-results{color:var(--muted);padding:40px 0;text-align:center}
.loading-indicator{text-align:center;color:var(--muted);padding:30px 0;font-size:13px;display:none}
.loading-indicator.active{display:block}
.loading-indicator::before{content:"⏳";margin-right:6px;display:inline-block;animation:spin 1.2s linear infinite}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
#scrollSentinel{height:1px}
.player{position:fixed;left:0;right:0;bottom:0;background:var(--player-bg);backdrop-filter:blur(12px);border-top:1px solid var(--border);padding:12px 20px;display:none;z-index:100;box-shadow:var(--shadow-lg)}
.player.active{display:block}
.player-top{display:flex;align-items:center;gap:14px;margin-bottom:8px}
.player-icon{font-size:22px}
.player-title{flex:1;font-size:14px;color:var(--text-strong);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.player-title .idx{color:var(--muted);font-size:12px;margin-right:6px}
.player-controls{display:flex;gap:6px;align-items:center}
.pbtn{background:var(--btn-bg);color:var(--text);border:none;width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:14px;display:flex;align-items:center;justify-content:center;transition:background .15s}
.pbtn:hover{background:var(--btn-bg-hover)}
.pbtn.main{background:var(--accent);color:#fff;width:42px;height:42px;font-size:16px}
.pbtn.active{background:var(--success);color:#fff}
.pbtn.stop{background:var(--danger);color:#fff}
.pbtn.share{background:var(--purple);color:#fff}
.progress{height:4px;background:var(--border);border-radius:2px;cursor:pointer;overflow:hidden}
.progress-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#8b5cf6);width:0;transition:width .1s linear}
.time{font-size:11px;color:var(--muted);min-width:90px;text-align:right}
.video-modal{position:fixed;inset:0;background:rgba(0,0,0,.9);display:none;align-items:center;justify-content:center;z-index:99;padding:20px}
.video-modal.active{display:flex}.video-modal video{max-width:100%;max-height:70vh;border-radius:8px}
.modal{position:fixed;inset:0;background:var(--overlay);display:none;align-items:center;justify-content:center;z-index:200;padding:20px;overflow-y:auto}
.modal.active{display:flex}
.modal-box{background:var(--surface);border:1px solid var(--border-hi);border-radius:14px;padding:26px;max-width:520px;width:100%;margin:auto;color:var(--text)}
.modal-box.debug{max-width:680px}.modal-box h3{margin:0 0 14px;color:var(--text-strong)}
.modal-box p{color:var(--text);opacity:.85;font-size:14px;line-height:1.5;margin:0 0 12px}
.detail{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px;margin:10px 0}
.detail-row{display:flex;align-items:center;gap:8px;margin:6px 0}
.detail-row b{min-width:90px;color:var(--muted);font-weight:400;font-size:12px}
.detail-row span{flex:1;color:var(--text-strong);word-break:break-all;font-family:monospace;font-size:13px}
.nonce-big{background:linear-gradient(135deg,#facc15,#f59e0b);color:#0f1115;padding:14px 18px;border-radius:10px;font-family:monospace;font-size:20px;font-weight:bold;text-align:center;margin:8px 0;user-select:all}
.copy-btn{background:var(--btn-bg);color:var(--text);border:none;padding:4px 10px;border-radius:6px;font-size:11px;cursor:pointer;white-space:nowrap}
.copy-btn.copied{background:var(--success);color:#fff}
.actions{display:flex;gap:8px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap}
[dir=rtl] .actions{justify-content:flex-start}
.qr-wrap{display:flex;justify-content:center;margin:12px 0}
.qr-wrap img{background:#fff;padding:8px;border-radius:8px;max-width:180px}
.modal-close{float:right;background:none;border:none;color:var(--muted);font-size:22px;cursor:pointer;margin:-10px -8px 0 0}
[dir=rtl] .modal-close{float:left}
.warn{background:rgba(245,158,11,.15);border:1px solid rgba(245,158,11,.4);border-radius:8px;padding:10px 12px;font-size:13px;color:#fde68a;margin:10px 0}
.info-banner{background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:10px;padding:10px 14px;font-size:13px;color:#93c5fd;margin-bottom:16px}
.debug-pre{background:var(--bg);border:1px solid var(--border);border-radius:8px;padding:12px;font-family:monospace;font-size:11px;white-space:pre-wrap;word-break:break-all;color:var(--text);max-height:300px;overflow:auto}
.share-input{width:100%;padding:12px;background:var(--bg);border:1px solid var(--border);border-radius:8px;color:var(--text);font-family:monospace;font-size:12px;outline:none;margin-bottom:6px}
.share-hint{font-size:12px;color:var(--muted);margin-bottom:10px}
.share-options{display:flex;flex-direction:column;gap:10px;margin:12px 0}
.share-option{padding:14px 16px;background:var(--bg);border:1px solid var(--border);border-radius:10px;cursor:pointer;display:flex;align-items:center;gap:12px;transition:border-color .15s}
.share-option:hover{border-color:var(--purple)}.share-option .icon{font-size:24px}
.share-option .text{flex:1}
.share-option .text .title{color:var(--text-strong);font-weight:500;font-size:14px}
.share-option .text .sub{color:var(--muted);font-size:12px;margin-top:2px}
.toast{position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:var(--success);color:#fff;padding:10px 20px;border-radius:20px;font-size:13px;z-index:500;opacity:0;pointer-events:none;transition:opacity .2s,transform .2s}
.toast.show{opacity:1;transform:translateX(-50%) translateY(-10px)}
@media(max-width:600px){
  body{padding:12px 10px 160px}
  h1{font-size:22px}
  .info-banner{font-size:12px;padding:8px 12px}
  .auth-bar{padding:10px 12px}
  .mode-btn{padding:10px 14px;font-size:13px}
  .tab{padding:8px 10px;font-size:13px}
  .time{display:none}
  .pbtn{width:32px;height:32px}.pbtn.main{width:38px;height:38px}
  .prefs{margin-left:auto}

  /* === Мобильный список: карточки строкой === */
  .grid{display:flex;flex-direction:column;gap:2px}
  .card{
    flex-direction:row;
    align-items:center;
    gap:10px;
    padding:10px 12px;
    border-radius:8px;
    overflow:hidden;
  }
  .card:hover{border-color:var(--border)}
  .card .preview{display:none}
  .card-info{
    flex:1;
    min-width:0;
    display:flex;
    flex-direction:column;
    gap:2px;
    overflow:hidden;
  }
  .card .name{
    font-size:13px;
    line-height:1.3;
    word-break:normal;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
    display:block;
  }
  .card .meta{font-size:11px;color:var(--muted);line-height:1.2}
  .cat-icon{font-size:18px;opacity:.85;flex-shrink:0;margin-right:0}
  .card .row{
    flex-direction:row;
    flex-wrap:nowrap;
    gap:6px;
    margin:0;
    flex-shrink:0;
  }
  .card .row .btn.secondary,
  .card .row a.btn{display:none}
  .card .row .btn.play-from{
    width:40px;height:40px;min-width:40px;
    padding:0;
    border-radius:50%;
    flex:0 0 40px;
    position:relative;
    font-size:0;
    color:transparent;
  }
  .card .row .btn.play-from::before{
    content:"▶";
    font-size:14px;
    color:#fff;
    position:absolute;
    top:50%;left:50%;
    transform:translate(-50%,-50%);
    margin-left:1px;
  }
  [data-theme=light] .card .row .btn.play-from.video::before{color:#171717}
  .card .row .btn.play-from.video::before{content:"▶"}
  .card.playing .row .btn.play-from::before{content:"♪";font-size:16px;margin-left:0}
  .card.playing.video-playing .row .btn.play-from::before{content:"■";font-size:12px;margin-left:0}
}
</style>
</head>
<body>
<div class="topbar">
  <div class="title-block">
    <h1><span id="siteTitle"><?= htmlspecialchars($SITE_TITLE) ?></span>
      <span class="total"><span id="totalNum"><?= (int)$totalFiles ?></span> <span data-i18n="items"></span></span>
    </h1>
    <p class="site-desc"><?= htmlspecialchars($SITE_DESCRIPTION) ?></p>
  </div>
  <div class="prefs">
    <button class="icon-btn" id="themeToggle">🌙</button>
    <div class="lang-wrap">
      <button class="icon-btn lang-btn" id="langBtn"><span>🌐</span><span id="langBtnLabel">EN</span></button>
      <div class="lang-menu" id="langMenu">
        <button data-lang="en">🇬🇧 English</button>
        <button data-lang="ru">🇷🇺 Русский</button>
        <button data-lang="zh">🇨🇳 中文</button>
        <button data-lang="es">🇪🇸 Español</button>
        <button data-lang="fr">🇫🇷 Français</button>
        <button data-lang="de">🇩🇪 Deutsch</button>
        <button data-lang="ja">🇯🇵 日本語</button>
        <button data-lang="ko">🇰🇷 한국어</button>
        <button data-lang="pt">🇧🇷 Português</button>
        <button data-lang="ar">🇸🇦 العربية</button>
      </div>
    </div>
  </div>
</div>

<div class="auth-bar">
  <div class="auth-status" id="authStatus" data-state="<?= $isSubscribed ? 'active' : ($userAddress ? 'no_sub' : 'none') ?>" data-addr="<?= $userAddress ? htmlspecialchars(substr($userAddress,0,8).'…'.substr($userAddress,-6), ENT_QUOTES) : '' ?>" data-expires-ms="<?= (int)($userExpires ?? 0) ?>"></div>
  <button class="wbtn primary" id="connectKeeperBtn" data-i18n="login_keeper" <?= $isSubscribed?'style="display:none"':'' ?>></button>
  <button class="wbtn pay" id="payBtn" data-i18n="pay_sub" <?= $isSubscribed?'style="display:none"':'' ?>></button>
  <button class="wbtn" id="disconnectBtn" data-i18n="logout" <?= $userAddress?'':'style="display:none"' ?>></button>
</div>

<?php if (!$isSubscribed && empty($userAddress)): ?>
<div class="info-banner" data-i18n="info_banner"></div>
<?php endif; ?>

<?php if ($totalFiles === 0): ?>
<p class="empty" data-i18n="empty_folder"></p>
<?php else: ?>

<div class="tabs" id="tabs">
<?php foreach ($tabKeys as $key): ?>
  <button class="tab <?= $key==='all'?'active':'' ?>" data-tab="<?= $key ?>"><span data-i18n="tab_<?= $key ?>"></span><span class="num" data-count="<?= $key ?>"><?= (int)($counts[$key] ?? 0) ?></span></button>
<?php endforeach; ?>
</div>

<div class="modes">
  <button class="mode-btn" id="radioBtn" disabled><span data-i18n="mode_radio"></span> <span class="count" id="radioCount"></span></button>
  <button class="mode-btn video" id="cinemaBtn" disabled><span data-i18n="mode_cinema"></span> <span class="count" id="cinemaCount"></span></button>
  <button class="mode-btn shuffle" id="reshuffleBtn" data-i18n="mode_reshuffle"></button>
</div>

<div class="toolbar">
  <div class="search-box">
    <input type="text" id="search" data-i18n-ph="search_ph" autocomplete="off">
    <button class="clear-btn" id="clear">✕</button>
  </div>
  <button class="wbtn" id="shareViewBtn" data-i18n="share_btn"></button>
  <div class="counter" id="counter"></div>
</div>

<div class="grid" id="grid"></div>
<p class="no-results" id="noResults" style="display:none" data-i18n="nothing_found"></p>
<div class="loading-indicator" id="loadingIndicator"><span data-i18n="loading"></span></div>
<div id="scrollSentinel"></div>

<div class="player" id="player">
  <div class="player-top">
    <span class="player-icon" id="playerIcon">🎵</span>
    <div class="player-title"><span class="idx" id="playerIdx"></span><span id="playerTitle">—</span></div>
    <div class="time" id="playerTime">0:00 / 0:00</div>
    <div class="player-controls">
      <button class="pbtn share" id="btnShare">🔗</button>
      <button class="pbtn" id="btnShuffle">🔀</button>
      <button class="pbtn" id="btnPrev">⏮</button>
      <button class="pbtn main" id="btnPlay">⏸</button>
      <button class="pbtn" id="btnNext">⏭</button>
      <button class="pbtn" id="btnRepeat">🔁</button>
      <button class="pbtn stop" id="btnStop">✕</button>
    </div>
  </div>
  <div class="progress" id="progress"><div class="progress-fill" id="progressFill"></div></div>
</div>

<div class="video-modal" id="videoModal"><video id="videoEl" controls autoplay></video></div>

<div class="modal" id="shareModal"><div class="modal-box">
  <button class="modal-close" data-close-modal="shareModal">×</button>
  <h3 id="shareTitle" data-i18n="share_title"></h3>
  <p id="shareDesc" style="display:none" data-i18n="share_desc"></p>
  <div id="shareOptions" class="share-options" style="display:none"></div>
  <div id="shareLinkWrap" style="display:none">
    <div class="share-hint" id="shareHint"></div>
    <input type="text" class="share-input" id="shareInput" readonly>
    <div class="actions">
      <button class="wbtn" id="shareBackBtn" style="display:none" data-i18n="back"></button>
      <button class="wbtn" data-close-modal="shareModal" data-i18n="close"></button>
      <button class="wbtn" id="shareNativeBtn" style="display:none" data-i18n="share_system"></button>
      <button class="wbtn primary" id="shareCopyBtn" data-i18n="copy"></button>
    </div>
  </div>
</div></div>

<div class="modal" id="payModal"><div class="modal-box">
  <button class="modal-close" data-close-modal="payModal">×</button>
  <h3 data-i18n="pay_title"></h3>
  <p data-i18n="pay_intro"></p>
  <p style="color:var(--muted);font-size:12px" data-i18n="pay_your_code"></p>
  <div class="nonce-big" id="nonceDisplay">…</div>
  <div class="detail">
    <div class="detail-row"><b data-i18n="pay_amount"></b><span><?= $WAVES_MIN_AMOUNT ?></span><button class="copy-btn" data-copy="<?= $WAVES_MIN_AMOUNT ?>" data-i18n="copy_short"></button></div>
    <div class="detail-row"><b data-i18n="pay_recipient"></b><span><?= htmlspecialchars($WAVES_SYSTEM_ADDRESS) ?></span><button class="copy-btn" data-copy="<?= htmlspecialchars($WAVES_SYSTEM_ADDRESS) ?>" data-i18n="copy_short"></button></div>
    <div class="detail-row"><b data-i18n="pay_attachment"></b><span id="nonceCopyRow">…</span><button class="copy-btn" id="nonceCopyBtn" data-i18n="copy_short"></button></div>
    <div class="detail-row"><b data-i18n="pay_duration"></b><span id="paySubDays"><?= $WAVES_SUBSCRIPTION_DAYS ?></span></div>
  </div>
  <div class="qr-wrap" id="qrWrap"></div>
  <div class="warn" data-i18n="pay_warn"></div>
  <div class="actions">
    <button class="wbtn" data-close-modal="payModal" data-i18n="close"></button>
    <button class="wbtn" id="openExchangeBtn">🌐 wx.network</button>
    <button class="wbtn primary" id="keeperPayBtn" data-i18n="pay_via_keeper"></button>
    <button class="wbtn pay" id="checkPaymentBtn" data-i18n="pay_check"></button>
  </div>
</div></div>

<div class="modal" id="debugModal"><div class="modal-box debug">
  <button class="modal-close" data-close-modal="debugModal">×</button>
  <h3 data-i18n="dbg_title"></h3>
  <p id="debugText">—</p>
  <div class="debug-pre" id="debugPre">—</div>
  <div class="actions">
    <button class="wbtn" id="copyDebugBtn" data-i18n="dbg_copy"></button>
    <button class="wbtn pay" data-close-modal="debugModal" id="goToPayBtn" data-i18n="dbg_to_pay"></button>
  </div>
</div></div>

<div class="toast" id="toast"></div>
<?php endif; ?>

<script id="initialData" type="application/json"><?= json_encode(['items'=>$initialItems,'total'=>$totalFiles,'counts'=>$counts,'pageSize'=>$PAGE_SIZE,'hasMore'=>$totalFiles>$PAGE_SIZE,'maxShareList'=>$MAX_SHARE_LIST_SIZE]) ?></script>
<script>
const WAVES_CFG={systemAddress:<?=json_encode($WAVES_SYSTEM_ADDRESS)?>,assetId:<?=json_encode(_core_asset_id())?>,decimals:<?=(int)_core_asset_decimals()?>,minAmount:<?=(float)$WAVES_MIN_AMOUNT?>,days:<?=(int)$WAVES_SUBSCRIPTION_DAYS?>};
const PREFS={defaultTheme:<?=json_encode($DEFAULT_THEME)?>,defaultLang:<?=json_encode($DEFAULT_LANG)?>};
let IS_SUBSCRIBED=<?=$isSubscribed?'true':'false'?>;
let SESSION_NONCE=null;

const I18N={
en:{items:"items",loading:"Loading…",login_keeper:"🦊 Login via Keeper",pay_sub:"💳 Pay subscription",logout:"🚪 Logout",sub_active:"Subscription active",until:"until",addr_no_sub:"⚠ Address confirmed, no subscription",no_sub_hint:"Only 🎵 Radio and 🎬 Cinema are available. Subscribe to open files.",info_banner:"Without subscription, previews are hidden. But 🎵 Radio, 🎬 Cinema, and sharing work.",empty_folder:"Nothing here yet.",tab_all:"📂 All",tab_audio:"🎵 Audio",tab_video:"🎬 Video",tab_image:"🖼 Images",tab_doc:"📄 Docs",tab_arch:"📦 Archives",tab_other:"📦 Other",mode_radio:"🎵 Radio",mode_cinema:"🎬 Cinema",mode_reshuffle:"🎲 Shuffle again",count_tracks:"({n} tracks)",count_videos:"({n} videos)",search_ph:"Search...",share_btn:"🔗 Share",counter_of:"{v} of {total}",nothing_found:"😕 Nothing found",btn_playlist:"▶ Playlist",btn_continuous:"▶ Continuous",btn_open:"Open",btn_download:"⬇ Download",share_title:"🔗 Share",share_desc:"Choose format:",share_ready:"🔗 Ready link",share_radio_title:"Radio playlist",share_cinema_title:"Cinema selection",share_tracks:"{n} tracks",share_videos:"{n} videos",share_playing:'{type} · from "{file}" · {n} items',type_radio:"🎵 Radio",type_cinema:"🎬 Cinema",close:"Close",back:"← Back",copy:"📋 Copy",share_system:"📤 Share…",link_copied:"Link copied",no_media:"No audio/video in current selection",pay_title:"💎 Pay subscription",pay_intro:'Send a transaction with parameters below. After confirmation click "🔄 Check".',pay_your_code:'⚠️ Your unique code. Put it in "Comment (Attachment)":',pay_amount:"Amount",pay_recipient:"Recipient",pay_attachment:"Attachment",pay_duration:"Duration",pay_days:"{n} days",pay_warn:"⚠️ Without the code we can't link payment. One-time code.",pay_via_keeper:"🦊 Pay via Keeper",pay_check:"🔄 Check",copy_short:"copy",dbg_title:"⚠ Auth error",dbg_copy:"📋 Copy debug",dbg_to_pay:"💳 Go to payment",tx_sent:"⏳ Transaction sent. Waiting for confirmation…",tx_checking:"⏳ Checking blockchain…",tx_not_found_3min:"⚠ Transaction not found in 3 minutes.",tx_not_found:"⚠ Transaction not found yet.",keeper_404:"Keeper not found. Open payment form?",err_keeper:"Keeper error"},
ru:{items:"шт.",loading:"Загрузка…",login_keeper:"🦊 Войти через Keeper",pay_sub:"💳 Оплатить подписку",logout:"🚪 Выйти",sub_active:"Подписка активна",until:"до",addr_no_sub:"⚠ Адрес подтверждён, подписки нет",no_sub_hint:"Доступны только 🎵 Радио и 🎬 Кинозал. Для открытия файлов — нужна подписка.",info_banner:"Без подписки превью скрыты. Работают 🎵 Радио, 🎬 Кинозал и share.",empty_folder:"В папке пока ничего нет.",tab_all:"📂 Все",tab_audio:"🎵 Аудио",tab_video:"🎬 Видео",tab_image:"🖼 Изображения",tab_doc:"📄 Документы",tab_arch:"📦 Архивы",tab_other:"📦 Прочее",mode_radio:"🎵 Радио",mode_cinema:"🎬 Кинозал",mode_reshuffle:"🎲 Перемешать снова",count_tracks:"({n} треков)",count_videos:"({n} видео)",search_ph:"Поиск...",share_btn:"🔗 Поделиться",counter_of:"{v} из {total}",nothing_found:"😕 Ничего не найдено",btn_playlist:"▶ Плейлист",btn_continuous:"▶ Подряд",btn_open:"Открыть",btn_download:"⬇ Скачать",share_title:"🔗 Поделиться",share_desc:"Выберите формат:",share_ready:"🔗 Готовая ссылка",share_radio_title:"Радио-плейлист",share_cinema_title:"Кино-подборка",share_tracks:"{n} треков",share_videos:"{n} видео",share_playing:"{type} · с «{file}» · {n} шт.",type_radio:"🎵 Радио",type_cinema:"🎬 Кино",close:"Закрыть",back:"← Назад",copy:"📋 Скопировать",share_system:"📤 Поделиться…",link_copied:"Ссылка скопирована",no_media:"Нет аудио/видео в подборке",pay_title:"💎 Оплата подписки",pay_intro:"Отправьте транзакцию с параметрами ниже. После подтверждения нажмите «🔄 Проверить».",pay_your_code:"⚠️ Ваш уникальный код. Впишите в поле «Комментарий»:",pay_amount:"Сумма",pay_recipient:"Получатель",pay_attachment:"Attachment",pay_duration:"Срок",pay_days:"{n} дней",pay_warn:"⚠️ Без кода платёж не привяжется. Код одноразовый.",pay_via_keeper:"🦊 Через Keeper",pay_check:"🔄 Проверить",copy_short:"копир.",dbg_title:"⚠ Ошибка авторизации",dbg_copy:"📋 Debug",dbg_to_pay:"💳 К оплате",tx_sent:"⏳ Транзакция отправлена. Ждём подтверждения…",tx_checking:"⏳ Проверяем блокчейн…",tx_not_found_3min:"⚠ Транзакция не найдена за 3 мин.",tx_not_found:"⚠ Транзакция пока не найдена.",keeper_404:"Keeper не найден. Открыть форму оплаты?",err_keeper:"Ошибка Keeper"},
zh:{items:"个",loading:"加载中…",login_keeper:"🦊 Keeper登录",pay_sub:"💳 支付订阅",logout:"🚪 退出",sub_active:"订阅有效",until:"至",addr_no_sub:"⚠ 地址已确认，无订阅",no_sub_hint:"仅 🎵🎬 可用。",info_banner:"无订阅预览隐藏。🎵🎬分享可用。",empty_folder:"暂无内容。",tab_all:"📂 全部",tab_audio:"🎵 音频",tab_video:"🎬 视频",tab_image:"🖼 图片",tab_doc:"📄 文档",tab_arch:"📦 压缩",tab_other:"📦 其他",mode_radio:"🎵 电台",mode_cinema:"🎬 影院",mode_reshuffle:"🎲 再次随机",count_tracks:"({n}首)",count_videos:"({n}个)",search_ph:"搜索...",share_btn:"🔗 分享",counter_of:"{v}/{total}",nothing_found:"😕 未找到",btn_playlist:"▶ 列表",btn_continuous:"▶ 连播",btn_open:"打开",btn_download:"⬇ 下载",share_title:"🔗 分享",share_desc:"选择格式:",share_ready:"🔗 链接",share_radio_title:"电台",share_cinema_title:"影院",share_tracks:"{n}首",share_videos:"{n}个",share_playing:"{type} · 从{file} · {n}",type_radio:"🎵",type_cinema:"🎬",close:"关闭",back:"← 返回",copy:"📋 复制",share_system:"📤 分享…",link_copied:"已复制",no_media:"无媒体",pay_title:"💎 支付",pay_intro:"发送交易后点击检查。",pay_your_code:"⚠️ 代码放备注:",pay_amount:"金额",pay_recipient:"收款人",pay_attachment:"备注",pay_duration:"期限",pay_days:"{n}天",pay_warn:"⚠️ 无代码无法关联。",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 检查",copy_short:"复制",dbg_title:"⚠ 错误",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ 已发送…",tx_checking:"⏳ 检查…",tx_not_found_3min:"⚠ 3分钟未找到。",tx_not_found:"⚠ 未找到。",keeper_404:"Keeper无。打开?",err_keeper:"错误"},
es:{items:"arch.",loading:"Cargando…",login_keeper:"🦊 Keeper login",pay_sub:"💳 Suscribirse",logout:"🚪 Salir",sub_active:"Activa",until:"hasta",addr_no_sub:"⚠ Sin suscripción",no_sub_hint:"Solo 🎵🎬.",info_banner:"Sin suscripción prévias ocultas.",empty_folder:"Vacío.",tab_all:"📂 Todos",tab_audio:"🎵 Audio",tab_video:"🎬 Vídeo",tab_image:"🖼 Imágenes",tab_doc:"📄 Docs",tab_arch:"📦 Archivos",tab_other:"📦 Otros",mode_radio:"🎵 Radio",mode_cinema:"🎬 Cine",mode_reshuffle:"🎲 Mezclar",count_tracks:"({n} pistas)",count_videos:"({n} vídeos)",search_ph:"Buscar...",share_btn:"🔗 Compartir",counter_of:"{v} de {total}",nothing_found:"😕 Nada",btn_playlist:"▶ Lista",btn_continuous:"▶ Seguido",btn_open:"Abrir",btn_download:"⬇ Descargar",share_title:"🔗 Compartir",share_desc:"Formato:",share_ready:"🔗 Enlace",share_radio_title:"Radio",share_cinema_title:"Cine",share_tracks:"{n} pistas",share_videos:"{n} vídeos",share_playing:"{type} · «{file}» · {n}",type_radio:"🎵",type_cinema:"🎬",close:"Cerrar",back:"← Atrás",copy:"📋 Copiar",share_system:"📤",link_copied:"Copiado",no_media:"Sin media",pay_title:"💎 Pagar",pay_intro:"Envía, pulsa Comprobar.",pay_your_code:"⚠️ Código en Comentario:",pay_amount:"Monto",pay_recipient:"Destinatario",pay_attachment:"Attachment",pay_duration:"Duración",pay_days:"{n} días",pay_warn:"⚠️ Sin código no hay vínculo.",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 Comprobar",copy_short:"copiar",dbg_title:"⚠ Error",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ Enviada…",tx_checking:"⏳ Comprobando…",tx_not_found_3min:"⚠ No encontrada.",tx_not_found:"⚠ No encontrada.",keeper_404:"Keeper ausente. Abrir?",err_keeper:"Error"},
fr:{items:"fich.",loading:"Chargement…",login_keeper:"🦊 Keeper",pay_sub:"💳 S'abonner",logout:"🚪 Quitter",sub_active:"Actif",until:"jusqu'au",addr_no_sub:"⚠ Pas d'abonnement",no_sub_hint:"Seuls 🎵🎬.",info_banner:"Sans abonnement aperçus masqués.",empty_folder:"Vide.",tab_all:"📂 Tous",tab_audio:"🎵 Audio",tab_video:"🎬 Vidéo",tab_image:"🖼 Images",tab_doc:"📄 Docs",tab_arch:"📦 Archives",tab_other:"📦 Autres",mode_radio:"🎵 Radio",mode_cinema:"🎬 Cinéma",mode_reshuffle:"🎲 Mélanger",count_tracks:"({n} pistes)",count_videos:"({n} vidéos)",search_ph:"Chercher...",share_btn:"🔗 Partager",counter_of:"{v} sur {total}",nothing_found:"😕 Rien",btn_playlist:"▶ Liste",btn_continuous:"▶ Suite",btn_open:"Ouvrir",btn_download:"⬇ Télécharger",share_title:"🔗 Partager",share_desc:"Format:",share_ready:"🔗 Lien",share_radio_title:"Radio",share_cinema_title:"Cinéma",share_tracks:"{n} pistes",share_videos:"{n} vidéos",share_playing:"{type} · «{file}» · {n}",type_radio:"🎵",type_cinema:"🎬",close:"Fermer",back:"← Retour",copy:"📋 Copier",share_system:"📤",link_copied:"Copié",no_media:"Rien",pay_title:"💎 Payer",pay_intro:"Envoyez puis Vérifier.",pay_your_code:"⚠️ Code dans Commentaire:",pay_amount:"Montant",pay_recipient:"Destinataire",pay_attachment:"Attachment",pay_duration:"Durée",pay_days:"{n} jours",pay_warn:"⚠️ Sans code pas de lien.",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 Vérifier",copy_short:"copier",dbg_title:"⚠ Erreur",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ Envoyée…",tx_checking:"⏳ Vérif…",tx_not_found_3min:"⚠ Non trouvée.",tx_not_found:"⚠ Pas trouvée.",keeper_404:"Keeper absent. Ouvrir?",err_keeper:"Erreur"},
de:{items:"St.",loading:"Lädt…",login_keeper:"🦊 Keeper",pay_sub:"💳 Abo",logout:"🚪 Abmelden",sub_active:"Aktiv",until:"bis",addr_no_sub:"⚠ Kein Abo",no_sub_hint:"Nur 🎵🎬.",info_banner:"Ohne Abo versteckt.",empty_folder:"Leer.",tab_all:"📂 Alle",tab_audio:"🎵 Audio",tab_video:"🎬 Video",tab_image:"🖼 Bilder",tab_doc:"📄 Dok.",tab_arch:"📦 Archive",tab_other:"📦 Sonst.",mode_radio:"🎵 Radio",mode_cinema:"🎬 Kino",mode_reshuffle:"🎲 Mischen",count_tracks:"({n} Titel)",count_videos:"({n} Videos)",search_ph:"Suche...",share_btn:"🔗 Teilen",counter_of:"{v} von {total}",nothing_found:"😕 Nichts",btn_playlist:"▶ Liste",btn_continuous:"▶ Weiter",btn_open:"Öffnen",btn_download:"⬇ DL",share_title:"🔗 Teilen",share_desc:"Format:",share_ready:"🔗 Link",share_radio_title:"Radio",share_cinema_title:"Kino",share_tracks:"{n} Titel",share_videos:"{n} Videos",share_playing:"{type} · «{file}» · {n}",type_radio:"🎵",type_cinema:"🎬",close:"Schließen",back:"← Zurück",copy:"📋 Kop.",share_system:"📤",link_copied:"Kopiert",no_media:"Nichts",pay_title:"💎 Zahlen",pay_intro:"Senden, dann Prüfen.",pay_your_code:"⚠️ Code in Kommentar:",pay_amount:"Betrag",pay_recipient:"Empfänger",pay_attachment:"Attachment",pay_duration:"Dauer",pay_days:"{n} Tage",pay_warn:"⚠️ Ohne Code keine Zuordnung.",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 Prüfen",copy_short:"kop.",dbg_title:"⚠ Fehler",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ Gesendet…",tx_checking:"⏳ Prüfe…",tx_not_found_3min:"⚠ Nicht gefunden.",tx_not_found:"⚠ Nicht gefunden.",keeper_404:"Keeper fehlt. Öffnen?",err_keeper:"Fehler"},
ja:{items:"件",loading:"読込中…",login_keeper:"🦊 Keeper",pay_sub:"💳 サブスク",logout:"🚪 ログアウト",sub_active:"有効",until:"まで",addr_no_sub:"⚠ サブスクなし",no_sub_hint:"🎵🎬のみ。",info_banner:"サブスクなしプレビュー非表示。",empty_folder:"空。",tab_all:"📂 全て",tab_audio:"🎵 音声",tab_video:"🎬 動画",tab_image:"🖼 画像",tab_doc:"📄 文書",tab_arch:"📦 圧縮",tab_other:"📦 他",mode_radio:"🎵 ラジオ",mode_cinema:"🎬 シネマ",mode_reshuffle:"🎲 シャッフル",count_tracks:"({n}曲)",count_videos:"({n}本)",search_ph:"検索...",share_btn:"🔗 共有",counter_of:"{v}/{total}",nothing_found:"😕 無し",btn_playlist:"▶ リスト",btn_continuous:"▶ 連続",btn_open:"開く",btn_download:"⬇ DL",share_title:"🔗 共有",share_desc:"形式:",share_ready:"🔗 リンク",share_radio_title:"ラジオ",share_cinema_title:"シネマ",share_tracks:"{n}曲",share_videos:"{n}本",share_playing:"{type} · {file} · {n}",type_radio:"🎵",type_cinema:"🎬",close:"閉じる",back:"← 戻る",copy:"📋",share_system:"📤",link_copied:"コピー済",no_media:"無し",pay_title:"💎 支払い",pay_intro:"送金後確認。",pay_your_code:"⚠️ コードをコメント:",pay_amount:"金額",pay_recipient:"受取人",pay_attachment:"Attachment",pay_duration:"期間",pay_days:"{n}日",pay_warn:"⚠️ コード必要。",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 確認",copy_short:"コピー",dbg_title:"⚠ エラー",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ 送信済…",tx_checking:"⏳ 確認…",tx_not_found_3min:"⚠ 未検出。",tx_not_found:"⚠ まだ。",keeper_404:"Keeper無。開く?",err_keeper:"エラー"},
ko:{items:"개",loading:"불러오는 중…",login_keeper:"🦊 Keeper",pay_sub:"💳 구독",logout:"🚪 로그아웃",sub_active:"활성",until:"까지",addr_no_sub:"⚠ 구독 없음",no_sub_hint:"🎵🎬만.",info_banner:"구독 없이 숨김.",empty_folder:"없음.",tab_all:"📂 전체",tab_audio:"🎵 오디오",tab_video:"🎬 비디오",tab_image:"🖼 이미지",tab_doc:"📄 문서",tab_arch:"📦 아카이브",tab_other:"📦 기타",mode_radio:"🎵 라디오",mode_cinema:"🎬 시네마",mode_reshuffle:"🎲 섞기",count_tracks:"({n}곡)",count_videos:"({n}개)",search_ph:"검색...",share_btn:"🔗 공유",counter_of:"{v}/{total}",nothing_found:"😕 없음",btn_playlist:"▶ 목록",btn_continuous:"▶ 연속",btn_open:"열기",btn_download:"⬇ 다운",share_title:"🔗 공유",share_desc:"형식:",share_ready:"🔗 링크",share_radio_title:"라디오",share_cinema_title:"시네마",share_tracks:"{n}곡",share_videos:"{n}개",share_playing:"{type} · {file} · {n}",type_radio:"🎵",type_cinema:"🎬",close:"닫기",back:"← 뒤로",copy:"📋",share_system:"📤",link_copied:"복사됨",no_media:"없음",pay_title:"💎 결제",pay_intro:"전송 후 확인.",pay_your_code:"⚠️ 코드를 메모:",pay_amount:"금액",pay_recipient:"수신인",pay_attachment:"Attachment",pay_duration:"기간",pay_days:"{n}일",pay_warn:"⚠️ 코드 필수.",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 확인",copy_short:"복사",dbg_title:"⚠ 오류",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ 전송…",tx_checking:"⏳ 확인…",tx_not_found_3min:"⚠ 미발견.",tx_not_found:"⚠ 없음.",keeper_404:"Keeper 없음. 열기?",err_keeper:"오류"},
pt:{items:"arq.",loading:"Carregando…",login_keeper:"🦊 Keeper",pay_sub:"💳 Assinar",logout:"🚪 Sair",sub_active:"Ativa",until:"até",addr_no_sub:"⚠ Sem assinatura",no_sub_hint:"Apenas 🎵🎬.",info_banner:"Sem assinatura prévias ocultas.",empty_folder:"Vazio.",tab_all:"📂 Todos",tab_audio:"🎵 Áudio",tab_video:"🎬 Vídeo",tab_image:"🖼 Imagens",tab_doc:"📄 Docs",tab_arch:"📦 Arquivos",tab_other:"📦 Outros",mode_radio:"🎵 Rádio",mode_cinema:"🎬 Cinema",mode_reshuffle:"🎲 Embaralhar",count_tracks:"({n} faixas)",count_videos:"({n} vídeos)",search_ph:"Buscar...",share_btn:"🔗 Compartilhar",counter_of:"{v} de {total}",nothing_found:"😕 Nada",btn_playlist:"▶ Lista",btn_continuous:"▶ Seguido",btn_open:"Abrir",btn_download:"⬇ Baixar",share_title:"🔗 Compartilhar",share_desc:"Formato:",share_ready:"🔗 Link",share_radio_title:"Rádio",share_cinema_title:"Cinema",share_tracks:"{n}",share_videos:"{n}",share_playing:"{type} · «{file}» · {n}",type_radio:"🎵",type_cinema:"🎬",close:"Fechar",back:"← Voltar",copy:"📋",share_system:"📤",link_copied:"Copiado",no_media:"Nada",pay_title:"💎 Pagar",pay_intro:"Envie, depois Verificar.",pay_your_code:"⚠️ Código em Comentário:",pay_amount:"Valor",pay_recipient:"Destinatário",pay_attachment:"Attachment",pay_duration:"Duração",pay_days:"{n} dias",pay_warn:"⚠️ Sem código sem vínculo.",pay_via_keeper:"🦊 Keeper",pay_check:"🔄 Verificar",copy_short:"copiar",dbg_title:"⚠ Erro",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ Enviada…",tx_checking:"⏳ Verif…",tx_not_found_3min:"⚠ Não encontrada.",tx_not_found:"⚠ Ainda não.",keeper_404:"Keeper ausente. Abrir?",err_keeper:"Erro"},
ar:{items:"عنصر",loading:"جار…",login_keeper:"🦊 Keeper",pay_sub:"💳 اشترك",logout:"🚪 خروج",sub_active:"فعال",until:"حتى",addr_no_sub:"⚠ لا اشتراك",no_sub_hint:"🎵🎬 فقط.",info_banner:"بدون اشتراك مخفية.",empty_folder:"فارغ.",tab_all:"📂 الكل",tab_audio:"🎵 صوت",tab_video:"🎬 فيديو",tab_image:"🖼 صور",tab_doc:"📄 وثائق",tab_arch:"📦 أرشيف",tab_other:"📦 أخرى",mode_radio:"🎵 راديو",mode_cinema:"🎬 سينما",mode_reshuffle:"🎲 خلط",count_tracks:"({n})",count_videos:"({n})",search_ph:"بحث...",share_btn:"🔗 مشاركة",counter_of:"{v}/{total}",nothing_found:"😕 لا",btn_playlist:"▶ قائمة",btn_continuous:"▶ متتالي",btn_open:"فتح",btn_download:"⬇",share_title:"🔗 مشاركة",share_desc:"تنسيق:",share_ready:"🔗 رابط",share_radio_title:"راديو",share_cinema_title:"سينما",share_tracks:"{n}",share_videos:"{n}",share_playing:"{type} · «{file}» · {n}",type_radio:"🎵",type_cinema:"🎬",close:"إغلاق",back:"→ رجوع",copy:"📋",share_system:"📤",link_copied:"نُسخ",no_media:"لا",pay_title:"💎",pay_intro:"أرسل ثم تحقق.",pay_your_code:"⚠️ الرمز في تعليق:",pay_amount:"مبلغ",pay_recipient:"مستلم",pay_attachment:"Attachment",pay_duration:"مدة",pay_days:"{n}",pay_warn:"⚠️ الرمز مطلوب.",pay_via_keeper:"🦊",pay_check:"🔄",copy_short:"نسخ",dbg_title:"⚠ خطأ",dbg_copy:"📋",dbg_to_pay:"💳",tx_sent:"⏳ مرسلة…",tx_checking:"⏳…",tx_not_found_3min:"⚠ لم يعثر.",tx_not_found:"⚠ لم يعثر.",keeper_404:"Keeper مفقود. افتح?",err_keeper:"خطأ"}
};
const LANG_CODES={en:'EN',ru:'RU',zh:'中',es:'ES',fr:'FR',de:'DE',ja:'JA',ko:'KO',pt:'PT',ar:'AR'};
const RTL_LANGS=['ar'];
const CAT_ICONS={audio:'🎵',video:'🎬',image:'🖼',doc:'📄',arch:'📦',other:'📁'};

function currentLang(){return localStorage.getItem('lang')||PREFS.defaultLang||'en';}
function currentTheme(){return localStorage.getItem('theme')||PREFS.defaultTheme||'dark';}
function t(key,params){const lang=currentLang();let s=(I18N[lang]&&I18N[lang][key])||(I18N.en&&I18N.en[key])||key;if(params)for(const k in params)s=s.replace('{'+k+'}',params[k]);return s;}
function applyLang(){
  const lang=currentLang();
  document.documentElement.lang=lang;
  document.documentElement.dir=RTL_LANGS.includes(lang)?'rtl':'ltr';
  document.querySelectorAll('[data-i18n]').forEach(el=>{el.textContent=t(el.dataset.i18n);});
  document.querySelectorAll('[data-i18n-ph]').forEach(el=>{el.placeholder=t(el.dataset.i18nPh);});
  const label=document.getElementById('langBtnLabel');if(label)label.textContent=LANG_CODES[lang]||lang.toUpperCase();
  document.querySelectorAll('#langMenu button').forEach(b=>{b.classList.toggle('active',b.dataset.lang===lang);});
  if(typeof refreshDyn==='function')refreshDyn();
}
function applyTheme(){const th=currentTheme();document.documentElement.setAttribute('data-theme',th);const tg=document.getElementById('themeToggle');if(tg)tg.textContent=th==='dark'?'☀️':'🌙';}
function setLang(l){localStorage.setItem('lang',l);applyLang();}
function setTheme(th){localStorage.setItem('theme',th);applyTheme();}
(function(){const th=localStorage.getItem('theme')||PREFS.defaultTheme||'dark';document.documentElement.setAttribute('data-theme',th);})();

const escRe=s=>String(s).replace(/[.*+?^${}()|[\]\\]/g,'\\$&');
const escH=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

(function(){
let playlist=[],pIndex=0,pMode=null,pShuffle=false,pRepeat=true,pOrder=[];

applyLang();applyTheme();

document.getElementById('themeToggle').addEventListener('click',()=>setTheme(currentTheme()==='dark'?'light':'dark'));
const langBtn=document.getElementById('langBtn'),langMenu=document.getElementById('langMenu');
langBtn.addEventListener('click',e=>{e.stopPropagation();langMenu.classList.toggle('open');});
document.addEventListener('click',()=>langMenu.classList.remove('open'));
langMenu.querySelectorAll('button').forEach(b=>b.addEventListener('click',()=>{setLang(b.dataset.lang);langMenu.classList.remove('open');}));

function renderAuthStatic(){
  const el=document.getElementById('authStatus');if(!el)return;
  const state=el.dataset.state,addr=el.dataset.addr,ms=parseInt(el.dataset.expiresMs||'0',10);
  if(state==='active'){const d=new Date(ms).toLocaleDateString(currentLang());
    el.innerHTML='<span class="ok">✓ '+t('sub_active')+'</span> · <span class="addr">'+addr+'</span> · <span class="muted">'+t('until')+' '+d+'</span>';
  }else if(state==='no_sub'){el.innerHTML='<span class="bad">'+t('addr_no_sub')+'</span> · <span class="addr">'+addr+'</span>';}
  else{el.innerHTML='<span class="muted">'+t('no_sub_hint')+'</span>';}
}
renderAuthStatic();

const initialEl=document.getElementById('initialData');
const initialData=initialEl?JSON.parse(initialEl.textContent):{items:[],total:0,counts:{},pageSize:60,hasMore:false,maxShareList:300};
const STATE={tab:'all',search:'',page:1,items:initialData.items||[],total:initialData.total||0,counts:initialData.counts||{},pageSize:initialData.pageSize||60,hasMore:!!initialData.hasMore,loading:false,tok:0,maxShareList:initialData.maxShareList||300};
const plCache={audio:null,video:null};

async function apiList(tab,search,page){const p=new URLSearchParams({action:'list',tab,search,page});return (await fetch('?'+p)).json();}
async function apiPlaylist(cat,search=''){if(!search&&plCache[cat])return plCache[cat];const p=new URLSearchParams({action:'playlist',cat});if(search)p.set('search',search);const j=await (await fetch('?'+p)).json();if(!search)plCache[cat]=j.items;return j.items;}
async function apiNames(tab,search){const p=new URLSearchParams({action:'names',tab,search});return (await fetch('?'+p)).json();}

const grid=document.getElementById('grid'),loadingIndicator=document.getElementById('loadingIndicator'),noResults=document.getElementById('noResults'),counter=document.getElementById('counter'),searchInput=document.getElementById('search'),clearBtn=document.getElementById('clear');

function renderCard(item){
  const streamUrl='?stream='+encodeURIComponent(item.n);
  const openUrl='?open='+encodeURIComponent(item.n);
  const dlUrl='?download='+encodeURIComponent(item.n);
  const icon=CAT_ICONS[item.c]||'📁';

  let previewHtml='';
  if(IS_SUBSCRIBED){
    if(item.c==='image')previewHtml='<div class="preview"><img src="'+openUrl+'" loading="lazy" alt=""></div>';
    else if(item.c==='audio')previewHtml='<div class="preview"><audio controls preload="none" src="'+streamUrl+'"></audio></div>';
    else if(item.c==='video')previewHtml='<div class="preview"><video controls preload="none" src="'+streamUrl+'"></video></div>';
  }

  let actions='';
  if(item.c==='audio')actions+='<button class="btn play-from" data-play-audio="'+escH(item.n)+'">'+escH(t('btn_playlist'))+'</button>';
  else if(item.c==='video')actions+='<button class="btn play-from video" data-play-video="'+escH(item.n)+'">'+escH(t('btn_continuous'))+'</button>';
  if(IS_SUBSCRIBED){
    actions+='<a class="btn secondary" href="'+openUrl+'" target="_blank">'+escH(t('btn_open'))+'</a>';
    actions+='<a class="btn" href="'+dlUrl+'">'+escH(t('btn_download'))+'</a>';
  }

  let nameHtml=escH(item.n);
  if(STATE.search){const q=STATE.search.trim();if(q)nameHtml=escH(item.n).replace(new RegExp(escRe(q),'gi'),m=>'<mark>'+escH(m)+'</mark>');}

  const card=document.createElement('div');
  card.className='card';card.dataset.file=item.n;card.dataset.cat=item.c;
  card.innerHTML=previewHtml+
    '<span class="cat-icon">'+icon+'</span>'+
    '<div class="card-info">'+
      '<div class="name">'+nameHtml+'</div>'+
      '<div class="meta">'+escH(item.sh)+' · '+escH((item.e||'file').toUpperCase())+'</div>'+
    '</div>'+
    (actions?'<div class="row">'+actions+'</div>':'');
  return card;
}
function attachCardHandlers(){
  grid.querySelectorAll('[data-play-audio]').forEach(b=>{if(b._b)return;b._b=1;b.addEventListener('click',()=>onPlayClick('audio',b.dataset.playAudio));});
  grid.querySelectorAll('[data-play-video]').forEach(b=>{if(b._b)return;b._b=1;b.addEventListener('click',()=>onPlayClick('video',b.dataset.playVideo));});
}
function appendItems(items){const frag=document.createDocumentFragment();items.forEach(it=>frag.appendChild(renderCard(it)));grid.appendChild(frag);attachCardHandlers();if(playlist.length)highlight();}
function renderAll(items){grid.innerHTML='';appendItems(items);}
function updateCounter(){counter.textContent=t('counter_of',{v:STATE.items.length,total:STATE.total});noResults.style.display=(STATE.total===0)?'block':'none';}
function updateCountsBar(){document.querySelectorAll('[data-count]').forEach(el=>{el.textContent=STATE.counts[el.dataset.count]||0;});}
function updateModeButtons(){const a=STATE.counts.audio||0,v=STATE.counts.video||0;const rb=document.getElementById('radioBtn'),cb=document.getElementById('cinemaBtn');if(rb){rb.disabled=!a;document.getElementById('radioCount').textContent=a?t('count_tracks',{n:a}):'';}if(cb){cb.disabled=!v;document.getElementById('cinemaCount').textContent=v?t('count_videos',{n:v}):'';}}

renderAll(STATE.items);updateCounter();updateModeButtons();

async function reload(){
  const tok=++STATE.tok;STATE.loading=true;STATE.page=1;loadingIndicator.classList.add('active');
  try{const data=await apiList(STATE.tab,STATE.search,1);if(tok!==STATE.tok)return;
    STATE.items=data.items;STATE.total=data.total;STATE.hasMore=data.hasMore;STATE.counts=data.counts||STATE.counts;
    renderAll(data.items);updateCounter();updateCountsBar();updateModeButtons();
  }catch(e){console.error(e);}
  finally{if(tok===STATE.tok){STATE.loading=false;loadingIndicator.classList.remove('active');}}
}
async function loadMore(){
  if(STATE.loading||!STATE.hasMore)return;
  const tok=STATE.tok;STATE.loading=true;loadingIndicator.classList.add('active');
  try{const data=await apiList(STATE.tab,STATE.search,STATE.page+1);if(tok!==STATE.tok)return;
    STATE.page++;STATE.items=STATE.items.concat(data.items);STATE.hasMore=data.hasMore;
    appendItems(data.items);updateCounter();
  }catch(e){console.error(e);}
  finally{if(tok===STATE.tok){STATE.loading=false;loadingIndicator.classList.remove('active');}}
}

const sentinel=document.getElementById('scrollSentinel');
if(sentinel)new IntersectionObserver(en=>{if(en[0].isIntersecting)loadMore();},{rootMargin:'400px'}).observe(sentinel);

document.querySelectorAll('.tab').forEach(tab=>{
  tab.addEventListener('click',()=>{document.querySelectorAll('.tab').forEach(x=>x.classList.toggle('active',x===tab));STATE.tab=tab.dataset.tab;reload();});
});

let searchTimer;
if(searchInput){
  searchInput.addEventListener('input',()=>{clearTimeout(searchTimer);clearBtn.style.display=searchInput.value?'block':'none';searchTimer=setTimeout(()=>{STATE.search=searchInput.value.trim();reload();},300);});
  searchInput.addEventListener('keydown',e=>{if(e.key==='Escape'){searchInput.value='';STATE.search='';clearBtn.style.display='none';reload();}});
}
if(clearBtn)clearBtn.addEventListener('click',()=>{searchInput.value='';STATE.search='';clearBtn.style.display='none';searchInput.focus();reload();});

const reshuffleBtn=document.getElementById('reshuffleBtn');
if(reshuffleBtn)reshuffleBtn.addEventListener('click',async()=>{await fetch('?action=reshuffle');plCache.audio=null;plCache.video=null;reload();});

const audioEl=new Audio(),videoEl=document.getElementById('videoEl'),videoModal=document.getElementById('videoModal'),player=document.getElementById('player'),playerIcon=document.getElementById('playerIcon'),playerTitle=document.getElementById('playerTitle'),playerIdx=document.getElementById('playerIdx'),playerTime=document.getElementById('playerTime'),progress=document.getElementById('progress'),progressFill=document.getElementById('progressFill'),btnPlay=document.getElementById('btnPlay'),btnPrev=document.getElementById('btnPrev'),btnNext=document.getElementById('btnNext'),btnStop=document.getElementById('btnStop'),btnShuffle=document.getElementById('btnShuffle'),btnRepeat=document.getElementById('btnRepeat'),btnShare=document.getElementById('btnShare');
const currentEl=()=>pMode==='video'?videoEl:audioEl;
function shuffleArr(a){for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];}return a;}
function buildOrder(){pOrder=playlist.map((_,i)=>i);if(pShuffle)shuffleArr(pOrder);}
function fmt(s){if(!isFinite(s))return'0:00';s=Math.floor(s);return Math.floor(s/60)+':'+(s%60).toString().padStart(2,'0');}
function highlight(){document.querySelectorAll('.card').forEach(c=>c.classList.remove('playing','video-playing'));if(!playlist.length)return;const n=playlist[pOrder[pIndex]];document.querySelectorAll('.card[data-file="'+CSS.escape(n)+'"]').forEach(c=>{c.classList.add('playing');if(pMode==='video')c.classList.add('video-playing');});}
function loadTrack(i){pIndex=((i%pOrder.length)+pOrder.length)%pOrder.length;const f=playlist[pOrder[pIndex]],el=currentEl();el.src='?stream='+encodeURIComponent(f);el.play().catch(()=>{});playerTitle.textContent=f;playerIdx.textContent='['+(pIndex+1)+'/'+pOrder.length+']';playerIcon.textContent=pMode==='video'?'🎬':'🎵';highlight();document.title=(pMode==='video'?'▶ ':'♪ ')+f;}
function startPlayer(files,mode,fromFile){stopPlayer(false);playlist=files.slice();pMode=mode;buildOrder();let s=0;if(fromFile){const r=playlist.indexOf(fromFile);if(r!==-1){s=pOrder.indexOf(r);if(s<0)s=0;}}player.classList.add('active');if(pMode==='video')videoModal.classList.add('active');loadTrack(s);}
function stopPlayer(close=true){audioEl.pause();audioEl.removeAttribute('src');audioEl.load();videoEl.pause();videoEl.removeAttribute('src');videoEl.load();document.querySelectorAll('.card').forEach(c=>c.classList.remove('playing','video-playing'));if(close){player.classList.remove('active');videoModal.classList.remove('active');playlist=[];pMode=null;document.title=document.getElementById('siteTitle').textContent;}}
function pNext(){if(!playlist.length)return;if(pIndex+1>=pOrder.length){pRepeat?loadTrack(0):stopPlayer();}else loadTrack(pIndex+1);}
function pPrev(){if(!playlist.length)return;const e=currentEl();if(e.currentTime>3){e.currentTime=0;return;}loadTrack(pIndex-1);}
function togglePlay(){const e=currentEl();if(e.paused){e.play();btnPlay.textContent='⏸';}else{e.pause();btnPlay.textContent='▶';}}
[audioEl,videoEl].forEach(el=>{el.addEventListener('ended',pNext);el.addEventListener('play',()=>btnPlay.textContent='⏸');el.addEventListener('pause',()=>btnPlay.textContent='▶');el.addEventListener('timeupdate',()=>{if(el!==currentEl())return;progressFill.style.width=(el.duration?el.currentTime/el.duration*100:0)+'%';playerTime.textContent=fmt(el.currentTime)+' / '+fmt(el.duration);});el.addEventListener('error',()=>setTimeout(pNext,500));});
if(btnPlay)btnPlay.onclick=togglePlay;if(btnNext)btnNext.onclick=pNext;if(btnPrev)btnPrev.onclick=pPrev;if(btnStop)btnStop.onclick=()=>stopPlayer(true);
if(btnShuffle)btnShuffle.onclick=()=>{pShuffle=!pShuffle;btnShuffle.classList.toggle('active',pShuffle);if(playlist.length){const cf=playlist[pOrder[pIndex]];buildOrder();pIndex=pOrder.indexOf(playlist.indexOf(cf));if(pIndex<0)pIndex=0;playerIdx.textContent='['+(pIndex+1)+'/'+pOrder.length+']';}};
if(btnRepeat){btnRepeat.onclick=()=>{pRepeat=!pRepeat;btnRepeat.classList.toggle('active',pRepeat);};btnRepeat.classList.add('active');}
if(progress)progress.onclick=e=>{const el=currentEl();if(!el.duration)return;const r=progress.getBoundingClientRect();el.currentTime=(e.clientX-r.left)/r.width*el.duration;};
document.addEventListener('keydown',e=>{if(e.target.tagName==='INPUT')return;if(!playlist.length)return;if(e.code==='Space'){e.preventDefault();togglePlay();}else if(e.key==='ArrowRight')pNext();else if(e.key==='ArrowLeft')pPrev();else if(e.key==='Escape'&&pMode==='video')videoModal.classList.remove('active');});
if(videoModal)videoModal.addEventListener('click',e=>{if(e.target===videoModal)videoModal.classList.remove('active');});
async function onPlayClick(mode,fromFile){const files=await apiPlaylist(mode);if(!files.length)return;startPlayer(files,mode,fromFile);}
const radioBtn=document.getElementById('radioBtn'),cinemaBtn=document.getElementById('cinemaBtn');
if(radioBtn)radioBtn.addEventListener('click',()=>onPlayClick('audio'));
if(cinemaBtn)cinemaBtn.addEventListener('click',()=>onPlayClick('video'));
if('mediaSession' in navigator){const upd=()=>{if(!playlist.length)return;navigator.mediaSession.metadata=new MediaMetadata({title:playlist[pOrder[pIndex]],artist:pMode==='video'?t('type_cinema'):t('type_radio')});};[audioEl,videoEl].forEach(el=>el.addEventListener('play',upd));navigator.mediaSession.setActionHandler('play',togglePlay);navigator.mediaSession.setActionHandler('pause',togglePlay);navigator.mediaSession.setActionHandler('nexttrack',pNext);navigator.mediaSession.setActionHandler('previoustrack',pPrev);}

window.refreshDyn=function(){renderAuthStatic();updateCounter();updateModeButtons();const ps=document.getElementById('paySubDays');if(ps)ps.textContent=t('pay_days',{n:WAVES_CFG.days});if(STATE.items&&STATE.items.length)renderAll(STATE.items.slice());};

const shareModal=document.getElementById('shareModal'),shareTitle=document.getElementById('shareTitle'),shareDesc=document.getElementById('shareDesc'),shareOptions=document.getElementById('shareOptions'),shareLinkWrap=document.getElementById('shareLinkWrap'),shareHint=document.getElementById('shareHint'),shareInput=document.getElementById('shareInput'),shareCopyBtn=document.getElementById('shareCopyBtn'),shareNativeBtn=document.getElementById('shareNativeBtn'),shareBackBtn=document.getElementById('shareBackBtn'),toast=document.getElementById('toast');
function showToast(text){if(!toast)return;toast.textContent=text;toast.classList.add('show');clearTimeout(toast._t);toast._t=setTimeout(()=>toast.classList.remove('show'),2000);}
function buildShareUrl(mode,files,fromFile,search){
  const base=location.origin+location.pathname,params=new URLSearchParams();
  params.set('play',mode);
  const totalOfType=STATE.counts[mode]||0;
  if(search)params.set('search',search);
  if(files&&files.length){const isFull=(files.length===totalOfType)&&!search;if(!isFull&&files.length<=STATE.maxShareList)params.set('list',files.join('|'));}
  if(fromFile)params.set('from',fromFile);
  return base+'#'+params.toString();
}
function parseShareParams(){const hash=location.hash.replace(/^#/,'');if(!hash)return null;const p=new URLSearchParams(hash),play=p.get('play');if(play!=='audio'&&play!=='video')return null;return{mode:play,files:p.get('list')?p.get('list').split('|').filter(Boolean):null,from:p.get('from')||null,search:p.get('search')||''};}
async function autoStartFromUrl(){const params=parseShareParams();if(!params)return;let files;if(params.files)files=params.files;else files=await apiPlaylist(params.mode,params.search);if(!files||!files.length){showToast(t('no_media'));return;}setTimeout(()=>{try{startPlayer(files,params.mode,params.from);}catch(e){console.error(e);}},150);}
function showShareLink(url,hint,showBack){if(!shareModal)return;shareTitle.textContent=t('share_ready');shareDesc.style.display='none';shareOptions.style.display='none';shareLinkWrap.style.display='block';shareHint.textContent=hint||'';shareInput.value=url;shareBackBtn.style.display=showBack?'':'none';shareNativeBtn.style.display=navigator.share?'':'none';shareModal.classList.add('active');setTimeout(()=>shareInput.select(),50);}
function showShareOptions(a,v){if(!shareModal)return;shareTitle.textContent=t('share_title');shareDesc.textContent=t('share_desc');shareDesc.style.display='block';shareLinkWrap.style.display='none';shareOptions.style.display='flex';shareOptions.innerHTML='';
  const add=(mode,files,icon,tk,sk)=>{const el=document.createElement('div');el.className='share-option';el.innerHTML='<span class="icon">'+icon+'</span><div class="text"><div class="title">'+escH(t(tk))+'</div><div class="sub">'+escH(t(sk,{n:files.length}))+'</div></div>';el.addEventListener('click',()=>{const url=buildShareUrl(mode,files,null,STATE.search);showShareLink(url,t(sk,{n:files.length}),true);});shareOptions.appendChild(el);};
  if(a.length)add('audio',a,'🎵','share_radio_title','share_tracks');
  if(v.length)add('video',v,'🎬','share_cinema_title','share_videos');
  shareModal.classList.add('active');
}
async function shareCurrentView(){const j=await apiNames(STATE.tab,STATE.search);if(!j.audio.length&&!j.video.length){showToast(t('no_media'));return;}if(j.audio.length&&j.video.length)showShareOptions(j.audio,j.video);else if(j.audio.length){showShareLink(buildShareUrl('audio',j.audio,null,STATE.search),t('share_tracks',{n:j.audio.length}),false);}else{showShareLink(buildShareUrl('video',j.video,null,STATE.search),t('share_videos',{n:j.video.length}),false);}}
function shareCurrentPlayback(){if(!playlist.length)return;const cf=playlist[pOrder[pIndex]],url=buildShareUrl(pMode,playlist,cf,'');showShareLink(url,t('share_playing',{type:pMode==='video'?t('type_cinema'):t('type_radio'),file:cf,n:playlist.length}),false);}
if(shareBackBtn)shareBackBtn.addEventListener('click',()=>{shareLinkWrap.style.display='none';shareOptions.style.display='flex';shareDesc.style.display='block';shareTitle.textContent=t('share_title');});
if(shareCopyBtn)shareCopyBtn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(shareInput.value);shareCopyBtn.textContent='✓';showToast(t('link_copied'));setTimeout(()=>shareCopyBtn.textContent=t('copy'),1500);}catch{shareInput.select();document.execCommand&&document.execCommand('copy');showToast(t('link_copied'));}});
if(shareNativeBtn)shareNativeBtn.addEventListener('click',async()=>{if(navigator.share){try{await navigator.share({title:document.getElementById('siteTitle').textContent,url:shareInput.value});}catch(e){}}});
const shareViewBtn=document.getElementById('shareViewBtn');
if(shareViewBtn)shareViewBtn.addEventListener('click',shareCurrentView);
if(btnShare)btnShare.addEventListener('click',shareCurrentPlayback);
autoStartFromUrl();
window.addEventListener('hashchange',()=>{if(parseShareParams())autoStartFromUrl();});

const connectKeeperBtn=document.getElementById('connectKeeperBtn'),payBtn=document.getElementById('payBtn'),disconnectBtn=document.getElementById('disconnectBtn'),payModal=document.getElementById('payModal'),debugModal=document.getElementById('debugModal'),keeperPayBtn=document.getElementById('keeperPayBtn'),openExchangeBtn=document.getElementById('openExchangeBtn'),checkPaymentBtn=document.getElementById('checkPaymentBtn'),nonceDisplay=document.getElementById('nonceDisplay'),nonceCopyRow=document.getElementById('nonceCopyRow'),nonceCopyBtn=document.getElementById('nonceCopyBtn'),debugText=document.getElementById('debugText'),debugPre=document.getElementById('debugPre'),copyDebugBtn=document.getElementById('copyDebugBtn'),goToPayBtn=document.getElementById('goToPayBtn');
document.querySelectorAll('[data-close-modal]').forEach(b=>b.addEventListener('click',()=>{const m=document.getElementById(b.getAttribute('data-close-modal'));if(m)m.classList.remove('active');}));
[payModal,debugModal,shareModal].forEach(m=>m&&m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('active');}));
function setupCopy(btn,text){btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(text);const old=btn.textContent;btn.textContent='✓';btn.classList.add('copied');setTimeout(()=>{btn.textContent=old;btn.classList.remove('copied');},1200);}catch{}});}
document.querySelectorAll('.copy-btn[data-copy]').forEach(b=>setupCopy(b,b.dataset.copy));
function showDebug(error,debugObj){if(!debugModal)return;debugText.textContent=error||'Error';debugPre.textContent=debugObj?JSON.stringify(debugObj,null,2):'(no data)';debugModal.classList.add('active');}
if(copyDebugBtn)copyDebugBtn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(debugPre.textContent);copyDebugBtn.textContent='✓';setTimeout(()=>copyDebugBtn.textContent=t('dbg_copy'),1500);}catch{}});
if(goToPayBtn)goToPayBtn.addEventListener('click',()=>openPayModal());
function getKeeper(){return window.KeeperWallet||window.WavesKeeper||null;}
async function waitKeeper(ms=5000){if(getKeeper())return getKeeper();return new Promise(resolve=>{let done=false;const finish=v=>{if(done)return;done=true;cleanup();resolve(v);};const onInit=()=>finish(getKeeper());window.addEventListener('WavesKeeperInitialized',onInit,{once:true});window.addEventListener('KeeperWalletInitialized',onInit,{once:true});const iv=setInterval(()=>{if(getKeeper())finish(getKeeper());},150);const to=setTimeout(()=>finish(null),ms);function cleanup(){clearInterval(iv);clearTimeout(to);window.removeEventListener('WavesKeeperInitialized',onInit);window.removeEventListener('KeeperWalletInitialized',onInit);}});}
function short(a){return a?a.slice(0,8)+'…'+a.slice(-6):'';}
function renderAuth(state){const el=document.getElementById('authStatus');if(state.subscribed&&state.address){el.dataset.state='active';el.dataset.addr=short(state.address);el.dataset.expiresMs=state.expires;if(connectKeeperBtn)connectKeeperBtn.style.display='none';if(payBtn)payBtn.style.display='none';if(disconnectBtn)disconnectBtn.style.display='';IS_SUBSCRIBED=true;renderAuthStatic();setTimeout(()=>location.reload(),600);}else if(state.address){el.dataset.state='no_sub';el.dataset.addr=short(state.address);el.dataset.expiresMs='0';if(connectKeeperBtn)connectKeeperBtn.style.display='';if(payBtn)payBtn.style.display='';if(disconnectBtn)disconnectBtn.style.display='';IS_SUBSCRIBED=false;renderAuthStatic();}else{el.dataset.state='none';el.dataset.addr='';el.dataset.expiresMs='0';if(connectKeeperBtn)connectKeeperBtn.style.display='';if(payBtn)payBtn.style.display='';if(disconnectBtn)disconnectBtn.style.display='none';IS_SUBSCRIBED=false;renderAuthStatic();}}
async function fetchNonce(){const r=await fetch('?action=nonce');const j=await r.json();SESSION_NONCE=j.nonce;if(nonceDisplay)nonceDisplay.textContent=SESSION_NONCE;if(nonceCopyRow)nonceCopyRow.textContent=SESSION_NONCE;return SESSION_NONCE;}
if(nonceCopyBtn)nonceCopyBtn.addEventListener('click',async()=>{if(!SESSION_NONCE)await fetchNonce();try{await navigator.clipboard.writeText(SESSION_NONCE);nonceCopyBtn.textContent='✓';setTimeout(()=>nonceCopyBtn.textContent=t('copy_short'),1200);}catch{}});
function strToBase64(s){const b=new TextEncoder().encode(s);let x='';for(let i=0;i<b.length;i++)x+=String.fromCharCode(b[i]);return btoa(x);}
async function connectViaKeeper(){const kp=await waitKeeper();if(!kp){if(confirm(t('keeper_404')))openPayModal();return;}try{await kp.publicState().catch(()=>{});await fetchNonce();const binaryB64='base64:'+strToBase64(SESSION_NONCE);const signed=await kp.signCustomData({version:1,binary:binaryB64});const state=await kp.publicState();const address=state.account&&state.account.address;if(!address)throw new Error('No address');const fd=new FormData();fd.append('address',address);fd.append('publicKey',signed.publicKey);fd.append('signature',signed.signature);const r=await fetch('?action=keeperAuth',{method:'POST',body:fd});const j=await r.json();if(!j.ok){showDebug(j.error||'Error',j.debug);return;}renderAuth(j);if(!j.subscribed)openPayModal();}catch(e){console.error(e);const msg=String(e.message||e);if(/rejected|denied|canceled/i.test(msg))return;showDebug(t('err_keeper'),{error:msg,stack:e.stack});}}
async function openPayModal(){if(!payModal)return;await fetchNonce();if(debugModal)debugModal.classList.remove('active');payModal.classList.add('active');const ps=document.getElementById('paySubDays');if(ps)ps.textContent=t('pay_days',{n:WAVES_CFG.days});const qrWrap=document.getElementById('qrWrap');if(qrWrap){qrWrap.innerHTML='';const img=new Image();img.src='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='+encodeURIComponent(WAVES_CFG.systemAddress);img.alt='QR';img.onerror=()=>{qrWrap.style.display='none';};qrWrap.appendChild(img);}if(keeperPayBtn){keeperPayBtn.style.display='none';waitKeeper(3000).then(kp=>{if(kp)keeperPayBtn.style.display='';});}}
async function keeperPay(){const kp=await waitKeeper();if(!kp)return showDebug(t('err_keeper'),{error:'not found'});if(!SESSION_NONCE)await fetchNonce();try{await kp.signAndPublishTransaction({type:4,data:{amount:{assetId:WAVES_CFG.assetId==='WAVES'?null:WAVES_CFG.assetId,tokens:String(WAVES_CFG.minAmount)},fee:{assetId:'WAVES',tokens:'0.001'},recipient:WAVES_CFG.systemAddress,attachment:SESSION_NONCE}});document.getElementById('authStatus').innerHTML='<span class="muted">'+t('tx_sent')+'</span>';pollPayment();}catch(e){if(!/rejected|denied|canceled/i.test(String(e.message||e)))showDebug(t('err_keeper'),{error:String(e.message||e)});}}
let pollTimer=null;
async function pollPayment(){if(pollTimer)clearInterval(pollTimer);let tries=0;pollTimer=setInterval(async()=>{tries++;try{const j=await fetch('?action=checkPayment').then(r=>r.json());if(j&&j.subscribed){clearInterval(pollTimer);pollTimer=null;if(payModal)payModal.classList.remove('active');renderAuth(j);}else if(tries>=36){clearInterval(pollTimer);pollTimer=null;document.getElementById('authStatus').innerHTML='<span class="bad">'+t('tx_not_found_3min')+'</span>';}}catch{}},5000);}
async function manualCheck(){document.getElementById('authStatus').innerHTML='<span class="muted">'+t('tx_checking')+'</span>';try{const j=await fetch('?action=checkPayment').then(r=>r.json());if(j.subscribed){if(payModal)payModal.classList.remove('active');renderAuth(j);}else document.getElementById('authStatus').innerHTML='<span class="bad">'+t('tx_not_found')+'</span>';}catch(e){}}
async function disconnect(){await fetch('?action=logout');if(pollTimer){clearInterval(pollTimer);pollTimer=null;}SESSION_NONCE=null;setTimeout(()=>location.reload(),200);}
if(connectKeeperBtn)connectKeeperBtn.addEventListener('click',connectViaKeeper);
if(payBtn)payBtn.addEventListener('click',openPayModal);
if(keeperPayBtn)keeperPayBtn.addEventListener('click',keeperPay);
if(checkPaymentBtn)checkPaymentBtn.addEventListener('click',manualCheck);
if(disconnectBtn)disconnectBtn.addEventListener('click',disconnect);
if(openExchangeBtn)openExchangeBtn.addEventListener('click',()=>window.open('https://wx.network/wallet/assets','_blank'));
})();
</script>
</body>
</html>
