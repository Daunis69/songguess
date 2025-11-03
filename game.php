<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$username = $_SESSION['username'] ?? 'Guest';

function normalize_answer($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

if (!isset($_SESSION['score'])) $_SESSION['score'] = 0;
if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
if (!isset($_SESSION['round'])) $_SESSION['round'] = 1;

if (!isset($_SESSION['current_track'])) {
    $_SESSION['current_track'] = [
        'image' => '',
        'label' => 'Click "Get Random Song" to start',
        'answer' => '',
        'preview_url' => ''
    ];
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
      <img id="cover" src="" alt="Album cover" style="width:100%;height:100%;object-fit:cover;border-radius:8px;display:none;">
      <div id="coverPlaceholder" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:16px;padding:12px;">
        Click "Get Random Song" to load a cover
      </div>
    </div>

    <div class="controls" style="margin-top:12px">
      <audio id="player" controls style="width:100%;margin-bottom:8px;display:<?php echo !empty($_SESSION['current_track']['preview_url']) ? 'block' : 'none'; ?>"></audio>
      <div style="margin-top:8px;color:var(--muted);font-size:13px;" id="trackLabel"><?php echo htmlspecialchars($_SESSION['current_track']['label']); ?></div>
    </div>

    <form id="guessForm" class="guessbox" onsubmit="return false;">
      <input id="guessInput" type="text" placeholder="Enter song name or artist..." autocomplete="off" />
      <button id="submitBtn" type="button">Guess</button>
    </form>

    <div class="hint">Look at the album cover and listen to the preview. Press Enter to submit.</div>
    
    <div style="margin-top:16px;display:flex;gap:8px;">
      <button id="newSongBtn" style="flex:1;background:var(--accent);color:var(--bg);border:none;padding:10px;border-radius:8px;cursor:pointer;font-weight:600;">Get Random Song</button>
      <button id="hintBtn" style="flex:1;background:rgba(255,255,255,0.1);color:var(--muted);border:none;padding:10px;border-radius:8px;cursor:pointer;font-weight:600;">Show Cover (Hint)</button>
      <button onclick="revealAnswer()" style="background:rgba(255,255,255,0.05);color:var(--muted);border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">Reveal Answer</button>
      <button onclick="location.href='logout.php'" style="background:rgba(255,255,255,0.05);color:var(--muted);border:none;padding:10px 16px;border-radius:8px;cursor:pointer;">Logout</button>
    </div>
  </main>

  <aside class="sidebar">
      <div style="display:flex;flex-direction:column;gap:16px">
          <div>
              <div class="section-title">Guesses</div>
              <div id="guesses" class="guesses"></div>
          </div>

          <div>
              <div class="section-title">Filters</div>
              <div style="display:flex;flex-direction:column;gap:8px;padding:10px;background:rgba(255,255,255,0.02);border-radius:8px">
                  <label style="font-size:13px;color:var(--muted)">Genre</label>
                  <select id="genreSelect" style="padding:8px;border-radius:6px;background:transparent;color:inherit;border:1px solid rgba(255,255,255,0.04)">
                      <option value="">Any</option>
                      <option>pop</option>
                      <option>rock</option>
                      <option>hiphop</option>
                      <option>jazz</option>
                      <option>classical</option>
                      <option>country</option>
                      <option>electronic</option>
                      <option>indie</option>
                      <option>soul</option>
                  </select>

                  <label style="font-size:13px;color:var(--muted)">Artist (optional)</label>
                  <input id="artistInput" placeholder="Enter artist name" style="padding:8px;border-radius:6px;background:transparent;color:inherit;border:1px solid rgba(255,255,255,0.04)">

                  <div style="display:flex;gap:8px;margin-top:6px">
                      <button id="applyFiltersBtn" style="flex:1;padding:8px;border-radius:6px;border:none;background:linear-gradient(180deg,var(--accent),var(--accent-2));color:var(--bg);cursor:pointer">Apply</button>
                      <button id="clearFiltersBtn" style="flex:1;padding:8px;border-radius:6px;border:none;background:rgba(255,255,255,0.03);color:var(--muted);cursor:pointer">Clear</button>
                  </div>
              </div>
          </div>

          <div>
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
      </div>
   </aside>
</div>

<script>
const input = document.getElementById('guessInput');
const submit = document.getElementById('submitBtn');
const guessesEl = document.getElementById('guesses');
const attemptsEl = document.getElementById('attempts');
const scoreEl = document.getElementById('score');
const player = document.getElementById('player');
const newSongBtn = document.getElementById('newSongBtn');
const hintBtn = document.getElementById('hintBtn');
const coverImg = document.getElementById('cover');
const coverPlaceholder = document.getElementById('coverPlaceholder');
const trackLabel = document.getElementById('trackLabel');

let currentTrack = null;
let activeGenre = '';
let activeArtist = '';

// new UI elements
const genreSelect = document.getElementById('genreSelect');
const artistInput = document.getElementById('artistInput');
const applyFiltersBtn = document.getElementById('applyFiltersBtn');
const clearFiltersBtn = document.getElementById('clearFiltersBtn');

applyFiltersBtn.addEventListener('click', () => {
  activeGenre = genreSelect.value || '';
  activeArtist = artistInput.value.trim();
  alert('Filters applied: ' + (activeArtist || activeGenre || 'None'));
});

clearFiltersBtn.addEventListener('click', () => {
  genreSelect.value = '';
  artistInput.value = '';
  activeGenre = '';
  activeArtist = '';
  alert('Filters cleared');
});

// Get random song from iTunes API (uses filters if set)
async function getRandomSong() {
  let searchTerms = [];
  if (activeArtist) searchTerms.push(activeArtist);
  if (activeGenre) searchTerms.push(activeGenre);
  if (searchTerms.length === 0) {
    const randomTerms = ["pop", "rock", "hiphop", "jazz", "classical", "country", "dance", "indie", "soul", "funk"];
    searchTerms.push(randomTerms[Math.floor(Math.random() * randomTerms.length)]);
  }
  const searchTerm = searchTerms.join(' ');
  try {
    const response = await fetch(`https://itunes.apple.com/search?term=${encodeURIComponent(searchTerm)}&entity=song&limit=50`);
    const data = await response.json();
    if (!data.results || data.results.length === 0) {
      alert("Couldn't find any songs, try a different filter.");
      return;
    }
    let track = null;
    const shuffled = data.results.sort(() => 0.5 - Math.random());
    for (let t of shuffled) {
      if (t.previewUrl) {
        track = t;
        break;
      }
    }
    if (!track) {
      alert("No tracks with preview found, try again.");
      return;
    }
    currentTrack = track;
    const artworkUrl = track.artworkUrl100.replace('100x100', '600x600');
    coverImg.style.display = 'none';
    coverImg.src = '';
    coverPlaceholder.style.display = 'flex';
    player.src = track.previewUrl;
    player.style.display = 'block';
    try { player.play(); } catch(e){}
    trackLabel.textContent = '🎧 Listening... Guess the song!';
    await fetch('save_track.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        image: artworkUrl,
        label: `${track.trackName} – ${track.artistName}`,
        answer: normalize_answer(`${track.trackName} ${track.artistName}`),
        preview_url: track.previewUrl,
        track_name: track.trackName,
        artist_name: track.artistName
      })
    });
    currentTrack.artworkUrl = artworkUrl;
  } catch (err) {
    console.error('Error fetching song:', err);
    alert('Error loading song. Please try again.');
  }
}

function normalize_answer(s) {
  s = s.toLowerCase();
  s = s.replace(/[^a-z0-9\s]/g, ' ');
  s = s.replace(/\s+/g, ' ');
  return s.trim();
}

newSongBtn.addEventListener('click', getRandomSong);

hintBtn.addEventListener('click', () => {
  if (!currentTrack || !currentTrack.artworkUrl) {
    alert('No cover available. Get a random song first.');
    return;
  }
  coverImg.src = currentTrack.artworkUrl;
  coverImg.style.display = 'block';
  coverPlaceholder.style.display = 'none';
});

if (input && submit) {
  input.addEventListener('keydown', e => { if (e.key === 'Enter') doGuess(); });
  submit.addEventListener('click', doGuess);
}

async function doGuess(){
  const val = input.value.trim();
  if (!val) return;
  if (!currentTrack) {
    alert('Get a song first!');
    return;
  }
  const guessDiv = addGuess(val,'⏳ Checking...');
  input.value = '';
  try {
    const userGuess = normalize_answer(val);
    const correctSong = normalize_answer(currentTrack.trackName || '');
    const correctArtist = normalize_answer(currentTrack.artistName || '');
    const currentAttempts = parseInt(attemptsEl.textContent) + 1;
    attemptsEl.textContent = currentAttempts;
    const isCorrect = (userGuess && (userGuess.includes(correctSong) || correctSong.includes(userGuess) || userGuess.includes(correctArtist) || correctArtist.includes(userGuess)));
    if (isCorrect) {
      updateGuessStatus(guessDiv, '✓ Correct!', '#1db954');
      const currentScore = parseInt(scoreEl.textContent) + Math.max(100 - (currentAttempts * 10), 10);
      scoreEl.textContent = currentScore;
      player.pause();
      setTimeout(() => {
        alert(`🎉 Correct! It was "${currentTrack.trackName}" by ${currentTrack.artistName}`);
      }, 300);
      await fetch('update_score.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          score: parseInt(scoreEl.textContent),
          attempts: currentAttempts
        })
      });
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
  if (!currentTrack) {
    alert('Get a song first!');
    return;
  }
  if (confirm('Are you sure you want to reveal the answer?')) {
    alert(`The answer was: "${currentTrack.trackName}" by ${currentTrack.artistName}`);
    player.pause();
  }
}

function escapeHtml(s){ 
  return s.replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]); 
}
</script>
</body>
</html>