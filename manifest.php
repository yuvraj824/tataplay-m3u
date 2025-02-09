<?php
require_once 'functions.php';
if (!logged_in()) {
    http_response_code(403);
    die("log in first.");
}
$id = $_GET['id'] ?? '';
if (empty($id) || !ctype_digit($id)) {
    http_response_code(400);
    exit('Invalid ID');
}

$catchupRequest = false;
$beginTimestamp = $endTimestamp = null;
if (isset($_GET['begin'], $_GET['end'])) {// TiviMate & Ott Navigator
    $catchupRequest = true;
    $beginTimestamp = intval($_GET['begin']);
    $endTimestamp = intval($_GET['end']);
    $beginFormatted = gmdate('Ymd\THis', $beginTimestamp);
    $endFormatted = gmdate('Ymd\THis', $endTimestamp);
}

$fetcherData = json_decode(getFetcherData(), true);

$channelData = null;
foreach ($fetcherData['data']['channels'] as $channel) {
    if ($channel['id'] === $id) {
        $channelData = $channel;
        break;
    }
}

if (!$channelData) {
    http_response_code(404);
    exit('<h1>data not found for channel</h1>');
}

if (!isset($channelData['is_catchup_available']) || $channelData['is_catchup_available'] === false) {
    $catchupRequest = false;
}

$hmac = getHmac($id);
if (!$hmac) {
    http_response_code(500);
    exit('<h1>Error fetching HMAC</h1>');
}

$manifestUrl = $channelData['manifest_url'];
if (strpos($manifestUrl, 'bpaita') === false) {
    header("Location: $manifestUrl");
    exit;
}

$manifestUrl = str_replace("bpaita", "bpaicatchupta", $manifestUrl);
$baseUrl = dirname($manifestUrl);

$manifestUrl .= "?$hmac";
if ($catchupRequest) {
    $manifestUrl .= '&begin=' . $beginFormatted . '&end=' . $endFormatted;
}

$originalMpdContent = fetchContent($manifestUrl);
if (!$originalMpdContent) {
    http_response_code(500);die("failed to fetch mpd");
}
$mpdContent = $originalMpdContent;

$mpdContent = preg_replace_callback('/<SegmentTemplate\s+.*?>/', function ($matches) use ($hmac) {
    $cleaned = preg_replace('/(\$Number\$\.m4s|\$RepresentationID\$\.dash)[^"]*/', '$1', $matches[0]);
    return str_replace(['$Number$.m4s', '$RepresentationID$.dash'],['$Number$.m4s?' . $hmac, '$RepresentationID$.dash?' . $hmac], $cleaned);
}, $mpdContent);

$mpdContent = preg_replace('/<BaseURL>.*<\/BaseURL>/', "<BaseURL>$baseUrl/dash/</BaseURL>", $mpdContent);
$mpdContent = str_replace("<!-- Created with Broadpeak BkS350 Origin Packager  (version=1.12.8-28913) -->","<!-- Created by @YGX_WORLD  (version=5.3) -->", $mpdContent);

if (strpos($mpdContent, 'pssh') === false && strpos($mpdContent, 'cenc:default_KID') === false) {

    $widevinePssh = extractWidevinePssh($mpdContent, $baseUrl, $catchupRequest);
    if ($widevinePssh === null) {
        http_response_code(500); die("Unable to extract Pssh.");
    }
    $mpdContent = preg_replace('/<BaseURL>.*<\/BaseURL>/', "<BaseURL>$baseUrl/dash/</BaseURL>", $mpdContent);

    $newContent = "<!-- Common Encryption -->\n      <ContentProtection schemeIdUri=\"urn:mpeg:dash:mp4protection:2011\" value=\"cenc\" cenc:default_KID=\"{$widevinePssh['kid']}\"/>";
 
    $mpdContent = str_replace('<ContentProtection value="cenc" schemeIdUri="urn:mpeg:dash:mp4protection:2011"/>',$newContent,$mpdContent);
 
    $pattern = '/<ContentProtection\s+schemeIdUri="(urn:[^"]+)"\s+value="Widevine"\/>/';

    $mpdContent = preg_replace_callback($pattern, function ($matches) use ($widevinePssh) {
        return "<!--Widevine-->\n      <ContentProtection schemeIdUri=\"{$matches[1]}\" value=\"Widevine\">\n        <cenc:pssh>{$widevinePssh['pssh']}</cenc:pssh>\n      </ContentProtection>";
    }, $mpdContent);
  
    $mpdContent = preg_replace('/xmlns="urn:mpeg:dash:schema:mpd:2011"/', '$0 xmlns:cenc="urn:mpe:cenc:2013"', $mpdContent);
}

header('Content-Type: application/dash+xml');
header('Content-Disposition: attachment; filename="mpd_script_by_@ygx_world.mpd"');
echo $mpdContent;
exit;

function extractWidevinePssh(string $content, string $baseUrl, ?int $catchupRequest): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $catchupRequest ? (int)($template['startNumber'] ?? 0) : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], $startNumber], $template['media']);
                    $url = "$baseUrl/dash/$media";
                    if (($content = fetchContent($url)) != false) {
                        $hexContent = bin2hex($content);
                        return extractKid($hexContent);
                    }
                }
            }
        }
    }
    return null;
}

function extractKid($hexContent) {
    $psshMarker = "70737368";
    $psshOffset = strpos($hexContent, $psshMarker);
    
    if ($psshOffset !== false) {
        $headerSizeHex = substr($hexContent, $psshOffset - 8, 8);
        $headerSize = hexdec($headerSizeHex);
        $psshHex = substr($hexContent, $psshOffset - 8, $headerSize * 2);
        $kidHex = substr($psshHex, 68, 32);
        $newPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $kidHex;
        $pssh = base64_encode(hex2bin($newPsshHex));
        $kid = substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20);
        
        return ['pssh' => $pssh, 'kid' => $kid];
    }
    
    return null;
}
//@yuvraj824
