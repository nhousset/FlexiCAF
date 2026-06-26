<?php
$tasks = getDb(FILE_TASKS); 
$allData = getDb(FILE_DATA);
$allUsers = getDb(FILE_USERS);
$allSettings = getDb(FILE_SETTINGS);

$canSaisie = hasPermission('can_saisie');
$canDashboard = hasPermission('can_dashboard');
$canSaisieOthers = hasPermission('can_saisie_others') || $_SESSION['role'] === 'admin';

$isManager = ($_SESSION['role'] === 'admin' || $canDashboard); 
// EVOLUTION: Chargement dynamique de l'option d'activation globale du Backlog
$show_backlog = !isset($allSettings['show_backlog']) || (bool)$allSettings['show_backlog'];

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
if ($isManager) {
    foreach($allUsers as $id => $u) {
        if (empty($u['is_excluded'])) {
            $displayUsers[$id] = mb_strtoupper($u['name'], 'UTF-8');
        }
    }
    // EVOLUTION: N'ajouter le Backlog virtuel que s'il est activé en administration
    if ($show_backlog) {
        $displayUsers['_virtual_unassigned_'] = 'RESTE À PLANIFIER';
    }
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
if (!$isManager) {
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
            
            // AGREGATION POUR LE GRAPHIQUE GLOBAL ET KPI
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

// AGREGATION POUR LE GRAPHIQUE DÉTAIL DU CONSULTANT
$chart_detail_type_month = [];
foreach($detail_grid as $tid => $months) {
    $t_type = $tasks[$tid]['type'] ?? 'Autre';
    if(!isset($chart_detail_type_month[$t_type])) {
        $chart_detail_type_month[$t_type] = array_fill_keys(array_keys($dash_months), 0);
    }
    foreach($months as $m_key => $val) {
        $chart_detail_type_month[$t_type][$m_key] += $val;
    }
}

// --------------------------------------------------------
// DONNÉES DU GRAPHIQUE DÉTAIL CONSULTANT
// --------------------------------------------------------
$typeColorsDetail = ['Technique' => '#fef08a', 'Fonctionnel' => '#bbf7d0', 'Structure' => '#bae6fd', 'Absences' => '#fca5a5', 'Formation' => '#e9d5ff'];
$chartDatasetsDetail = [];
$capDataDetail = [];
foreach($dash_months as $m_key => $m_data) {
    $capDataDetail[] = ($detail_uid === '_virtual_unassigned_') ? 0 : $m_data['working_days'];
}
$chartDatasetsDetail[] = [
    'type' => 'line', 'label' => 'Capacité Théorique', 'data' => $capDataDetail, 'borderColor' => '#ef4444', 'backgroundColor' => '#ef4444',
    'borderWidth' => 3, 'fill' => false, 'tension' => 0.4, 'pointRadius' => 5, 'pointBackgroundColor' => '#ffffff', 'pointBorderColor' => '#ef4444', 'pointBorderWidth' => 2, 'pointHoverRadius' => 7, 'order' => 0 
];
foreach($chart_detail_type_month as $type => $monthsData) {
    if(array_sum($monthsData) > 0) {
        $chartDatasetsDetail[] = [
            'type' => 'bar', 'label' => $type, 'data' => array_values($monthsData), 'backgroundColor' => $typeColorsDetail[$type] ?? '#cbd5e1', 'borderColor' => 'rgba(0,0,0,0.05)', 'borderWidth' => 1, 'borderRadius' => 6, 'order' => 1
        ];
    }
}

// --------------------------------------------------------
// MOTEURS DE CHARTES GRAPHIQUES ET HEATMAPS
// --------------------------------------------------------
function getMonthlyHeatmapStyle($valeur, $capacite_max, $is_virtual = false) {
    if ($is_virtual) {
        if ($valeur == 0) return 'background-color: #f8f9fa; color: #adb5bd;';
        return 'background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%); color: #334155; font-weight: bold; border: 1px dashed #94a3b8; box-shadow: inset 0 2px 4px rgba(0,0,0,0.02);';
    }
    if ($valeur == 0) return 'background-color: #ffffff;';
    $perc = $capacite_max > 0 ? round(($valeur / $capacite_max) * 100) : 0;
    if ($perc <= 60) return 'background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%); color: #065f46; border: 1px solid #6ee7b7; box-shadow: inset 0 2px 4px rgba(255,255,255,0.5);';
    if ($perc <= 90) return 'background: linear-gradient(135deg, #34d399 0%, #10b981 100%); color: #022c22; font-weight: bold; border: 1px solid #059669; box-shadow: inset 0 2px 4px rgba(255,255,255,0.3);';
    if ($perc <= 100) return 'background: linear-gradient(135deg, #fde047 0%, #facc15 100%); color: #713f12; font-weight: bold; border: 1px solid #eab308; box-shadow: inset 0 2px 4px rgba(255,255,255,0.4);';
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
?>

<style>
#viewTabsContent .table-responsive { max-height: 70vh; overflow-y: auto; }
#viewTabsContent .table-responsive thead th { position: sticky; top: 0; z-index: 100; background-color: #f8fafc !important; box-shadow: inset 0 -2px 0 #e2e8f0, 0 4px 6px -2px rgba(0,0,0,0.05); }
</style>

<ul class="nav nav-tabs" id="viewTabs" role="tablist">
  <?php if ($isManager): ?>
      <li class="nav-item" role="presentation"><button class="nav-link active" id="vue1-tab" data-bs-toggle="tab" data-bs-target="#vue1" type="button"><i class="bi bi-person-lines-fill"></i> Consultant / Mois</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link text-primary fw-bold" id="vue-detail-tab" data-bs-toggle="tab" data-bs-target="#vue-detail" type="button"><i class="bi bi-person-badge"></i> Détail Consultant</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="vue2-tab" data-bs-toggle="tab" data-bs-target="#vue2" type="button"><i class="bi bi-folder-fill"></i> Projet / Mois</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="vue3-tab" data-bs-toggle="tab" data-bs-target="#vue3" type="button"><i class="bi bi-grid-3x3-gap-fill"></i> Projet / Consultant</button></li>
  <?php else: ?>
      <li class="nav-item" role="presentation"><button class="nav-link active text-primary fw-bold" id="vue-detail-tab" data-bs-toggle="tab" data-bs-target="#vue-detail" type="button"><i class="bi bi-person-badge"></i> Mon activité</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" id="vue2-tab" data-bs-toggle="tab" data-bs-target="#vue2" type="button"><i class="bi bi-folder-fill"></i> Mes projets</button></li>
  <?php endif; ?>
</ul>

<div class="tab-content bg-white border border-top-0 p-3 rounded-bottom shadow-sm" id="viewTabsContent">

    <?php if ($isManager): ?>
    <div class="tab-pane active" id="vue1" role="tabpanel">
        <div class="d-flex justify-content-end mb-3 gap-2">
            <button class="btn btn-sm btn-outline-secondary" id="btnToggleKpi" onclick="toggleDashboardSection('kpi_container', 'cookie_kpi')"><i class="bi bi-eye-slash"></i> Masquer KPIs</button>
            <button class="btn btn-sm btn-outline-secondary" id="btnToggleChart" onclick="toggleDashboardSection('chart_container', 'cookie_chart')"><i class="bi bi-eye-slash"></i> Masquer Graphique</button>
        </div>

        <div id="kpi_container"><?php include 'views/kpi_global.php'; ?></div>
        <div id="chart_container"><?php include 'views/chart_global.php'; ?></div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-start text-muted text-uppercase small w-25">Ressources (<?= $real_users_count ?><?= $show_backlog ? ' + Backlog' : '' ?>)</th>
                        <?php foreach($dash_months as $m_key => $m_data): ?>
                            <th><div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div><div class="small text-muted fw-normal" style="font-size: 0.65rem;">Max: <?= $m_data['working_days'] ?></div></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($displayUsers as $uid => $uname): $isVirtual = ($uid === '_virtual_unassigned_'); ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center">
                                <?php if($isVirtual): ?>
                                    <div class="bg-warning text-dark rounded-circle text-center me-2 shadow-sm border border-dark" style="width:28px; height:28px; line-height:28px; font-weight:bold; font-size: 0.8rem;"><i class="bi bi-inbox-fill"></i></div>
                                    <span class="fw-bold text-dark small fst-italic"><?= htmlspecialchars($uname) ?></span>
                                <?php else: ?>
                                    <div class="bg-dark text-white rounded-circle text-center me-2" style="width:28px; height:28px; line-height:28px; font-weight:bold; font-size: 0.75rem;"><?= mb_strtoupper(mb_substr($uname, 0, 1, 'UTF-8'), 'UTF-8') ?></div>
                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_user_month[$uid][$m_key] ?? 0; $cap_max = $m_data['working_days']; $style = getMonthlyHeatmapStyle($valeur, $cap_max, $isVirtual);
                            $details_array = [];
                            if (isset($breakdown_user_month_task[$uid][$m_key])) {
                                foreach($breakdown_user_month_task[$uid][$m_key] as $tid => $val) {
                                    if ($val > 0) $details_array[] = ['title' => $tasks[$tid]['title'] ?? 'Inconnu', 'type' => $tasks[$tid]['type'] ?? 'Technique', 'color' => $tasks[$tid]['color'] ?? '#e2e8f0', 'val' => $val];
                                }
                            }
                            $details_json = htmlspecialchars(json_encode($details_array), ENT_QUOTES, 'UTF-8');
                        ?>
                            <td style="<?= $style ?> position: relative; cursor: pointer;" onclick="openDetailModal('<?= addslashes(htmlspecialchars($uname)) ?>', '<?= $m_key ?>', this)" data-details="<?= $details_json ?>">
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?></div><?php if(!$isVirtual): ?><div style="font-size: 0.65rem; opacity: 0.8;"><?= round(($valeur/$cap_max)*100) ?>% Alloué</div><?php endif; ?>
                                <?php else: ?><span class="text-muted" style="opacity: 0.3;">—</span><?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL ÉQUIPE (Charge / Capacité)</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = 0; foreach($displayUsers as $uid => $uname) { $total_load += $pivot_user_month[$uid][$m_key] ?? 0; }
                            $total_cap = $m_data['working_days'] * $real_users_count; $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0; $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>"><div class="fs-6"><?= $total_load ?></div><div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap ?>)</div></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="tab-pane <?= !$isManager ? 'active' : '' ?>" id="vue-detail" role="tabpanel">
        <?php if ($isManager): ?>
        <form method="GET" class="mb-4 d-flex align-items-center bg-light p-2 rounded border">
            <input type="hidden" name="action" value="home"><input type="hidden" name="date" value="<?= htmlspecialchars($_GET['date'] ?? '') ?>">
            <label class="me-3 fw-bold small text-primary"><i class="bi bi-funnel-fill"></i> Zoom sur :</label>
            <select name="detail_uid" class="form-select form-select-sm w-auto fw-bold" onchange="this.form.submit()">
                <?php foreach($displayUsers as $uid => $uname): ?><option value="<?= $uid ?>" <?= $uid === $detail_uid ? 'selected' : '' ?>><?= htmlspecialchars($uname) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php else: ?><h5 class="mb-3 text-primary fw-bold"><i class="bi bi-person-badge"></i> Mon Activité</h5><?php endif; ?>

        <div class="bg-light p-3 rounded shadow-sm border mb-4">
            <h6 class="fw-bold text-muted small text-uppercase mb-3"><i class="bi bi-bar-chart-fill text-primary"></i> Répartition par type d'activité</h6>
            <canvas id="detailChart" style="max-height: 250px; width: 100%;"></canvas>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr><th class="text-start text-muted text-uppercase small w-25">Projets & Activités</th><?php foreach($dash_months as $m_key => $m_data): ?><th><div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach($tasksByType as $type => $groupTasks): ?>
                        <tr class="table-secondary"><td colspan="7" class="fw-bold text-uppercase" style="letter-spacing: 1px; font-size: 0.8rem;"><i class="bi bi-collection"></i> <?= mb_strtoupper(htmlspecialchars($type), 'UTF-8') ?></td></tr>
                        <?php foreach($groupTasks as $tid => $t): $color = htmlspecialchars($t['color'] ?? '#e2e8f0'); ?>
                        <tr>
                            <td class="text-start ps-4"><div class="d-flex align-items-center"><div class="rounded me-2" style="width: 10px; height: 10px; background-color: <?= $color ?>;"></div><div><div class="fw-bold text-dark small"><?= htmlspecialchars($t['title']) ?></div><div class="text-muted" style="font-size: 0.6rem;"><?= htmlspecialchars($t['itbm']) ?></div></div></div></td>
                            <?php foreach($dash_months as $m_key => $m_data): 
                                $valeur = $detail_grid[$tid][$m_key] ?? 0; $isClickable = ($_SESSION['role'] === 'admin' || $canSaisieOthers || ($canSaisie && $detail_uid === $_SESSION['user_id'])); $style = getDetailHeatmapStyle($valeur);
                            ?>
                                <td class="text-center align-middle" style="<?= $style ?> <?= $isClickable ? "cursor: pointer;" : "" ?>" <?php if($isClickable): ?>onclick="openFastModal('<?= $detail_uid ?>', '<?= addslashes(htmlspecialchars($detail_uname)) ?>', '<?= $m_key ?>', '<?= $tid ?>')"<?php endif; ?>>
                                    <?= $valeur > 0 ? "<div class='fs-6'>$valeur</div>" : "<span class='text-muted' style='opacity: 0.15;'>—</span>" ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark text-center">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL AFFECTÉ</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = $pivot_user_month[$detail_uid][$m_key] ?? 0; $cap_max = ($detail_uid === '_virtual_unassigned_') ? 0 : $m_data['working_days'];
                            $perc = $cap_max > 0 ? round(($total_load / $cap_max) * 100) : 0; $style = getMonthlyHeatmapStyle($total_load, $cap_max, ($detail_uid === '_virtual_unassigned_'));
                        ?>
                            <td style="<?= $style ?>"><div class="fs-6"><?= $total_load ?></div><?php if($cap_max > 0): ?><div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $cap_max ?>)</div><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div class="tab-pane" id="vue2" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead class="table-light text-center">
                    <tr><th class="text-start text-muted text-uppercase small w-25">Projets & Activités</th><?php foreach($dash_months as $m_key => $m_data): ?><th><div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach($tasks as $tid => $t): if(array_sum($pivot_task_month[$tid]) == 0) continue; $color = htmlspecialchars($t['color'] ?? '#e2e8f0'); $type = htmlspecialchars($t['type'] ?? 'Technique'); ?>
                    <tr>
                        <td class="text-start"><div class="d-flex align-items-center"><div class="rounded me-2" style="width: 12px; height: 12px; background-color: <?= $color ?>;"></div><div><div class="fw-bold text-dark small"><?= htmlspecialchars($t['title']) ?></div><div class="text-muted" style="font-size: 0.65rem;"><span class="badge bg-light text-dark border me-1"><?= mb_strtoupper($type, 'UTF-8') ?></span><?= htmlspecialchars($t['itbm']) ?></div></div></div></td>
                        <?php foreach($dash_months as $m_key => $m_data): $valeur = $pivot_task_month[$tid][$m_key] ?? 0; $style = getProjectHeatmapStyle($valeur); ?>
                            <td class="text-center align-middle" style="<?= $style ?> <?= $canSaisieOthers ? "cursor: pointer;" : "" ?>" <?php if($canSaisieOthers): ?>onclick="openFastModal('', '', '<?= $m_key ?>', '<?= $tid ?>')"<?php endif; ?>><?= $valeur > 0 ? "<div class='fs-6'>$valeur</div>" : "<span class='text-muted' style='opacity: 0.2;'>—</span>" ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark text-center">
                    <tr>
                        <td class="text-end fw-bold align-middle">EFFORT TOTAL DÉPLOYÉ</td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $total_load = 0; foreach($displayUsers as $uid => $uname) $total_load += $pivot_user_month[$uid][$m_key] ?? 0;
                            $total_cap = $m_data['working_days'] * $real_users_count; $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0; $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>"><div class="fs-6"><?= $total_load ?></div><div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap ?>)</div></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <?php if ($isManager): ?>
    <div class="tab-pane" id="vue3" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr><th class="text-start text-muted text-uppercase small">Projets / Activités</th><?php foreach($displayUsers as $uid => $uname): ?><th><?= htmlspecialchars($uname) ?></th><?php endforeach; ?></tr>
                </thead>
                <tbody>
                    <?php foreach($tasks as $tid => $t): if(array_sum($pivot_task_user[$tid]) == 0) continue; $color = htmlspecialchars($t['color'] ?? '#e2e8f0'); $type = htmlspecialchars($t['type'] ?? 'Technique'); ?>
                    <tr>
                        <td class="text-start"><div class="d-flex align-items-center"><div class="rounded me-2" style="width: 12px; height: 12px; background-color: <?= $color ?>;"></div><div><span class="fw-bold text-dark small d-block"><?= htmlspecialchars($t['title']) ?></span><span class="badge bg-light text-dark border" style="font-size: 0.55rem;"><?= mb_strtoupper($type, 'UTF-8') ?></span></div></div></td>
                        <?php foreach($displayUsers as $uid => $uname): $valeur = $pivot_task_user[$tid][$uid] ?? 0; $style = getProjectHeatmapStyle($valeur); ?>
                            <td class="text-center align-middle" style="<?= $style ?>"><?= $valeur > 0 ? "<div class='fs-6'>$valeur</div>" : "<span class='text-muted' style='opacity: 0.2;'>—</span>" ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td class="text-end fw-bold align-middle">TOTAL CUMULÉ (6 MOIS)</td>
                        <?php foreach($displayUsers as $uid => $uname): 
                            $total_load = array_sum(array_column($pivot_task_user, $uid)); $total_cap_6m = ($uid === '_virtual_unassigned_') ? 0 : array_sum(array_column($dash_months, 'working_days'));
                            $perc = $total_cap_6m > 0 ? round(($total_load / $total_cap_6m) * 100) : 0; $style = getMonthlyHeatmapStyle($total_load, $total_cap_6m, ($uid === '_virtual_unassigned_'));
                        ?>
                            <td style="<?= $style ?>"><div class="fs-6"><?= $total_load ?></div><?php if($total_cap_6m > 0): ?><div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap_6m ?>)</div><?php endif; ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="fastAddModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0 shadow-lg"><div class="modal-header py-2 border-0 transition-color" id="fastAddModalHeader" style="background-color: #212529; color: #fff;"><h6 class="modal-title mb-0 fw-bold" id="fastAddModalTitle"><i class="bi bi-journal-check me-1"></i> Affectation</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" id="fastAddModalCloseBtn"></button></div><div class="modal-body bg-light"><form method="POST" action="?action=home<?= isset($_GET['date']) ? '&date='.$_GET['date'] : '' ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>"><input type="hidden" name="month_saisie" id="modal_month" value=""><div class="text-center mb-3"><div id="modal_uname_display" class="fw-bold text-dark fs-6 mb-1"></div><?php if ($canSaisieOthers): ?><select name="target_user_id" id="modal_uid_select" class="form-select form-select-sm border-primary fw-bold mb-2 d-none" required><?php foreach($displayUsers as $uid => $uname): ?><option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?></option><?php endforeach; ?></select><?php else: ?><input type="hidden" name="target_user_id" id="modal_uid_hidden" value=""><?php endif; ?><div class="badge bg-secondary shadow-sm" id="modal_month_display"></div></div><div class="mb-3"><label class="form-label small fw-bold text-muted">Sur le projet :</label><select name="task_id" id="modal_task_id" class="form-select form-select-sm" required onchange="updateModalColor()"><option value="" disabled selected data-color="#212529">Sélectionner...</option><?php foreach($tasks as $id => $t): $type = htmlspecialchars($t['type'] ?? 'Technique'); $color = htmlspecialchars($t['color'] ?? '#212529'); ?><option value="<?= $id ?>" data-color="<?= $color ?>">[<?= mb_strtoupper($type, 'UTF-8') ?>] <?= htmlspecialchars($t['title']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label small fw-bold text-muted d-flex justify-content-between"><span>Volume :</span></label><input type="range" class="form-range mb-2" id="valeur_slider" min="0" max="10" step="0.1" value="1" oninput="syncValeur(this.value, 'slider')"><div class="input-group input-group-sm"><input type="text" inputmode="decimal" pattern="^[0-9]*([.,][0-9]+)?$" name="valeur" id="valeur_input" class="form-control text-center fw-bold" value="1" required oninput="syncValeur(this.value, 'input')"></div></div><div class="mb-4"><select name="edit_mode" class="form-select form-select-sm bg-white"><option value="replace" selected>Remplacer l'existant (=)</option><option value="add">Ajouter à l'existant (+)</option></select></div><button type="submit" class="btn btn-dark btn-sm w-100 fw-bold py-2 shadow-sm">Valider</button></form></div></div></div></div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-info text-white py-2"><h6 class="modal-title mb-0"><i class="bi bi-list-task"></i> Détail des affectations du mois</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light"><div class="text-center mb-3"><div class="fw-bold text-dark fs-5" id="detail_modal_uname"></div><div class="badge bg-secondary fs-6" id="detail_modal_month_display"></div></div><div class="table-responsive"><table class="table table-sm table-bordered align-middle bg-white shadow-sm"><thead class="table-light text-center small"><tr><th class="text-start">Projet / Activité</th><th style="width: 100px;">Charge</th></tr></thead><tbody id="detail_modal_body"></tbody><tfoot class="table-light text-center fw-bold"><tr><td class="text-end">TOTAL DU MOIS</td><td id="detail_modal_total" class="text-primary"></td></tr></tfoot></table></div></div></div></div></div>

<script>
function setCookie(name, value, days) { var expires = ""; if (days) { var date = new Date(); date.setTime(date.getTime() + (days*24*60*60*1000)); expires = "; expires=" + date.toUTCString(); } document.cookie = name + "=" + (value || "")  + expires + "; path=/"; }
function getCookie(name) { var nameEQ = name + "="; var ca = document.cookie.split(';'); for(var i=0;i < ca.length;i++) { var c = ca[i]; while (c.charAt(0)==' ') c = c.substring(1,c.length); if (c.indexOf(nameEQ) == 0) return c.substring(nameEQ.length,c.length); } return null; }

function toggleDashboardSection(containerId, cookieName) {
    const container = document.getElementById(containerId); if (!container) return;
    const isHidden = container.classList.contains('d-none');
    const btn = document.getElementById('btnToggle' + (containerId === 'kpi_container' ? 'Kpi' : 'Chart'));
    if (isHidden) {
        container.classList.remove('d-none'); setCookie(cookieName, 'show', 365);
        if (btn) btn.innerHTML = '<i class="bi bi-eye-slash"></i> Masquer ' + (containerId === 'kpi_container' ? 'KPIs' : 'Graphique');
        if (containerId === 'chart_container' && window.capacityChartInstance) window.capacityChartInstance.resize();
    } else {
        container.classList.add('d-none'); setCookie(cookieName, 'hide', 365);
        if (btn) btn.innerHTML = '<i class="bi bi-eye"></i> Afficher ' + (containerId === 'kpi_container' ? 'KPIs' : 'Graphique');
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const kpiContainer = document.getElementById('kpi_container');
    const chartContainer = document.getElementById('chart_container');
    if (kpiContainer && getCookie('cookie_kpi') === 'hide') {
        kpiContainer.classList.add('d-none');
        const btnKpi = document.getElementById('btnToggleKpi'); if (btnKpi) btnKpi.innerHTML = '<i class="bi bi-eye"></i> Afficher KPIs';
    }
    if (chartContainer && getCookie('cookie_chart') === 'hide') {
        chartContainer.classList.add('d-none');
        const btnChart = document.getElementById('btnToggleChart'); if (btnChart) btnChart.innerHTML = '<i class="bi bi-eye"></i> Afficher Graphique';
    }

    let activeTab = localStorage.getItem('activeTab_monthly');
    let tabEl = activeTab ? document.querySelector(activeTab) : null;
    if (tabEl) { let tab = new bootstrap.Tab(tabEl); tab.show(); }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function (e) { localStorage.setItem('activeTab_monthly', '#' + e.target.id); });
    });

    const fastAddModalEl = document.getElementById('fastAddModal');
    if (fastAddModalEl) {
        fastAddModalEl.addEventListener('shown.bs.modal', function () {
            const valInput = document.getElementById('valeur_input');
            if (valInput) { valInput.focus(); valInput.select(); }
        });
    }

    const ctxDetail = document.getElementById('detailChart');
    if (ctxDetail) {
        let detailChart = new Chart(ctxDetail.getContext('2d'), {
            data: { labels: <?= json_encode(array_column($dash_months, 'label')) ?>, datasets: <?= json_encode($chartDatasetsDetail) ?> },
            options: {
                responsive: true, maintainAspectRatio: false, layout: { padding: { top: 10, bottom: 10 } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, beginAtZero: true, title: { display: false }, grid: { color: 'rgba(0,0,0,0.05)', borderDash: [5,5] }, border: { display: false } }
                },
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 8, font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' } } },
                    tooltip: { mode: 'index', intersect: false, backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 10, cornerRadius: 8 }
                },
                interaction: { mode: 'nearest', axis: 'x', intersect: false }
            }
        });
        const detailTab = document.getElementById('vue-detail-tab');
        if (detailTab) { detailTab.addEventListener('shown.bs.tab', function () { detailChart.resize(); }); }
    }
});

function syncValeur(val, source) {
    let v = val.toString().replace(',', '.');
    if (source === 'slider') document.getElementById('valeur_input').value = v;
    else if (source === 'input' && !isNaN(v) && v !== '') document.getElementById('valeur_slider').value = v;
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
            const r = parseInt(hex.substr(0, 2), 16), g = parseInt(hex.substr(2, 2), 16), b = parseInt(hex.substr(4, 2), 16);
            if ((((r * 299) + (g * 587) + (b * 114)) / 1000) >= 128) { header.style.color = '#1e293b'; closeBtn.classList.remove('btn-close-white'); }
            else { header.style.color = '#ffffff'; closeBtn.classList.add('btn-close-white'); }
        }
    }
}

function openFastModal(uid, uname, monthKey, taskId = '') {
    document.getElementById('modal_month').value = monthKey; 
    const parts = monthKey.split('-'), dateObj = new Date(parts[0], parts[1] - 1);
    const monthName = dateObj.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('modal_month_display').innerText = monthName.charAt(0).toUpperCase() + monthName.slice(1);
    const unameDisplay = document.getElementById('modal_uname_display'), uidSelect = document.getElementById('modal_uid_select'), uidHidden = document.getElementById('modal_uid_hidden');
    if (uid) {
        unameDisplay.innerText = uname; unameDisplay.classList.remove('d-none');
        if(uidSelect) { uidSelect.value = uid; uidSelect.classList.add('d-none'); }
        if(uidHidden) uidHidden.value = uid;
    } else {
        unameDisplay.classList.add('d-none'); if(uidSelect) { uidSelect.classList.remove('d-none'); uidSelect.value = ''; }
    }
    const taskSelect = document.getElementById('modal_task_id');
    taskSelect.value = taskId ? taskId : '';
    document.getElementById('valeur_slider').value = 1; document.getElementById('valeur_input').value = 1;
    updateModalColor(); (new bootstrap.Modal(document.getElementById('fastAddModal'))).show();
}

function openDetailModal(uname, monthKey, cellElement) {
    const parts = monthKey.split('-'), dateObj = new Date(parts[0], parts[1] - 1);
    document.getElementById('detail_modal_month_display').innerText = dateObj.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('detail_modal_uname').innerText = uname;
    const detailsRaw = cellElement.getAttribute('data-details'), tbody = document.getElementById('detail_modal_body');
    tbody.innerHTML = ''; let total = 0;
    if (detailsRaw && detailsRaw !== '[]') {
        JSON.parse(detailsRaw).forEach(d => {
            total += d.val;
            tbody.innerHTML += `<tr><td class="text-start"><div class="d-flex align-items-center"><div class="rounded me-2" style="width:10px; height:10px; background-color:${d.color};"></div><div><div class="fw-bold text-dark small">${d.title}</div><div class="text-muted" style="font-size:0.6rem;"><span class="badge bg-light text-dark border">${d.type.toUpperCase()}</span></div></div></div></td><td class="text-center fw-bold">${d.val}</td></tr>`;
        });
    } else { tbody.innerHTML = '<tr><td colspan="2" class="text-center text-muted small py-3">Aucune charge affectée.</td></tr>'; }
    document.getElementById('detail_modal_total').innerText = total; (new bootstrap.Modal(document.getElementById('detailModal'))).show();
}
</script>
