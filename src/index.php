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
    if (!hasPermission('can_saisie') && $_SESSION['role'] !== 'admin') { die("Action non autorisée."); }

    $task_id = $_POST['task_id'];
    $valeur = floatval(str_replace(',', '.', trim($_POST['valeur'])));
    $month_saisie = $_POST['month_saisie']; 
    $edit_mode = $_POST['edit_mode'] ?? 'add'; 
    $date_to_save = $month_saisie . '-01'; 
    
    $canSaisieOthers = hasPermission('can_saisie_others') || $_SESSION['role'] === 'admin';
    $target_user_id = (!empty($_POST['target_user_id']) && $canSaisieOthers) ? $_POST['target_user_id'] : $_SESSION['user_id'];

    $data = getDb(FILE_DATA);

    if ($edit_mode === 'replace') {
        $data = array_filter($data, function($e) use ($target_user_id, $task_id, $date_to_save) {
            return !($e['user_id'] === $target_user_id && $e['task_id'] === $task_id && $e['date'] === $date_to_save);
        });
        $data = array_values($data); 
    }

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
    
    // Log Audit
    $tasks_for_audit = getDb(FILE_TASKS);
    $users_for_audit = getDb(FILE_USERS);
    $taskName = $tasks_for_audit[$task_id]['title'] ?? 'Inconnu';
    $targetName = ($target_user_id === $_SESSION['user_id']) ? $_SESSION['name'] : ($users_for_audit[$target_user_id]['name'] ?? 'Inconnu');
    $action_desc = ($edit_mode === 'replace') ? "Remplacement par" : "Ajout de";
    logAudit("Saisie Planning", "$action_desc $valeur JH pour $targetName sur le projet [$taskName] (Mois: $month_saisie)");
    
    $redirect_url = '?action=home';
    if(isset($_GET['date'])) $redirect_url .= '&date='.$_GET['date'];
    if(isset($_GET['detail_uid'])) $redirect_url .= '&detail_uid='.$_GET['detail_uid'];
    header("Location: $redirect_url&success=1"); exit;
}

// ---------------------------------------------------------
// TRAITEMENTS ADMIN
// ---------------------------------------------------------
if ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks'))) {
    
    if (isset($_GET['download_all']) && $_SESSION['role'] === 'admin') {
        if (!class_exists('ZipArchive')) {
            header('Location: ?action=admin&zip_error=1'); 
            exit;
        }

        $settings = getDb(FILE_SETTINGS);
        $teamName = !empty($settings['app_name']) ? $settings['app_name'] : 'FlexiCAF';
        $safeTeamName = preg_replace('/[^a-z0-9]/i', '_', $teamName);

        $zip = new ZipArchive();
        $zipname = 'backup_' . $safeTeamName . '_' . date('Y-m-d') . '.zip';
        $zip_path = sys_get_temp_dir() . '/' . $zipname;

        if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            $files_to_zip = ['users.json', 'tasks.json', 'data.json', 'admin.json', 'settings.json', 'audit.json'];
            
            foreach ($files_to_zip as $file) {
                $filepath = DIR_DB . $file;
                if (file_exists($filepath)) {
                    $zip->addFile($filepath, $file);
                }
            }
            $zip->close();

            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zipname . '"');
            header('Content-Length: ' . filesize($zip_path));
            header('Pragma: no-cache');
            header('Expires: 0');
            readfile($zip_path);
            
            unlink($zip_path);
            exit;
        } else {
            die("Erreur : Impossible de créer l'archive ZIP.");
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($_SESSION['role'] === 'admin') {
            
            if (isset($_POST['update_settings'])) {
                $settings = getDb(FILE_SETTINGS);
                $settings['app_name'] = trim($_POST['app_name']);
                if (empty($settings['app_name'])) $settings['app_name'] = 'FlexiCAF';
                // EVOLUTION: Enregistrement de l'option d'activation du Reste à planifier
                $settings['show_backlog'] = isset($_POST['show_backlog']); 
                saveDb(FILE_SETTINGS, $settings);
                logAudit("Configuration", "Modification des paramètres globaux de l'application");
                header('Location: ?action=admin&settings_success=1'); exit;
            }

            if (isset($_POST['add_user'])) {
                $users = getDb(FILE_USERS);
                $users[uniqid('usr_')] = [
                    'name' => $_POST['u_name'], 'email' => $_POST['u_email'],
                    'password' => password_hash($_POST['u_pass'], PASSWORD_DEFAULT),
                    'can_saisie' => isset($_POST['u_can_saisie']),
                    'can_saisie_others' => isset($_POST['u_can_saisie_others']),
                    'can_dashboard' => isset($_POST['u_can_dashboard']),
                    'can_manage_tasks' => isset($_POST['u_can_manage_tasks']),
                    'is_excluded' => isset($_POST['u_is_excluded'])
                ];
                saveDb(FILE_USERS, $users);
                logAudit("Utilisateurs", "Création du compte : " . $_POST['u_name']);
            }
            
            if (isset($_POST['edit_user'])) {
                $users = getDb(FILE_USERS);
                $uid = $_POST['user_id'];
                if (isset($users[$uid])) {
                    $users[$uid]['name'] = $_POST['u_name'];
                    $users[$uid]['email'] = $_POST['u_email'];
                    if (!empty($_POST['u_pass'])) $users[$uid]['password'] = password_hash($_POST['u_pass'], PASSWORD_DEFAULT);
                    $users[$uid]['can_saisie'] = isset($_POST['u_can_saisie']);
                    $users[$uid]['can_saisie_others'] = isset($_POST['u_can_saisie_others']);
                    $users[$uid]['can_dashboard'] = isset($_POST['u_can_dashboard']);
                    $users[$uid]['can_manage_tasks'] = isset($_POST['u_can_manage_tasks']);
                    $users[$uid]['is_excluded'] = isset($_POST['u_is_excluded']);
                    saveDb(FILE_USERS, $users);
                    logAudit("Utilisateurs", "Modification des droits/infos du compte : " . $_POST['u_name']);
                }
            }

            if (isset($_POST['update_users_json'])) {
                $raw_json = trim($_POST['raw_users_json']);
                $decoded = json_decode($raw_json, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $_SESSION['json_error_msg'] = "Syntaxe JSON invalide : " . json_last_error_msg();
                    header('Location: ?action=admin&json_user_error=1'); exit;
                } elseif (!is_array($decoded)) {
                    $_SESSION['json_error_msg'] = "La structure JSON est incorrecte (doit être un tableau ou un objet).";
                    header('Location: ?action=admin&json_user_error=1'); exit;
                } else {
                    if (file_exists(FILE_USERS) && !is_writable(FILE_USERS)) {
                        $_SESSION['json_error_msg'] = "Droits insuffisants : le fichier users.json n'est pas accessible en écriture.";
                        header('Location: ?action=admin&json_user_error=1'); exit;
                    }
                    
                    $result = @file_put_contents(FILE_USERS, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    if ($result === false) {
                        $_SESSION['json_error_msg'] = "Erreur système lors de l'écriture sur le disque.";
                        header('Location: ?action=admin&json_user_error=1'); exit;
                    }
                    
                    logAudit("JSON Utilisateurs", "Mise à jour de la base complète en mode expert (JSON).");
                    header('Location: ?action=admin&json_user_success=1'); exit;
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
            logAudit("Catalogue", "Ajout de la nouvelle activité : " . $_POST['t_title']);
            header('Location: ?action=admin'); exit;
        }

        if (isset($_POST['edit_task'])) {
            $tasks = getDb(FILE_TASKS);
            $tid = $_POST['task_id'];
            if (isset($tasks[$tid])) {
                $tasks[$tid]['title'] = $_POST['t_title'];
                $tasks[$tid]['type']  = $_POST['t_type'];
                $tasks[$tid]['color'] = $_POST['t_color'];
                $tasks[$tid]['itbm']  = $_POST['t_itbm'];
                $tasks[$tid]['desc']  = $_POST['t_desc'];
                saveDb(FILE_TASKS, $tasks);
                logAudit("Catalogue", "Modification de l'activité : " . $_POST['t_title']);
            }
            header('Location: ?action=admin'); exit;
        }

        if (isset($_POST['update_tasks_json'])) {
            $raw_json = trim($_POST['raw_tasks_json']);
            $decoded = json_decode($raw_json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $_SESSION['json_error_msg'] = "Syntaxe JSON invalide : " . json_last_error_msg();
                header('Location: ?action=admin&json_error=1'); exit;
            } elseif (!is_array($decoded)) {
                $_SESSION['json_error_msg'] = "La structure JSON est incorrecte (doit être un tableau ou un objet).";
                header('Location: ?action=admin&json_error=1'); exit;
            } else {
                if (file_exists(FILE_TASKS) && !is_writable(FILE_TASKS)) {
                    $_SESSION['json_error_msg'] = "Droits insuffisants : le fichier tasks.json n'est pas modifiable (vérifiez le CHMOD).";
                    header('Location: ?action=admin&json_error=1'); exit;
                }
                
                $result = @file_put_contents(FILE_TASKS, json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                if ($result === false) {
                    $_SESSION['json_error_msg'] = "Erreur système lors de l'écriture sur le disque.";
                    header('Location: ?action=admin&json_error=1'); exit;
                }
                
                logAudit("JSON Catalogue", "Mise à jour du catalogue complet en mode expert (JSON).");
                header('Location: ?action=admin&json_success=1'); exit;
            }
        }
    }
}

require 'includes/header.php';

if (in_array($action, ['login', 'init_admin'])) { require 'views/auth.php'; } 
elseif ($action === 'home') { require 'views/workspace.php'; } 
elseif ($action === 'admin' && ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks'))) { require 'views/admin.php'; }

echo "</div></body></html>";
