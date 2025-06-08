<?php
// admin_text_extract.php
// Usage: /admin_text_extract.php?url=https://www.kaderock.com
// Shows all readable text from the cached or live site as plain text (for admin use)

if (!isset($_GET['url'])) {
    http_response_code(400);
    echo 'Missing url parameter.';
    exit;
}

$url = $_GET['url'];

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL.';
    exit;
}

$cacheDir = __DIR__ . '/cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . '/' . md5($url) . '.html';
$cacheLifetime = 86400; // 1 day in seconds

// Use cached content if available
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $html = file_get_contents($cacheFile);
} else {
    // Fetch and cache as usual
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: PHP Proxy/1.0'
            ]
        ]
    ];
    $context = stream_context_create($options);
    $html = @file_get_contents($url, false, $context);
    if ($html === false) {
        http_response_code(502);
        echo 'Failed to fetch remote content.';
        exit;
    }
    file_put_contents($cacheFile, $html);
}

// Extract readable text from HTML
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
$xpath = new DOMXPath($dom);
$nodes = $xpath->query('//body//*[not(self::script or self::style or self::noscript)]/text()[normalize-space()] | //body/text()[normalize-space()]');
$texts = [];
foreach ($nodes as $node) {
    $text = trim($node->nodeValue);
    if ($text !== '') {
        $texts[] = $text;
    }
}
header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $texts);
exit;
