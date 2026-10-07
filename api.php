<?php
// 1) Set admin password hash. Generate with:  php -r "echo password_hash('YOUR-LONG-PASSWORD', PASSWORD_DEFAULT);"
const PASS_HASH = '';
const FILE = __DIR__ . '/content.json';
header('Content-Type: application/json; charset=utf-8');
$a = $_GET['action'] ?? '';

if ($a === 'save') {
    $in = json_decode(file_get_contents('php://input'), true);
    if (!PASS_HASH || !password_verify($in['pass'] ?? '', PASS_HASH) || !is_array($in['content'] ?? null)) {
        usleep(800000); http_response_code(403); exit('{"ok":false}');
    }
    file_put_contents(FILE, json_encode($in['content'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    exit('{"ok":true}');
}

if ($a === 'stats') {
    // For every trader with a "feed" URL (set in admin) fetch stats server-side, cache 15 min.
    $c = is_file(FILE) ? json_decode(file_get_contents(FILE), true) : [];
    $out = [];
    foreach (($c['traders'] ?? []) as $t) {
        $u = $t['feed'] ?? '';
        if (strpos($u, 'https://') !== 0) { $out[] = null; continue; }
        $cache = sys_get_temp_dir() . '/ultima_' . md5($u) . '.json';
        if (is_file($cache) && time() - filemtime($cache) < 900) { $out[] = json_decode(file_get_contents($cache), true); continue; }
        $raw = @file_get_contents($u, false, stream_context_create(['http' => ['timeout' => 6]]));
        $d = $raw ? adapt(json_decode($raw, true)) : null;
        if ($d) file_put_contents($cache, json_encode($d));
        $out[] = $d;
    }
    exit(json_encode($out));
}

/* Map the Ultima/UMSOCIAL response to the format the site expects:
   {"1M":{"ret":4.2,"dd":1.8,"win":64,"pf":1.6,"trades":120,"series":[0,1.1,...]}, "3M":{...}, "6M":{...}, "1Y":{...}, "ALL":{...}, "monthly":[1.2,-0.4,...]}
   Currently passes data through unchanged: adjust once the real Ultima endpoint/export format is known. */
function adapt($raw) { return is_array($raw) ? $raw : null; }

http_response_code(404); echo '{}';
