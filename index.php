<?php
include 'functions.php';

if (!logged_in()) {
    header('Location: login.php');
    exit;
}

// Get the current URL for playlist
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$playlistUrl = $protocol . $host . dirname($_SERVER['PHP_SELF']) . '/playlist.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TATAPLAY Localhost Script</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            background: linear-gradient(135deg, #1a1a1a 0%, #0d0d0d 100%);
            font-family: 'Inter', sans-serif;
            color: #e1e1e1;
            padding: 2rem;
            min-height: 100vh;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(30, 30, 30, 0.95);
            border-radius: 16px;
            padding: 2rem;
        }
        
        h1 {
            text-align: center;
            margin-bottom: 2rem;
            color: #fff;
        }
        
        .url-section {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
        }
        
        input {
            flex: 1;
            padding: 0.75rem;
            background: #252525;
            border: 1px solid #333;
            border-radius: 4px;
            color: #fff;
        }
        
        button {
            padding: 0.75rem 1rem;
            background: #2980b9;
            border: none;
            border-radius: 4px;
            color: #fff;
            cursor: pointer;
        }
        
        button:hover {
            background: #3498db;
        }
        
        .players-section {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 8px;
        }
        
        h2 {
            margin-bottom: 1rem;
            font-size: 1.1rem;
            color: #fff;
        }
        
        ul {
            list-style-type: none;
            margin-left: 1rem;
        }
        
        li {
            margin-bottom: 0.5rem;
        }
        
        li a {
            color: #3498db;
            text-decoration: none;
        }
        
        li a:hover {
            text-decoration: underline;
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
        }
        
        .footer a:hover {
            color: #fff;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>TATAPLAY Localhost M3U</h1>
        
        <div class="url-section">
            <input type="text" id="playlist-url" value="<?php echo htmlspecialchars($playlistUrl); ?>" readonly>
            <button onclick="copyPlaylistUrl()">Copy</button>
        </div>
        
        <div class="footer">
            Coded with ❤️ by <a href="https://t.me/ygx_world" target="_blank">YGX WORLD</a> team
        </div>
    </div>

    <script>
    function copyPlaylistUrl() {
        const urlInput = document.getElementById('playlist-url');
        urlInput.select();
        document.execCommand('copy');
        
        const btn = document.querySelector('button');
        const originalText = btn.textContent;
        btn.textContent = 'Copied!';
        
        setTimeout(() => {
            btn.textContent = originalText;
        }, 1000);
    }
    </script>
</body>
</html>
<!--@yuvraj824;-->