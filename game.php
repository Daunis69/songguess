<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$username = $_SESSION['username'] ?? 'Guest';

$SPOTIFY_CLIENT_ID     = getenv('SPOTIFY_CLIENT_ID') ?: '089a2e52b40f4f23868421b4504b6348';
$SPOTIFY_CLIENT_SECRET = getenv('SPOTIFY_CLIENT_SECRET') ?: 'f861f40c21554950891f324bc3cff835';

function getSpotifyToken($id, $secret) {
    if (!$id || !$secret || $id === '089a2e52b40f4f23868421b4504b6348') {
        return null;
    }
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    if ($res === false) {
        curl_close($ch);
        return null;
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($httpCode !== 200) {
        return null;
    }
    $data = json_decode($res, true);
    return $data['access_token'] ?? null;
}

function searchRandomTrackWithPreview($token, $attempts = 10) {
    if (!$token) return null;
    $queries = ['a','e','i','o','u','the','love','pop','rock','remix','dance','2020','2021','2022','2023','beat','girl','boy','night','home'];
    for ($i = 0; $i < $attempts; $i++) {
        $q = rawurlencode($queries[array_rand($queries)]);
        $url = "https://api.spotify.com/v1/search?q={$q}&type=track&limit=50";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $res = curl_exec($ch);
        if ($res === false) {
            curl_close($ch);
            continue;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            continue;
        }
        $data = json_decode($res, true);
        $items = $data['tracks']['items'] ?? [];
        if (empty($items)) continue;
        shuffle($items);
        foreach ($items as $t) {
            $image = $t['album']['images'][0]['url'] ?? null;
            $preview = $t['preview_url'] ?? null;
            if ($image) {
                return [
                    'image' => $image,
                    'preview_url' => $preview,
                    'name' => $t['name'] ?? '',
                    'artist' => $t['artists'][0]['name'] ?? ''
                ];
            }
        }
    }
    return null;
}

function normalize_answer($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

if (!isset($_SESSION['score'])) $_SESSION['score'] = 0;
if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
if (!isset($_SESSION['round'])) $_SESSION['round'] = 1;

$preview = null;
$useSpotify = false;
if ($SPOTIFY_CLIENT_ID !== '089a2e52b40f4f23868421b4504b6348' && $SPOTIFY_CLIENT_SECRET !== 'f861f40c21554950891f324bc3cff835') {
    $token = getSpotifyToken($SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET);
    if ($token) {
        $preview = searchRandomTrackWithPreview($token, 10);
        if ($preview) $useSpotify = true;
    }
}

$coverSrc = '';
$trackLabel = 'Unknown';
$answer = '';

if ($preview && $useSpotify) {
    $coverSrc = $preview['image'] ?? '';
    $trackLabel = trim(($preview['name'] ?? '') . ' – ' . ($preview['artist'] ?? ''));
    $answer = normalize_answer(($preview['name'] ?? '') . ' ' . ($preview['artist'] ?? ''));
} else {
    $searchDirs = [__DIR__ . '/audio', __DIR__];
    $imgExts = ['jpg','jpeg','png','webp'];
    $foundImage = '';
    foreach ($searchDirs as $d) {
        if (!is_dir($d)) continue;
        foreach ($imgExts as $ext) {
            $matches = glob($d . '/*.' . $ext) ?: [];
            if (!empty($matches)) { $foundImage = $matches[array_rand($matches)]; break 2; }
        }
    }
    if ($foundImage) {
        $rel = str_replace('\\', '/', str_replace(__DIR__, '', $foundImage));
        $rel = ltrim($rel, '/');
        $coverSrc = $rel;
        $trackLabel = basename($foundImage);
        $base = pathinfo($foundImage, PATHINFO_FILENAME);
        $answer = normalize_answer(str_replace(['_','-'], ' ', $base));
    } else {
        if (file_exists(__DIR__ . '/cover.jpg')) {
            $coverSrc = 'cover.jpg';
            $trackLabel = 'Cover';
            $answer = 'unknown';
        } else {
            $coverSrc = '';
            $trackLabel = 'No cover found';
            $answer = 'unknown';
        }
    }
}

if (!empty($coverSrc)) {
    if (preg_match('#^https?://#i', $coverSrc)) {
    } else {
        $localPath = __DIR__ . '/' . ltrim($coverSrc, '/\\');
        if (file_exists($localPath)) {
            $coverSrc = str_replace('\\', '/', ltrim(str_replace(__DIR__, '', $localPath), '/'));
        } else {
            $try1 = __DIR__ . '/audio/' . ltrim($coverSrc, '/\\');
            $try2 = __DIR__ . '/' . ltrim($coverSrc, '/\\');
            if (file_exists($try1)) {
                $coverSrc = str_replace('\\','/', ltrim(str_replace(__DIR__, '', $try1), '/'));
            } elseif (file_exists($try2)) {
                $coverSrc = str_replace('\\','/', ltrim(str_replace(__DIR__, '', $try2), '/'));
            } else {
                $coverSrc = '';
            }
        }
    }
}

$_SESSION['current_track'] = [
    'image' => $coverSrc,
    'label' => $trackLabel,
    'answer' => $answer
];

if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo '<pre style="background:#111;color:#ddd;padding:10px;">';
    echo "coverSrc: " . var_export($coverSrc, true) . PHP_EOL;
    echo "trackLabel: " . var_export($trackLabel, true) . PHP_EOL;
    echo "answer: " . var_export($answer, true) . PHP_EOL;
    echo "session_current_track:" . PHP_EOL;
    print_r($_SESSION['current_track']);
    echo '</pre>';
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Songuess – Guess the Cover</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="wrap">
  <header>
      <div class="brand">
          <div class="logo">SG</div>
          <div>
              <h1>SONGUESS</h1>
              <div class="user"><?php echo htmlspecialchars($username); ?></div>
          </div>
      </div>
      <div class="user">Round <?php echo $_SESSION['round']; ?> • Guess the song by its album cover</div>
  </header>

  <main class="player">
    <div class="art">
      <?php if (!empty($_SESSION['current_track']['image'])): ?>
        <img id="cover" src="<?php echo htmlspecialchars($_SESSION['current_track']['image']); ?>" alt="Album cover" style="width:100%;height:100%;object-fit:cover;border-radius:8px;">
      <?php else: ?>
        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;padding:12px;">
          No cover available — guess by title/artist hint
        </div>
      <?php endif; ?>
    </div>

    <div class="controls" style="margin-top:12px">
      <div style="margin-top:8px;color:var(--muted);font-size:13px;"><?php echo htmlspecialchars($trackLabel); ?></div>
    </div>

    <form id="guessForm" class="guessbox" onsubmit="return false;">
      <input id="guessInput" type="text" placeholder="Enter song name or artist..." autocomplete="off" />
      <button id="submitBtn" type="button">Guess</button>
    </form>

    <div class="hint">Look at the album cover and guess the song. Press Enter to submit.</div>
    
    <div style="margin-top:16px;display:flex;gap:8px;">
      <button onclick="location.href='game.php'" style="flex:1;background:var(--accent);color:var(--bg);border:none;padding:10px;border-radius:8px;cursor:pointer;font-weight:600;">New Cover</button>
      <button onclick="revealAnswer()" style="flex:1;background:rgba(255,255,255,0.1);color:var(--muted);border:none;padding:10px;border-radius:8px;cursor:pointer;font-weight:600;">Reveal Answer</button>
      <button onclick="location.href='logout.php'" style="background:rgba(255,255,255,0.05);color:var(--muted);border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">Logout</button>
    </div>
  </main>

  <aside class="sidebar">
      <div>
          <div class="section-title">Guesses</div>
          <div id="guesses" class="guesses"></div>
      </div>

      <div style="margin-top:16px">
          <div class="section-title">Stats</div>
          <div style="display:flex;gap:10px">
              <div style="flex:1;background:rgba(255,255,255,0.02);padding:10px;border-radius:8px;text-align:center">
                  <div style="font-size:20px" id="attempts"><?php echo $_SESSION['attempts']; ?></div>
                  <div class="status">Attempts</div>
              </div>
              <div style="flex:1;background:rgba(255,255,255,0.02);padding:10px;border-radius:8px;text-align:center">
                  <div style="font-size:20px" id="score"><?php echo $_SESSION['score']; ?></div>
                  <div class="status">Score</div>
              </div>
          </div>
      </div>
  </aside>
</div>

<script>
const input = document.getElementById('guessInput');
const submit = document.getElementById('submitBtn');
const guessesEl = document.getElementById('guesses');
const attemptsEl = document.getElementById('attempts');
const scoreEl = document.getElementById('score');

if (input && submit) {
  input.addEventListener('keydown', e => { if (e.key === 'Enter') doGuess(); });
  submit.addEventListener('click', doGuess);
}

async function doGuess(){
  const val = input.value.trim();
  if (!val) return;
  
  const guessDiv = addGuess(val,'⏳ Checking...');
  input.value = '';
  
  try {
    const res = await fetch('guess.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ guess: val })
    });
    const j = await res.json();
    
    const currentAttempts = parseInt(attemptsEl.textContent) + 1;
    attemptsEl.textContent = currentAttempts;
    
    if (j.correct) {
      updateGuessStatus(guessDiv, '✓ Correct!', '#1db954');
      const currentScore = parseInt(scoreEl.textContent) + Math.max(100 - (currentAttempts * 10), 10);
      scoreEl.textContent = currentScore;
      
      setTimeout(() => {
        alert('🎉 Correct! The answer was: ' + (j.revealed || 'the song'));
      }, 300);
    } else {
      updateGuessStatus(guessDiv, '✗ Wrong', '#ff4444');
    }
  } catch (err) {
    updateGuessStatus(guessDiv, '⚠ Error', '#ff9800');
    console.error('Guess error:', err);
  }
}

function addGuess(text, status){
  const div = document.createElement('div');
  div.className='guess';
  div.innerHTML = `<span>${escapeHtml(text)}</span><span class="status" style="font-size:12px">${status}</span>`;
  guessesEl.prepend(div);
  return div;
}

function updateGuessStatus(div, status, color){
  const statusEl = div.querySelector('.status');
  if (statusEl) {
    statusEl.textContent = status;
    if (color) statusEl.style.color = color;
  }
}

function revealAnswer() {
  if (confirm('Are you sure you want to reveal the answer?')) {
    fetch('reveal.php')
      .then(r => r.json())
      .then(j => {
        alert('The answer was: ' + j.answer);
      });
  }
}

function escapeHtml(s){ 
  return s.replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); 
}
</script>
</body>
</html>