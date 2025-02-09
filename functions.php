<?php
error_reporting(0);

function logged_in() {
    return file_exists("app/creds");
}

function getCreds() {
    if (!logged_in()) {
        http_response_code(403);
       die("Not logged in.");
    }
    $data = file_get_contents("app/creds");
    $json = json_decode($data, true);
    return [
        'accessToken' => $json['data']['accessToken'],
        'sid' => $json['data']['userDetails']['sid'],
        'sname' => $json['data']['userDetails']['sName'],
        'profileId' => $json['data']['userProfile']['id']
    ];
}

function cache_updater($utility, $content, $id, $exp) {
    $cacheDir = "app/cache/$utility";
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    $cacheData = [
        'content' => $content,
        'exp' => $exp
    ];
    $cacheFile = "$cacheDir/$id.json";
    file_put_contents($cacheFile, json_encode($cacheData));
}

function cache_retrive($utility, $id) {
    $cacheFile = "app/cache/$utility/$id.json";
    if (!file_exists($cacheFile)) {
        return false;
    }
    $cacheData = json_decode(file_get_contents($cacheFile), true);
    if (!isset($cacheData['content']) || !isset($cacheData['exp'])) {
        return false;
    }
    if (time() > $cacheData['exp']) {
        unlink($cacheFile);
        return false;
    }
    return $cacheData['content'];
}

function fetchContent($url, $want_response_header = false) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => $want_response_header,
        CURLOPT_NOBODY => $want_response_header,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.69.69.69 YGX/537.36',
            'Origin: https://watch.tataplay.com',
            'Referer: https://watch.tataplay.com/'
        ]
    ]);
    
    $response = curl_exec($ch);
    $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $responseCode === 200 ? $response : null;
}

function channelDetails($id) {
    $creds = getCreds();
    $url = "https://tm.tapi.videoready.tv/content-detail/pub/api/v6/channels/$id?platform=WEB";
    $channelDetailsHeaders = [
        "accept: */*", 
        "accept-language: en-US,en;q=0.9",
        "authorization: bearer " . $creds['accessToken'],
        "cache-control: no-cache",
        "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $creds['sname'] . "\"}", 
        "platform: web",
        "pragma: no-cache",
        "profileid: " . $creds['profileId'],
        "Referer: https://watch.tataplay.com/",
        "Origin: https://watch.tataplay.com",
        "Referrer-Policy: strict-origin-when-cross-origin",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0"
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $channelDetailsHeaders,
    ]);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate, br');
    
    $response = curl_exec($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpcode !== 200) {
        http_response_code(500);
        die("error occured while fetching channel details.");
    }

    $channelDetails = json_decode($response, true);
    $entitlements = $channelDetails['data']['detail']['entitlements'];
    $specialId = "1000001274";
    $epids = [];
    
    if (in_array($specialId, $entitlements)) {
        $epids[] = [
            "epid" => "Subscription",
            "bid" => $specialId
        ];
    } elseif (!empty($entitlements)) {
        $epids[] = [
            "epid" => "Subscription",
            "bid" => $entitlements[0]
        ];
    }
    return $epids;
}


function decodeJWT($jwt) {
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    $payload = base64_decode(strtr($parts[1], '-_', '+/'));
    return json_decode($payload, true);
}

function generateJWT($channelId) {
    if (cache_retrive("jwt", $channelId)) {
        return cache_retrive("jwt", $channelId);
    }
    $epids = channelDetails($channelId);
    $creds = getCreds();
    $url = "https://tm.tapi.videoready.tv/auth-service/v3/sampling/token-service/token";
    $payload = json_encode([
        'action' => 'stream',
        'epids' => $epids,
        'samplingExpiry' => 'wLixk6fGx27amZptXg2I/w==#v2'
    ]);
    $headers = [
        "accept: */*",
        "accept-language: en-US,en;q=0.9",
        "authorization: bearer " . $creds['accessToken'],
        "content-type: application/json",
        "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"7683d93848b0f472c508e38b1827038a\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $creds['sname'] . "\"}", 
        "locale: ENG",
        "platform: web",
        "pragma: no-cache",
        "profileid: " . $creds['profileId'],
        "x-device-platform: PC",
        "x-device-type: WEB",
        "x-subscriber-id: " . $creds['sid'],
        "x-subscriber-name: " . $creds['sname'],
        "Referer: https://watch.tataplay.com/",
        "Origin: https://watch.tataplay.com",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0"
    ];
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    curl_setopt($curl, CURLOPT_ENCODING, 'gzip, deflate, br');
    
    $tokenResponse = curl_exec($curl);
    curl_close($curl);
    
    $json = json_decode($tokenResponse, true);
    if (isset($json['code']) && $json['code'] === 0 && isset($json['data']['token'])) {
        $jwt = $json['data']['token'];
        $jwtData = decodeJWT($jwt);
        cache_updater("jwt", $jwt, $channelId, $jwtData['exp']);
        return $jwt;
    }
    else {
        $err = $json['message'] ?? "Unknown Error during generation of JWT token.";
        http_response_code(500);
        die("Error: " . $err);
    }
}

function getHmac($id) {
    if (cache_retrive("hmac", $id)) {
        return cache_retrive("hmac", $id);
    }
    
    $creds = getCreds();
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://tm.tapi.videoready.tv/digital-feed-services/api/partner/cdn/player/details/LIVE/{$id}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate, br');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: */*',
        'accept-language: en-US,en;q=0.9,en-IN;q=0.8',
        'authorization: ' . $creds['accessToken'],
        'content-type: application/json',
        "device_details: {\"pl\":\"web\",\"os\":\"WINDOWS\",\"lo\":\"en-us\",\"app\":\"1.44.7\",\"dn\":\"PC\",\"bv\":129,\"bn\":\"CHROME\",\"device_id\":\"7683d93848b0f472c508e38b1827038a\",\"device_type\":\"WEB\",\"device_platform\":\"PC\",\"device_category\":\"open\",\"manufacturer\":\"WINDOWS_CHROME_129\",\"model\":\"PC\",\"sname\":\"" . $creds['sname'] . "\"}", 
        'kp: false',
        'locale: ENG',
        'origin: https://watch.tataplay.com',
        'platform: web',
        'priority: u=1, i',
        "profileid: " . $creds['profileId'],
        'referer: https://watch.tataplay.com/',
        'sec-ch-ua: "Microsoft Edge";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: cross-site',
        'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0',
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $response_json = json_decode($response, true);
        $encrypted_token = $response_json['data']['dashWidewinePlayUrl'];
        $key = 'aesEncryptionKey';
        $decrypted = openssl_decrypt(base64_decode(explode('#', $encrypted_token)[0]), 'AES-128-ECB', $key, OPENSSL_RAW_DATA);
        
        $header_response = fetchContent($decrypted, true);
        preg_match('/hdntl=(.*?)(;|$)/', $header_response, $hdntl_matches);
        $hdntl = isset($hdntl_matches[1]) ? "hdntl=" . $hdntl_matches[1] : null;
        
        preg_match('/exp=(\d+)/', $hdntl, $exp_matches);
        $exp_time = isset($exp_matches[1]) ? (int)$exp_matches[1] : time() + 600;
        
        cache_updater("hmac", $hdntl, $id, $exp_time);        
        return $hdntl;
    }
    return null;
}

function getFetcherData() {
    $filename = 'app/data/data.json';
    if (!file_exists(dirname($filename))) {mkdir(dirname($filename), 0755, true);}
    $cacheTime = 86400;
    $apiUrl = 'https://api.ygxworld.workers.dev/fetcher.json';
    if (file_exists($filename) && (time() - filemtime($filename)) < $cacheTime) {
        $cachedData = @file_get_contents($filename);
        if ($cachedData !== false) {return $cachedData;}
    }
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode === 200 && !empty($response)) {
        file_put_contents($filename, $response);
        return $response;
    } else {
        $cachedData = @file_get_contents($filename);
        file_put_contents($filename, $cachedData);
        return $cachedData;
    }
}
