<?php
//========================================================//
// Don't Sell this Script, This is 100% Free.
//========================================================//
include 'functions.php';
if (!logged_in()) {
  die("log in first.");
}
$jsonData = getFetcherData();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$requestUri = $_SERVER['REQUEST_URI'];
$currentScript = basename($_SERVER['SCRIPT_NAME']);
$baseMpdUrl = $protocol . $host . str_replace($currentScript, 'manifest.php', $requestUri);
$baseWvUrl = $protocol . $host . str_replace($currentScript, 'widevine.php', $requestUri);
$data = json_decode($jsonData, true);

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (stripos($userAgent, 'tivimate') !== false) { // tivimate
    $headers = '|User-Agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.69.69.69 YGX/537.36"&Origin="https://watch.tataplay.com"&Referer="https://watch.tataplay.com/"';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';

} elseif ($userAgent === 'Mozilla/5.0 (Windows NT 10.0; rv:78.0) Gecko/20100101 Firefox/78.0') { // NS player
    $headers = '%7CUser-Agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.69.69.69 YGX/537.36&Origin=https://watch.tataplay.com/&Referer=https://watch.tataplay.com/';
    $ctag = null;
    
} else { //for ott nav. and other players 
    $headers = '|User-Agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.69.69.69 YGX/537.36&Origin=https://watch.tataplay.com&Referer=https://watch.tataplay.com/';
    $ctag = 'catchup-type="append" catchup-days="8" catchup-source="&begin={utc}&end={utcend}"';
}

echo "#EXTM3U x-tvg-url=\"https://avkb.short.gy/epg.xml.gz\"\n#Script by @YGX_WORLD\n\n";
foreach ($data['data']['channels'] as $channel) {
    $id = $channel['id'];
    $name = $channel['name'];
    $logo = $channel['logo_url'];
    $genre = $channel['primaryGenre'];
    $mpdUrl = $baseMpdUrl . '?id=' . $id;
    $wvUrl = $baseWvUrl . '?id=' . $id;    
//  echo "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
//  echo "#KODIPROP:inputstream.adaptive.license_key=__CLEARKEY_LICENSE_URL__?id=$id\n";
    echo "#KODIPROP:inputstream.adaptive.license_type=com.widevine.alpha\n";
    echo "#KODIPROP:inputstream.adaptive.license_key=$wvUrl\n";
    echo "#EXTINF:-1 tvg-id=\"ts$id\" $ctag group-title=\"$genre\" tvg-logo=\"https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/$logo\",$name\n";
    echo $mpdUrl . $headers . "\n\n";
}
exit;
//@yuvraj824
