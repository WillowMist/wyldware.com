<?php
/**
 * WyldWare Contact Form Handler
 * Drop this in the wyldware/ directory alongside the HTML files
 * Works with PHP mail() or direct SMTP
 */

$TO_EMAIL = 'willow@fortsackville.com';
$FROM_EMAIL = 'noreply@wyldware.com';
$FROM_NAME = 'WyldWare Contact Form';

// ── Handle POST only ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact.html');
    exit;
}

// ── Bot Protection ─────────────────────────────────────────────────────────────

// 1. Honeypot field (should be empty)
if (!empty($_POST['website'])) {
    error_log("[WyldWare Contact] Bot detected: honeypot field filled");
    http_response_code(400);
    echo msgPage('Submission Rejected', 'Something didn\'t look right. If you\'re a human, please try again.', '/contact.html', '← Try again');
    exit;
}

// 2. Math CAPTCHA
$mathAnswer = intval($_POST['math_answer'] ?? 0);
$mathResult = intval($_POST['math_result'] ?? 0);
if ($mathAnswer !== $mathResult) {
    error_log("[WyldWare Contact] Bot detected: math CAPTCHA failed (got $mathAnswer, expected $mathResult)");
    http_response_code(400);
    echo msgPage('Math Check Failed', 'The math answer didn\'t match. If you\'re a human, please try again.', '/contact.html', '← Try again');
    exit;
}

// 3. Timestamp check (form submitted too fast = bot)
$timestamp = intval($_POST['form_timestamp'] ?? 0);
$now = time() * 1000; // Convert to milliseconds
$timeDiff = ($now - $timestamp) / 1000; // Difference in seconds
if ($timeDiff < 3) {
    error_log("[WyldWare Contact] Bot detected: form submitted in {$timeDiff}s (too fast)");
    http_response_code(400);
    echo msgPage('Too Fast', 'That was submitted awfully quick. If you\'re a human, please try again.', '/contact.html', '← Try again');
    exit;
}

// ── Normal Form Processing ──────────────────────────────────────────────────────
$email   = trim($_POST['email'] ?? '');
$message = trim($_POST['message'] ?? '');

if (!$name || !$email || !$message) {
    http_response_code(400);
    echo msgPage('Missing Info', 'Please fill in all fields before sending.', '/contact.html', '← Try again');
    exit;
}

$safeName = htmlspecialchars(preg_replace('/[<>"\'\\x00-\\x1F]/', '', $name), ENT_QUOTES, 'UTF-8');
$safeEmail = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
if (!$safeEmail) {
    http_response_code(400);
    echo msgPage('Invalid Email', 'That email address doesn\'t look right. Please check it and try again.', '/contact.html', '← Try again');
    exit;
}

$body = "New message from WyldWare contact form:\n\nName: $name\nEmail: $email\n\nMessage:\n$message";
$subject = "WyldWare Contact: $safeName";
$headers = "From: \"$FROM_NAME\" <$FROM_EMAIL>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($TO_EMAIL, $subject, $body, $headers);

if ($sent) {
    echo msgPage('Message Received', 'Thanks for reaching out. We\'ll get back to you shortly — usually within a day or two.', '/index.html', '← Back to WyldWare');
} else {
    error_log("[WyldWare Contact] mail() failed");
    echo msgPage('Something went wrong', 'We couldn\'t send your message. Please email us directly at willow@fortsackville.com.', '/contact.html', '← Try again');
}

function msgPage($title, $msg, $linkHref, $linkText) {
    return '<!DOCTYPE html><html lang="en"><head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>'.$title.' — WyldWare</title>
  <link rel="stylesheet" href="/css/style.css">
  <style>
    body{background:#18181B;color:#fff;font-family:Lato,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
    .box{text-align:center;max-width:500px;padding:2rem}
    .box h1{font-family:Inconsolata,monospace;color:#027ABB;margin-bottom:1rem}
    .box p{color:#e4e4e7;margin-bottom:1.5rem;line-height:1.6}
    .box a{color:#027ABB}
  </style>
</head><body><div class="box"><h1>'.$title.'</h1><p>'.$msg.'</p><a href="'.$linkHref.'">'.$linkText.'</a></div></body></html>';
}
?>
