
<?php
session_start();

// ==========================================
// CONFIGURATION & INIT DES FICHIERS JSON
// ==========================================
define('APP_NAME', 'FlexiCAF');
define('FILE_USERS', 'users.json');
define('FILE_DATA', 'data.json');
define('HEURES_PAR_JOURNEE', 8.0);

// Auto-génération de la base utilisateurs s'il n'y a rien
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

// Auto-génération de la base de données
if (!file_exists(FILE_DATA)) {
    file_put_contents(FILE_DATA, json_encode([], JSON_PRETTY_PRINT));
}

// ==========================================
// HELPERS
// ==========================================
function getDb($file) { return json_decode(file_get_contents($file), true); }
function saveDb($file, $data) { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }

// ==========================================
// ROUTEUR & LOGIQUE MÉTIER
// ==========================================
$action = $_GET['action'] ?? 'home';

// Déconnexion
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// Traitement du Login
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $users = getDb(FILE_USERS);
    $login = $_POST['login'] ?? '';
    $pass = $_POST['password'] ?? '';

    if (isset($users[$login]) && password_verify($pass, $users[$login]['password'])) {
        $_SESSION['user'] = $login;
        $_SESSION['role'] = $users[$login]['role'];
        $_SESSION['name'] = $users[$login]['name'];
        header('Location: ?action=home');
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
}

// Protection des pages (Redirection si non loggué)
if ($action !== 'login' && !isset($_SESSION['user'])) {
    header('Location: ?action=login');
    exit;
}

// Traitement de la Saisie (POST)
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_SPECIAL_CHARS);
    $mode = filter_input(INPUT_POST, 'mode', FILTER_SANITIZE_SPECIAL_CHARS);
    $valeur = filter_input(INPUT_POST, 'valeur', FILTER_VALIDATE_FLOAT);
    $date_saisie = filter_input(INPUT_POST, 'date_saisie', FILTER_SANITIZE_SPECIAL_CHARS);

    $duree_jours = match($mode) {
        'demi' => $valeur * 0.5,
        'quart' => $valeur * 0.25,
        'heure' => $valeur / HEURES_PAR_JOURNEE,
        default => 0.5
    };

    $data = getDb(FILE_DATA);
    $data[] = [
        'id' => uniqid(),
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
// VUES (HTML / BOOTSTRAP)
// ==========================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style> body { background-color: #f4f6f9; } .navbar-brand { font-weight: bold; letter-spacing: 1px; } </style>
</head>
<body>

<?php if (isset($_SESSION['user'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container">
        <a class="navbar-brand" href="?action=home">⚡ <?= APP_NAME ?></a>
        <div class="d-flex text-white align-items-center">
            <span class="me-3">Connecté : <strong><?= $_SESSION['name'] ?></strong></span>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="?action=dashboard" class="btn btn-outline-info btn-sm me-2">Dashboard Manager</a>
            <?php endif; ?>
            <a href="?action=logout" class="btn btn-danger btn-sm">Déconnexion</a>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container">
    
    <?php 
    // VUE 1 : LOGIN
    if ($action === 'login'): ?>
        <div class="row justify-content-center mt-5">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white text-center"><h4><?= APP_NAME ?></h4></div>
                    <div class="card-body">
                        <?php if (isset($error)) echo "<div class='alert alert-danger'>$error</div>"; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label>Login</label>
                                <input type="text" name="login" class="form-control" required placeholder="Ex: admin ou thomas">
                            </div>
                            <div class="mb-3">
                                <label>Mot de passe</label>
                                <input type="password" name="password" class="form-control" required value="admin123">
                                <small class="text-muted">Par défaut : admin123 (admin) ou consultant (users)</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <?php 
    // VUE 2 : SAISIE (Utilisateur)
    elseif ($action === 'home'): ?>
        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white"><h5 class="mb-0">Ma saisie d'activité</h5></div>
                    <div class="card-body">
                        <?php if(isset($_GET['success'])) echo "<div class='alert alert-success py-2'>Saisie enregistrée !</div>"; ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label>Date d'activité</label>
                                <input type="date" name="date_saisie" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label>Type</label>
                                <select name="type" class="form-select" required>
                                    <option value="Run">Run (Ops quotidien)</option>
                                    <option value="Projet">Projet (Build)</option>
                                    <option value="Réunion">Réunion / Admin</option>
                                    <option value="Absence">Absence / Congés</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Mode de saisie</label>
                                <select name="mode" id="modeSelect" class="form-select" onchange="updateUI()">
                                    <option value="demi">Demi-journée</option>
                                    <option value="quart">Quart de jour</option>
                                    <option value="heure">À l'heure</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label id="valeurLabel">Quantité (0.5 jour)</label>
                                <input type="number" name="valeur" id="valeurInput" class="form-control" step="1" min="1" max="10" value="1" required>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Valider</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white"><h5 class="mb-0">Mon historique récent</h5></div>
                    <div class="card-body p-0">
                        <table class="table table-striped mb-0">
                            <thead><tr><th>Date</th><th>Type</th><th>Saisie</th><th>Équivalent</th></tr></thead>
                            <tbody>
                                <?php 
                                $data = getDb(FILE_DATA);
                                $myEntries = array_filter($data, fn($e) => $e['user'] === $_SESSION['user']);
                                usort($myEntries, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                                foreach(array_slice($myEntries, 0, 10) as $e): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= $e['type'] ?></span></td>
                                        <td><?= $e['valeur'] ?> (<?= $e['mode'] ?>)</td>
                                        <td><strong><?= $e['equivalent_j'] ?> j</strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
        function updateUI() {
            const mode = document.getElementById('modeSelect').value;
            const input = document.getElementById('valeurInput');
            const label = document.getElementById('valeurLabel');
            if (mode === 'demi') { label.innerText = 'Nombre de demi-journées'; input.step = 1; input.value = 1; }
            if (mode === 'quart') { label.innerText = 'Nombre de quarts'; input.step = 1; input.value = 1; }
            if (mode === 'heure') { label.innerText = 'Nombre d\'heures'; input.step = 0.5; input.value = 1; }
        }
        </script>

    <?php 
    // VUE 3 : DASHBOARD (Admin uniquement)
    elseif ($action === 'dashboard' && $_SESSION['role'] === 'admin'): ?>
        <div class="card shadow-sm">
            <div class="card-header bg-info text-white"><h5 class="mb-0">Vue Manager : Toutes les activités</h5></div>
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead class="table-light"><tr><th>Consultant</th><th>Date</th><th>Activité</th><th>Charge (Jours)</th></tr></thead>
                    <tbody>
                        <?php 
                        $data = getDb(FILE_DATA);
                        usort($data, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                        foreach($data as $e): ?>
                            <tr>
                                <td><strong><?= $e['name'] ?></strong></td>
                                <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                <td><?= $e['type'] ?></td>
                                <td><?= $e['equivalent_j'] ?> j</td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
