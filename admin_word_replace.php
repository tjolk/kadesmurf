<?php
// admin_word_replace.php
// Usage: /admin_word_replace.php?url=https://www.kaderock.com
// Shows all unique words and allows admin to define replacements

$mappingFile = __DIR__ . '/word_replacements.json';
$excludeFile = __DIR__ . '/word_exclude.json';

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

// Build unique word list with frequency count
$wordCounts = [];
foreach ($texts as $line) {
    foreach (preg_split('/\W+/u', $line, -1, PREG_SPLIT_NO_EMPTY) as $word) {
        // Filter out numbers and single characters
        if (preg_match('/^\d+$/', $word)) continue; // skip numbers
        if (mb_strlen($word) < 2) continue; // skip single characters
        if (!isset($wordCounts[$word])) {
            $wordCounts[$word] = 0;
        }
        $wordCounts[$word]++;
    }
}
// Order by frequency descending, then alphabetically
uksort($wordCounts, function($a, $b) use ($wordCounts) {
    if ($wordCounts[$a] === $wordCounts[$b]) {
        return strcasecmp($a, $b);
    }
    return $wordCounts[$b] - $wordCounts[$a];
});
$allWords = $wordCounts;

// Load existing replacements
$replacements = [];
if (file_exists($mappingFile)) {
    $replacements = json_decode(file_get_contents($mappingFile), true) ?: [];
}

// Load excluded words
$excludedWords = [];
if (file_exists($excludeFile)) {
    $excludedWords = json_decode(file_get_contents($excludeFile), true) ?: [];
}

// Remove excluded words from the list
foreach ($excludedWords as $exWord) {
    unset($allWords[$exWord]);
}

// Move words with replacements to the bottom
$withReplacement = [];
$withoutReplacement = [];
foreach ($allWords as $word => $count) {
    if (isset($replacements[$word])) {
        $withReplacement[$word] = $count;
    } else {
        $withoutReplacement[$word] = $count;
    }
}
$allWords = $withoutReplacement + $withReplacement;

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
    echo '<div style="background: #cfc; padding: 10px;">Replacements smurfed!</div>';
}

// Handle AJAX exclude/unexclude requests
if (isset($_POST['exclude_word'])) {
    $word = $_POST['exclude_word'];
    if (!in_array($word, $excludedWords, true)) {
        $excludedWords[] = $word;
        file_put_contents($excludeFile, json_encode($excludedWords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    echo 'excluded';
    exit;
}
if (isset($_POST['unexclude_word'])) {
    $word = $_POST['unexclude_word'];
    $excludedWords = array_values(array_diff($excludedWords, [$word]));
    file_put_contents($excludeFile, json_encode($excludedWords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo 'unexcluded';
    exit;
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
        .word-list { display: flex; flex-direction: column; gap: 0.5em; }
        .word-row { display: flex; align-items: center; gap: 0.5em; background: #fafafa; padding: 0.5em; border-radius: 6px; }
        .word-label { min-width: 90px; font-weight: bold; font-size: 1em; color: #333; }
        .word-row input[type=text] { flex: 1; min-width: 0; }
        button { font-size: 1em; padding: 8px 16px; margin-top: 1em; }
        /* Custom scrollbar for mobile friendliness */
        .word-list::-webkit-scrollbar { height: 8px; width: 8px; background: #eee; border-radius: 8px; }
        .word-list::-webkit-scrollbar-thumb { background: #bbb; border-radius: 8px; }
        .word-list { scrollbar-width: thin; scrollbar-color: #bbb #eee; }
        @media (max-width: 600px) {
            .container { padding: 0.5em; }
            .word-label { font-size: 1em; min-width: 60px; }
            .word-row { padding: 0.4em; }
        }
    </style>
</head>
<body>
<div class="container">
<h2>Word Replacement Admin</h2>
<p>URL: <code><?= htmlspecialchars($url) ?></code></p>
<form method="post" id="replaceForm">
<div class="word-list">
<?php foreach ($allWords as $word => $count): ?>
  <div class="word-row">
    <span class="word-label" title="Frequency: <?= $count ?>"><?= htmlspecialchars($word) ?></span>
    <input type="text" name="replace_<?= md5($word) ?>" value="<?= isset($replacements[$word]) ? htmlspecialchars($replacements[$word]) : '' ?>" data-word="<?= htmlspecialchars($word) ?>">
    <button type="button" class="exclude-btn" data-word="<?= htmlspecialchars($word) ?>">Exclude</button>
  </div>
<?php endforeach; ?>
</div>
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
                indicator.textContent = 'Autosmurfed';
                setTimeout(() => { indicator.textContent = ''; }, 1200);
            });
        }, 500); // debounce
    }
});

// Exclude button logic
form.addEventListener('click', function(e) {
    if (e.target.classList.contains('exclude-btn')) {
        const word = e.target.getAttribute('data-word');
        if (confirm('Exclude "' + word + '" from the list?')) {
            const fd = new FormData();
            fd.append('exclude_word', word);
            fetch(window.location.href, { method: 'POST', body: fd })
                .then(r => r.text())
                .then(() => { e.target.closest('.word-row').remove(); });
        }
    }
});
</script>
</div>
</body>
</html>
