<?php
session_start();
$username = $_SESSION['username'] ?? null;

/**
 * Configure your Spotify credentials here or set them in environment variables.
 * Get credentials from: https://developer.spotify.com/dashboard/
 */
$SPOTIFY_CLIENT_ID     = getenv('SPOTIFY_CLIENT_ID') ?: 'YOUR_SPOTIFY_CLIENT_ID';
$SPOTIFY_CLIENT_SECRET = getenv('SPOTIFY_CLIENT_SECRET') ?: 'YOUR_SPOTIFY_CLIENT_SECRET';

function getSpotifyToken($id, $secret) {
    $ch = curl_init('https://accounts.spotify.com/api/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($id . ':' . $secret),
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    $res = curl_exec($ch);
    curl_close($ch);
    if (!$res) return null;
    $data = json_decode($res, true);
    return $data['access_token'] ?? null;
}

function searchRandomTrackWithPreview($token, $attempts = 6) {
    if (!$token) return null;
    for ($i = 0; $i < $attempts; $i++) {
        // random query - single letter or popular word to broaden results
        $queries = ['a','e','i','o','u','the','love','pop','rock','2020','2021'];
        $q = $queries[array_rand($queries)];
        $q = rawurlencode($q);
        $url = "https://api.spotify.com/v1/search?q={$q}&type=track&limit=50";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
        $res = curl_exec($ch);
        curl_close($ch);
        if (!$res) continue;
        $data = json_decode($res, true);
        if (empty($data['tracks']['items'])) continue;
        // shuffle and pick a track that has preview_url
        $items = $data['tracks']['items'];
        shuffle($items);
        foreach ($items as $t) {
            if (!empty($t['preview_url'])) {
                return [
                    'preview_url' => $t['preview_url'],
                    'name' => $t['name'] ?? '',
                    'artist' => $t['artists'][0]['name'] ?? ''
                ];
            }
        }
    }
    return null;
}

$preview = null;
if ($SPOTIFY_CLIENT_ID !== 'YOUR_SPOTIFY_CLIENT_ID' && $SPOTIFY_CLIENT_SECRET !== 'YOUR_SPOTIFY_CLIENT_SECRET') {
    $token = getSpotifyToken($SPOTIFY_CLIENT_ID, $SPOTIFY_CLIENT_SECRET);
    if ($token) {
        $preview = searchRandomTrackWithPreview($token, 8);
    }
}

// fallback local snippet if Spotify failed / not configured
$audioSrc = $preview['preview_url'] ?? 'horse.ogg';
$trackLabel = isset($preview['name']) ? ($preview['name'] . ' — ' . $preview['artist']) : 'Preview audio';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Songuess — Game</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="wrap">
        <header>
            <div class="brand">
                <div class="logo">SG</div>
                <div>
                    <h1>SONGUESS</h1>
                    <div class="user"><?php echo $username ? "Player: ".htmlspecialchars($username) : "Guest"; ?></div>
                </div>
            </div>
            <div class="user">Round 1 • Guess the song</div>
        </header>

        <main class="player">
            <div class="art">Album artwork / preview</div>

            <div class="controls">
                <audio id="audio" controls preload="metadata">
                    <source src="<?php echo htmlspecialchars($audioSrc); ?>" type="audio/mpeg">
                    Your browser does not support the audio element.
                </audio>
                <div style="margin-top:8px;color:var(--muted);font-size:13px;"><?php echo htmlspecialchars($trackLabel); ?></div>
            </div>

            <form id="guessForm" class="guessbox" onsubmit="return false;">
                <input id="guessInput" type="text" placeholder="Enter song name or artist..." autocomplete="off" />
                <button id="submitBtn" type="button">Guess</button>
            </form>

            <div class="hint">Tip: use song title, artist, or both. Press Enter to submit.</div>
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
                        <div style="font-size:20px">0</div>
                        <div class="status">Attempts</div>
                    </div>
                    <div style="flex:1;background:rgba(255,255,255,0.02);padding:10px;border-radius:8px;text-align:center">
                        <div style="font-size:20px">0</div>
                        <div class="status">Score</div>
                    </div>
                </div>
            </div>
        </aside>
    </div>

    <script>
        const guessesEl = document.getElementById('guesses');
        const input = document.getElementById('guessInput');
        const submit = document.getElementById('submitBtn');

        // correct answer is unknown server-side for random preview, this demo uses 'horse' if local fallback
        const correct = '<?php echo $preview ? addslashes(strtolower($preview['name'])) : "horse"; ?>';

        function addGuess(text, ok){
            const div = document.createElement('div');
            div.className = 'guess';
            div.innerHTML = `<span>${escapeHtml(text)}</span><span class="status">${ok===true ? 'Correct' : ok===false ? 'Wrong' : 'Pending'}</span>`;
            guessesEl.prepend(div);
        }

        function escapeHtml(s){ return s.replace(/[&<>"']/g, c=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' })[c]); }

        submit.addEventListener('click', doGuess);
        input.addEventListener('keydown', e=>{ if(e.key === 'Enter') doGuess(); });

        function doGuess(){
            const val = input.value.trim();
            if(!val) return;
            const isCorrect = correct && (val.toLowerCase().includes(correct) || correct.includes(val.toLowerCase()));
            addGuess(val, isCorrect ? true : false);
            input.value = '';
            if(isCorrect) {
                submit.disabled = true;
                input.disabled = true;
                setTimeout(()=> alert('Nice! You guessed the song.'), 200);
            }
        }
    </script>
</body>
</html>