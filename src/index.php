<?php
require_once 'includes/functions.php';

$action = $_GET['action'] ?? 'home';

if (!file_exists(FILE_ADMIN) && $action !== 'init_admin') {
    header('Location: ?action=init_admin'); exit;
}

if ($action === 'init_admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    saveDb(FILE_ADMIN, ['password_hash' => password_hash($_POST['admin_password'], PASSWORD_DEFAULT)]);
    $_SESSION['user_id'] = 'admin'; $_SESSION['name'] = 'Administrateur'; $_SESSION['role'] = 'admin';
    header('Location: ?action=admin'); exit;
}

if ($action === 'logout') { session_destroy(); header('Location: ?action=login'); exit; }

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login']; $pass = $_POST['password'];
    if ($login === 'admin') {
        $adminData = getDb(FILE_ADMIN);
        if (password_verify($pass, $adminData['password_hash'])) {
            $_SESSION['user_id'] = 'admin'; $_SESSION['name'] = 'Administrateur'; $_SESSION['role'] = 'admin';
            header('Location: ?action=home'); exit;
        }
    } else {
        foreach (getDb(FILE_USERS) as $id => $u) {
            if ($u['email'] === $login && password_verify($pass, $u['password'])) {
                $_SESSION['user_id'] = $id; $_SESSION['name'] = $u['name']; $_SESSION['role'] = 'user';
                header('Location: ?action=home'); exit;
            }
        }
    }
    $error = "Identifiants invalides.";
}

if (!in_array($action, ['login', 'init_admin']) && !isset($_SESSION['user_id'])) {
    header('Location: ?action=login'); exit;
}

// Traitement : Saisie depuis le formulaire principal ou la modale d'allocation rapide
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    if (!hasPermission('can_saisie')) { die("Action non autorisée."); }

    $task_id = $_POST['task_id'];
    $valeur = floatval($_POST['valeur']);
    $saisie_mode = $_POST['saisie_mode'] ?? 'unique';
    
    $date_start = $_POST['date_start'];
    $date_end = !empty($_POST['date_end']) ? $_POST['date_end'] : $date_start;
    $days = $_POST['days'] ?? [];
    
    $target_user_id = (!empty($_POST['target_user_id']) && $_SESSION['role'] === 'admin') ? $_POST['target_user_id'] : $_SESSION['user_id'];

    $datesToInsert = generateDatesList($date_start, $date_end, $saisie_mode, $days);
    
    $data = getDb(FILE_DATA);
    foreach ($datesToInsert as $d) {
        $data[] = [
            'id' => uniqid('evt_'),
            'user_id' => $target_user_id,
            'task_id' => $task_id,
            'date' => $d,
            'valeur_j' => $valeur
        ];
    }
    saveDb(FILE_DATA, $data);
    
    $redirect_url = '?action=home';
    if(isset($_GET['view'])) $redirect_url .= '&view='.$_GET['view'];
    if(isset($_GET['date'])) $redirect_url .= '&date='.$_GET['date'];
    
    header("Location: $redirect_url&success=1"); exit;
}

// Traitements Admin
if ($action === 'admin' && $_SESSION['role'] === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Création Utilisateur
    if (isset($_POST['add_user'])) {
        $users = getDb(FILE_USERS);
        $users[uniqid('usr_')] = [
            'name' => $_POST['u_name'],
            'email' => $_POST['u_email'],
            'password' => password_hash($_POST['u_pass'], PASSWORD_DEFAULT),
            'can_saisie' => isset($_POST['u_can_saisie']),
            'can_dashboard' => isset($_POST['u_can_dashboard'])
        ];
        saveDb(FILE_USERS, $users);
    }
    
    // Modification Utilisateur
    if (isset($_POST['edit_user'])) {
        $users = getDb(FILE_USERS);
        $uid = $_POST['user_id'];
        if (isset($users[$uid])) {
            $users[$uid]['name'] = $_POST['u_name'];
            $users[$uid]['email'] = $_POST['u_email'];
            if (!empty($_POST['u_pass'])) { // Ne modifie le mdp que s'il est renseigné
                $users[$uid]['password'] = password_hash($_POST['u_pass'], PASSWORD_DEFAULT);
            }
            $users[$uid]['can_saisie'] = isset($_POST['u_can_saisie']);
            $users[$uid]['can_dashboard'] = isset($_POST['u_can_dashboard']);
            saveDb(FILE_USERS, $users);
        }
    }

    // Création Tâche
    if (isset($_POST['add_task'])) {
        $tasks = getDb(FILE_TASKS);
        $tasks[uniqid('tsk_')] = [
            'title' => $_POST['t_title'], 'desc' => $_POST['t_desc'], 
            'itbm' => $_POST['t_itbm'], 'color' => $_POST['t_color']
        ];
        saveDb(FILE_TASKS, $tasks);
    }
    header('Location: ?action=admin'); exit;
}

require 'includes/header.php';

if (in_array($action, ['login', 'init_admin'])) { require 'views/auth.php'; } 
elseif ($action === 'home') { require 'views/workspace.php'; } 
elseif ($action === 'admin' && $_SESSION['role'] === 'admin') { require 'views/admin.php'; }

echo "</div></body></html>";
