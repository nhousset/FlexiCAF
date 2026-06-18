<?php
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'home';

// 1. Initialisation de l'Administrateur
if (!file_exists(FILE_ADMIN) && $action !== 'init_admin') {
    header('Location: ?action=init_admin');
    exit;
}

if ($action === 'init_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['admin_password'];
    saveDb(FILE_ADMIN, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $_SESSION['user_id'] = 'admin';
    $_SESSION['name'] = 'Administrateur';
    $_SESSION['role'] = 'admin';
    header('Location: ?action=admin');
    exit;
}

// 2. Déconnexion
if ($action === 'logout') {
    session_destroy();
    header('Location: ?action=login');
    exit;
}

// 3. Authentification
if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'];
    $pass = $_POST['password'];

    if ($login === 'admin') {
        $adminData = getDb(FILE_ADMIN);
        if (password_verify($pass, $adminData['password_hash'])) {
            $_SESSION['user_id'] = 'admin';
            $_SESSION['name'] = 'Administrateur';
            $_SESSION['role'] = 'admin';
            header('Location: ?action=home'); exit;
        }
    } else {
        $users = getDb(FILE_USERS);
        foreach ($users as $id => $u) {
            if ($u['email'] === $login && password_verify($pass, $u['password'])) {
                $_SESSION['user_id'] = $id;
                $_SESSION['name'] = $u['name'];
                $_SESSION['role'] = 'user';
                header('Location: ?action=home'); exit;
            }
        }
    }
    $error = "Identifiants invalides.";
}

// Protection des routes
if (!in_array($action, ['login', 'init_admin']) && !isset($_SESSION['user_id'])) {
    header('Location: ?action=login'); exit;
}

// 4. Traitement des saisies CAF (Unique, Continue, Récurrente)
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $task_id = $_POST['task_id'];
    $valeur = floatval($_POST['valeur']);
    $saisie_mode = $_POST['saisie_mode']; // 'unique', 'continue', 'recurrence'
    
    $date_start = $_POST['date_start'];
    $date_end = $_POST['date_end'] ?: $date_start;
    $days = $_POST['days'] ?? []; // Jours cochés pour la récurrence

    $datesToInsert = generateDatesList($date_start, $date_end, $saisie_mode, $days);
    
    $data = getDb(FILE_DATA);
    foreach ($datesToInsert as $d) {
        $data[] = [
            'id' => uniqid('evt_'),
            'user_id' => $_SESSION['user_id'],
            'user_name' => $_SESSION['name'],
            'task_id' => $task_id,
            'date' => $d,
            'valeur_j' => $valeur
        ];
    }
    saveDb(FILE_DATA, $data);
    header('Location: ?action=home&success=1'); exit;
}

// 5. Traitements Admin (Création Utilisateurs & Tâches)
if ($action === 'admin' && $_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_user'])) {
        $users = getDb(FILE_USERS);
        $users[uniqid('usr_')] = [
            'name' => $_POST['u_name'],
            'email' => $_POST['u_email'],
            'password' => password_hash($_POST['u_pass'], PASSWORD_DEFAULT)
        ];
        saveDb(FILE_USERS, $users);
    }
    if (isset($_POST['add_task'])) {
        $tasks = getDb(FILE_TASKS);
        $tasks[uniqid('tsk_')] = [
            'title' => $_POST['t_title'],
            'desc' => $_POST['t_desc'],
            'itbm' => $_POST['t_itbm']
        ];
        saveDb(FILE_TASKS, $tasks);
    }
    header('Location: ?action=admin'); exit;
}

// ==========================================
// AFFICHAGE DES VUES
// ==========================================
require 'includes/header.php';

if (in_array($action, ['login', 'init_admin'])) {
    require 'views/auth.php';
} elseif ($action === 'home') {
    require 'views/saisie.php';
} elseif ($action === 'admin' && $_SESSION['role'] === 'admin') {
    require 'views/admin.php';
}

echo "</div></body></html>";
