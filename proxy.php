<?php
// src/proxy.php
// Usage: /proxy.php/https://example.com or /proxy.php?url=https://example.com

// --- Robust PATH_INFO/REQUEST_URI parsing for pretty URLs ---
$targetUrl = null;
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']) {
    // Remove leading slash
    $targetUrl = ltrim($_SERVER['PATH_INFO'], '/');
    // If the URL is percent-encoded, decode it
    $targetUrl = urldecode($targetUrl);
    // If the URL is missing the double slash after scheme, fix it
    if (preg_match('#^(https?:)(/[^/])#', $targetUrl, $m)) {
        // e.g. "https:/example.com" => "https://example.com"
        $targetUrl = preg_replace('#^(https?:)/#', '$1//', $targetUrl);
    }
} elseif (isset($_GET['url'])) {
    $targetUrl = urldecode($_GET['url']);
}

// Validate URL
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL.';
    exit;
}
$url = $targetUrl;

// Caching setup
$cacheDir = __DIR__ . '/cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . '/' . md5($url) . '.html';
$cacheLifetime = 86400; // 1 day in seconds

// Serve from cache if available and fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $content = file_get_contents($cacheFile);
} else {
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
    // Save to cache
    file_put_contents($cacheFile, $content);
}

// Load word replacements if available
$mappingFile = __DIR__ . '/word_replacements.json';
$replacements = [];
if (file_exists($mappingFile)) {
    $replacements = json_decode(file_get_contents($mappingFile), true) ?: [];
}

// Function to replace words in HTML text nodes only
function replace_words_in_html($html, $replacements) {
    if (empty($replacements)) return $html;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//body//*[not(self::script or self::style or self::noscript)]/text() | //body/text()');
    foreach ($nodes as $node) {
        $text = $node->nodeValue;
        foreach ($replacements as $from => $to) {
            // Whole word, case-sensitive
            $text = preg_replace('/\b' . preg_quote($from, '/') . '\b/u', $to, $text);
        }
        $node->nodeValue = $text;
    }
    return $dom->saveHTML();
}

// Optionally, modify the HTML here (e.g., inject a script)
// Example: inject a banner at the top
$banner = '<div style="background: rgba(255,255,255,0.0); color: #4695D6; padding: 0px; text-align: center; font-weight: bold; position:sticky; top:0; z-index:9999;">Ṫ̶̡̉͂̆́̏̕h̸͓̪̯̱́̄̽̓̄̅́̾̂͋̕͝í̶̢̮̮̑̽͋̍̿͒̈͌̊̈́̚͝ṡ̴̢̧̡̰̟͚̟̻̻̞̰̗̤̎̌̅̾̃͐̂͂̎͝ͅ ̴͈́́̈̒͒̋̅̅̌̂͛̀̄ẁ̴̧̰̣̙̹̤̭͔̹̚ē̴̡̢̟̯͍̜̙͌́̂̿̅̏́̐̾̅̕͝b̵̨̨̰̻̩͚̜̲͕̅͐͋͗̑͆͌͘s̵̡̢͚̞̪̗͍͉̘͚̭̬̱̺̈́̓̍i̸̢͒̂̎͘t̸̳̮͖̮̭͔̝͚̼̻̞̞̖͈̻̿̏̾͂̇̒̋͋̈́̀͝ẹ̶̢̢̺̫̜͈̼̈̽̂̎̽̋̃̈́̈́͝ ̴̢̛͙͓̺̞̼͓̜̾̃̽̑i̸̢̩̟͖̗̜͕̯̐̀̀̂̈́̉s̸̘͍̰͔̣̦͉̩͎̓̓̈́̚ ̷͕̻͙̗̬̳̇͑̅͠ş̷̘̰̯̘̠̫͚͙͒̔̐ṃ̷͔̩̺̠͆̀̐͊̃̈́͒̂̀̋͝ụ̷̢͚͔̲̬̼̌r̵̢̛̟̠̫̠̹͛̐̎̈́̉̄̽́́̆̂̔̈̚f̵̧̧̲̗͚͙͙̝̩͙̼̻͑̀̋͗̓̓͗̂̄̔̌͘̕͜͠ȩ̵̢̟̯͍̜̙̏̂́̈́̈́̑̄̋̚͠b̵̨̨̰̻̩͚̜̲͕̅͐͋͗̑͆͌͘s̵̡̢͚̞̪̗͍͉̘͚̭̬̱̺̈́̓̍i̸̢͒̂̎͘t̸̳̮͖̮̭͔̝͚̼̻̞̞̖͈̻̿̏̾͂̇̒̋͋̈́̀͝ẹ̶̢̢̺̫̜͈̼̈̽̂̎̽̋̃̈́̈́͝ ̴̢̛͙͓̺̞̼͓̜̾̃̽̑i̸̢̩̟͖̗̜͕̯̐̀̀̂̈́̉s̸̘͍̰͔̣̦͉̩͎̓̓̈́̚ ̷͕̻͙̗̬̳̇͑̅͠ş̷̘̰̯̘̠̫͚͙͒̔̐ṃ̷͔̩̺̠͆̀̐͊̃̈́͒̂̀̋͝ụ̷̢͚͔̲̬̼̌r̵̢̛̟̠̫̠̹͛̐̎̈́̉̄̽́́̆̂̔̈̚f̵̧̧̲̗͚͙͙̝̩͙̼̻͑̀̋͗̓̓͗̂̄̔̌͘̕͜͠ȩ̵̢̟̯͍̜̙̏̂́̈́̈́̑̄̋̚͠</div>';
$content = preg_replace('/<body[^>]*>/i', '$0' . $banner, $content, 1);

// Apply word replacements to the HTML
$content = replace_words_in_html($content, $replacements);

// Inject a script to rewrite all links to go through the proxy and replace images with 'smurfen01.webp'
$script = <<<EOT
<script>
function replaceImages(proxyBase) {
  var imgs = document.querySelectorAll('img');
  imgs.forEach(function(img) {
    if (img.classList.contains('w-100') && img.classList.contains('h-auto')) {
      img.src = proxyBase.replace(/proxy\.php$/, '') + 'smurfen01.jpg';
    }
  });
}

function removeAdnxsLinks() {
  var selectors = [
    'a[href*="adnxs.com"]',
    'iframe[src*="adnxs.com"]',
    'script[src*="adnxs.com"]',
    'img[src*="adnxs.com"]',
    '[src*="adnxs.com"]',
    '[href*="adnxs.com"]'
  ];
  selectors.forEach(function(selector) {
    document.querySelectorAll(selector).forEach(function(el) {
      el.remove();
    });
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Get the base URL of the proxy (current origin + pathname up to proxy.php)
  var proxyBase = window.location.origin + window.location.pathname.replace(/proxy\.php.*/, 'proxy.php');
  // Rewrite all anchor tags
  var links = document.querySelectorAll('a[href]');
  links.forEach(function(link) {
    var href = link.getAttribute('href');
    if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
      var url;
      try {
        url = new URL(href, document.baseURI);
      } catch (e) {
        return;
      }
      if (url.protocol === 'http:' || url.protocol === 'https:') {
        // Rewrite to the query parameter format: /proxy.php?url=...
        link.setAttribute('href', proxyBase + '?url=' + encodeURIComponent(url.href));
      }
    }
  });
  replaceImages(proxyBase);
  removeAdnxsLinks();
  // Observe DOM changes for dynamically loaded images and ads
  var observer = new MutationObserver(function() {
    replaceImages(proxyBase);
    removeAdnxsLinks();
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
EOT;
$content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1);

// Output the modified content
header('Content-Type: text/html');
echo $content;

// Backend function to display all readable text of the website
if (isset($_GET['showtext']) && $_GET['showtext'] === '1') {
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
}
