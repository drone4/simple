<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/xlsx.php';

session_start();

if (isset($_SESSION['login_time']) && time() - (int) $_SESSION['login_time'] > SESSION_TIMEOUT_SECONDS) {
    session_destroy();
    session_start();
}

$pdo = db();
$message = null;
$error = null;

$action = $_GET['action'] ?? 'dashboard';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = post('username', '');
    $password = post('password', '');

    if (isset(USERS[$username]) && USERS[$username] === $password) {
        $_SESSION['user'] = $username;
        $_SESSION['login_time'] = time();
        header('Location: index.php');
        exit;
    }

    $error = 'Hibás felhasználónév vagy jelszó.';
}

if ($action === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}

$user = $_SESSION['user'] ?? null;

if ($user === null) {
    renderLogin($error);
    exit;
}

if ($action === 'save_patient' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = nullableInt(post('id'));

    $data = [
        'name' => post('name', ''),
        'taj' => post('taj', ''),
        'sex' => nullableInt(post('sex')),
        'birth_date' => post('birth_date'),
        'admission_date' => post('admission_date'),
        'admission_bno1' => post('admission_bno1'),
        'admission_bno3' => post('admission_bno3'),
        'from_where' => nullableInt(post('from_where')),
        'discharge_date' => post('discharge_date'),
        'to_where' => nullableInt(post('to_where')),
        'nursing_days' => nullableInt(post('nursing_days')),
        'tumor' => boolToInt(post('tumor')),
        'dm' => boolToInt(post('dm')),
        'cirrhosis' => boolToInt(post('cirrhosis')),
        'copd' => boolToInt(post('copd')),
        'hematologic' => boolToInt(post('hematologic')),
        'hospital_3m' => boolToInt(post('hospital_3m')),
    ];

    if ($data['name'] === '' || $data['taj'] === '') {
        $error = 'A név és TAJ kötelező.';
    } else {
        $now = nowIso();
        if ($patientId === null) {
            $entryId = nextEntryId($pdo, 'patient', $user);
            $stmt = $pdo->prepare(
                'INSERT INTO patients (
                    entry_id, created_by, name, taj, sex, birth_date, admission_date, admission_bno1, admission_bno3,
                    from_where, discharge_date, to_where, nursing_days, tumor, dm, cirrhosis, copd,
                    hematologic, hospital_3m, created_at, updated_at
                 ) VALUES (
                    :entry_id, :created_by, :name, :taj, :sex, :birth_date, :admission_date, :admission_bno1, :admission_bno3,
                    :from_where, :discharge_date, :to_where, :nursing_days, :tumor, :dm, :cirrhosis, :copd,
                    :hematologic, :hospital_3m, :created_at, :updated_at
                 )'
            );
            $stmt->execute(array_merge($data, [
                'entry_id' => $entryId,
                'created_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $message = 'Beteg mentve. Azonosító: ' . $entryId;
        } else {
            $stmt = $pdo->prepare(
                'UPDATE patients SET
                    name=:name, taj=:taj, sex=:sex, birth_date=:birth_date, admission_date=:admission_date,
                    admission_bno1=:admission_bno1, admission_bno3=:admission_bno3, from_where=:from_where,
                    discharge_date=:discharge_date, to_where=:to_where, nursing_days=:nursing_days,
                    tumor=:tumor, dm=:dm, cirrhosis=:cirrhosis, copd=:copd, hematologic=:hematologic,
                    hospital_3m=:hospital_3m, updated_at=:updated_at
                 WHERE id=:id'
            );
            $stmt->execute(array_merge($data, [
                'id' => $patientId,
                'updated_at' => $now,
            ]));
            $message = 'Beteg adatai frissítve.';
        }
    }
}

if ($action === 'save_catheter' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $catheterId = nullableInt(post('id'));
    $patientId = nullableInt(post('patient_id'));

    $data = [
        'patient_id' => $patientId,
        'taj' => post('taj', ''),
        'catheter_type' => post('catheter_type'),
        'anatomy_place' => nullableInt(post('anatomy_place')),
        'lumen_count' => nullableInt(post('lumen_count')),
        'extender_count' => nullableInt(post('extender_count')),
        'used_days' => nullableInt(post('used_days')),
        'indication_ok' => boolToInt(post('indication_ok')),
        'puncture_ok' => boolToInt(post('puncture_ok')),
        'puncture_problem_day' => post('puncture_problem_day'),
        'puncture_problem' => post('puncture_problem'),
        'fever' => boolToInt(post('fever')),
        'cri1' => post('cri1'),
        'cri2' => post('cri2'),
        'cri3' => post('cri3'),
        'pathogen' => post('pathogen'),
        'dressing_type' => nullableInt(post('dressing_type')),
        'dressing_days' => nullableInt(post('dressing_days')),
        'fixing_ok' => boolToInt(post('fixing_ok')),
        'fixing_problem' => post('fixing_problem'),
        'svaf' => post('svaf'),
    ];

    if ($data['patient_id'] === null) {
        $error = 'Katéterhez kötelező beteg kiválasztása.';
    } else {
        $now = nowIso();
        if ($catheterId === null) {
            $entryId = nextEntryId($pdo, 'catheter', $user);
            $stmt = $pdo->prepare(
                'INSERT INTO catheters (
                    entry_id, patient_id, taj, catheter_type, anatomy_place, lumen_count, extender_count,
                    used_days, indication_ok, puncture_ok, puncture_problem_day, puncture_problem,
                    fever, cri1, cri2, cri3, pathogen, dressing_type, dressing_days,
                    fixing_ok, fixing_problem, svaf, created_by, created_at, updated_at
                 ) VALUES (
                    :entry_id, :patient_id, :taj, :catheter_type, :anatomy_place, :lumen_count, :extender_count,
                    :used_days, :indication_ok, :puncture_ok, :puncture_problem_day, :puncture_problem,
                    :fever, :cri1, :cri2, :cri3, :pathogen, :dressing_type, :dressing_days,
                    :fixing_ok, :fixing_problem, :svaf, :created_by, :created_at, :updated_at
                 )'
            );
            $stmt->execute(array_merge($data, [
                'entry_id' => $entryId,
                'created_by' => $user,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
            $message = 'Katéter mentve. Azonosító: ' . $entryId;
        } else {
            $stmt = $pdo->prepare(
                'UPDATE catheters SET
                    patient_id=:patient_id, taj=:taj, catheter_type=:catheter_type, anatomy_place=:anatomy_place,
                    lumen_count=:lumen_count, extender_count=:extender_count, used_days=:used_days,
                    indication_ok=:indication_ok, puncture_ok=:puncture_ok, puncture_problem_day=:puncture_problem_day,
                    puncture_problem=:puncture_problem, fever=:fever, cri1=:cri1, cri2=:cri2, cri3=:cri3,
                    pathogen=:pathogen, dressing_type=:dressing_type, dressing_days=:dressing_days,
                    fixing_ok=:fixing_ok, fixing_problem=:fixing_problem, svaf=:svaf, updated_at=:updated_at
                WHERE id=:id'
            );
            $stmt->execute(array_merge($data, [
                'id' => $catheterId,
                'updated_at' => $now,
            ]));
            $message = 'Katéter adatai frissítve.';
        }
    }
}

if ($action === 'export') {
    $patients = $pdo->query('SELECT * FROM patients ORDER BY id')->fetchAll();
    $catheters = $pdo->query('SELECT * FROM catheters ORDER BY id')->fetchAll();

    $patientRows = [[
        'entry_id', 'created_by', 'name', 'taj', 'sex', 'birth_date', 'admission_date', 'admission_bno1',
        'admission_bno3', 'from_where', 'discharge_date', 'to_where', 'nursing_days', 'tumor', 'dm', 'cirrhosis',
        'copd', 'hematologic', 'hospital_3m', 'created_at', 'updated_at'
    ]];
    foreach ($patients as $p) {
        $patientRows[] = [
            $p['entry_id'], $p['created_by'], $p['name'], $p['taj'], $p['sex'], $p['birth_date'],
            $p['admission_date'], $p['admission_bno1'], $p['admission_bno3'], $p['from_where'],
            $p['discharge_date'], $p['to_where'], $p['nursing_days'], $p['tumor'], $p['dm'], $p['cirrhosis'],
            $p['copd'], $p['hematologic'], $p['hospital_3m'], $p['created_at'], $p['updated_at']
        ];
    }

    $catheterRows = [[
        'entry_id', 'patient_id', 'taj', 'catheter_type', 'anatomy_place', 'lumen_count', 'extender_count',
        'used_days', 'indication_ok', 'puncture_ok', 'puncture_problem_day', 'puncture_problem', 'fever',
        'cri1', 'cri2', 'cri3', 'pathogen', 'dressing_type', 'dressing_days', 'fixing_ok', 'fixing_problem',
        'svaf', 'created_by', 'created_at', 'updated_at'
    ]];

    foreach ($catheters as $c) {
        $catheterRows[] = [
            $c['entry_id'], $c['patient_id'], $c['taj'], $c['catheter_type'], $c['anatomy_place'],
            $c['lumen_count'], $c['extender_count'], $c['used_days'], $c['indication_ok'], $c['puncture_ok'],
            $c['puncture_problem_day'], $c['puncture_problem'], $c['fever'], $c['cri1'], $c['cri2'], $c['cri3'],
            $c['pathogen'], $c['dressing_type'], $c['dressing_days'], $c['fixing_ok'], $c['fixing_problem'],
            $c['svaf'], $c['created_by'], $c['created_at'], $c['updated_at']
        ];
    }

    outputXlsx($patientRows, $catheterRows, 'kateter_export.xlsx');
}

$patientSearch = trim((string) ($_GET['patient_q'] ?? ''));
$catheterSearch = trim((string) ($_GET['catheter_q'] ?? ''));

$patients = getPatients($pdo, $patientSearch);
$catheters = getCatheters($pdo, $catheterSearch);

$editPatient = null;
if (isset($_GET['edit_patient'])) {
    $stmt = $pdo->prepare('SELECT * FROM patients WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit_patient']]);
    $editPatient = $stmt->fetch() ?: null;
}

$editCatheter = null;
if (isset($_GET['edit_catheter'])) {
    $stmt = $pdo->prepare('SELECT * FROM catheters WHERE id = :id');
    $stmt->execute(['id' => (int) $_GET['edit_catheter']]);
    $editCatheter = $stmt->fetch() ?: null;
}

renderDashboard($user, $patients, $catheters, $editPatient, $editCatheter, $message, $error, $patientSearch, $catheterSearch);

function getPatients(PDO $pdo, string $q): array
{
    if ($q === '') {
        return $pdo->query('SELECT * FROM patients ORDER BY id DESC')->fetchAll();
    }

    $stmt = $pdo->prepare('SELECT * FROM patients WHERE name LIKE :q OR taj LIKE :q OR entry_id LIKE :q ORDER BY id DESC');
    $stmt->execute(['q' => '%' . $q . '%']);
    return $stmt->fetchAll();
}

function getCatheters(PDO $pdo, string $q): array
{
    if ($q === '') {
        return $pdo->query('SELECT c.*, p.name as patient_name FROM catheters c JOIN patients p ON p.id = c.patient_id ORDER BY c.id DESC')->fetchAll();
    }

    $stmt = $pdo->prepare('SELECT c.*, p.name as patient_name FROM catheters c JOIN patients p ON p.id = c.patient_id WHERE c.taj LIKE :q OR c.entry_id LIKE :q OR p.name LIKE :q ORDER BY c.id DESC');
    $stmt->execute(['q' => '%' . $q . '%']);
    return $stmt->fetchAll();
}

function renderLogin(?string $error): void
{
    ?>
    <!doctype html>
    <html lang="hu">
    <head>
        <meta charset="utf-8">
        <title>Belépés</title>
        <style>
            body{font-family:Arial, sans-serif; margin:40px}
            .box{max-width:380px; border:1px solid #ccc; padding:20px; border-radius:8px}
            label{display:block; margin-top:10px}
            input{width:100%; padding:8px}
            button{margin-top:16px; padding:10px 14px}
            .error{color:#b00020}
        </style>
    </head>
    <body>
    <div class="box">
        <h2>Belépés</h2>
        <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>
        <form method="post" action="?action=login">
            <label>Felhasználónév <input name="username" required></label>
            <label>Jelszó <input type="password" name="password" required></label>
            <button type="submit">Belépés</button>
        </form>
    </div>
    </body>
    </html>
    <?php
}

function renderDashboard(string $user, array $patients, array $catheters, ?array $editPatient, ?array $editCatheter, ?string $message, ?string $error, string $patientSearch, string $catheterSearch): void
{
    ?>
    <!doctype html>
    <html lang="hu">
    <head>
        <meta charset="utf-8">
        <title><?= h(APP_NAME) ?></title>
        <style>
            body{font-family:Arial,sans-serif;margin:20px;background:#f8f9fb}
            .top{display:flex;justify-content:space-between;align-items:center}
            .grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
            .card{background:#fff;border-radius:8px;padding:16px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
            label{display:block;font-size:13px;margin-top:8px}
            input,select,textarea{width:100%;padding:7px}
            button,.btn{margin-top:12px;padding:8px 12px;display:inline-block}
            table{width:100%;border-collapse:collapse;margin-top:12px;font-size:12px}
            th,td{border:1px solid #ddd;padding:6px;text-align:left}
            .success{color:#0a7f33}
            .error{color:#b00020}
            .small{font-size:12px;color:#555}
            .span2{grid-column:span 2}
        </style>
    </head>
    <body>
    <div class="top">
        <h2><?= h(APP_NAME) ?></h2>
        <div>Belépve: <strong><?= h($user) ?></strong> | <a href="?action=export">XLSX export</a> | <a href="?action=logout">Kilépés</a></div>
    </div>

    <?php if ($message): ?><p class="success"><?= h($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="error"><?= h($error) ?></p><?php endif; ?>

    <div class="grid">
        <div class="card">
            <h3>Beteg adatlap <?= $editPatient ? '(szerkesztés)' : '(új)' ?></h3>
            <form method="post" action="?action=save_patient">
                <input type="hidden" name="id" value="<?= h((string)($editPatient['id'] ?? '')) ?>">
                <label>Név <input name="name" value="<?= h($editPatient['name'] ?? '') ?>" required></label>
                <label>TAJ <input name="taj" value="<?= h($editPatient['taj'] ?? '') ?>" required></label>
                <label>Nem <select name="sex"><option value="">-</option><option value="1" <?= (($editPatient['sex'] ?? '') == 1) ? 'selected' : '' ?>>1-férfi</option><option value="2" <?= (($editPatient['sex'] ?? '') == 2) ? 'selected' : '' ?>>2-nő</option></select></label>
                <label>Szül. dátum <input type="date" name="birth_date" value="<?= h($editPatient['birth_date'] ?? '') ?>"></label>
                <label>Felvétel dátum <input type="date" name="admission_date" value="<?= h($editPatient['admission_date'] ?? '') ?>"></label>
                <label>Felvételi dg (BNO1) <input name="admission_bno1" value="<?= h($editPatient['admission_bno1'] ?? '') ?>"></label>
                <label>Felvételi dg (BNO3) <input name="admission_bno3" value="<?= h($editPatient['admission_bno3'] ?? '') ?>"></label>
                <label>Honnan <select name="from_where"><option value="">-</option><option value="1" <?= (($editPatient['from_where'] ?? '') == 1) ? 'selected' : '' ?>>1-saját klinika</option><option value="2" <?= (($editPatient['from_where'] ?? '') == 2) ? 'selected' : '' ?>>2-más klinika</option><option value="3" <?= (($editPatient['from_where'] ?? '') == 3) ? 'selected' : '' ?>>3-otthonról</option><option value="4" <?= (($editPatient['from_where'] ?? '') == 4) ? 'selected' : '' ?>>4-más eü intézmény</option></select></label>
                <label>Távozás dátum <input type="date" name="discharge_date" value="<?= h($editPatient['discharge_date'] ?? '') ?>"></label>
                <label>Hová <select name="to_where"><option value="">-</option><option value="1" <?= (($editPatient['to_where'] ?? '') == 1) ? 'selected' : '' ?>>1-saját klinika</option><option value="2" <?= (($editPatient['to_where'] ?? '') == 2) ? 'selected' : '' ?>>2-más klinika</option><option value="3" <?= (($editPatient['to_where'] ?? '') == 3) ? 'selected' : '' ?>>3-haza</option><option value="4" <?= (($editPatient['to_where'] ?? '') == 4) ? 'selected' : '' ?>>4-exit</option></select></label>
                <label>Ápolási napok száma <input type="number" name="nursing_days" value="<?= h((string)($editPatient['nursing_days'] ?? '')) ?>"></label>

                <?php foreach (['tumor'=>'Tumor','dm'=>'DM','cirrhosis'=>'Cirrhosis','copd'=>'COPD','hematologic'=>'Hematológiai betegség','hospital_3m'=>'Kórházi kezelés 3 hónapon belül'] as $field=>$label): ?>
                    <label><?= h($label) ?>
                        <select name="<?= h($field) ?>">
                            <option value="">-</option>
                            <option value="1" <?= (($editPatient[$field] ?? '') == 1) ? 'selected' : '' ?>>1-igen</option>
                            <option value="0" <?= (($editPatient[$field] ?? '') === 0 || ($editPatient[$field] ?? '') === '0') ? 'selected' : '' ?>>0-nem</option>
                        </select>
                    </label>
                <?php endforeach; ?>

                <button type="submit">Beteg mentése</button>
                <?php if ($editPatient): ?><a class="btn" href="index.php">Mégse</a><?php endif; ?>
            </form>
        </div>

        <div class="card">
            <h3>Katéter adatlap <?= $editCatheter ? '(szerkesztés)' : '(új)' ?></h3>
            <form method="post" action="?action=save_catheter">
                <input type="hidden" name="id" value="<?= h((string)($editCatheter['id'] ?? '')) ?>">
                <label>Beteg kiválasztás</label>
                <select name="patient_id" required>
                    <option value="">-- válassz beteget --</option>
                    <?php foreach ($patients as $p): ?>
                        <?php $sel = ((string)($editCatheter['patient_id'] ?? '') === (string)$p['id']) ? 'selected' : ''; ?>
                        <option value="<?= h((string)$p['id']) ?>" <?= $sel ?>><?= h($p['name'] . ' | TAJ: ' . $p['taj']) ?></option>
                    <?php endforeach; ?>
                </select>
                <label>TAJ <input name="taj" value="<?= h($editCatheter['taj'] ?? '') ?>"></label>
                <label>Katéter típusa <select name="catheter_type"><option value="">-</option><?php foreach (['CVC','HD CVC','AC','PVC'] as $x): ?><option value="<?= h($x) ?>" <?= (($editCatheter['catheter_type'] ?? '') === $x) ? 'selected' : '' ?>><?= h($x) ?></option><?php endforeach; ?></select></label>
                <label>Anatómiai hely <select name="anatomy_place"><option value="">-</option><option value="1" <?= (($editCatheter['anatomy_place'] ?? '') == 1) ? 'selected' : '' ?>>1-subclavia</option><option value="2" <?= (($editCatheter['anatomy_place'] ?? '') == 2) ? 'selected' : '' ?>>2-jugularis</option><option value="3" <?= (($editCatheter['anatomy_place'] ?? '') == 3) ? 'selected' : '' ?>>3-femoralis</option><option value="4" <?= (($editCatheter['anatomy_place'] ?? '') == 4) ? 'selected' : '' ?>>4-radalis</option><option value="5" <?= (($editCatheter['anatomy_place'] ?? '') == 5) ? 'selected' : '' ?>>5-cubitalis</option><option value="6" <?= (($editCatheter['anatomy_place'] ?? '') == 6) ? 'selected' : '' ?>>6-alkar</option></select></label>
                <label>Lumen száma <input type="number" name="lumen_count" value="<?= h((string)($editCatheter['lumen_count'] ?? '')) ?>"></label>
                <label>Hosszabbító száma <input type="number" name="extender_count" value="<?= h((string)($editCatheter['extender_count'] ?? '')) ?>"></label>
                <label>Alkalmazott napok száma <input type="number" name="used_days" value="<?= h((string)($editCatheter['used_days'] ?? '')) ?>"></label>
                <label>Érkanül indikált betartás <select name="indication_ok"><option value="">-</option><option value="1" <?= (($editCatheter['indication_ok'] ?? '') == 1) ? 'selected' : '' ?>>igen</option><option value="0" <?= (($editCatheter['indication_ok'] ?? '') === 0 || ($editCatheter['indication_ok'] ?? '') === '0') ? 'selected' : '' ?>>nem</option></select></label>
                <label>Szúrcsatorna állapot megfelelő <select name="puncture_ok"><option value="">-</option><option value="1" <?= (($editCatheter['puncture_ok'] ?? '') == 1) ? 'selected' : '' ?>>igen</option><option value="0" <?= (($editCatheter['puncture_ok'] ?? '') === 0 || ($editCatheter['puncture_ok'] ?? '') === '0') ? 'selected' : '' ?>>nem</option></select></label>
                <label>Szúrcsatorna probléma napja <input type="date" name="puncture_problem_day" value="<?= h($editCatheter['puncture_problem_day'] ?? '') ?>"></label>
                <label>Probléma szúrcsat <input name="puncture_problem" value="<?= h($editCatheter['puncture_problem'] ?? '') ?>"></label>
                <label>Láz <select name="fever"><option value="">-</option><option value="1" <?= (($editCatheter['fever'] ?? '') == 1) ? 'selected' : '' ?>>igen</option><option value="0" <?= (($editCatheter['fever'] ?? '') === 0 || ($editCatheter['fever'] ?? '') === '0') ? 'selected' : '' ?>>nem</option></select></label>
                <label>CRI1 <input name="cri1" value="<?= h($editCatheter['cri1'] ?? '') ?>"></label>
                <label>CRI2 <input name="cri2" value="<?= h($editCatheter['cri2'] ?? '') ?>"></label>
                <label>CRI3 <input name="cri3" value="<?= h($editCatheter['cri3'] ?? '') ?>"></label>
                <label>Kitenyészett kórokozó <input name="pathogen" value="<?= h($editCatheter['pathogen'] ?? '') ?>"></label>
                <label>Kötszer típus <select name="dressing_type"><option value="">-</option><option value="1" <?= (($editCatheter['dressing_type'] ?? '') == 1) ? 'selected' : '' ?>>1-tegCHG</option><option value="2" <?= (($editCatheter['dressing_type'] ?? '') == 2) ? 'selected' : '' ?>>2-tegM</option><option value="3" <?= (($editCatheter['dressing_type'] ?? '') == 3) ? 'selected' : '' ?>>3-cosm</option></select></label>
                <label>Kötszer hány napig <input type="number" name="dressing_days" value="<?= h((string)($editCatheter['dressing_days'] ?? '')) ?>"></label>
                <label>Rögzítő fedőkötés állapot megfelelő <select name="fixing_ok"><option value="">-</option><option value="1" <?= (($editCatheter['fixing_ok'] ?? '') == 1) ? 'selected' : '' ?>>igen</option><option value="0" <?= (($editCatheter['fixing_ok'] ?? '') === 0 || ($editCatheter['fixing_ok'] ?? '') === '0') ? 'selected' : '' ?>>nem</option></select></label>
                <label>Probléma fedőkötés <input name="fixing_problem" value="<?= h($editCatheter['fixing_problem'] ?? '') ?>"></label>
                <label>SVÁF <input name="svaf" value="<?= h($editCatheter['svaf'] ?? '') ?>"></label>
                <button type="submit">Katéter mentése (+ új)</button>
                <?php if ($editCatheter): ?><a class="btn" href="index.php">Mégse</a><?php endif; ?>
            </form>
        </div>

        <div class="card span2">
            <h3>Betegek listája</h3>
            <form method="get"><input type="text" name="patient_q" placeholder="Keresés név / TAJ / azonosító" value="<?= h($patientSearch) ?>"> <button type="submit">Keresés</button></form>
            <table>
                <thead><tr><th>Azonosító</th><th>Név</th><th>TAJ</th><th>Felvevő</th><th>Művelet</th></tr></thead>
                <tbody>
                <?php foreach ($patients as $p): ?>
                    <tr>
                        <td><?= h($p['entry_id']) ?></td>
                        <td><?= h($p['name']) ?></td>
                        <td><?= h($p['taj']) ?></td>
                        <td><?= h($p['created_by']) ?></td>
                        <td><a href="?edit_patient=<?= h((string)$p['id']) ?>">Szerkesztés</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card span2">
            <h3>Katéterek listája</h3>
            <form method="get"><input type="text" name="catheter_q" placeholder="Keresés beteg / TAJ / azonosító" value="<?= h($catheterSearch) ?>"> <button type="submit">Keresés</button></form>
            <table>
                <thead><tr><th>Azonosító</th><th>Beteg</th><th>TAJ</th><th>Típus</th><th>Felvevő</th><th>Művelet</th></tr></thead>
                <tbody>
                <?php foreach ($catheters as $c): ?>
                    <tr>
                        <td><?= h($c['entry_id']) ?></td>
                        <td><?= h($c['patient_name']) ?></td>
                        <td><?= h($c['taj']) ?></td>
                        <td><?= h((string)$c['catheter_type']) ?></td>
                        <td><?= h($c['created_by']) ?></td>
                        <td><a href="?edit_catheter=<?= h((string)$c['id']) ?>">Szerkesztés</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="small">Megjegyzés: egy beteghez tetszőleges számú katéter rögzíthető.</p>
    </body>
    </html>
    <?php
}
