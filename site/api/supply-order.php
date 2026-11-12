<?php
/**
 * SMS Medical website: supply order mailer.
 * Receives the supply order form (JSON) and emails it to client services via Resend.
 * The Resend API key lives OUTSIDE the website folder in /home/<user>/.resend_key (one line, the key only).
 * Supply orders contain facility/contact info only, no patient information.
 */

// ---- settings -------------------------------------------------------------
$TO        = ['info@smsmedical.net'];                       // who receives supply orders
$FROM      = 'SMS Medical Website <admin@smsmedical.net>';  // must be on the Resend-verified domain
$ALLOWED   = ['https://smsmedical.net', 'https://www.smsmedical.net', 'https://new.smsmedical.net', 'https://smsmedlab.com', 'https://www.smsmedlab.com'];
$KEY_FILE  = dirname(__DIR__, 2) . '/.resend_key';
$MAX_PER_HOUR = 10;                                         // per visitor IP
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }
function clean($s, $n = 200) {
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string)$s);
    $s = trim($s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
}
function h($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Method not allowed']);

$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin !== '' && !in_array($origin, $ALLOWED, true)) out(403, ['ok' => false, 'error' => 'Not allowed']);

$raw = file_get_contents('php://input', false, null, 0, 20000);
$d = json_decode($raw, true);
if (!is_array($d)) out(400, ['ok' => false, 'error' => 'Bad request']);

// honeypot: real people never fill this hidden field
if (!empty($d['website'])) out(200, ['ok' => true]);

// simple per-IP rate limit
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0';
$rlFile = sys_get_temp_dir() . '/sms_supply_' . md5($ip);
$now = time();
$hits = json_decode((string)@file_get_contents($rlFile), true);
$hits = is_array($hits) ? array_values(array_filter($hits, function ($t) use ($now) { return $t > $now - 3600; })) : [];
if (count($hits) >= $MAX_PER_HOUR) out(429, ['ok' => false, 'error' => 'Too many orders from this connection. Please call (708) 424-9405.']);

$fac   = clean(isset($d['facility']) ? $d['facility'] : '');
$name  = clean(isset($d['name']) ? $d['name'] : '');
$phone = clean(isset($d['phone']) ? $d['phone'] : '', 40);
$email = clean(isset($d['email']) ? $d['email'] : '', 120);
$acct  = clean(isset($d['account']) ? $d['account'] : '', 60);
$date  = clean(isset($d['needed_by']) ? $d['needed_by'] : '', 20);
$notes = clean(isset($d['notes']) ? $d['notes'] : '', 500);
$other = clean(isset($d['other']) ? $d['other'] : '', 500);
$items = [];
foreach ((isset($d['items']) && is_array($d['items']) ? $d['items'] : []) as $it) {
    $n = clean(isset($it['name']) ? $it['name'] : '', 80);
    $q = isset($it['qty']) ? (int)$it['qty'] : 0;
    if ($n !== '' && $q > 0 && $q <= 10000) $items[] = [$n, $q];
    if (count($items) >= 20) break;
}
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';

if (!$items && $other === '')                      out(422, ['ok' => false, 'error' => 'Add at least one item.']);
if ($fac === '')                                   out(422, ['ok' => false, 'error' => 'Enter your facility or practice name.']);
if ($name === '')                                  out(422, ['ok' => false, 'error' => 'Enter a contact name.']);
if (strlen(preg_replace('/\D/', '', $phone)) < 10) out(422, ['ok' => false, 'error' => 'Enter a phone number we can call.']);

$key = trim((string)@file_get_contents($KEY_FILE));
if ($key === '') { error_log('supply-order: Resend key missing at ' . $KEY_FILE); out(503, ['ok' => false, 'error' => 'Online ordering is not available right now. Please call (708) 424-9405.']); }

$dash = function ($v) { return $v === '' ? '—' : $v; };
$lines = [
    'New supply order from the website', '',
    'Facility: ' . $fac, 'Account #: ' . $dash($acct), 'Contact: ' . $name, 'Phone: ' . $phone,
    'Email: ' . $dash($email), 'Needed by: ' . $dash($date), 'Delivery notes: ' . $dash($notes), '', 'Items:',
];
foreach ($items as $it) $lines[] = '- ' . $it[0] . ': ' . $it[1];
if ($other !== '') $lines[] = '- Other: ' . $other;
$text = implode("\n", $lines);

$rows = '';
foreach ($items as $it) $rows .= '<tr><td style="padding:8px 12px;border-top:1px solid #e5eaf1">' . h($it[0]) . '</td><td style="padding:8px 12px;border-top:1px solid #e5eaf1;text-align:right;font-weight:700">' . (int)$it[1] . '</td></tr>';
if ($other !== '') $rows .= '<tr><td colspan="2" style="padding:8px 12px;border-top:1px solid #e5eaf1"><b>Other:</b> ' . h($other) . '</td></tr>';
$kv = '';
foreach ([['Facility', $fac], ['Account #', $dash($acct)], ['Contact', $name], ['Phone', $phone], ['Email', $dash($email)], ['Needed by', $dash($date)], ['Delivery notes', $dash($notes)]] as $p)
    $kv .= '<tr><td style="padding:6px 12px;color:#64748b;width:140px">' . h($p[0]) . '</td><td style="padding:6px 12px;color:#0f172a">' . h($p[1]) . '</td></tr>';
$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px;color:#0f172a">'
      . '<h2 style="margin:0 0 4px;color:#1b2a4a">New supply order</h2><p style="margin:0 0 16px;color:#64748b">Submitted on the SMS Medical website</p>'
      . '<table style="border-collapse:collapse;width:100%;margin-bottom:18px">' . $kv . '</table>'
      . '<table style="border-collapse:collapse;width:100%;border:1px solid #e5eaf1"><tr style="background:#f4f7fb"><th style="text-align:left;padding:8px 12px">Item</th><th style="text-align:right;padding:8px 12px">Qty</th></tr>' . $rows . '</table></div>';

$payload = ['from' => $FROM, 'to' => $TO, 'subject' => 'Supply order: ' . $fac, 'text' => $text, 'html' => $html];
if ($email !== '') $payload['reply_to'] = $email;

$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($resp === false || $code >= 400) {
    error_log('supply-order: Resend failed (' . $code . ') ' . ($cerr ? $cerr : substr((string)$resp, 0, 300)));
    out(502, ['ok' => false, 'error' => 'We could not send your order. Please call (708) 424-9405.']);
}
$hits[] = $now; @file_put_contents($rlFile, json_encode($hits));
out(200, ['ok' => true]);
