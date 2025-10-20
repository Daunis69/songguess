<?php
session_start();
$username = $_SESSION['username'] ?? null;
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
                    <source src="horse.ogg" type="audio/ogg">
                    Your browser does not support the audio element.
                </audio>
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


        const correct = 'horse'; 

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
            const isCorrect = typeof correct === 'string' ? (val.toLowerCase().includes(correct) || correct.includes(val.toLowerCase())) : null;
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