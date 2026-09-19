<?php
// helpers/player_avatar.php
// Dynamic Player Position Silhouette & Avatar Generator

function getPlayerPositionKey($position) {
    $p = strtolower(trim((string)($position ?? '')));
    if (empty($p)) return 'allrounder';
    if (strpos($p, 'goal') !== false || strpos($p, 'gk') !== false) return 'goalkeeper';
    if (strpos($p, 'defen') !== false || strpos($p, 'back') !== false || strpos($p, 'cb') !== false || strpos($p, 'lb') !== false || strpos($p, 'rb') !== false || strpos($p, 'sweep') !== false) return 'defender';
    if (strpos($p, 'mid') !== false) return 'midfielder';
    if (strpos($p, 'wing') !== false) return 'winger';
    if (strpos($p, 'forw') !== false || strpos($p, 'strik') !== false || strpos($p, 'cf') !== false || strpos($p, 'target') !== false || strpos($p, 'false') !== false) return 'forward';
    if (strpos($p, 'bat') !== false) return 'batsman';
    if (strpos($p, 'bowl') !== false) return 'bowler';
    if (strpos($p, 'wicket') !== false || strpos($p, 'keeper') !== false) return 'wicketkeeper';
    if (strpos($p, 'all') !== false || strpos($p, 'round') !== false) return 'allrounder';
    return 'allrounder';
}

function renderPlayerAvatar($position, $jerseyNo = '', $size = 64) {
    $key = getPlayerPositionKey($position);
    $jno = trim((string)($jerseyNo ?? ''));
    $fs  = (strlen($jno) > 1) ? '13.5' : '16';
    
    // Number text element
    $numTextChest = $jno !== '' 
        ? '<text x="50" y="53" text-anchor="middle" font-size="' . $fs . '" font-family="Outfit, Inter, sans-serif" font-weight="900" fill="#1b5e20">' . htmlspecialchars($jno) . '</text>'
        : '';
    $numTextMid = $jno !== '' 
        ? '<text x="48" y="51" text-anchor="middle" font-size="' . $fs . '" font-family="Outfit, Inter, sans-serif" font-weight="900" fill="#1b5e20">' . htmlspecialchars($jno) . '</text>'
        : '';
    $numTextFwd = $jno !== '' 
        ? '<text x="49" y="49" text-anchor="middle" font-size="' . $fs . '" font-family="Outfit, Inter, sans-serif" font-weight="900" fill="#1b5e20">' . htmlspecialchars($jno) . '</text>'
        : '';
    $numTextBat = $jno !== '' 
        ? '<text x="46" y="49" text-anchor="middle" font-size="' . $fs . '" font-family="Outfit, Inter, sans-serif" font-weight="900" fill="#1b5e20">' . htmlspecialchars($jno) . '</text>'
        : '';

    switch ($key) {
        case 'goalkeeper':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="50" cy="22" r="7" fill="#ffffff"/>
                <path d="M 40 35 L 26 22 L 20 15 C 18 12 24 10 27 14 L 33 22 L 40 30 Z" fill="#ffffff"/>
                <path d="M 60 35 L 74 22 L 80 15 C 82 12 76 10 73 14 L 67 22 L 60 30 Z" fill="#ffffff"/>
                <circle cx="21" cy="14" r="3.5" fill="#ffffff"/>
                <circle cx="79" cy="14" r="3.5" fill="#ffffff"/>
                <path d="M 37 34 L 63 34 L 61 64 L 39 64 Z" fill="#ffffff"/>
                <path d="M 39 64 L 61 64 L 64 78 L 52 78 L 50 72 L 48 78 L 36 78 Z" fill="#ffffff"/>
                <rect x="40" y="78" width="6" height="12" rx="3" fill="#ffffff"/>
                <rect x="54" y="78" width="6" height="12" rx="3" fill="#ffffff"/>
                ' . $numTextChest . '
            </svg>';

        case 'defender':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="50" cy="21" r="7.5" fill="#ffffff"/>
                <path d="M 33 34 C 33 30 67 30 67 34 L 64 64 L 36 64 Z" fill="#ffffff"/>
                <path d="M 34 35 L 23 48 L 26 58 L 31 56 L 28 49 L 36 38 Z" fill="#ffffff"/>
                <path d="M 66 35 L 77 48 L 74 58 L 69 56 L 72 49 L 64 38 Z" fill="#ffffff"/>
                <path d="M 75 52 L 85 52 C 85 62 75 66 75 66 C 75 66 65 62 65 52 Z" fill="#ffffff" opacity="0.95"/>
                <path d="M 36 64 L 64 64 L 67 79 L 53 79 L 50 73 L 47 79 L 33 79 Z" fill="#ffffff"/>
                <rect x="37" y="79" width="7" height="12" rx="3.5" fill="#ffffff"/>
                <rect x="56" y="79" width="7" height="12" rx="3.5" fill="#ffffff"/>
                ' . $numTextChest . '
            </svg>';

        case 'midfielder':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="48" cy="20" r="7" fill="#ffffff"/>
                <path d="M 34 33 L 62 33 L 59 62 L 37 62 Z" fill="#ffffff"/>
                <path d="M 35 34 L 24 45 L 28 54 L 32 52 L 29 46 L 37 38 Z" fill="#ffffff"/>
                <path d="M 61 34 L 72 43 L 78 50 L 74 53 L 69 46 L 60 38 Z" fill="#ffffff"/>
                <path d="M 37 62 L 59 62 L 61 76 L 50 76 L 48 71 L 46 76 L 35 76 Z" fill="#ffffff"/>
                <rect x="37" y="76" width="6" height="15" rx="3" fill="#ffffff"/>
                <path d="M 52 76 L 58 76 L 64 88 L 59 89 Z" fill="#ffffff"/>
                <circle cx="73" cy="81" r="8" fill="#ffffff"/>
                <circle cx="73" cy="81" r="3.2" fill="#1b5e20"/>
                <circle cx="73" cy="74" r="1.5" fill="#1b5e20"/>
                <circle cx="79" cy="79" r="1.5" fill="#1b5e20"/>
                <circle cx="76" cy="87" r="1.5" fill="#1b5e20"/>
                <circle cx="68" cy="86" r="1.5" fill="#1b5e20"/>
                ' . $numTextMid . '
            </svg>';

        case 'forward':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="52" cy="19" r="7" fill="#ffffff"/>
                <path d="M 39 32 L 65 32 L 58 60 L 36 58 Z" fill="#ffffff"/>
                <path d="M 40 34 L 27 42 L 23 39 L 36 31 Z" fill="#ffffff"/>
                <path d="M 64 33 L 76 43 L 73 47 L 62 38 Z" fill="#ffffff"/>
                <path d="M 36 58 L 58 60 L 59 73 L 48 72 L 34 69 Z" fill="#ffffff"/>
                <path d="M 35 69 L 41 71 L 32 87 L 26 85 Z" fill="#ffffff"/>
                <path d="M 52 72 L 58 73 L 73 66 L 75 72 L 57 80 Z" fill="#ffffff"/>
                <circle cx="83" cy="58" r="7.5" fill="#ffffff"/>
                <circle cx="83" cy="58" r="3" fill="#1b5e20"/>
                <path d="M 73 54 L 67 52" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>
                <path d="M 72 62 L 66 64" stroke="#ffffff" stroke-width="1.8" stroke-linecap="round"/>
                ' . $numTextFwd . '
            </svg>';

        case 'winger':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="50" cy="19" r="7" fill="#ffffff"/>
                <path d="M 38 31 L 63 32 L 57 60 L 37 58 Z" fill="#ffffff"/>
                <path d="M 38 33 L 26 43 L 22 41 L 34 30 Z" fill="#ffffff"/>
                <path d="M 62 33 L 74 44 L 70 48 L 59 38 Z" fill="#ffffff"/>
                <path d="M 37 58 L 57 60 L 59 73 L 35 70 Z" fill="#ffffff"/>
                <path d="M 36 70 L 41 71 L 31 87 L 25 85 Z" fill="#ffffff"/>
                <path d="M 52 72 L 58 73 L 70 85 L 65 88 Z" fill="#ffffff"/>
                <circle cx="79" cy="80" r="7" fill="#ffffff"/>
                <path d="M 80 44 L 86 48 L 80 52" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round"/>
                <path d="M 87 44 L 93 48 L 87 52" fill="none" stroke="#ffffff" stroke-width="2.2" stroke-linecap="round"/>
                ' . $numTextChest . '
            </svg>';

        case 'batsman':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="44" cy="20" r="7" fill="#ffffff"/>
                <path d="M 44 20 L 54 22 L 50 25 Z" fill="#ffffff"/>
                <path d="M 34 32 L 58 32 L 56 62 L 36 62 Z" fill="#ffffff"/>
                <path d="M 36 34 L 46 44 L 42 47 L 34 38 Z" fill="#ffffff"/>
                <path d="M 56 34 L 48 44 L 52 47 L 58 38 Z" fill="#ffffff"/>
                <line x1="48" y1="45" x2="62" y2="58" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>
                <path d="M 60 55 L 75 74 C 77 77 75 80 72 80 L 63 68 Z" fill="#ffffff"/>
                <rect x="36" y="62" width="8" height="26" rx="4" fill="#ffffff"/>
                <rect x="48" y="62" width="8" height="26" rx="4" fill="#ffffff"/>
                ' . $numTextBat . '
            </svg>';

        case 'bowler':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="48" cy="20" r="7" fill="#ffffff"/>
                <path d="M 56 32 L 68 18 L 74 12" stroke="#ffffff" stroke-width="4.5" stroke-linecap="round"/>
                <circle cx="77" cy="11" r="5" fill="#ffffff"/>
                <path d="M 40 32 L 28 24 L 22 26" stroke="#ffffff" stroke-width="4" stroke-linecap="round"/>
                <path d="M 38 32 L 62 32 L 58 62 L 38 62 Z" fill="#ffffff"/>
                <path d="M 38 62 L 44 62 L 35 88 L 29 87 Z" fill="#ffffff"/>
                <path d="M 52 62 L 58 62 L 67 85 L 61 88 Z" fill="#ffffff"/>
                ' . $numTextChest . '
            </svg>';

        case 'wicketkeeper':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="50" cy="24" r="7" fill="#ffffff"/>
                <path d="M 38 36 L 62 36 L 60 62 L 40 62 Z" fill="#ffffff"/>
                <circle cx="28" cy="52" r="6" fill="#ffffff"/>
                <circle cx="72" cy="52" r="6" fill="#ffffff"/>
                <path d="M 38 38 L 29 50" stroke="#ffffff" stroke-width="4.5" stroke-linecap="round"/>
                <path d="M 62 38 L 71 50" stroke="#ffffff" stroke-width="4.5" stroke-linecap="round"/>
                <path d="M 40 62 L 28 72 L 34 86 L 42 86 L 38 74 L 46 62 Z" fill="#ffffff"/>
                <path d="M 60 62 L 72 72 L 66 86 L 58 86 L 62 74 L 54 62 Z" fill="#ffffff"/>
                <line x1="16" y1="52" x2="16" y2="82" stroke="#ffffff" stroke-width="1.8" opacity="0.6"/>
                <line x1="20" y1="52" x2="20" y2="82" stroke="#ffffff" stroke-width="1.8" opacity="0.6"/>
                ' . $numTextChest . '
            </svg>';

        default: // allrounder / athlete
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="' . $size . '" height="' . $size . '" style="display:inline-block;vertical-align:middle;">
                <circle cx="50" cy="50" r="46" fill="rgba(255,255,255,0.18)" stroke="rgba(255,255,255,0.5)" stroke-width="2.5"/>
                <circle cx="50" cy="20" r="7.5" fill="#ffffff"/>
                <path d="M 35 32 L 65 32 L 61 63 L 39 63 Z" fill="#ffffff"/>
                <path d="M 35 34 L 24 45 L 28 55 L 34 52 L 30 46 L 38 38 Z" fill="#ffffff"/>
                <path d="M 65 34 L 76 45 L 72 55 L 66 52 L 70 46 L 62 38 Z" fill="#ffffff"/>
                <path d="M 39 63 L 61 63 L 64 78 L 50 78 L 48 72 L 46 78 L 36 78 Z" fill="#ffffff"/>
                <rect x="38" y="78" width="7" height="13" rx="3.5" fill="#ffffff"/>
                <rect x="55" y="78" width="7" height="13" rx="3.5" fill="#ffffff"/>
                ' . $numTextChest . '
            </svg>';
    }
}
