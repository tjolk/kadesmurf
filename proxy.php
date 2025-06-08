<?php
// src/proxy.php
// Usage: /proxy.php?url=https://www.kaderock.com

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}

$url = $_GET['url'];

// Validate URL
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL.';
    exit;
}

// Fetch the remote content
$options = [
    'http' => [
        'method' => 'GET',
        'header' => [
            'User-Agent: PHP Proxy/1.0'
        ]
    ]
];
$context = stream_context_create($options);

$content = @file_get_contents($url, false, $context);

if ($content === false) {
    http_response_code(502);
    echo 'Failed to fetch remote content.';
    exit;
}

// Optionally, modify the HTML here (e.g., inject a script)
// Example: inject a banner at the top
$banner = '<div style="background: #222; color: #fff; padding: 10px; text-align: center;">This is a proxy banner</div>';
$content = preg_replace('/<body[^>]*>/i', '$0' . $banner, $content, 1);

// Output the modified content
header('Content-Type: text/html');
echo $content;
