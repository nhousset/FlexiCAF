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
// TRAITEMENT : SAISIE MENSUELLE (Ajout ou Remplacement)
// ---------------------------------------------------------
if ($action === 'home' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['task_id'])) {
    if (!hasPermission('can_saisie') && $_SESSION['role'] !== 'admin') { die("Action non autorisée."); }

    $task_id = $_POST['task_id'];
    $valeur = floatval($_POST['valeur']); // Peut être 0 pour supprimer
    $month_saisie = $_POST['month_saisie']; 
    $edit_mode = $_POST['edit_mode'] ?? 'add'; // 'replace' ou 'add'
    
    $date_to_save = $month_saisie . '-01'; 
    $target_user_id = (!empty($_POST['target_user_id']) && $_SESSION['role'] === 'admin') ? $_POST['target_user_id'] : $_SESSION['user_id'];

    $data = getDb(FILE_DATA);

    // Si on est en mode "Remplacer", on supprime les entrées existantes pour ce mois/user/tâche
    if ($edit_mode === 'replace') {
        $data = array_filter($data, function($e) use ($target_user_id, $task_id, $date_to_save) {
            return !($e['user_id'] === $target_user_id && $e['task_id'] === $task_id && $e['date'] === $date_to_save);
        });
        $data = array_values($data); // Ré-indexer le tableau
    }

    // On insère la nouvelle valeur uniquement si elle est > 0
    if ($valeur > 0) {
        $data[] = [
            'id' => uniqid('evt_'),
            'user_id' => $target_user_id,
            'task_id' => $task_id,
            'date' => $date_to_save,
            'valeur_j' => $valeur
        ];
    }
    
    saveDb(FILE_DATA, $data);
    
    $redirect_url = '?action=home';
    if(isset($_GET['date'])) $redirect_url .= '&date='.$_GET['date'];
    if(isset($_GET['detail_uid'])) $redirect_url .= '&detail_uid='.$_GET['detail_uid'];
    header("Location: $redirect_url&success=1"); exit;
}

// ---------------------------------------------------------
// TRAITEMENTS ADMIN
// ---------------------------------------------------------
if ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
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

    if (isset($_POST['add_task'])) {
        $tasks = getDb(FILE_TASKS);
        $tasks[uniqid('tsk_')] = [
            'title' => $_POST['t_title'], 'type' => $_POST['t_type'],
            'desc'  => $_POST['t_desc'], 'itbm' => $_POST['t_itbm'], 'color' => $_POST['t_color']
        ];
        saveDb(FILE_TASKS, $tasks);
        header('Location: ?action=admin'); exit;
    }

    if (isset($_POST['update_tasks_json'])) {
        $raw_json = trim($_POST['raw_tasks_json']);
        $decoded = json_decode($raw_json, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            saveDb(FILE_TASKS, $decoded);
            header('Location: ?action=admin&json_success=1'); exit;
        } else {
            header('Location: ?action=admin&json_error=1'); exit;
        }
    }
}

require 'includes/header.php';

if (in_array($action, ['login', 'init_admin'])) { require 'views/auth.php'; } 
elseif ($action === 'home') { require 'views/workspace.php'; } 
elseif ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks'))) { require 'views/admin.php'; }

echo "</div></body></html>";
