<?php
function getUserIP()
{
    // 1. Check for IP addresses forwarded through proxy servers
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // The header can contain a comma-separated list if it passed multiple proxies
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        // The first IP in the list is typically the original client
        return trim($ipList[0]);
    }

    // 2. Check for shared internet service provider headers
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }

    // 3. Default fallback to the direct TCP connection IP
    return $_SERVER['REMOTE_ADDR'];
}

// Usage example:
$user_ip = getUserIP();
echo "Device IP Address: " . htmlspecialchars($user_ip, ENT_QUOTES, 'UTF-8');
