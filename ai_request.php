<?php

$keysEnv = getenv('GEMINI_API_KEYS');
$prompt  = getenv('USER_PROMPT');
$userId  = getenv('USER_ID') ?: 'anonymous';

if (!$keysEnv || !$prompt) {
    exit("Error: Missing API Keys or Prompt.");
}

$apiKeyPool = array_filter(array_map('trim', explode(',', $keysEnv)));
if (empty($apiKeyPool)) {
    exit("Error: No valid Gemini API keys found in pool.");
}

$lockDir = __DIR__ . '/locks';
if (!is_dir($lockDir)) {
    mkdir($lockDir, 0777, true);
}

$maxAttempts = count($apiKeyPool) * 2; // Allow retries for 503 high demand spikes
$attempt = 0;
$success = false;
$finalResult = [];
$selectedApiKey = null;
$keyFileHandle = null;

while ($attempt < $maxAttempts && !$success) {
    $attempt++;
    $lockedKeyFound = false;
    shuffle($apiKeyPool);

    // Concurrency-safe key locking loop
    foreach ($apiKeyPool as $candidateKey) {
        $keyHash = md5($candidateKey);
        $lockFile = "{$lockDir}/key_{$keyHash}.lock";

        $fp = fopen($lockFile, 'c+');
        if ($fp === false) continue;

        // Exclusive non-blocking lock to prevent parallel runner collisions
        if (flock($fp, LOCK_EX | LOCK_NB)) {
            $lastUsed = stream_get_contents($fp);
            $currentTime = time();
            
            // Respect 5 RPM cool-down window (requires at least 12 seconds gap per key)
            if (!empty($lastUsed) && ($currentTime - (int)$lastUsed) < 12) {
                flock($fp, LOCK_UN);
                fclose($fp);
                continue;
            }

            $selectedApiKey = $candidateKey;
            $keyFileHandle = $fp;
            $lockedKeyFound = true;
            break;
        } else {
            fclose($fp);
        }
    }

    if (!$lockedKeyFound) {
        sleep(3);
        continue;
    }

    // Fetch fresh live proxy list & pick a random one
    $proxyApiUrl = 'https://api.proxyscrape.com/v4/free-proxy-list/get?request=display_proxies&proxy_format=protocolipport&format=text&protocol=http';
    $proxyData = @file_get_contents($proxyApiUrl);
    $proxyPool = $proxyData ? array_filter(explode("\n", str_replace("\r", "", $proxyData))) : [];
    $selectedProxy = !empty($proxyPool) ? trim($proxyPool[array_rand($proxyPool)]) : null;
    if ($selectedProxy && !str_starts_with($selectedProxy, 'http')) { 
        $selectedProxy = 'http://' . $selectedProxy; 
    }

    // Call Google Gemini API using gemini-3.8-flash model endpoint
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent?key=' . $selectedApiKey;
    $payload = [
        'contents' => [
            ['parts' => [['text' => $prompt]]]
        ]
    ];

    $ch = curl_init($apiUrl);
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];

    // Apply proxy if successfully fetched
    if (!empty($selectedProxy)) {
        $curlOptions[CURLOPT_PROXY] = $selectedProxy;
    }

    curl_setopt_array($ch, $curlOptions);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $curlErrno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Save timestamp to lock file for cool-down tracking & release lock
    ftruncate($keyFileHandle, 0);
    rewind($keyFileHandle);
    fwrite($keyFileHandle, (string)time());
    fflush($keyFileHandle);
    flock($keyFileHandle, LOCK_UN);
    fclose($keyFileHandle);

    if ($curlErrno !== 0) {
        // Proxy tunnel failure -> retry with another proxy/key
        $replyText = "cURL Error [{$curlErrno}]: {$curlError}";
        sleep(2);
        continue;
    } elseif ($httpCode === 429 || $httpCode === 503) {
        // Handle Rate Limit (429) or High Demand Server Overload (503) by backing off and rotating
        sleep(5);
        continue;
    } elseif ($httpCode !== 200) {
        $replyText = "API Error [HTTP {$httpCode}]: {$response}";
    } else {
        $decoded = json_decode($response, true);
        $replyText = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? 'Unexpected response format';
        $success = true;
    }

    $finalResult = [
        'response' => $replyText,
        'http_code' => $httpCode,
        'attempt' => $attempt
    ];
}

// Save output package to responses directory
$outputData = [
    'user_id' => $userId,
    'prompt' => $prompt,
    'success' => $success,
    'data' => $finalResult['response'] ?? 'Execution failed or rate-limited/overloaded across all keys/proxies.',
    'attempts_made' => $attempt,
    'timestamp' => date('c')
];

$dir = __DIR__ . '/responses';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents("{$dir}/{$userId}.json", json_encode($outputData, JSON_PRETTY_PRINT));
echo "Processed user {$userId}. Success: " . ($success ? 'true' : 'false') . "\n";
