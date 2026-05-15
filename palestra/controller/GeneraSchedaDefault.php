<?php
/**
 * GeneraSchedaDefault.php
 * Genera automaticamente una scheda di allenamento personalizzata
 * al momento della registrazione, in base a obiettivo e giorni/settimana.
 *
 * Logica:
 *  1 giorno  → Full Body (3 serie × 12 rip, 2 esercizi/gruppo)
 *  2 giorni  → Full Body (3 serie × 12 rip, 2 esercizi/gruppo)
 *  3 giorni  → Full Body (3 serie × 10 rip, 3 esercizi/gruppo)
 *  4 giorni  → Upper / Lower / Upper / Lower
 *  5 giorni  → Push / Pull / Legs / Upper / Lower
 *  6-7 giorni→ PPL×2  (Push/Pull/Legs ripetuti)
 *
 * Utilizzo in AuthController.php, subito prima del redirect:
 *   require_once __DIR__ . '/GeneraSchedaDefault.php';
 *   generaSchedaDefault($db, $nuovo_id, $obiettivo, $giorni_settimana);
 */

function generaSchedaDefault(mysqli $db, int $uid, string $obiettivo, int $giorni): void
{
    // ── 1. Recupera gli id_esercizio dal DB per nome ──────────────────────────
    // Usiamo i nomi esatti inseriti nel seed SQL
    $nomi_usati = [
        // petto
        'Panca Piana', 'Push-up', 'Croci con manubri',
        // schiena
        'Stacchi', 'Trazioni', 'Rematore con manubri',
        // gambe
        'Squat', 'Affondi', 'Leg Press',
        // spalle
        'Military Press', 'Alzate laterali',
        // braccia
        'Curl con manubri', 'Tricep Dips',
        // addome
        'Plank', 'Mountain Climbers',
        // cardio / corpo completo
        'Burpees', 'Squat con salto', 'Corsa sul posto',
    ];

    $placeholders = implode(',', array_fill(0, count($nomi_usati), '?'));
    $st = $db->prepare("SELECT id_esercizio, nome FROM esercizi WHERE nome IN ($placeholders)");
    $types = str_repeat('s', count($nomi_usati));
    $st->bind_param($types, ...$nomi_usati);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);

    $ex = []; // $ex['Nome Esercizio'] = id_esercizio
    foreach ($rows as $r) { $ex[$r['nome']] = (int)$r['id_esercizio']; }

    // ── 2. Blocchi esercizi per split ─────────────────────────────────────────
    // Ogni blocco = [ [id_esercizio, serie, ripetizioni], ... ]
    // Le scelte variano in base all'obiettivo

    // Esercizi base per gruppo
    $petto_base   = [[$ex['Panca Piana'] ?? 0, 3, '10'], [$ex['Push-up'] ?? 0, 3, '12'], [$ex['Croci con manubri'] ?? 0, 3, '12']];
    $schiena_base = [[$ex['Stacchi'] ?? 0, 3, '8'],  [$ex['Trazioni'] ?? 0, 3, '8'],  [$ex['Rematore con manubri'] ?? 0, 3, '12']];
    $gambe_base   = [[$ex['Squat'] ?? 0, 4, '8'],    [$ex['Affondi'] ?? 0, 3, '12'],  [$ex['Leg Press'] ?? 0, 3, '12']];
    $spalle_base  = [[$ex['Military Press'] ?? 0, 3, '10'], [$ex['Alzate laterali'] ?? 0, 3, '15']];
    $braccia_base = [[$ex['Curl con manubri'] ?? 0, 3, '12'], [$ex['Tricep Dips'] ?? 0, 3, '12']];
    $core_base    = [[$ex['Plank'] ?? 0, 3, '45s'],  [$ex['Mountain Climbers'] ?? 0, 3, '20']];
    $cardio_base  = [[$ex['Burpees'] ?? 0, 4, '15'], [$ex['Squat con salto'] ?? 0, 4, '12'], [$ex['Corsa sul posto'] ?? 0, 3, '60s']];

    // Varianti per obiettivo: quanti esercizi prendere per gruppo
    // (prende i primi N dal blocco base)
    $n = match($obiettivo) {
        'perdita_peso'    => ['petto' => 2, 'schiena' => 2, 'gambe' => 2, 'spalle' => 1, 'braccia' => 1, 'core' => 2, 'cardio' => 2],
        'massa_muscolare' => ['petto' => 3, 'schiena' => 3, 'gambe' => 3, 'spalle' => 2, 'braccia' => 2, 'core' => 1, 'cardio' => 0],
        default           => ['petto' => 2, 'schiena' => 2, 'gambe' => 2, 'spalle' => 2, 'braccia' => 1, 'core' => 2, 'cardio' => 1],
    };

    // Aggiusta serie/rip per obiettivo
    $mod = match($obiettivo) {
        'perdita_peso'    => ['serie' => 4, 'rip' => '15'],
        'massa_muscolare' => ['serie' => 4, 'rip' => '8'],
        default           => ['serie' => 3, 'rip' => '12'],
    };

    // Helper: prende gli esercizi di un gruppo con serie/rip dell'obiettivo
    $gruppo = function(array $blocco, int $quanti, ?string $rip_override = null) use ($mod): array {
        $out = [];
        foreach (array_slice($blocco, 0, $quanti) as $e) {
            if (!$e[0]) continue; // skip se id non trovato
            $out[] = [$e[0], $mod['serie'], $rip_override ?? $e[2]];
        }
        return $out;
    };

    // ── 3. Componi i giorni in base allo split ────────────────────────────────
    // $piano = [ giorno_num => [ [id, serie, rip], ... ], ... ]
    // giorno_num: 1=Lunedì … 7=Domenica

    $piano = [];

    if ($giorni <= 3) {
        // ── FULL BODY ─────────────────────────────────────────────────────────
        // 1g→Lun, 2g→Lun+Gio, 3g→Lun+Mer+Ven
        $map_giorni = [
            1 => [1],
            2 => [1, 4],
            3 => [1, 3, 5],
        ];
        $giorni_fb = $map_giorni[$giorni];

        // Full body: petto + schiena + gambe + spalle + (braccia) + core + (cardio)
        $fb = array_merge(
            $gruppo($petto_base,   $n['petto']),
            $gruppo($schiena_base, $n['schiena']),
            $gruppo($gambe_base,   $n['gambe']),
            $gruppo($spalle_base,  $n['spalle']),
            $n['braccia'] > 0 ? $gruppo($braccia_base, $n['braccia']) : [],
            $gruppo($core_base,    $n['core']),
            $n['cardio'] > 0  ? $gruppo($cardio_base,  $n['cardio'])  : []
        );

        foreach ($giorni_fb as $g) {
            $piano[$g] = $fb;
        }

    } elseif ($giorni === 4) {
        // ── UPPER / LOWER / UPPER / LOWER → Lun Mer Gio Sab ─────────────────
        $upper = array_merge(
            $gruppo($petto_base,   $n['petto']),
            $gruppo($schiena_base, $n['schiena']),
            $gruppo($spalle_base,  $n['spalle']),
            $gruppo($braccia_base, $n['braccia'])
        );
        $lower = array_merge(
            $gruppo($gambe_base, $n['gambe']),
            $gruppo($core_base,  $n['core']),
            $n['cardio'] > 0 ? $gruppo($cardio_base, $n['cardio']) : []
        );
        $piano = [1 => $upper, 3 => $lower, 4 => $upper, 6 => $lower];

    } elseif ($giorni === 5) {
        // ── PUSH / PULL / LEGS / UPPER / LOWER → Lun…Ven ────────────────────
        $push = array_merge($gruppo($petto_base, $n['petto']), $gruppo($spalle_base, $n['spalle']), $gruppo($braccia_base, 1));
        $pull = array_merge($gruppo($schiena_base, $n['schiena']), $gruppo($braccia_base, $n['braccia']));
        $legs = array_merge($gruppo($gambe_base, $n['gambe']), $gruppo($core_base, $n['core']));
        $upper = array_merge($gruppo($petto_base, 2), $gruppo($schiena_base, 2), $gruppo($spalle_base, 1));
        $lower = array_merge($gruppo($gambe_base, 2), $gruppo($core_base, 1), $n['cardio'] > 0 ? $gruppo($cardio_base, 1) : []);
        $piano = [1 => $push, 2 => $pull, 3 => $legs, 4 => $upper, 5 => $lower];

    } else {
        // ── PPL×2 → 6-7 giorni: Lun Push / Mar Pull / Mer Legs / Gio Push / Ven Pull / Sab Legs ─
        $push = array_merge($gruppo($petto_base, $n['petto']), $gruppo($spalle_base, $n['spalle']), $gruppo($braccia_base, 1));
        $pull = array_merge($gruppo($schiena_base, $n['schiena']), $gruppo($braccia_base, $n['braccia']));
        $legs = array_merge($gruppo($gambe_base, $n['gambe']), $gruppo($core_base, $n['core']));
        $piano = [1 => $push, 2 => $pull, 3 => $legs, 4 => $push, 5 => $pull, 6 => $legs];
        if ($giorni === 7) {
            $active = array_merge($gruppo($cardio_base, 2), $gruppo($core_base, 1));
            $piano[7] = $active; // Domenica: cardio + core leggero
        }
    }

    // ── 4. Crea la scheda nel DB ──────────────────────────────────────────────
    $nomi_scheda = [
        'perdita_peso'    => 'Scheda Dimagrimento',
        'massa_muscolare' => 'Scheda Massa',
        'tonificazione'   => 'Scheda Tonificazione',
    ];
    $nome_scheda = $nomi_scheda[$obiettivo] ?? 'La mia scheda';

    $st = $db->prepare("INSERT INTO schede (id_utente, nome, obiettivo, attiva) VALUES (?, ?, ?, 1)");
    $st->bind_param("iss", $uid, $nome_scheda, $obiettivo);
    $st->execute();
    $id_scheda = $db->insert_id;

    // ── 5. Inserisci gli esercizi per ogni giorno ─────────────────────────────
    $st2 = $db->prepare("
        INSERT INTO scheda_esercizi (id_scheda, id_esercizio, giorno, serie, ripetizioni, ordine)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($piano as $giorno_num => $esercizi) {
        foreach ($esercizi as $ordine => $e) {
            [$id_ex, $serie, $rip] = $e;
            if (!$id_ex) continue;
            $ordine_val = $ordine + 1;
            $st2->bind_param("iiiisi", $id_scheda, $id_ex, $giorno_num, $serie, $rip, $ordine_val);
            $st2->execute();
        }
    }
}