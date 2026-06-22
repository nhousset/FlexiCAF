<?php
session_start();

define('DIR_DB', __DIR__ . '/../db/');
define('FILE_ADMIN', DIR_DB . 'admin.json');
define('FILE_USERS', DIR_DB . 'users.json');
define('FILE_TASKS', DIR_DB . 'tasks.json');
define('FILE_DATA', DIR_DB . 'data.json');
define('FILE_SETTINGS', DIR_DB . 'settings.json'); // NOUVEAU

if (!is_dir(DIR_DB)) mkdir(DIR_DB, 0755, true);

// Initialisations des fichiers par défaut
if (!file_exists(FILE_USERS)) file_put_contents(FILE_USERS, json_encode([]));
if (!file_exists(FILE_DATA)) file_put_contents(FILE_DATA, json_encode([]));
if (!file_exists(FILE_SETTINGS)) file_put_contents(FILE_SETTINGS, json_encode(['app_name' => 'FlexiCAF'], JSON_PRETTY_PRINT));

if (!file_exists(FILE_TASKS)) {
    $defaultTasks = [
        uniqid('tsk_') => ['title' => 'Run - Support N2/N3', 'type' => 'Technique', 'desc' => 'Résolution incidents', 'itbm' => 'ITBM-RUN-001', 'color' => '#bae6fd'],
        uniqid('tsk_') => ['title' => 'Comités et Réunions', 'type' => 'Structure', 'desc' => 'Admin', 'itbm' => 'ITBM-MCO-002', 'color' => '#fef08a'],
        uniqid('tsk_') => ['title' => 'Congés Payés', 'type' => 'Absences', 'desc' => 'Absence', 'itbm' => 'ITBM-ABS-000', 'color' => '#fecaca'],
        uniqid('tsk_') => ['title' => 'Projet Migration', 'type' => 'Fonctionnel', 'desc' => 'Build', 'itbm' => 'ITBM-PRJ-010', 'color' => '#bbf7d0']
    ];
    file_put_contents(FILE_TASKS, json_encode($defaultTasks, JSON_PRETTY_PRINT));
}

function getDb($file) { return json_decode(file_get_contents($file), true) ?: []; }
function saveDb($file, $data) { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }

function generateDatesList($start, $end, $mode, $selectedDays = []) {
    $dates = [];
    $begin = new DateTime($start);
    $endDt = new DateTime($end);
    $endDt->modify('+1 day'); 
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $endDt);

    foreach ($daterange as $date) {
        $dayOfWeek = $date->format('N'); 
        if ($mode === 'continue') {
            if ($dayOfWeek < 6) $dates[] = $date->format('Y-m-d');
        } elseif ($mode === 'recurrence') {
            if (in_array($dayOfWeek, $selectedDays)) $dates[] = $date->format('Y-m-d');
        } else {
            $dates[] = $date->format('Y-m-d');
        }
    }
    return $dates;
}

function hasPermission($perm) {
    if ($_SESSION['role'] === 'admin') return true;
    $users = getDb(FILE_USERS);
    $u = $users[$_SESSION['user_id']] ?? [];
    return isset($u[$perm]) ? (bool)$u[$perm] : false;
}
