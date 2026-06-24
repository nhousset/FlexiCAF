<?php
$tasks = getDb(FILE_TASKS); 
$allData = getDb(FILE_DATA);
$allUsers = getDb(FILE_USERS);

$canSaisie = hasPermission('can_saisie');
$canDashboard = hasPermission('can_dashboard');
// VÉRIFICATION DE LA NOUVELLE PERMISSION
$canSaisieOthers = hasPermission('can_saisie_others') || $_SESSION['role'] === 'admin';

// --------------------------------------------------------
// MOTEUR DE NAVIGATION TEMPORELLE (Fenêtre de 6 mois)
// --------------------------------------------------------
$anchor_date_str = $_GET['date'] ?? date('Y-m-01');
$start_dash = new DateTime($anchor_date_str);
$start_dash->modify('first day of this month');

$nav_prev = (clone $start_dash)->modify('-6 months')->format('Y-m-d');
$nav_next = (clone $start_dash)->modify('+6 months')->format('Y-m-d');

$mois_fr = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];

$dash_months = [];
for ($i = 0; $i < 6; $i++) {
    $month_key = $start_dash->format('Y-m');
    $dash_months[$month_key] = [
        'label' => $mois_fr[(int)$start_dash->format('n')] . ' ' . $start_dash->format('Y'),
        'date_obj' => clone $start_dash,
        'working_days' => 0
    ];
    
    $curr = clone $start_dash;
    $end_of_month = (clone $start_dash)->modify('last day of this month');
    while($curr <= $end_of_month) {
        if (!in_array($curr->format('N'), [6, 7])) $dash_months[$month_key]['working_days']++;
        $curr->modify('+1 day');
    }
    $start_dash->modify('+1 month');
}

// --------------------------------------------------------
// PRÉPARATION DES ACTEURS
// --------------------------------------------------------
$displayUsers = [];
if ($_SESSION['role'] === 'admin' || $canDashboard) {
    foreach($allUsers as $id => $u) {
        if (empty($u['is_excluded'])) {
            $displayUsers[$id] = mb_strtoupper($u['name'], 'UTF-8');
        }
    }
    $displayUsers['_virtual_unassigned_'] = 'RESTE À PLANIFIER';
} else {
    $displayUsers[$_SESSION['user_id']] = mb_strtoupper($_SESSION['name'], 'UTF-8');
}

$real_users_count = 0;
foreach($displayUsers as $uid => $uname) {
    if ($uid !== '_virtual_unassigned_') $real_users_count++;
}

// --------------------------------------------------------
// INITIALISATION DES VUES CROISÉES
// --------------------------------------------------------
$pivot_user_month = [];
$pivot_task_month = [];
$pivot_task_user  = [];
$breakdown_user_month_task = []; 

foreach($displayUsers as $uid => $uname) {
    $pivot_user_month[$uid] = array_fill_keys(array_keys($dash_months), 0);
    $breakdown_user_month_task[$uid] = array_fill_keys(array_keys($dash_months), []);
    foreach($tasks as $tid => $t) $pivot_task_user[$tid][$uid] = 0;
}
foreach($tasks as $tid => $t) {
    $pivot_task_month[$tid] = array_fill_keys(array_keys($dash_months), 0);
}

// --------------------------------------------------------
// PRÉPARATION SPÉCIFIQUE : DÉTAIL CONSULTANT
// --------------------------------------------------------
$detail_uid = $_GET['detail_uid'] ?? $_SESSION['user_id'];
if ($_SESSION['role'] !== 'admin' && !$canDashboard) {
    $detail_uid = $_SESSION['user_id'];
}

if (!isset($displayUsers[$detail_uid])) {
    $keys = array_keys($displayUsers);
    $detail_uid = !empty($keys) ? $keys[0] : '';
}

$detail_uname = $displayUsers[$detail_uid] ?? 'INCONNU';

$tasksByType = [];
foreach($tasks as $tid => $t) {
    $type = $t['type'] ?? 'Technique';
    $tasksByType[$type][$tid] = $t;
}
ksort($tasksByType); 

$detail_grid = [];
foreach($tasks as $tid => $t) {
    $detail_grid[$tid] = array_fill_keys(array_keys($dash_months), 0);
}

// --------------------------------------------------------
// AGRÉGATION DES DONNÉES
// --------------------------------------------------------
$chart_type_month = []; 

foreach($allData as $e) {
    $uid = $e['user_id'];
    $tid = $e['task_id'];
    
    $dt = new DateTime($e['date']);
    $m_key = $dt->format('Y-m');
    
    if (isset($dash_months[$m_key])) {
        if (isset($displayUsers[$uid])) {
            $pivot_user_month[$uid][$m_key] += $e['valeur_j'];
            if(isset($pivot_task_month[$tid])) $pivot_task_month[$tid][$m_key] += $e['valeur_j'];
            if(isset($pivot_task_user[$tid][$uid])) $pivot_task_user[$tid][$uid] += $e['valeur_j'];
            
            if (!isset($breakdown_user_month_task[$uid][$m_key][$tid])) {
                $breakdown_user_month_task[$uid][$m_key][$tid] = 0;
            }
            $breakdown_user_month_task[$uid][$m_key][$tid] += $e['valeur_j'];
            
            // AGREGATION POUR LE GRAPHIQUE
            $t_type = $tasks[$tid]['type'] ?? 'Autre';
            if(!isset($chart_type_month[$t_type])) {
                $chart_type_month[$t_type] = array_fill_keys(array_keys($dash_months), 0);
            }
            $chart_type_month[$t_type][$m_key] += $e['valeur_j'];
        }
        
        if ($uid === $detail_uid && isset($detail_grid[$tid])) {
            $detail_grid[$tid][$m_key] += $e['valeur_j'];
        }
    }
}

// --------------------------------------------------------
// MOTEUR DE COULEURS HEATMAP
// --------------------------------------------------------
function getMonthlyHeatmapStyle($valeur, $capacite_max, $is_virtual = false) {
    if ($is_virtual) {
        if ($valeur == 0) return 'background-color: #f8f9fa; color: #adb5bd;';
        return 'background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #334155; font-weight: bold; border: 1px dashed #94a3b8; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);';
    }
    
    if ($valeur == 0) return 'background-color: #ffffff;';
    $perc = $capacite_max > 0 ? round(($valeur / $capacite_max) * 100) : 0;
    
    if ($perc <= 60) {
        return 'background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; border: 1px solid #6ee7b7; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);'; 
    }
    if ($perc <= 90) {
        return 'background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: #022c22; font-weight: bold; border: 1px solid #059669; box-shadow: inset 0 2px 4px rgba(255,255,255,0.3);'; 
    }
    if ($perc <= 100) {
        return 'background: linear-gradient(135deg, #fde047 0%, #facc15 100%); color: #713f12; font-weight: bold; border: 1px solid #eab308; box-shadow: inset 0 2px 4px rgba(255,255,255,0.4);'; 
    }
    return 'background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: #ffffff; font-weight: bold; border: 1px solid #991b1b; box-shadow: inset 0 2px 4px rgba(255,255,255,0.2), 0 2px 5px rgba(239, 68, 68, 0.3); text-shadow: 0 1px 2px rgba(0,0,0,0.2);'; 
}

function getDetailHeatmapStyle($valeur) {
    if ($valeur == 0) return 'background-color: #ffffff;';
    return 'background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; border: 1px solid #6ee7b7; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); font-weight: bold; transition: all 0.2s;'; 
}

function getProjectHeatmapStyle($valeur) {
    if ($valeur == 0) return 'background-color: #ffffff;';
    return 'background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%); color: #3730a3; border: 1px solid #a5b4fc; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5); font-weight: bold; transition: all 0.2s;';
}

// --------------------------------------------------------
// PRÉPARATION DONNÉES GRAPHIQUE (CHART.JS)
// --------------------------------------------------------
$typeColors = [
    'Technique' => '#fef08a',   
    'Fonctionnel' => '#bbf7d0', 
    'Structure' => '#bae6fd',   
    'Absences' => '#fca5a5',    
    'Formation' => '#e9d5ff'    
];

$chartDatasets = [];

$capData = [];
foreach($dash_months as $m_key => $m_data) {
    $capData[] = $m_data['working_days'] * $real_users_count;
}
$chartDatasets[] = [
    'type' => 'line',
    'label' => 'Capacité de l\'équipe',
    'data' => $capData,
    'borderColor' => '#ef4444', 
    'backgroundColor' => '#ef4444',
    'borderWidth' => 2,
    'fill' => false,
    'tension' => 0.3,
    'pointRadius' => 5,
    'pointBackgroundColor' => '#ffffff',
    'pointBorderColor' => '#ef4444',
    'pointBorderWidth' => 2,
    'order' => 0 
];

foreach($chart_type_month as $type => $monthsData) {
    if(array_sum($monthsData) > 0) {
        $chartDatasets[] = [
            'type' => 'bar',
            'label' => $type,
            'data' => array_values($monthsData),
            'backgroundColor' => $typeColors[$type] ?? '#cbd5e1', 
            'borderColor' => 'rgba(0,0,0,0.1)',
            'borderWidth' => 1,
            'order' => 1
        ];
    }
}
?>

<style>
/* =======================================================
   Figer l'en-tête des tableaux principaux
   ======================================================= */
#viewTabsContent .table-responsive {
    max-height: 70vh; 
    overflow-y: auto;
}
#viewTabsContent .table-responsive thead th {
    position: sticky;
    top: 0;
    z-index: 100;
    background-color: #f8fafc !important; 
    box-shadow: inset 0 -2px 0 #e2e8f0, 0 4px 6px -2px rgba(0,0,0,0.05); 
}

/* =======================================================
   ÉVOLUTION : Animation Fade plus smooth pour les onglets
   ======================================================= */
.tab-pane.fade {
    transition: opacity 0.4s ease-in-out;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3 bg-white p-2 rounded shadow-sm border">
    <div>
        <a href="?action=home&date=<?= $nav_prev ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-double-left"></i> 6 Mois Précédents</a>
        <a href="?action=home&date=<?= date('Y-m-01') ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>" class="btn btn-sm btn-light border mx-1">Mois en cours</a>
        <a href="?action=home&date=<?= $nav_next ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>" class="btn btn-sm btn-light border">6 Mois Suivants <i class="bi bi-chevron-double-right"></i></a>
    </div>
    <div class="text-muted small fw-bold">
        Pilotage Mensuel de la Capacité
    </div>
</div>

<ul class="nav nav-tabs" id="viewTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="vue1-tab" data-bs-toggle="tab" data-bs-target="#vue1" type="button"><i class="bi bi-person-lines-fill"></i> Consultant / Mois</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-primary fw-bold" id="vue-detail-tab" data-bs-toggle="tab" data-bs-target="#vue-detail" type="button"><i class="bi bi-person-badge"></i> Détail Consultant</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="vue2-tab" data-bs-toggle="tab" data-bs-target="#vue2" type="button"><i class="bi bi-folder-fill"></i> Projet / Mois</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="vue3-tab" data-bs-toggle="tab" data-bs-target="#vue3" type="button"><i class="bi bi-grid-3x3-gap-fill"></i> Projet / Consultant</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link text-secondary fw-bold" id="vue-graph-tab" data-bs-toggle="tab" data-bs-target="#vue-graph" type="button"><i class="bi bi-bar-chart-fill"></i> Graphique</button>
  </li>
  <?php if($canSaisie): ?>
  <li class="nav-item ms-auto" role="presentation">
    <button class="nav-link text-success fw-bold" id="saisie-tab" data-bs-toggle="tab" data-bs-target="#saisie" type="button"><i class="bi bi-plus-circle"></i> Saisie Libre</button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content bg-white border border-top-0 p-3 rounded-bottom shadow-sm" id="viewTabsContent">

    <div class="tab-pane fade show active" id="vue1" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-start text-muted text-uppercase small w-25">Ressources (<?= $real_users_count ?> + Backlog)</th>
                        <?php foreach($dash_months as $m_key => $m_data): ?>
                            <th style="min-width: 120px;">
                                <div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div>
                                <div class="small text-muted fw-normal" style="font-size: 0.65rem;">Max: <?= $m_data['working_days'] ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($displayUsers as $uid => $uname): 
                        $isVirtual = ($uid === '_virtual_unassigned_');
                    ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center">
                                <?php if($isVirtual): ?>
                                    <div class="bg-warning text-dark rounded-circle text-center me-2 shadow-sm border border-dark" style="width:28px; height:28px; line-height:28px; font-weight:bold; font-size: 0.8rem;">
                                        <i class="bi bi-inbox-fill"></i>
                                    </div>
                                    <span class="fw-bold text-dark small fst-italic"><?= htmlspecialchars($uname) ?></span>
                                <?php else: ?>
                                    <div class="bg-dark text-white rounded-circle text-center me-2" style="width:28px; height:28px; line-height:28px; font-weight:bold; font-size: 0.75rem;">
                                        <?= mb_strtoupper(mb_substr($uname, 0, 1, 'UTF-8'), 'UTF-8') ?>
                                    </div>
                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_user_month[$uid][$m_key] ?? 0;
                            $cap_max = $m_data['working_days'];
                            $style = getMonthlyHeatmapStyle($valeur, $cap_max, $isVirtual);
                            
                            $details_array = [];
                            if (isset($breakdown_user_month_task[$uid][$m_key])) {
                                foreach($breakdown_user_month_task[$uid][$m_key] as $tid => $val) {
                                    if ($val > 0) {
                                        $details_array[] = [
                                            'title' => $tasks[$tid]['title'] ?? 'Inconnu',
                                            'type' => $tasks[$tid]['type'] ?? 'Technique',
                                            'color' => $tasks[$tid]['color'] ?? '#e2e8f0',
                                            'val' => $val
                                        ];
                                    }
                                }
                            }
                            $details_json = htmlspecialchars(json_encode($details_array), ENT_QUOTES, 'UTF-8');
                        ?>
                            <td style="<?= $style ?> position: relative; cursor: pointer; transition: all 0.2s;" 
                                onclick="openDetailModal('<?= addslashes(htmlspecialchars($uname)) ?>', '<?= $m_key ?>', this)"
                                data-details="<?= $details_json ?>">
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?></div>
                                    <?php if(!$isVirtual): ?>
                                        <div style="font-size: 0.65rem; opacity: 0.8;"><?= round(($valeur/$cap_max)*100) ?>% Alloué</div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL ÉQUIPE (Charge / Capacité)</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = 0;
                            foreach($displayUsers as $uid => $uname) {
                                $total_load += $pivot_user_month[$uid][$m_key] ?? 0;
                            }
                            $total_cap = $m_data['working_days'] * $real_users_count;
                            $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?></div>
                                <div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap ?>)</div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="mt-2 small text-muted"><i class="bi bi-info-circle"></i> Cliquez sur une cellule pour consulter le détail des affectations de ce mois.</div>
    </div>

    <div class="tab-pane fade" id="vue-detail" role="tabpanel">
        
        <?php if ($_SESSION['role'] === 'admin' || $canDashboard): ?>
        <form method="GET" class="mb-4 d-flex align-items-center bg-light p-2 rounded border">
            <input type="hidden" name="action" value="home">
            <input type="hidden" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
            <label class="me-3 fw-bold small text-primary"><i class="bi bi-funnel-fill"></i> Zoom sur :</label>
            <select name="detail_uid" class="form-select form-select-sm w-auto fw-bold" onchange="this.form.submit()">
                <?php foreach($displayUsers as $uid => $uname): ?>
                    <option value="<?= $uid ?>" <?= $uid === $detail_uid ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php else: ?>
            <h5 class="mb-3 text-primary fw-bold"><i class="bi bi-person-badge"></i> Mon Détail d'Affectation</h5>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th class="text-start text-muted text-uppercase small w-25">Projets & Activités</th>
                        <?php foreach($dash_months as $m_key => $m_data): ?>
                            <th><div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach($tasksByType as $type => $groupTasks): 
                        $hasDataInGroup = false;
                        foreach($groupTasks as $tid => $t) {
                            if(array_sum($detail_grid[$tid]) > 0) { $hasDataInGroup = true; break; }
                        }
                    ?>
                        <tr class="table-secondary">
                            <td colspan="7" class="fw-bold text-uppercase" style="letter-spacing: 1px; font-size: 0.8rem;">
                                <i class="bi bi-collection"></i> <?= mb_strtoupper(htmlspecialchars($type), 'UTF-8') ?>
                            </td>
                        </tr>
                        <?php foreach($groupTasks as $tid => $t): 
                            $color = htmlspecialchars($t['color'] ?? '#e2e8f0');
                        ?>
                        <tr>
                            <td class="text-start ps-4">
                                <div class="d-flex align-items-center">
                                    <div class="rounded me-2" style="width: 10px; height: 10px; background-color: <?= $color ?>;"></div>
                                    <div>
                                        <div class="fw-bold text-dark small"><?= htmlspecialchars($t['title']) ?></div>
                                        <div class="text-muted" style="font-size: 0.6rem;"><?= htmlspecialchars($t['itbm']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <?php foreach($dash_months as $m_key => $m_data): 
                                $valeur = $detail_grid[$tid][$m_key] ?? 0;
                                $isClickable = ($_SESSION['role'] === 'admin' || $canSaisieOthers || ($canSaisie && $detail_uid === $_SESSION['user_id']));
                                $style = getDetailHeatmapStyle($valeur);
                            ?>
                                <td class="text-center align-middle" style="<?= $style ?> <?= $isClickable ? "cursor: pointer;" : "" ?>" 
                                    <?php if($isClickable): ?>
                                    onclick="openFastModal('<?= $detail_uid ?>', '<?= addslashes(htmlspecialchars($detail_uname)) ?>', '<?= $m_key ?>', '<?= $tid ?>')"
                                    <?php endif; ?>>
                                    
                                    <?php if($valeur > 0): ?>
                                        <div class="fs-6"><?= $valeur ?></div>
                                    <?php else: ?>
                                        <span class="text-muted" style="opacity: 0.15;">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark text-center">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL AFFECTÉ (Ce profil)</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = $pivot_user_month[$detail_uid][$m_key] ?? 0;
                            $cap_max = ($detail_uid === '_virtual_unassigned_') ? 0 : $m_data['working_days'];
                            $perc = $cap_max > 0 ? round(($total_load / $cap_max) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $cap_max, ($detail_uid === '_virtual_unassigned_'));
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?></div>
                                <?php if($cap_max > 0): ?>
                                    <div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $cap_max ?>)</div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="tab-pane fade" id="vue2" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr>
                        <th class="text-start text-muted text-uppercase small w-25">Projets & Activités</th>
                        <?php foreach($dash_months as $m_key => $m_data): ?>
                            <th><div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tasks as $tid => $t): 
                        if(array_sum($pivot_task_month[$tid]) == 0) continue;
                        $color = htmlspecialchars($t['color'] ?? '#e2e8f0');
                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                    ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center">
                                <div class="rounded me-2" style="width: 12px; height: 12px; background-color: <?= $color ?>;"></div>
                                <div>
                                    <div class="fw-bold text-dark small"><?= htmlspecialchars($t['title']) ?></div>
                                    <div class="text-muted" style="font-size: 0.65rem;">
                                        <span class="badge bg-light text-dark border me-1"><?= mb_strtoupper($type, 'UTF-8') ?></span>
                                        <?= htmlspecialchars($t['itbm']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_task_month[$tid][$m_key] ?? 0;
                            $style = getProjectHeatmapStyle($valeur);
                        ?>
                            <td class="text-center align-middle" style="<?= $style ?> <?= $canSaisieOthers ? "cursor: pointer;" : "" ?>"
                                <?php if($canSaisieOthers): ?>
                                    onclick="openFastModal('', '', '<?= $m_key ?>', '<?= $tid ?>')" title="Affecter quelqu'un sur ce projet"
                                <?php endif; ?>>
                                
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?></div>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.2;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark text-center">
                    <tr>
                        <td class="text-end fw-bold align-middle">EFFORT TOTAL DÉPLOYÉ</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = 0;
                            foreach($displayUsers as $uid => $uname) $total_load += $pivot_user_month[$uid][$m_key] ?? 0;
                            
                            $total_cap = $m_data['working_days'] * $real_users_count;
                            $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?></div>
                                <div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap ?>)</div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="tab-pane fade" id="vue3" role="tabpanel">
        <div class="alert alert-light border small py-2 mb-3">
            <i class="bi bi-info-square"></i> Cette vue agrège l'effort total de chaque consultant sur la période affichée (les 6 mois).
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th class="text-start text-muted text-uppercase small">Projets / Activités</th>
                        <?php foreach($displayUsers as $uid => $uname): ?>
                            <th class="small fw-bold text-dark"><?= htmlspecialchars($uname) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tasks as $tid => $t): 
                        if(array_sum($pivot_task_user[$tid]) == 0) continue;
                        $color = htmlspecialchars($t['color'] ?? '#e2e8f0');
                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                    ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center">
                                <div class="rounded me-2" style="width: 12px; height: 12px; background-color: <?= $color ?>;"></div>
                                <div>
                                    <span class="fw-bold text-dark small d-block"><?= htmlspecialchars($t['title']) ?></span>
                                    <span class="badge bg-light text-dark border" style="font-size: 0.55rem;"><?= mb_strtoupper($type, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </td>
                        <?php foreach($displayUsers as $uid => $uname): 
                            $valeur = $pivot_task_user[$tid][$uid] ?? 0;
                            $style = getProjectHeatmapStyle($valeur);
                        ?>
                            <td class="text-center align-middle" style="<?= $style ?>">
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?></div>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.2;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL CUMULÉ (6 MOIS)</td>
                        <?php foreach($displayUsers as $uid => $uname): 
                            $total_load = array_sum(array_column($pivot_task_user, $uid));
                            $total_cap_6m = ($uid === '_virtual_unassigned_') ? 0 : array_sum(array_column($dash_months, 'working_days'));
                            $perc = $total_cap_6m > 0 ? round(($total_load / $total_cap_6m) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $total_cap_6m, ($uid === '_virtual_unassigned_'));
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?></div>
                                <?php if($total_cap_6m > 0): ?>
                                    <div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap_6m ?>)</div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="tab-pane fade" id="vue-graph" role="tabpanel">
        <div class="alert alert-light border small py-2 mb-3 shadow-sm">
            <i class="bi bi-info-square text-primary"></i> <strong>Aperçu analytique :</strong> Répartition de la charge globale de l'équipe par type d'activité superposée à la capacité théorique.
        </div>
        <div class="bg-white p-4 rounded shadow-sm border">
            <canvas id="capacityChart" style="max-height: 500px; width: 100%;"></canvas>
        </div>
    </div>

    <?php if($canSaisie): ?>
    <div class="tab-pane fade mt-3" id="saisie" role="tabpanel">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white"><i class="bi bi-pencil-square"></i> Déclarer une charge manuellement</div>
                    <div class="card-body">
                        <form method="POST">
                            
                            <?php if ($canSaisieOthers): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary"><i class="bi bi-person-fill"></i> Consultant ciblé</label>
                                <select name="target_user_id" class="form-select border-primary" required>
                                    <?php foreach($displayUsers as $uid => $uname): ?>
                                        <option value="<?= $uid ?>" <?= $uid === $_SESSION['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label small fw-bold">Projet / Activité ciblé</label>
                                <select name="task_id" class="form-select" required>
                                    <option value="" disabled selected>Sélectionner un projet...</option>
                                    <?php foreach($tasks as $id => $t): 
                                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                                    ?>
                                        <option value="<?= $id ?>">
                                            [<?= mb_strtoupper($type, 'UTF-8') ?>] <?= htmlspecialchars($t['title']) ?> (<?= htmlspecialchars($t['itbm']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Mois d'imputation</label>
                                    <input type="month" name="month_saisie" class="form-control fw-bold text-primary" value="<?= date('Y-m') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Volume</label>
                                    <div class="input-group">
                                        <input type="text" inputmode="decimal" pattern="^[0-9]*([.,][0-9]+)?$" name="valeur" class="form-control fw-bold text-center" value="1" placeholder="ex: 0.5" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 bg-light p-2 rounded border">
                                <label class="form-label small fw-bold text-muted">Mode de sauvegarde :</label>
                                <select name="edit_mode" class="form-select form-select-sm">
                                    <option value="add">Ajouter à l'existant (+)</option>
                                    <option value="replace" selected>Remplacer l'existant (=)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 fw-bold py-2"><i class="bi bi-save"></i> Enregistrer l'affectation</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="fastAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header py-2 border-0 transition-color" id="fastAddModalHeader" style="background-color: #212529; color: #fff; transition: background-color 0.3s ease;">
        <h6 class="modal-title mb-0 fw-bold" id="fastAddModalTitle"><i class="bi bi-journal-check me-1" style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);"></i> <span style="text-shadow: 0 1px 2px rgba(0,0,0,0.3);">Affectation</span></h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="fastAddModalCloseBtn"></button>
      </div>
      <div class="modal-body bg-light">
        <form method="POST" action="?action=home<?= isset($_GET['date']) ? '&date='.$_GET['date'] : '' ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>">
            <input type="hidden" name="month_saisie" id="modal_month" value="">
            
            <div class="text-center mb-3">
                <div id="modal_uname_display" class="fw-bold text-dark fs-6 mb-1"></div>
                
                <?php if ($canSaisieOthers): ?>
                <select name="target_user_id" id="modal_uid_select" class="form-select form-select-sm border-primary fw-bold mb-2 d-none" required>
                    <?php foreach($displayUsers as $uid => $uname): ?>
                        <option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                    <input type="hidden" name="target_user_id" id="modal_uid_hidden" value="">
                <?php endif; ?>
                
                <div class="badge bg-secondary shadow-sm" id="modal_month_display"></div>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Sur le projet :</label>
                <select name="task_id" id="modal_task_id" class="form-select form-select-sm" required onchange="updateModalColor()">
                    <option value="" disabled selected data-color="#212529">Sélectionner...</option>
                    <?php foreach($tasks as $id => $t): 
                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                        $color = htmlspecialchars($t['color'] ?? '#212529');
                    ?>
                        <option value="<?= $id ?>" data-color="<?= $color ?>">[<?= mb_strtoupper($type, 'UTF-8') ?>] <?= htmlspecialchars($t['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted d-flex justify-content-between">
                    <span>Volume (0 pour effacer) :</span>
                </label>
                
                <input type="range" class="form-range mb-2" id="valeur_slider" min="0" max="10" step="0.1" value="1" oninput="syncValeur(this.value, 'slider')">
                
                <div class="input-group input-group-sm">
                    <input type="text" inputmode="decimal" pattern="^[0-9]*([.,][0-9]+)?$" name="valeur" id="valeur_input" class="form-control text-center fw-bold" value="1" placeholder="ex: 0.5" required oninput="syncValeur(this.value, 'input')">
                </div>
            </div>

            <div class="mb-4">
                <select name="edit_mode" class="form-select form-select-sm bg-white">
                    <option value="replace" selected>Remplacer l'existant (=)</option>
                    <option value="add">Ajouter à l'existant (+)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold py-2 shadow-sm">Valider</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-list-task"></i> Détail des affectations du mois</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <div class="text-center mb-3">
            <div class="fw-bold text-dark fs-5" id="detail_modal_uname"></div>
            <div class="badge bg-secondary fs-6" id="detail_modal_month_display"></div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered align-middle bg-white shadow-sm">
                <thead class="table-light text-center small">
                    <tr><th class="text-start">Projet / Activité</th><th style="width: 100px;">Charge</th></tr>
                </thead>
                <tbody id="detail_modal_body">
                </tbody>
                <tfoot class="table-light text-center fw-bold">
                    <tr><td class="text-end">TOTAL DU MOIS</td><td id="detail_modal_total" class="text-primary"></td></tr>
                </tfoot>
            </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let activeTab = localStorage.getItem('activeTab_monthly');
    if (activeTab && document.querySelector(activeTab)) {
        let tab = new bootstrap.Tab(document.querySelector(activeTab));
        tab.show();
    }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab_monthly', '#' + e.target.id);
        });
    });

    const fastAddModalEl = document.getElementById('fastAddModal');
    if (fastAddModalEl) {
        fastAddModalEl.addEventListener('shown.bs.modal', function () {
            const valInput = document.getElementById('valeur_input');
            if (valInput) {
                valInput.focus();  
                valInput.select(); 
            }
        });
    }

    const ctx = document.getElementById('capacityChart');
    if (ctx) {
        let capacityChart = new Chart(ctx.getContext('2d'), {
            data: {
                labels: <?= json_encode(array_column($dash_months, 'label')) ?>,
                datasets: <?= json_encode($chartDatasets) ?>
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { 
                        stacked: true,
                        grid: { display: false }
                    },
                    y: { 
                        stacked: true, 
                        beginAtZero: true, 
                        title: { display: true, text: 'Charge (Jours)', font: {weight: 'bold'} } 
                    }
                },
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });

        const graphTab = document.getElementById('vue-graph-tab');
        if (graphTab) {
            graphTab.addEventListener('shown.bs.tab', function () {
                capacityChart.resize();
            });
        }
    }
});

function syncValeur(val, source) {
    let v = val.toString().replace(',', '.');
    if (source === 'slider') {
        document.getElementById('valeur_input').value = v;
    } else if (source === 'input') {
        if(!isNaN(v) && v !== '') {
            document.getElementById('valeur_slider').value = v;
        }
    }
}

function updateModalColor() {
    const select = document.getElementById('modal_task_id');
    const header = document.getElementById('fastAddModalHeader');
    const closeBtn = document.getElementById('fastAddModalCloseBtn');
    
    if(select.selectedIndex >= 0) {
        const color = select.options[select.selectedIndex].getAttribute('data-color');
        if(color) {
            header.style.backgroundColor = color;
            
            const hex = color.replace('#', '');
            const r = parseInt(hex.substr(0, 2), 16);
            const g = parseInt(hex.substr(2, 2), 16);
            const b = parseInt(hex.substr(4, 2), 16);
            const yiq = ((r * 299) + (g * 587) + (b * 114)) / 1000;
            
            if (yiq >= 128) {
                header.style.color = '#1e293b'; 
                closeBtn.classList.remove('btn-close-white');
            } else {
                header.style.color = '#ffffff';
                closeBtn.classList.add('btn-close-white');
            }
        }
    }
}

function openFastModal(uid, uname, monthKey, taskId = '') {
    document.getElementById('modal_month').value = monthKey; 
    const parts = monthKey.split('-');
    const dateObj = new Date(parts[0], parts[1] - 1);
    const monthName = dateObj.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('modal_month_display').innerText = monthName.charAt(0).toUpperCase() + monthName.slice(1);
    
    const unameDisplay = document.getElementById('modal_uname_display');
    const uidSelect = document.getElementById('modal_uid_select');
    const uidHidden = document.getElementById('modal_uid_hidden');

    if (uid) {
        unameDisplay.innerText = uname;
        unameDisplay.classList.remove('d-none');
        if(uidSelect) { uidSelect.value = uid; uidSelect.classList.add('d-none'); }
        if(uidHidden) uidHidden.value = uid;
    } else {
        unameDisplay.classList.add('d-none');
        if(uidSelect) { uidSelect.classList.remove('d-none'); uidSelect.value = ''; }
    }

    const taskSelect = document.getElementById('modal_task_id');
    if (taskId) {
        taskSelect.value = taskId;
    } else {
        taskSelect.value = '';
    }
    
    document.getElementById('valeur_slider').value = 1;
    document.getElementById('valeur_input').value = 1;
    
    updateModalColor();
    
    var myModal = new bootstrap.Modal(document.getElementById('fastAddModal'));
    myModal.show();
}

function openDetailModal(uname, monthKey, cellElement) {
    const parts = monthKey.split('-');
    const dateObj = new Date(parts[0], parts[1] - 1);
    const monthName = dateObj.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('detail_modal_month_display').innerText = monthName.charAt(0).toUpperCase() + monthName.slice(1);
    document.getElementById('detail_modal_uname').innerText = uname;

    const detailsRaw = cellElement.getAttribute('data-details');
    const tbody = document.getElementById('detail_modal_body');
    tbody.innerHTML = '';
    let total = 0;

    if (detailsRaw && detailsRaw !== '[]') {
        const details = JSON.parse(detailsRaw);
        details.forEach(d => {
            total += d.val;
            const typeUpper = d.type.toUpperCase();
            tbody.innerHTML += `
                <tr>
                    <td class="text-start">
                        <div class="d-flex align-items-center">
                            <div class="rounded me-2" style="width: 10px; height: 10px; background-color: ${d.color}; border: 1px solid rgba(0,0,0,0.1);"></div>
                            <div>
                                <div class="fw-bold text-dark small">${d.title}</div>
                                <div class="text-muted" style="font-size: 0.6rem;"><span class="badge bg-light text-dark border">${typeUpper}</span></div>
                            </div>
                        </div>
                    </td>
                    <td class="text-center fw-bold">${d.val}</td>
                </tr>
            `;
        });
    } else {
        tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted small py-3">Aucune charge affectée ce mois-ci.</td></tr>';
    }
    document.getElementById('detail_modal_total').innerText = total;

    var myModal = new bootstrap.Modal(document.getElementById('detailModal'));
    myModal.show();
}
</script>
