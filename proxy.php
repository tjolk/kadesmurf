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

// Inject a script to rewrite all links to go through the proxy and replace images with 'smurfen01.webp'
$script = <<<EOT
<script>
function replaceImages() {
  // Select images with class 'w-100' and 'h-auto' (in any order, even with other classes)
  var imgs = document.querySelectorAll('img');
  imgs.forEach(function(img) {
    if (img.classList.contains('w-100') && img.classList.contains('h-auto')) {
      // Use the correct path relative to proxy.php
      img.src = window.location.pathname.replace(/[^\/]+$/, '') + 'smurfen01.webp';
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Rewrite all anchor tags
  var links = document.querySelectorAll('a[href]');
  links.forEach(function(link) {
    var href = link.getAttribute('href');
    if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
      var url;
      try {
        url = new URL(href, window.location.origin);
      } catch (e) {
        return;
      }
      if (url.protocol === 'http:' || url.protocol === 'https:') {
        link.setAttribute('href', '?url=' + encodeURIComponent(url.href));
      }
    }
  });
  replaceImages();
  // Observe DOM changes for dynamically loaded images
  var observer = new MutationObserver(function() {
    replaceImages();
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
EOT;
$content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1);

// Output the modified content
header('Content-Type: text/html');
echo $content;
