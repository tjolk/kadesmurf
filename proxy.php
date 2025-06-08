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

// Inject a script to rewrite all links to go through the proxy
$script = <<<EOT
<script>
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
  // Replace images with class 'w-100 h-auto' with smurfen01.webp
  var imgs = document.querySelectorAll('img.w-100.h-auto');
  imgs.forEach(function(img) {
    img.src = 'smurfen01.webp';
  });
});
</script>
EOT;
$content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1);

// Output the modified content
header('Content-Type: text/html');
echo $content;
