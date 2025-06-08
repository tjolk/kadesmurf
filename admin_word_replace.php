<?php
// admin_word_replace.php
// Usage: /admin_word_replace.php?url=https://www.kaderock.com
// Shows all unique words and allows admin to define replacements

$mappingFile = __DIR__ . '/word_replacements.json';

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
$cacheLifetime = 86400; // 1 day

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

// Build unique word list (case-insensitive, but preserve original)
$allWords = [];
foreach ($texts as $line) {
    foreach (preg_split('/\W+/u', $line, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        $allWords[$word] = true;
    }
}
ksort($allWords, SORT_NATURAL | SORT_FLAG_CASE);

// Load existing replacements
$replacements = [];
if (file_exists($mappingFile)) {
    $replacements = json_decode(file_get_contents($mappingFile), true) ?: [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($allWords as $word => $_) {
        $replacement = isset($_POST['replace_' . md5($word)]) ? trim($_POST['replace_' . md5($word)]) : '';
        if ($replacement !== '' && $replacement !== $word) {
            $replacements[$word] = $replacement;
        } elseif (isset($replacements[$word])) {
            unset($replacements[$word]);
        }
    }
    file_put_contents($mappingFile, json_encode($replacements, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo '<div style="background: #cfc; padding: 10px;">Replacements saved!</div>';
}

// Show the form
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Word Replacement Admin</title>
    <style>
        body { font-family: sans-serif; margin: 0; padding: 0; background: #f8f8f8; }
        .container { max-width: 700px; margin: 0 auto; padding: 1em; background: #fff; box-shadow: 0 2px 8px #0001; }
        h2 { margin-top: 0; }
        table { border-collapse: collapse; width: 100%; font-size: 1em; }
        th, td { border: 1px solid #ccc; padding: 6px 4px; }
        th { background: #eee; }
        input[type=text] { width: 100%; box-sizing: border-box; font-size: 1em; padding: 4px; }
        button { font-size: 1em; padding: 8px 16px; margin-top: 1em; }
        @media (max-width: 600px) {
            .container { padding: 0.5em; }
            table, thead, tbody, th, td, tr { display: block; width: 100%; }
            th, td { border: none; border-bottom: 1px solid #ccc; }
            tr { margin-bottom: 1em; background: #fafafa; }
            th { background: #eee; font-weight: bold; }
            td { background: #fff; }
            td:before { content: attr(data-label); font-weight: bold; display: block; margin-bottom: 2px; }
        }
    </style>
</head>
<body>
<div class="container">
<h2>Word Replacement Admin</h2>
<p>URL: <code><?= htmlspecialchars($url) ?></code></p>
<form method="post" id="replaceForm" autocomplete="off">
<table>
<tr><th>Original Word</th><th>Replacement</th></tr>
<?php foreach ($allWords as $word => $_): ?>
<tr>
    <td data-label="Original Word"><?= htmlspecialchars($word) ?></td>
    <td data-label="Replacement"><input type="text" name="replace_<?= md5($word) ?>" value="<?= isset($replacements[$word]) ? htmlspecialchars($replacements[$word]) : '' ?>" data-word="<?= htmlspecialchars($word) ?>"></td>
</tr>
<?php endforeach; ?>
</table>
<p><button type="submit">Save Replacements</button></p>
</form>
<script>
// Auto-save on input change
const form = document.getElementById('replaceForm');
let timeout = null;
form.addEventListener('input', function(e) {
    if (e.target.tagName === 'INPUT') {
        clearTimeout(timeout);
        timeout = setTimeout(() => {
            const formData = new FormData(form);
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            }).then(r => r.text()).then(txt => {
                // Optionally show a small autosave indicator
                let indicator = document.getElementById('autosave-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'autosave-indicator';
                    indicator.style.position = 'fixed';
                    indicator.style.bottom = '10px';
                    indicator.style.right = '10px';
                    indicator.style.background = '#cfc';
                    indicator.style.padding = '6px 12px';
                    indicator.style.borderRadius = '6px';
                    indicator.style.boxShadow = '0 2px 8px #0002';
                    indicator.style.zIndex = 1000;
                    document.body.appendChild(indicator);
                }
                indicator.textContent = 'Autosaved';
                setTimeout(() => { indicator.textContent = ''; }, 1200);
            });
        }, 500); // debounce
    }
});
</script>
</div>
</body>
</html>
