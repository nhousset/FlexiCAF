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

// ---------------------------------------------------------
// TRAITEMENT : SAISIE MENSUELLE
// ---------------------------------------------------------
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    if (!hasPermission('can_saisie')) { die("Action non autorisée."); }

    $task_id = $_POST['task_id'];
    $valeur = floatval($_POST['valeur']);
    $month_saisie = $_POST['month_saisie']; 
    
    $date_to_save = $month_saisie . '-01'; 
    $target_user_id = (!empty($_POST['target_user_id']) && $_SESSION['role'] === 'admin') ? $_POST['target_user_id'] : $_SESSION['user_id'];

    $data = getDb(FILE_DATA);
    $data[] = [
        'id' => uniqid('evt_'),
        'user_id' => $target_user_id,
        'task_id' => $task_id,
        'date' => $date_to_save,
        'valeur_j' => $valeur
    ];
    saveDb(FILE_DATA, $data);
    
    $redirect_url = '?action=home';
    if(isset($_GET['date'])) $redirect_url .= '&date='.$_GET['date'];
    header("Location: $redirect_url&success=1"); exit;
}

// ---------------------------------------------------------
// TRAITEMENTS ADMIN (Super-Admin & Admin des Tâches)
// ---------------------------------------------------------
if ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Sécurité : Seul le Super-Admin peut gérer les utilisateurs
    if ($_SESSION['role'] === 'admin') {
        if (isset($_POST['add_user'])) {
            $users = getDb(FILE_USERS);
            $users[uniqid('usr_')] = [
                'name' => $_POST['u_name'], 'email' => $_POST['u_email'],
                'password' => password_hash($_POST['u_pass'], PASSWORD_DEFAULT),
                'can_saisie' => isset($_POST['u_can_saisie']),
                'can_dashboard' => isset($_POST['u_can_dashboard']),
                'can_manage_tasks' => isset($_POST['u_can_manage_tasks']),
                'is_excluded' => isset($_POST['u_is_excluded'])
            ];
            saveDb(FILE_USERS, $users);
        }
        
        if (isset($_POST['edit_user'])) {
            $users = getDb(FILE_USERS);
            $uid = $_POST['user_id'];
            if (isset($users[$uid])) {
                $users[$uid]['name'] = $_POST['u_name'];
                $users[$uid]['email'] = $_POST['u_email'];
                if (!empty($_POST['u_pass'])) $users[$uid]['password'] = password_hash($_POST['u_pass'], PASSWORD_DEFAULT);
                $users[$uid]['can_saisie'] = isset($_POST['u_can_saisie']);
                $users[$uid]['can_dashboard'] = isset($_POST['u_can_dashboard']);
                $users[$uid]['can_manage_tasks'] = isset($_POST['u_can_manage_tasks']);
                $users[$uid]['is_excluded'] = isset($_POST['u_is_excluded']);
                saveDb(FILE_USERS, $users);
            }
        }
    }

    // Accessible au Super-Admin OU à l'Admin des tâches
    if (isset($_POST['add_task'])) {
        $tasks = getDb(FILE_TASKS);
        $tasks[uniqid('tsk_')] = [
            'title' => $_POST['t_title'], 
            'type'  => $_POST['t_type'],
            'desc'  => $_POST['t_desc'], 
            'itbm'  => $_POST['t_itbm'], 
            'color' => $_POST['t_color']
        ];
        saveDb(FILE_TASKS, $tasks);
    }
    header('Location: ?action=admin'); exit;
}

require 'includes/header.php';

if (in_array($action, ['login', 'init_admin'])) { require 'views/auth.php'; } 
elseif ($action === 'home') { require 'views/workspace.php'; } 
elseif ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks'))) { require 'views/admin.php'; }

echo "</div></body></html>";
