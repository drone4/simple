<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(dirname(DB_PATH))) {
        mkdir(dirname(DB_PATH), 0777, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON;');

    initializeSchema($pdo);

    return $pdo;
}

function initializeSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS counters (
            name TEXT PRIMARY KEY,
            value INTEGER NOT NULL
        );'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS patients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_id TEXT NOT NULL UNIQUE,
            created_by TEXT NOT NULL,
            name TEXT NOT NULL,
            taj TEXT NOT NULL,
            sex INTEGER,
            birth_date TEXT,
            admission_date TEXT,
            admission_bno1 TEXT,
            admission_bno3 TEXT,
            from_where INTEGER,
            discharge_date TEXT,
            to_where INTEGER,
            nursing_days INTEGER,
            tumor INTEGER,
            dm INTEGER,
            cirrhosis INTEGER,
            copd INTEGER,
            hematologic INTEGER,
            hospital_3m INTEGER,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS catheters (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_id TEXT NOT NULL UNIQUE,
            patient_id INTEGER NOT NULL,
            taj TEXT NOT NULL,
            catheter_type TEXT,
            anatomy_place INTEGER,
            lumen_count INTEGER,
            extender_count INTEGER,
            used_days INTEGER,
            indication_ok INTEGER,
            puncture_ok INTEGER,
            puncture_problem_day TEXT,
            puncture_problem TEXT,
            fever INTEGER,
            cri1 TEXT,
            cri2 TEXT,
            cri3 TEXT,
            pathogen TEXT,
            dressing_type INTEGER,
            dressing_days INTEGER,
            fixing_ok INTEGER,
            fixing_problem TEXT,
            svaf TEXT,
            created_by TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY(patient_id) REFERENCES patients(id) ON DELETE CASCADE
        );'
    );
}

function nextEntryId(PDO $pdo, string $counterName, string $username): string
{
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('SELECT value FROM counters WHERE name = :name');
    $stmt->execute(['name' => $counterName]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        $next = 1;
        $ins = $pdo->prepare('INSERT INTO counters(name, value) VALUES(:name, :value)');
        $ins->execute(['name' => $counterName, 'value' => $next]);
    } else {
        $next = ((int) $current) + 1;
        $upd = $pdo->prepare('UPDATE counters SET value = :value WHERE name = :name');
        $upd->execute(['value' => $next, 'name' => $counterName]);
    }

    $pdo->commit();

    return sprintf('%s-%06d-%s', strtoupper($counterName), $next, $username);
}
