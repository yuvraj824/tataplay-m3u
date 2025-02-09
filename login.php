<?php
include_once 'functions.php';

function saveCreds($data) {
    $credsFile = 'app/creds';
    if (!file_exists(dirname($credsFile))) {
        mkdir(dirname($credsFile), 0755, true);
    }
    file_put_contents($credsFile, json_encode($data));
}

function doCurlRequest($url, $postData) {
    $headers = [
        'accept: */*',
        'accept-language: en-US,en;q=0.9',
        'cache-control: no-cache',
        'content-type: application/json',
        'device_details: {"pl":"web","os":"WINDOWS","lo":"en-us","app":"1.48.8","dn":"PC","bv":116,"bn":"OPERA","device_id":"7683d93848b0f472c508e38b1827038a","device_type":"WEB","device_platform":"PC","device_category":"open","manufacturer":"WINDOWS_OPERA_116","model":"PC","sname":""}',
        'locale: ENG',
        'origin: https://watch.tataplay.com',
        'platform: web',
        'pragma: no-cache',
        'priority: u=1, i',
        'referer: https://watch.tataplay.com/',
        'sec-ch-ua: "Opera";v="116", "Chromium";v="131", "Not_A Brand";v="24"',
        'sec-ch-ua-mobile: ?0',
        'sec-ch-ua-platform: "Windows"',
        'sec-fetch-dest: empty',
        'sec-fetch-mode: cors',
        'sec-fetch-site: cross-site',
        'user-agent: Android'
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    
    $response = curl_exec($ch);
    $error = curl_errno($ch) ? ['error' => curl_error($ch)] : json_decode($response, true);
    curl_close($ch);
    return $error;
}

$message = '';
$finalUrl = '';
$showOtpForm = false;
$hiddenFields = [];

if (logged_in()) {
    $message = 'Logged in.';
    $showOtpForm = false;
}elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'get_otp' && !logged_in()) {
            $sid = trim($_POST['sid'] ?? '');
            
            if (strlen($sid) !== 10 || !ctype_digit($sid)) {
                $message = 'SID must be of 10 digits.';
            } else {
                $response = doCurlRequest('https://tm.tapi.videoready.tv/login-service/pub/api/v2/generate/otp', [
                    'sid' => $sid,
                    'rmn' => ''
                ]);

                if (isset($response['error'])) {
                    $message = 'Error: ' . $response['error'];
                } elseif (isset($response['code']) && $response['code'] === 0) {
                    $message = 'OTP sent successfully to ' . $response['data']['decryptedRMN'];
                    $hiddenFields = [
                        'sid' => $sid,
                        'encrypted_rmn' => $response['data']['rmn'] ?? ''
                    ];
                    $showOtpForm = true;
                } else {
                    $message = 'Error: ' . ($response['message'] ?? 'Unknown error during OTP generation.');
                }
            }
        } elseif ($_POST['action'] === 'verify_otp') {
            $sid = trim($_POST['sid'] ?? '');
            $encryptedRmn = trim($_POST['encrypted_rmn'] ?? '');
            $otp = trim($_POST['otp'] ?? '');

            if (!ctype_digit($otp) || strlen($otp) !== 6) {
                $message = 'Please enter a valid 6-digit OTP.';
                $showOtpForm = true;
                $hiddenFields = ['sid' => $sid, 'encrypted_rmn' => $encryptedRmn];
            } else {
                $response = doCurlRequest('https://tm.tapi.videoready.tv/login-service/pub/api/v3/login/ott', [
                    'rmn' => $encryptedRmn,
                    'sid' => $sid,
                    'authorization' => $otp,
                    'loginOption' => 'OTP'
                ]);

                if (isset($response['error'])) {
                    $message = 'Error: ' . $response['error'];
                } elseif (isset($response['code']) && $response['code'] === 0) {
                    $message = 'Login successful for SID: ' . $sid;
                    saveCreds($response);
                } else {
                    $message = 'Error: ' . ($response['message'] ?? 'Unknown error during OTP verification.');
                    $showOtpForm = true;
                    $hiddenFields = ['sid' => $sid, 'encrypted_rmn' => $encryptedRmn];
                }
            }
        }
    }
}


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TPLAY Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body, html {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
            font-family: 'Inter', sans-serif;
            color: #e1e1e1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        
        .card {
            background: rgba(30, 30, 30, 0.95);
            border-radius: 16px;
            width: 95%;
            max-width: 400px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .card-header h1 {
            font-size: 2rem;
            font-weight: 600;
            background: linear-gradient(45deg, #fff, #b3b3b3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }
        
        .notice {
            background: rgba(255, 193, 7, 0.1);
            border: 1px solid rgba(255, 193, 7, 0.3);
            color: #ffc107;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            text-align: center;
        }
        
        .notice strong {
            color: #ffd700;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #b3b3b3;
            font-weight: 500;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #333;
            border-radius: 8px;
            background: #252525;
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input[type="text"]:focus {
            border-color: #666;
            outline: none;
            box-shadow: 0 0 0 2px rgba(102, 102, 102, 0.2);
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(45deg, #444, #333);
            color: #fff;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin-bottom: 0.5rem;
        }
        
        .btn:hover {
            background: linear-gradient(45deg, #555, #444);
            transform: translateY(-1px);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #2980b9, #2c3e50);
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #3498db, #34495e);
        }
        
        .message {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.95rem;
            background: rgba(39, 174, 96, 0.1);
            color: #2ecc71;
        }
        
        .message.error {
            background: rgba(231, 76, 60, 0.1);
            color: #e74c3c;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #333;
            color: #666;
            font-size: 0.9rem;
        }
        
        .footer a {
            color: #888;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer a:hover {
            color: #fff;
        }
        
        @media (max-width: 480px) {
            .card {
                width: 90%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="card-header">
            <h1>TPLAY LOGIN</h1>
        </div>
        <div class="card-body">
            <?php if ($message): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (!$showOtpForm && !logged_in()): ?>
                <div class="notice">
                    ⚠️ Note: Script works only with <strong>Active</strong> TATAPLAY account.<br>
                    Inactive accounts will not work.
                </div>
                <form method="post" action="">
                    <input type="hidden" name="action" value="get_otp">
                    <div class="form-group">
                        <label for="sid">SID LOGIN:</label>
                        <input type="text" id="sid" name="sid" maxlength="10" required pattern="\d{10}" placeholder="Enter your subscriber ID">
                    </div>
                    <input type="submit" value="Get OTP" class="btn">
                </form>
            <?php elseif ($showOtpForm): ?>
                <form method="post" action="">
                    <input type="hidden" name="action" value="verify_otp">
                    <input type="hidden" name="sid" value="<?php echo htmlspecialchars($hiddenFields['sid']); ?>">
                    <input type="hidden" name="encrypted_rmn" value="<?php echo htmlspecialchars($hiddenFields['encrypted_rmn']); ?>">
                    <div class="form-group">
                        <label for="otp">Enter 6-digit OTP</label>
                        <input type="text" id="otp" name="otp" maxlength="6" required pattern="\d{6}" placeholder="Enter OTP">
                    </div>
                    <input type="submit" value="Verify OTP" class="btn">
                </form>
            <?php elseif (logged_in()): ?>
                <a href="index.php" class="btn btn-primary">Home Page</a>
            <?php endif; ?>
        </div>
        <div class="footer">
            Coded with ❤️ by <a href="https://t.me/ygx_world" target="_blank">YGX WORLD</a> team
        </div>
    </div>
</body>
</html>
<!--@yuvraj824;-->