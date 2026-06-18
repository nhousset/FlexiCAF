<?php
session_start();

// ==========================================
// CONFIGURATION & ALIGNEMENT ARCHITECTURE
// ==========================================
define('APP_NAME', 'FlexiCAF');
define('DIR_DB', 'db/');
define('FILE_USERS', DIR_DB . 'users.json');
define('FILE_DATA', DIR_DB . 'data.json');
define('HEURES_PAR_JOURNEE', 8.0);

// Création automatique du répertoire db/ s'il n'existe pas
if (!is_dir(DIR_DB)) {
    mkdir(DIR_DB, 0755, true);
}

// Auto-génération de la base utilisateurs si absente
if (!file_exists(FILE_USERS)) {
    $defaultUsers = [
        'admin' => ['password' => password_hash('admin123', PASSWORD_DEFAULT), 'role' => 'admin', 'name' => 'Administrateur', 'fte' => 1],
        'thomas' => ['password' => password_hash('consultant', PASSWORD_DEFAULT), 'role' => 'user', 'name' => 'Thomas', 'fte' => 1],
        'raphael' => ['password' => password_hash('consultant', PASSWORD_DEFAULT), 'role' => 'user', 'name' => 'Raphaël', 'fte' => 1],
        'antoine' => ['password' => password_hash('consultant', PASSWORD_DEFAULT), 'role' => 'user', 'name' => 'Antoine', 'fte' => 1],
        'kevin' => ['password' => password_hash('consultant', PASSWORD_DEFAULT), 'role' => 'user', 'name' => 'Kévin', 'fte' => 1],
    ];
    file_put_contents(FILE_USERS, json_encode($defaultUsers, JSON_PRETTY_PRINT));
}

// Auto-génération du fichier de données d'activité si absent
if (!file_exists(FILE_DATA)) {
    file_put_contents(FILE_DATA, json_encode([], JSON_PRETTY_PRINT));
}

// ==========================================
// FONCTIONS ABSTRACTION ENTRÉES/SORTIES
// ==========================================
function getDb($file) { 
    return json_decode(file_get_contents($file), true); 
}

function saveDb($file, $data) { 
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); 
}

// ==========================================
// CONTROLEUR & LOGIQUE ROUTAGE
// ==========================================
$action = $_GET['action'] ?? 'home';

// Action : Déconnexion
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// Action : Traitement Authentification
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getDb(FILE_USERS);
    $login = strtolower(trim($_POST['login'] ?? ''));
    $pass = $_POST['password'] ?? '';

    if (isset($users[$login]) && password_verify($pass, $users[$login]['password'])) {
        $_SESSION['user'] = $login;
        $_SESSION['role'] = $users[$login]['role'];
        $_SESSION['name'] = $users[$login]['name'];
        header('Location: ?action=home');
        exit;
    } else {
        $error = "Identifiants invalides.";
    }
}

// Filtre de Sécurité : Redirection systématique vers login si hors session
if ($action !== 'login' && !isset($_SESSION['user'])) {
    header('Location: ?action=login');
    exit;
}

// Action : Enregistrement d'une activité (POST)
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
    $mode = filter_input(INPUT_POST, 'mode', FILTER_SANITIZE_SPECIAL_CHARS);
    $valeur = filter_input(INPUT_POST, 'valeur', FILTER_VALIDATE_FLOAT);
    $date_saisie = filter_input(INPUT_POST, 'date_saisie', FILTER_SANITIZE_SPECIAL_CHARS);

    // Calcul de la capacité normalisée en fraction de jour
    $duree_jours = match($mode) {
        'demi' => $valeur * 0.5,
        'quart' => $valeur * 0.25,
        'heure' => $valeur / HEURES_PAR_JOURNEE,
        default => 0.5
    };

    $data = getDb(FILE_DATA);
    $data[] = [
        'id' => uniqid('evt_'),
        'date' => $date_saisie,
        'user' => $_SESSION['user'],
        'name' => $_SESSION['name'],
        'type' => $type,
        'mode' => $mode,
        'valeur' => $valeur,
        'equivalent_j' => round($duree_jours, 3),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    saveDb(FILE_DATA, $data);
    header('Location: ?action=home&success=1');
    exit;
}

// ==========================================
// RENDU DES VUES GRAPHIQUES (BOOTSTRAP 5)
// ==========================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Gestion de Capacité</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: system-ui, -apple-system, sans-serif; }
        .card { border: none; border-radius: 8px; }
        .navbar-brand { font-weight: 700; letter-spacing: 0.5px; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="?action=home">⚡ <?= APP_NAME ?></a>
        <div class="d-flex text-white align-items-center">
            <span class="me-3 small">Utilisateur : <strong class="text-info"><?= $_SESSION['name'] ?></strong></span>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="?action=dashboard" class="btn btn-outline-light btn-sm me-2">Console Manager</a>
            <?php endif; ?>
            <a href="?action=logout" class="btn btn-sm btn-danger">Quitter</a>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container">
    
    <?php 
    // ------------------------------------------
    // VUE : MISE EN RELATION ET AUTHENTIFICATION
    // ------------------------------------------
    if ($action === 'login'): ?>
        <div class="row justify-content-center" style="margin-top: 10%;">
            <div class="col-md-4">
                <div class="card shadow-lg">
                    <div class="card-header bg-dark text-white text-center py-3">
                        <h4 class="mb-0"><?= APP_NAME ?></h4>
                        <span class="text-muted small">Portatif • Léger • Temps Réel</span>
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($error)) echo "<div class='alert alert-danger py-2 small'>$error</div>"; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Identifiant</label>
                                <input type="text" name="login" class="form-control" required placeholder="Ex: admin, thomas...">
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Mot de passe</label>
                                <input type="password" name="password" class="form-control" required>
                                <div class="form-text xsmall text-muted">Initialisation : `admin123` pour l'admin, `consultant` pour l'équipe.</div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Ouvrir la session</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php 
    // ------------------------------------------
    // VUE : CONSOLE CONSULTANT (SAISIE & LOGS)
    // ------------------------------------------
    elseif ($action === 'home'): ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0">Déclaration d'activité</h5></div>
                    <div class="card-body p-4">
                        <?php if(isset($_GET['success'])) echo "<div class='alert alert-success py-2 small'>Activité enregistrée avec succès.</div>"; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Date de l'activité</label>
                                <input type="date" name="date_saisie" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Nature de la tâche</label>
                                <select name="type" class="form-select" required>
                                    <option value="Run">Run (Support, Supervision Ops)</option>
                                    <option value="Projet">Projet (Build, Intégration)</option>
                                    <option value="Réunion">Réunion / Comité</option>
                                    <option value="Formation">Formation technique</option>
                                    <option value="Absence">Absence / Congés</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Unité de mesure</label>
                                <select name="mode" id="modeSelect" class="form-select" onchange="adaptFormUnit()">
                                    <option value="demi">Demi-journée (Par défaut)</option>
                                    <option value="quart">Quart de journée</option>
                                    <option value="heure">Décompte à l'heure</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold" id="valeurLabel">Nombre de demi-journées</label>
                                <input type="number" name="valeur" id="valeurInput" class="form-control" step="1" min="1" max="10" value="1" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100 py-2">Enregistrer</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0">Mes 10 derniers enregistrements</h5></div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 align-middle">
                                <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Saisie Initiale</th><th>Équivalent Jours</th></tr></thead>
                                <tbody>
                                    <?php 
                                    $data = getDb(FILE_DATA);
                                    $myEntries = array_filter($data, fn($e) => $e['user'] === $_SESSION['user']);
                                    usort($myEntries, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                                    
                                    if (empty($myEntries)): ?>
                                        <tr><td colspan="4" class="text-center text-muted py-4">Aucune activité déclarée pour le moment.</td></tr>
                                    <?php else: ?>
                                        <?php foreach(array_slice($myEntries, 0, 10) as $e): ?>
                                            <tr>
                                                <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                                <td><span class="badge bg-secondary"><?= $e['type'] ?></span></td>
                                                <td class="small"><?= $e['valeur'] ?> (<?= $e['mode'] ?>)</td>
                                                <td><strong><?= $e['equivalent_j'] ?> j</strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
        function adaptFormUnit() {
            const mode = document.getElementById('modeSelect').value;
            const input = document.getElementById('valeurInput');
            const label = document.getElementById('valeurLabel');
            if (mode === 'demi') { label.innerText = 'Nombre de demi-journées'; input.step = 1; input.value = 1; }
            if (mode === 'quart') { label.innerText = 'Nombre de quarts'; input.step = 1; input.value = 1; }
            if (mode === 'heure') { label.innerText = 'Nombre d\'heures'; input.step = 0.5; input.value = 1; }
        }
        </script>

    <?php 
    // ------------------------------------------
    // VUE : DASHBOARD GLOBAL MANAGER
    // ------------------------------------------
    elseif ($action === 'dashboard' && $_SESSION['role'] === 'admin'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Suivi d'Équipe Consolider (Vue Globale)</h5>
                <span class="badge bg-dark">Fichiers d'infrastructure : OK</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover mb-0 align-middle">
                        <thead class="table-light"><tr><th>Consultant IT Ops</th><th>Date d'activité</th><th>Catégorie</th><th>Charge calculée</th></tr></thead>
                        <tbody>
                            <?php 
                            $data = getDb(FILE_DATA);
                            usort($data, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                            
                            if (empty($data)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">Aucune donnée globale disponible dans l'infrastructure JSON.</td></tr>
                            <?php else: ?>
                                <?php foreach($data as $e): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($e['name']) ?></strong></td>
                                        <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                        <td><span class="badge bg-outline-dark text-dark border"><?= htmlspecialchars($e['type']) ?></span></td>
                                        <td class="fw-bold text-end pe-4"><?= $e['equivalent_j'] ?> j</td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
