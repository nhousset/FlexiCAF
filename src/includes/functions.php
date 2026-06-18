<?php
session_start();

define('DIR_DB', __DIR__ . '/../db/');
define('FILE_ADMIN', DIR_DB . 'admin.json');
define('FILE_USERS', DIR_DB . 'users.json');
define('FILE_TASKS', DIR_DB . 'tasks.json');
define('FILE_DATA', DIR_DB . 'data.json');

// Création du répertoire de données s'il n'existe pas
if (!is_dir(DIR_DB)) mkdir(DIR_DB, 0755, true);

// Init des fichiers par défaut si manquants
if (!file_exists(FILE_USERS)) file_put_contents(FILE_USERS, json_encode([]));
if (!file_exists(FILE_DATA)) file_put_contents(FILE_DATA, json_encode([]));
if (!file_exists(FILE_TASKS)) {
    // Tâches IT Ops par défaut avec code ITBM
    $defaultTasks = [
        uniqid() => ['title' => 'Run - Support N2/N3', 'desc' => 'Résolution d\'incidents et requêtes', 'itbm' => 'ITBM-RUN-001'],
        uniqid() => ['title' => 'MCO Infrastructure', 'desc' => 'Supervision, patchs, maintenance', 'itbm' => 'ITBM-MCO-002'],
        uniqid() => ['title' => 'Congés Payés', 'desc' => 'Absence validée', 'itbm' => 'ITBM-ABS-000']
    ];
    file_put_contents(FILE_TASKS, json_encode($defaultTasks, JSON_PRETTY_PRINT));
}

function getDb($file) { return json_decode(file_get_contents($file), true) ?: []; }
function saveDb($file, $data) { file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)); }

// Générateur de dates pour les saisies multiples
function generateDatesList($start, $end, $mode, $selectedDays = []) {
    $dates = [];
    $begin = new DateTime($start);
    $endDt = new DateTime($end);
    $endDt->modify('+1 day'); // Inclure le dernier jour
    
    $interval = new DateInterval('P1D');
    $daterange = new DatePeriod($begin, $interval, $endDt);

    foreach ($daterange as $date) {
        $dayOfWeek = $date->format('N'); // 1 = Lundi, 7 = Dimanche
        if ($mode === 'continue') {
            if ($dayOfWeek < 6) $dates[] = $date->format('Y-m-d'); // Hors WE
        } elseif ($mode === 'recurrence') {
            if (in_array($dayOfWeek, $selectedDays)) $dates[] = $date->format('Y-m-d');
        } else {
            $dates[] = $date->format('Y-m-d'); // Mode unique (une seule date passée)
        }
    }
    return $dates;
}
