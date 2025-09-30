<?php
require_once 'db.php';

// Proxy request logging function
function logProxyRequest($filename, $proxyId, $proxyUrl, $httpCode, $fellbackToDirect = false) {
    $logFile = '/www/imguruk.com/log/proxy_requests.log';
    $timestamp = date('Y-m-d H:i:s');
    $status = $fellbackToDirect ? 'FALLBACK' : ($httpCode === 200 ? 'SUCCESS' : 'FAILED');

    $logEntry = json_encode([
        'timestamp' => $timestamp,
        'filename' => $filename,
        'proxy_id' => $proxyId,
        'proxy_url' => $proxyUrl,
        'http_code' => $httpCode,
        'status' => $status
    ]) . "\n";

    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Get the host and check subdomain
$host = $_SERVER['HTTP_HOST'];
$subdomain = explode('.', $host)[0];

// Only run image proxy if subdomain is 'i'
if ($subdomain === 'i') {
    $uri = $_SERVER['REQUEST_URI'];

    // Validate that the request is for a specific file with an extension
    $pathInfo = pathinfo($uri);
    if (!empty($pathInfo['extension']) && !empty($pathInfo['filename'])) {
        $filename = basename($uri);

        // Ignore favicon requests
        if ($filename === 'favicon.ico') {
            http_response_code(404);
            exit;
        }

        // Check if this is a local uk- file
        if (strpos($filename, 'uk-') === 0) {
            $localPath = '/www/imguruk.com/web/uploads/' . $filename;

            if (file_exists($localPath)) {
                // Serve local file
                $mimeType = mime_content_type($localPath);
                header('Content-Type: ' . $mimeType);
                readfile($localPath);
                exit;
            } else {
                // File not found
                http_response_code(404);
                echo '';
                exit;
            }
        }

        // Otherwise, try to use a contributor proxy, fallback to direct imgur
        $proxy = getRandomProxy();

        if ($proxy) {
            // Use contributor proxy
            $proxyUrl = rtrim($proxy['proxy_url'], '/') . $uri;
            $apiToken = '';

            // Get the user's API token for this proxy
            $db = getDB();
            $stmt = $db->prepare('SELECT u.api_token FROM users u INNER JOIN user_proxies p ON u.id = p.user_id WHERE p.id = :proxy_id');
            $stmt->bindValue(':proxy_id', $proxy['id'], SQLITE3_INTEGER);
            $result = $stmt->execute();
            $proxyUser = $result->fetchArray(SQLITE3_ASSOC);

            if ($proxyUser && !empty($proxyUser['api_token'])) {
                $apiToken = $proxyUser['api_token'];
            }

            // Request from contributor proxy
            $ch = curl_init($proxyUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'X-API-Token: ' . $apiToken
            ]);
            curl_setopt($ch, CURLOPT_REFERER, 'https://imguruk.com');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

            if ($httpCode === 200 && $response) {
                $headers = substr($response, 0, $headerSize);
                $body = substr($response, $headerSize);
                curl_close($ch);

                // Log successful proxy request
                logProxyRequest($filename, $proxy['id'], $proxy['proxy_url'], $httpCode);

                // Parse and send Content-Type header
                if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $matches)) {
                    header('Content-Type: ' . trim($matches[1]));
                }

                echo $body;
                exit;
            }

            // Log failed proxy request
            logProxyRequest($filename, $proxy['id'], $proxy['proxy_url'], $httpCode);

            curl_close($ch);
            // If proxy failed, fall through to direct fetch
        }

        // Fallback: fetch directly from imgur
        $imgurUrl = 'https://i.imgur.com' . $uri;

        $ch = curl_init($imgurUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        curl_close($ch);

        // Log fallback to direct fetch
        if ($proxy) {
            logProxyRequest($filename, null, 'DIRECT_IMGUR', $httpCode, true);
        }

        // Parse and send Content-Type header
        if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headers, $matches)) {
            header('Content-Type: ' . trim($matches[1]));
        }

        // Output the content
        echo $body;
    } else {
        // Invalid request - no file extension
        http_response_code(404);
        echo '';
    }
} else {
    // Show homepage for non-i subdomains
    require_once 'auth.php';
    require_once 'admin.php';

    $isLoggedIn = isLoggedIn();
    $username = $_SESSION['username'] ?? '';
    $isUserAdmin = $isLoggedIn ? isAdmin(getCurrentUserId()) : false;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ImgurUK - Image Hosting</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0a0a0a; color: #fff; min-height: 100vh; }
            nav { background: #1a1a1a; padding: 1rem 2rem; border-bottom: 1px solid #333; }
            nav .container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; }
            nav h1 { font-size: 1.5rem; }
            nav .links a { color: #fff; text-decoration: none; margin-left: 1.5rem; padding: 0.5rem 1rem; border-radius: 4px; transition: background 0.2s; }
            nav .links a:hover { background: #333; }
            .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
            .hero { text-align: center; padding: 4rem 2rem; }
            .hero h2 { font-size: 3rem; margin-bottom: 1rem; }
            .hero p { font-size: 1.2rem; color: #aaa; margin-bottom: 2rem; }
            .hero img { max-width: 600px; width: 100%; border-radius: 8px; margin: 2rem auto; display: block; box-shadow: 0 4px 12px rgba(0,0,0,0.5); }
            .cta { display: inline-block; background: #4CAF50; color: #fff; padding: 1rem 2rem; border-radius: 4px; text-decoration: none; font-weight: bold; transition: background 0.2s; }
            .cta:hover { background: #45a049; }
            .github-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #fff; text-decoration: none; margin-bottom: 2rem; padding: 0.75rem 1.5rem; background: #333; border-radius: 4px; transition: background 0.2s; }
            .github-link:hover { background: #444; }
            .github-link svg { width: 20px; height: 20px; fill: currentColor; }
        </style>
    </head>
    <body>
        <nav>
            <div class="container">
                <h1>ImgurUK</h1>
                <div class="links">
                    <?php if ($isLoggedIn): ?>
                        <span>Welcome, <?php echo htmlspecialchars($username); ?></span>
                        <a href="/upload.html">Upload</a>
                        <a href="/contribute.html">Contribute</a>
                        <?php if ($isUserAdmin): ?>
                            <a href="/admin.html">Admin</a>
                        <?php endif; ?>
                        <a href="/api.php?action=logout">Logout</a>
                    <?php else: ?>
                        <a href="/login.html">Login</a>
                        <a href="/register.html">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <div class="hero">
            <h2>Simple Image Hosting</h2>
            <p>Upload and share your images instantly</p>
            <a href="https://github.com/daleus/imguruk" target="_blank" rel="noopener noreferrer" class="github-link">
                <svg viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg">
                    <path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/>
                </svg>
                View on GitHub
            </a>
            <img src="https://i.imguruk.com/uk-4.png" alt="Example hosted image">
            <?php if ($isLoggedIn): ?>
                <a href="/upload.html" class="cta">Upload Image</a>
            <?php else: ?>
                <a href="/register.html" class="cta">Get Started</a>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
}
