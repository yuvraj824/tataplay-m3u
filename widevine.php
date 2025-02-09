<?php
require_once 'functions.php';
if (!logged_in()) {
    http_response_code(500);
    die("log in first.");
}
$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    http_response_code(400);
    exit('Invalid ID');
}
$fetcherData = json_decode(getFetcherData(), true);

$channelData = null;
foreach ($fetcherData['data']['channels'] as $channel) {
    if ($channel['id'] === $id) {
        $channelData = $channel;
        break;
    }
}

if ($channelData === null) {
    http_response_code(404);
    exit('<h1>data not found for channel id.</h1>');
}


 if (($licenseUrl = $channelData['license_url'])) {
    
    if (($jwt = generateJWT($id))) {        
        $licenseUrl .= "&ls_session=" . $jwt;
        http_response_code(307);
        header("Location: $licenseUrl");
        exit;
    } 
}
http_response_code(500);
exit('An error occurred.');
//@yuvraj824