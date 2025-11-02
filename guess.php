
<?php
session_start();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$guess = trim($input['guess'] ?? '');

if ($guess === '') { 
    echo json_encode(['ok'=>false,'error'=>'empty','correct'=>false]); 
    exit; 
}

if (empty($_SESSION['current_track']['answer'])) { 
    echo json_encode(['ok'=>false,'error'=>'no_track','correct'=>false]); 
    exit; 
}

function normalize($s) {
    $s = mb_strtolower((string)$s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9\s]/u', ' ', $s);
    $s = preg_replace('/\s+/', ' ', $s);
    return trim($s);
}

$answer = $_SESSION['current_track']['answer'];
$nGuess = normalize($guess);
$correct = false;

if ($nGuess !== '' && $answer !== '') {
    
    if ($nGuess === $answer) {
        $correct = true;
    }
  
    else if (strpos($nGuess, $answer) !== false || strpos($answer, $nGuess) !== false) {
        $correct = true;
    }
  
    else {
        $aWords = array_values(array_filter(explode(' ', $answer)));
        $gWords = array_values(array_filter(explode(' ', $nGuess)));
        if (!empty($aWords) && !empty($gWords)) {
            $matches = 0;
            foreach ($aWords as $w) {
                if (strlen($w) < 3) continue; // Skip short words like "a", "in", etc.
                foreach ($gWords as $gw) {
                    if ($gw === $w) { 
                        $matches++; 
                        break; 
                    }
                }
            }
 
            $significantWords = count(array_filter($aWords, function($w) { return strlen($w) >= 3; }));
            if ($matches >= max(1, ceil($significantWords / 2))) {
                $correct = true;
            }
        }
    }
}


if (!isset($_SESSION['attempts'])) $_SESSION['attempts'] = 0;
if (!isset($_SESSION['score'])) $_SESSION['score'] = 0;

$_SESSION['attempts']++;

if ($correct) {
 
    $points = max(100 - ($_SESSION['attempts'] * 10), 10);
    $_SESSION['score'] += $points;
    $_SESSION['round'] = ($_SESSION['round'] ?? 1) + 1;
}

echo json_encode([
    'ok' => true,
    'correct' => $correct,
    'revealed' => $_SESSION['current_track']['label'] ?? '',
    'attempts' => $_SESSION['attempts'],
    'score' => $_SESSION['score']
]);
exit;
?>


