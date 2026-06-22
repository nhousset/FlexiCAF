<?php
$tasks = getDb(FILE_TASKS); 
$allData = getDb(FILE_DATA);
$allUsers = getDb(FILE_USERS);

$canSaisie = hasPermission('can_saisie');
$canDashboard = hasPermission('can_dashboard');

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
// PRÉPARATION DES ACTEURS (Avec Utilisateur Virtuel)
// --------------------------------------------------------
$displayUsers = [];
if ($_SESSION['role'] === 'admin' || $canDashboard) {
    if ($_SESSION['role'] === 'admin') $displayUsers['admin'] = 'Administrateur';
    foreach($allUsers as $id => $u) {
        if (empty($u['is_excluded'])) $displayUsers[$id] = $u['name'];
    }
    // Création de l'utilisateur virtuel pour la charge non affectée
    $displayUsers['_virtual_unassigned_'] = 'Reste à planifier';
} else {
    $displayUsers[$_SESSION['user_id']] = $_SESSION['name'];
}

// Calcul du nombre de VRAIS collaborateurs (pour la capacité équipe)
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

foreach($displayUsers as $uid => $uname) {
    $pivot_user_month[$uid] = array_fill_keys(array_keys($dash_months), 0);
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
$detail_uname = $displayUsers[$detail_uid] ?? 'Inconnu';

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
        }
        
        if ($uid === $detail_uid && isset($detail_grid[$tid])) {
            $detail_grid[$tid][$m_key] += $e['valeur_j'];
        }
    }
}

// --------------------------------------------------------
// MOTEUR DE COULEURS HEATMAP (Dégradé % et Utilisateur Virtuel)
// --------------------------------------------------------
function getMonthlyHeatmapStyle($valeur, $capacite_max, $is_virtual = false) {
    // Règle spéciale pour "Reste à planifier" (Pas de % car pas de capacité)
    if ($is_virtual) {
        if ($valeur == 0) return 'background-color: #f8f9fa; color: #adb5bd;';
        return 'background-color: #f1f5f9; color: #334155; font-weight: bold; border: 1px dashed #94a3b8; box-shadow: inset 0 0 5px rgba(0,0,0,0.05);';
    }
    
    if ($valeur == 0) return 'background-color: #ffffff;';
    $perc = $capacite_max > 0 ? round(($valeur / $capacite_max) * 100) : 0;
    
    // Dégradé de Vert à Rouge
    if ($perc <= 60) return 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;'; // Vert très clair
    if ($perc <= 90) return 'background-color: #34d399; color: #064e3b; font-weight: bold; border: 1px solid #10b981;'; // Vert validé
    if ($perc <= 100) return 'background-color: #fde047; color: #854d0e; font-weight: bold; border: 1px solid #facc15;'; // Jaune (Alerte)
    if ($perc <= 120) return 'background-color: #fb923c; color: #7c2d12; font-weight: bold; border: 1px solid #f97316;'; // Orange (Surcharge)
    return 'background-color: #ef4444; color: #ffffff; font-weight: bold; box-shadow: inset 0 0 0 2px #b91c1c;'; // Rouge (Critique)
}
?>

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
                                <div class="small text-muted fw-normal" style="font-size: 0.65rem;">Max: <?= $m_data['working_days'] ?> JH</div>
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
                                        <?= strtoupper(substr($uname, 0, 1)) ?>
                                    </div>
                                    <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_user_month[$uid][$m_key];
                            $cap_max = $m_data['working_days'];
                            $style = getMonthlyHeatmapStyle($valeur, $cap_max, $isVirtual);
                        ?>
                            <td style="<?= $style ?> position: relative; cursor: pointer;" onclick="openFastModal('<?= $uid ?>', '<?= htmlspecialchars($uname) ?>', '<?= $m_key ?>')">
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?> <small>JH</small></div>
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
                                $total_load += $pivot_user_month[$uid][$m_key];
                            }
                            $total_cap = $m_data['working_days'] * $real_users_count;
                            $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?> JH</div>
                                <div style="font-size: 0.65rem; opacity: 0.8;"><?= $perc ?>% (sur <?= $total_cap ?>)</div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
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
                                <i class="bi bi-collection"></i> <?= htmlspecialchars($type) ?>
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
                                $valeur = $detail_grid[$tid][$m_key];
                                $isClickable = ($_SESSION['role'] === 'admin' || $canSaisie) ? "cursor: pointer;" : "";
                            ?>
                                <td class="text-center" style="<?= $valeur > 0 ? 'background-color: #f0fdf4;' : '' ?> <?= $isClickable ?>" 
                                    <?php if($isClickable): ?>
                                    onclick="openFastModal('<?= $detail_uid ?>', '<?= addslashes(htmlspecialchars($detail_uname)) ?>', '<?= $m_key ?>', '<?= $tid ?>')"
                                    <?php endif; ?>>
                                    
                                    <?php if($valeur > 0): ?>
                                        <span class="badge bg-success px-2 py-1 fs-6"><?= $valeur ?> JH</span>
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
                            $total_load = $pivot_user_month[$detail_uid][$m_key];
                            $cap_max = ($detail_uid === '_virtual_unassigned_') ? 0 : $m_data['working_days'];
                            $perc = $cap_max > 0 ? round(($total_load / $cap_max) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $cap_max, ($detail_uid === '_virtual_unassigned_'));
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?> JH</div>
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
                                        <span class="badge bg-light text-dark border me-1"><?= $type ?></span>
                                        <?= htmlspecialchars($t['itbm']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_task_month[$tid][$m_key];
                        ?>
                            <td class="text-center" style="<?= $valeur > 0 ? 'background-color: #f8fafc;' : '' ?>"
                                <?php if($_SESSION['role'] === 'admin'): ?>
                                    onclick="openFastModal('', '', '<?= $m_key ?>', '<?= $tid ?>')" style="cursor:pointer;" title="Affecter quelqu'un sur ce projet"
                                <?php endif; ?>>
                                
                                <?php if($valeur > 0): ?>
                                    <span class="badge bg-primary px-2 py-1 fs-6"><?= $valeur ?> JH</span>
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
                            foreach($displayUsers as $uid => $uname) $total_load += $pivot_user_month[$uid][$m_key];
                            
                            $total_cap = $m_data['working_days'] * $real_users_count;
                            $perc = $total_cap > 0 ? round(($total_load / $total_cap) * 100) : 0;
                            $style = getMonthlyHeatmapStyle($total_load, $total_cap, false);
                        ?>
                            <td style="<?= $style ?>">
                                <div class="fs-6"><?= $total_load ?> JH</div>
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
            <i class="bi bi-info-square"></i> Cette vue agrège l'effort total (en JH) de chaque consultant sur la période affichée (les 6 mois).
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
                                    <span class="badge bg-light text-dark border" style="font-size: 0.55rem;"><?= $type ?></span>
                                </div>
                            </div>
                        </td>
                        <?php foreach($displayUsers as $uid => $uname): 
                            $valeur = $pivot_task_user[$tid][$uid];
                        ?>
                            <td style="<?= $valeur > 0 ? 'background-color: #f0fdf4; color: #166534; font-weight: bold;' : '' ?>">
                                <?= $valeur > 0 ? $valeur . ' JH' : '<span class="text-muted" style="opacity: 0.2;">—</span>' ?>
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
                                <div class="fs-6"><?= $total_load ?> JH</div>
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

    <?php if($canSaisie): ?>
    <div class="tab-pane fade mt-3" id="saisie" role="tabpanel">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white"><i class="bi bi-pencil-square"></i> Déclarer une charge manuellement</div>
                    <div class="card-body">
                        <form method="POST">
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
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
                                            [<?= $type ?>] <?= htmlspecialchars($t['title']) ?> (<?= htmlspecialchars($t['itbm']) ?>)
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
                                    <label class="form-label small fw-bold">Volume (Jours-Hommes)</label>
                                    <div class="input-group">
                                        <input type="number" name="valeur" class="form-control fw-bold text-center" step="0.25" min="0" max="31" value="1" required>
                                        <span class="input-group-text bg-light">JH</span>
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
    <div class="modal-content">
      <div class="modal-header bg-dark text-white py-2">
        <h6 class="modal-title mb-0"><i class="bi bi-lightning-charge-fill text-warning"></i> Affectation Rapide</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <form method="POST" action="?action=home<?= isset($_GET['date']) ? '&date='.$_GET['date'] : '' ?><?= isset($_GET['detail_uid']) ? '&detail_uid='.$_GET['detail_uid'] : '' ?>">
            <input type="hidden" name="month_saisie" id="modal_month" value="">
            
            <div class="text-center mb-3">
                <div id="modal_uname_display" class="fw-bold text-dark fs-6 mb-1"></div>
                
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <select name="target_user_id" id="modal_uid_select" class="form-select form-select-sm border-primary fw-bold mb-2 d-none" required>
                    <?php foreach($displayUsers as $uid => $uname): ?>
                        <option value="<?= $uid ?>"><?= htmlspecialchars($uname) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php else: ?>
                    <input type="hidden" name="target_user_id" id="modal_uid_hidden" value="">
                <?php endif; ?>
                
                <div class="badge bg-secondary" id="modal_month_display"></div>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Sur le projet :</label>
                <select name="task_id" id="modal_task_id" class="form-select form-select-sm" required>
                    <option value="" disabled selected>Sélectionner...</option>
                    <?php foreach($tasks as $id => $t): 
                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                    ?>
                        <option value="<?= $id ?>">[<?= $type ?>] <?= htmlspecialchars($t['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Volume (0 pour effacer) :</label>
                <div class="input-group input-group-sm">
                    <input type="number" name="valeur" class="form-control text-center fw-bold" step="0.25" min="0" max="31" value="1" required>
                    <span class="input-group-text">JH</span>
                </div>
            </div>

            <div class="mb-3">
                <select name="edit_mode" class="form-select form-select-sm bg-white">
                    <option value="replace" selected>Remplacer l'existant (=)</option>
                    <option value="add">Ajouter à l'existant (+)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold">Valider</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
});

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
    
    var myModal = new bootstrap.Modal(document.getElementById('fastAddModal'));
    myModal.show();
}
</script>
