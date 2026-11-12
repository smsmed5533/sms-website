<?php
/**
 * SMS Medical website: account-request and general-inquiry mailer.
 * Receives the website forms (JSON) and emails them via Resend:
 *   account (Open an account), inquiry (Send us a message), pickup (courier pickup request),
 *   application (careers, with an optional PDF/Word resume attached; nothing is stored on this server).
 * Uses the same Resend key as supply-order.php: /home/<user>/.resend_key (outside the website folder).
 * The forms ask people not to send health information; nothing is stored on this server.
 */

// ---- settings -------------------------------------------------------------
$TO        = ['info@smsmedlab.com'];                        // who receives these messages
$FROM      = 'SMS Medical Website <admin@smsmedical.net>';  // must be on the Resend-verified domain
$ALLOWED   = ['https://smsmedical.net', 'https://www.smsmedical.net', 'https://new.smsmedical.net', 'https://smsmedlab.com', 'https://www.smsmedlab.com'];
$KEY_FILE  = dirname(__DIR__, 2) . '/.resend_key';
$MAX_PER_HOUR = 6;                                          // per visitor IP
$PHONE     = '(708) 424-9405';
// ---------------------------------------------------------------------------

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }
function clean($s, $n = 200) {
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', (string)$s);   // keep newlines (\x0A) for messages
    $s = trim($s);
    return function_exists('mb_substr') ? mb_substr($s, 0, $n) : substr($s, 0, $n);
}
function one($s, $n = 200) { return clean(str_replace(["\r", "\n"], ' ', (string)$s), $n); }
function h($s) { return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8')); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(405, ['ok' => false, 'error' => 'Method not allowed']);
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if ($origin !== '' && !in_array($origin, $ALLOWED, true)) out(403, ['ok' => false, 'error' => 'Not allowed']);

$d = json_decode(file_get_contents('php://input', false, null, 0, 6 * 1024 * 1024), true);
if (!is_array($d)) out(400, ['ok' => false, 'error' => 'Bad request']);
if (!empty($d['website'])) out(200, ['ok' => true]);                 // honeypot

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0';
$rlFile = sys_get_temp_dir() . '/sms_inquiry_' . md5($ip);
$now = time();
$hits = json_decode((string)@file_get_contents($rlFile), true);
$hits = is_array($hits) ? array_values(array_filter($hits, function ($t) use ($now) { return $t > $now - 3600; })) : [];
if (count($hits) >= $MAX_PER_HOUR) out(429, ['ok' => false, 'error' => "Too many messages from this connection. Please call $PHONE."]);

$g = function ($k, $n = 200) use ($d) { return one(isset($d[$k]) ? $d[$k] : '', $n); };
$kind  = $g('kind', 20); if (!in_array($kind, ['account', 'inquiry', 'pickup', 'application'], true)) $kind = 'inquiry';
$name  = $g('name');
$email = $g('email', 120);
$phone = $g('phone', 40);
$msg   = clean(isset($d['message']) ? $d['message'] : '', 3000);
if ($name === '')                                  out(422, ['ok' => false, 'error' => 'Enter your name.']);
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $email = 'INVALID';
if ($kind !== 'pickup' && ($email === '' || $email === 'INVALID')) out(422, ['ok' => false, 'error' => 'Enter a valid email address.']);
if ($email === 'INVALID') out(422, ['ok' => false, 'error' => 'Enter a valid email address.']);
$attach = null;

if ($kind === 'account') {
    $practice = $g('practice'); $type = $g('facility_type', 80);
    $services = [];
    foreach ((isset($d['services']) && is_array($d['services']) ? $d['services'] : []) as $sv) { $sv = one($sv, 60); if ($sv !== '') $services[] = $sv; if (count($services) >= 8) break; }
    if ($practice === '')                              out(422, ['ok' => false, 'error' => 'Enter your practice or facility name.']);
    if ($type === '')                                  out(422, ['ok' => false, 'error' => 'Choose your type of practice or facility.']);
    if ($g('city') === '' || $g('zip', 12) === '')     out(422, ['ok' => false, 'error' => 'Enter your city and ZIP code.']);
    if (strlen(preg_replace('/\D/', '', $phone)) < 10) out(422, ['ok' => false, 'error' => 'Enter a phone number we can call.']);
    $rows = [
        ['Practice / facility', $practice], ['Type', $type], ['Services', $services ? implode(', ', $services) : '—'],
        ['Address', trim($g('address') . ', ' . $g('city') . ' ' . $g('zip', 12), ', ')],
        ['Contact', $name], ['Title', $g('title') ?: '—'], ['Phone', $phone], ['Email', $email],
        ['NPI', $g('npi', 20) ?: '—'], ['EHR system', $g('ehr', 80) ?: '—'], ['Specimens / month', $g('volume', 40) ?: '—'],
        ['Notes', $msg !== '' ? $msg : '—'],
    ];
    $subject = 'New account request: ' . $practice;
    $title = 'New account request';
} elseif ($kind === 'pickup') {
    $practice = $g('practice');
    if ($practice === '')                              out(422, ['ok' => false, 'error' => 'Enter your practice or facility name.']);
    if (strlen(preg_replace('/\D/', '', $phone)) < 10) out(422, ['ok' => false, 'error' => 'Enter a phone number we can call.']);
    if ($g('address') === '')                          out(422, ['ok' => false, 'error' => 'Enter the pickup address.']);
    if ($g('date', 20) === '')                         out(422, ['ok' => false, 'error' => 'Choose a pickup date.']);
    $rows = [
        ['Practice / facility', $practice], ['Account #', $g('account', 60) ?: '—'], ['Contact', $name], ['Phone', $phone], ['Email', $email ?: '—'],
        ['Pickup address', $g('address')], ['Where to find specimens', $g('location') ?: '—'], ['Pickup date', $g('date', 20)], ['Ready by', $g('ready', 20) ?: '—'],
        ['Specimens / bags', $g('count', 20) ?: '—'], ['Storage', $g('storage', 40) ?: '—'], ['Notes', $msg !== '' ? $msg : '—'],
    ];
    $subject = 'Courier pickup request: ' . $practice . ' (' . $g('date', 20) . ')';
    $title = 'Courier pickup request';
} elseif ($kind === 'application') {
    $pos = $g('position', 80);
    if ($pos === '')                                   out(422, ['ok' => false, 'error' => 'Choose a position.']);
    if (strlen(preg_replace('/\D/', '', $phone)) < 10) out(422, ['ok' => false, 'error' => 'Enter a phone number we can call.']);
    if (isset($d['resume']) && is_array($d['resume']) && !empty($d['resume']['data'])) {
        $fn = preg_replace('/[^A-Za-z0-9._ -]/', '_', one(isset($d['resume']['name']) ? $d['resume']['name'] : 'resume', 120));
        $bin = base64_decode((string)$d['resume']['data'], true);
        if ($bin === false || strlen($bin) > 4 * 1024 * 1024 || !preg_match('/\.(pdf|docx?)$/i', $fn))
            out(422, ['ok' => false, 'error' => 'Please attach a PDF or Word file up to 4 MB.']);
        $magic = substr($bin, 0, 4);
        if (!in_array($magic, ["%PDF", "PK\x03\x04", "\xD0\xCF\x11\xE0"], true))
            out(422, ['ok' => false, 'error' => 'That file doesn\'t look like a PDF or Word document.']);
        $attach = ['filename' => $fn, 'content' => base64_encode($bin)];
    }
    $rows = [
        ['Position', $pos], ['Name', $name], ['Email', $email], ['Phone', $phone], ['City', $g('city') ?: '—'],
        ['Availability', $g('availability', 40) ?: '—'], ['Certifications / licenses', $g('credentials') ?: '—'],
        ['Resume', $attach ? $attach['filename'] . ' (attached)' : 'Not attached'], ['Experience', $msg !== '' ? $msg : '—'],
    ];
    $subject = 'Job application: ' . $pos . ' – ' . $name;
    $title = 'New job application';
} else {
    $role = $g('role', 60);
    if ($role === '') out(422, ['ok' => false, 'error' => 'Tell us who you are.']);
    if ($msg === '')  out(422, ['ok' => false, 'error' => 'Enter a message.']);
    $rows = [['Name', $name], ['Email', $email], ['Phone', $phone !== '' ? $phone : '—'], ['I am a', $role], ['Message', $msg]];
    $subject = 'Website message from ' . $name . ' (' . $role . ')';
    $title = 'New website message';
}

$key = trim((string)@file_get_contents($KEY_FILE));
if ($key === '') { error_log('inquiry: Resend key missing at ' . $KEY_FILE); out(503, ['ok' => false, 'error' => "This form isn't available right now. Please call $PHONE."]); }

$text = $title . "\n\n"; $kv = '';
foreach ($rows as $r) {
    $text .= $r[0] . ': ' . $r[1] . "\n";
    $kv .= '<tr><td style="padding:7px 12px;color:#64748b;width:160px;vertical-align:top">' . h($r[0]) . '</td><td style="padding:7px 12px;color:#0f172a">' . h($r[1]) . '</td></tr>';
}
$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:640px;color:#0f172a">'
      . '<h2 style="margin:0 0 4px;color:#1b2a4a">' . h($title) . '</h2><p style="margin:0 0 16px;color:#64748b">Submitted on the SMS Medical website</p>'
      . '<table style="border-collapse:collapse;width:100%;border:1px solid #e5eaf1">' . $kv . '</table></div>';

$payload = ['from' => $FROM, 'to' => $TO, 'subject' => $subject, 'text' => $text, 'html' => $html];
if ($email !== '') $payload['reply_to'] = $email;
if ($attach) $payload['attachments'] = [$attach];
$ch = curl_init('https://api.resend.com/emails');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$resp = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
if ($resp === false || $code >= 400) {
    error_log('inquiry: Resend failed (' . $code . ') ' . ($cerr ? $cerr : substr((string)$resp, 0, 300)));
    out(502, ['ok' => false, 'error' => "We couldn't send this. Please call $PHONE."]);
}
$hits[] = $now; @file_put_contents($rlFile, json_encode($hits));
out(200, ['ok' => true]);
