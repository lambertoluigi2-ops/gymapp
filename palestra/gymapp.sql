-- ============================================
--   GymApp — Database Completo e Corretto
--   Compatibile con tutto il codice PHP
-- ============================================

CREATE DATABASE IF NOT EXISTS gymapp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gymapp;

-- ============================================
--   UTENTI
--   Nessuna modifica rispetto all'originale
-- ============================================
CREATE TABLE IF NOT EXISTS utenti (
    id_utente        INT AUTO_INCREMENT PRIMARY KEY,
    google_id        VARCHAR(255) UNIQUE,
    nome             VARCHAR(100) NOT NULL,
    cognome          VARCHAR(100) NOT NULL,
    email            VARCHAR(255) NOT NULL UNIQUE,
    password         VARCHAR(255),
    peso_partenza    FLOAT,
    obiettivo_peso   FLOAT,
    altezza          FLOAT,
    obiettivo        ENUM('perdita_peso','massa_muscolare','tonificazione'),
    giorni_settimana INT,
    foto             VARCHAR(500),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   PASSWORD RESET
--   AGGIUNTA: mancava nel DB originale.
--   Usata in AuthController.php per il
--   recupero password via email + token.
-- ============================================
CREATE TABLE IF NOT EXISTS password_reset (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    id_utente   INT NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    scadenza    DATETIME NOT NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



CREATE TABLE IF NOT EXISTS pesi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_utente INT NOT NULL,
    peso FLOAT NOT NULL,
    data DATE NOT NULL,
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE CASCADE,
    UNIQUE KEY id_utente_data (id_utente, data)
);

-- ============================================
--   ESERCIZI
--   AGGIUNTA colonna `tipo`: usata in
--   scheda.php (e.tipo, $tipo_icon).
--   Valori: forza | cardio | mobilita
-- ============================================
CREATE TABLE IF NOT EXISTS esercizi (
    id_esercizio     INT AUTO_INCREMENT PRIMARY KEY,
    nome             VARCHAR(150) NOT NULL,
    descrizione      TEXT,
    gruppo_muscolare ENUM('petto','schiena','gambe','spalle','braccia','addome','cardio','corpo_completo') NOT NULL,
    tipo             ENUM('forza','cardio','mobilita') NOT NULL DEFAULT 'forza',
    difficolta       ENUM('facile','medio','difficile') DEFAULT 'medio'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   SCHEDE
--   MODIFICHE rispetto all'originale:
--   - Aggiunta colonna `obiettivo` (usata in
--     scheda.php: INSERT INTO schede (..., obiettivo, ...))
--   - Aggiunta colonna `attiva` (usata in
--     scheda.php: WHERE attiva=1, SET attiva=0)
--   - Aggiunta colonna `created_at` (usata in
--     scheda.php: ORDER BY created_at DESC)
--   - `data_inizio` e `data_fine` ora opzionali
--     perché il codice non le passa mai
-- ============================================
CREATE TABLE IF NOT EXISTS schede (
    id_scheda   INT AUTO_INCREMENT PRIMARY KEY,
    id_utente   INT NOT NULL,
    nome        VARCHAR(100) NOT NULL,
    descrizione TEXT,
    obiettivo   ENUM('perdita_peso','massa_muscolare','tonificazione'),
    attiva      TINYINT(1) NOT NULL DEFAULT 0,
    data_inizio DATE,
    data_fine   DATE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   SCHEDA_ESERCIZI  (era "contiene")
--   RINOMINATA: il codice usa esclusivamente
--   il nome `scheda_esercizi`, mai `contiene`.
--   MODIFICHE alle colonne:
--   - `giorno` invece di `giorno_settimana`
--     (INSERT usa il nome breve)
--   - `ripetizioni` diventa VARCHAR(20) perché
--     il codice ci passa stringhe tipo "3x12"
--   - `peso_kg` invece di `peso_consigliato`
--   - Aggiunta colonna `ordine` (usata
--     nell'INSERT con valore 99)
--   - Rimosso `recupero_sec` (mai usato)
-- ============================================
CREATE TABLE IF NOT EXISTS scheda_esercizi (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    id_scheda    INT NOT NULL,
    id_esercizio INT NOT NULL,
    giorno       INT NOT NULL COMMENT '1=Lunedì … 7=Domenica',
    serie        INT NOT NULL DEFAULT 3,
    ripetizioni  VARCHAR(20) NOT NULL DEFAULT '10',
    peso_kg      FLOAT,
    ordine       INT NOT NULL DEFAULT 99,
    FOREIGN KEY (id_scheda)    REFERENCES schede(id_scheda)        ON DELETE CASCADE,
    FOREIGN KEY (id_esercizio) REFERENCES esercizi(id_esercizio)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   ALLENAMENTI
--   Nessuna modifica rispetto all'originale
-- ============================================
CREATE TABLE IF NOT EXISTS allenamenti (
    id_allenamento INT AUTO_INCREMENT PRIMARY KEY,
    id_utente      INT NOT NULL,
    id_scheda      INT,
    data           DATE NOT NULL,
    ora_inizio     TIME,
    ora_fine       TIME,
    durata_sec     INT DEFAULT 0,
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente) ON DELETE CASCADE,
    FOREIGN KEY (id_scheda) REFERENCES schede(id_scheda) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   SESSIONE_ESERCIZI
--   Nessuna modifica rispetto all'originale.
--   Confermato: peso_usato e completato sono
--   già presenti e usati correttamente.
-- ============================================
CREATE TABLE IF NOT EXISTS sessione_esercizi (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    id_allenamento    INT NOT NULL,
    id_esercizio      INT NOT NULL,
    serie_eseguite    INT DEFAULT 0,
    ripetizioni_fatte INT DEFAULT 0,
    peso_usato        FLOAT DEFAULT 0,
    completato        BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (id_allenamento) REFERENCES allenamenti(id_allenamento) ON DELETE CASCADE,
    FOREIGN KEY (id_esercizio)   REFERENCES esercizi(id_esercizio)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   PIANI ABBONAMENTO
--   Nessuna modifica
-- ============================================
CREATE TABLE IF NOT EXISTS piani_abbonamento (
    id_piano      INT AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(100) NOT NULL,
    descrizione   TEXT,
    prezzo        FLOAT NOT NULL,
    durata_giorni INT NOT NULL,
    tipo          ENUM('mensile','trimestrale','annuale') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   ABBONAMENTI
--   Nessuna modifica
-- ============================================
CREATE TABLE IF NOT EXISTS abbonamenti (
    id_abbonamento    INT AUTO_INCREMENT PRIMARY KEY,
    id_utente         INT NOT NULL,
    id_piano          INT NOT NULL,
    data_inizio       DATE NOT NULL,
    data_fine         DATE NOT NULL,
    stato             ENUM('attivo','scaduto','annullato') DEFAULT 'attivo',
    stripe_payment_id VARCHAR(255),
    FOREIGN KEY (id_utente) REFERENCES utenti(id_utente)           ON DELETE CASCADE,
    FOREIGN KEY (id_piano)  REFERENCES piani_abbonamento(id_piano) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
--   DATI DI ESEMPIO — ESERCIZI
--   Aggiunta colonna `tipo` a ogni riga
-- ============================================
INSERT INTO esercizi (nome, descrizione, gruppo_muscolare, tipo, difficolta) VALUES
('Panca Piana',          'Disteso su panca, spingi il bilanciere verso l\'alto',         'petto',          'forza',    'medio'),
('Push-up',              'Flessioni a terra a corpo libero',                             'petto',          'forza',    'facile'),
('Croci con manubri',    'Disteso su panca, apri le braccia lateralmente',               'petto',          'forza',    'medio'),
('Squat',                'Piedi larghezza spalle, scendi fino a 90 gradi',               'gambe',          'forza',    'medio'),
('Affondi',              'Passo avanti e scendi con il ginocchio posteriore',            'gambe',          'forza',    'facile'),
('Leg Press',            'Spingi la pedana con i piedi alla macchina',                   'gambe',          'forza',    'facile'),
('Stacchi',              'Solleva il bilanciere da terra con la schiena dritta',         'schiena',        'forza',    'difficile'),
('Trazioni',             'Appeso alla sbarra, tirati su con i gomiti',                   'schiena',        'forza',    'difficile'),
('Rematore con manubri', 'Busto inclinato, porta il manubrio verso il fianco',           'schiena',        'forza',    'medio'),
('Military Press',       'In piedi o seduto, spingi il bilanciere sopra la testa',       'spalle',         'forza',    'medio'),
('Alzate laterali',      'Braccia lungo i fianchi, solleva i manubri di lato',           'spalle',         'forza',    'facile'),
('Curl con manubri',     'Fletti il gomito portando il manubrio verso la spalla',        'braccia',        'forza',    'facile'),
('Tricep Dips',          'Appoggiato su una panca, fletti e distendi le braccia',        'braccia',        'forza',    'facile'),
('Plank',                'In posizione di flessione, mantieni il corpo dritto',          'addome',         'mobilita', 'facile'),
('Mountain Climbers',    'In posizione plank, porta le ginocchia verso il petto',        'addome',         'cardio',   'medio'),
('Burpees',              'Misto di squat, plank e salto verticale',                      'corpo_completo', 'cardio',   'difficile'),
('Squat con salto',      'Squat normale con salto esplosivo alla fine',                  'gambe',          'cardio',   'medio'),
('Corsa sul posto',      'Corri alzando le ginocchia a 90 gradi',                        'cardio',         'cardio',   'facile');

-- ============================================
--   DATI DI ESEMPIO — PIANI ABBONAMENTO
-- ============================================
INSERT INTO piani_abbonamento (nome, descrizione, prezzo, durata_giorni, tipo) VALUES
('Piano Mensile',     'Accesso completo per 1 mese',    29.99, 30,  'mensile'),
('Piano Trimestrale', 'Accesso completo per 3 mesi',    79.99, 90,  'trimestrale'),
('Piano Annuale',     'Accesso completo per 12 mesi',  249.99, 365, 'annuale');
