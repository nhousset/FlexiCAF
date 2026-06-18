<?php
$tasks = getDb(FILE_TASKS); 
$allData = getDb(FILE_DATA);
$allUsers = getDb(FILE_USERS);

// Permissions courantes
$canSaisie = hasPermission('can_saisie');
$canDashboard = hasPermission('can_dashboard');

// --------------------------------------------------------
// MOTEUR DE NAVIGATION TEMPORELLE (Pour le Planning Quotidien)
// --------------------------------------------------------
$view_mode = $_GET['view'] ?? 'week'; 
$anchor_date_str = $_GET['date'] ?? date('Y-m-d');
$anchor_date = new DateTime($anchor_date_str);

$plan_dates = [];
$nav_prev = '';
$nav_next = '';
$display_period_text = '';

if ($view_mode === 'week') {
    $start_date = clone $anchor_date;
    $start_date->modify('Monday this week');
    $nav_prev = (clone $start_date)->modify('-1 week')->format('Y-m-d');
    $nav_next = (clone $start_date)->modify('+1 week')->format('Y-m-d');
    $display_period_text = "Semaine du " . $start_date->format('d/m/Y');
    for($i=0; $i<7; $i++) {
        $plan_dates[] = clone $start_date;
        $start_date->modify('+1 day');
    }
} else {
    $start_date = clone $anchor_date;
    $start_date->modify('first day of this month');
    $end_of_month = (clone $start_date)->modify('last day of this month');
    $nav_prev = (clone $start_date)->modify('-1 month')->format('Y-m-d');
    $nav_next = (clone $start_date)->modify('+1 month')->format('Y-m-d');
    
    $mois_fr = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];
    $display_period_text = "Mois de " . $mois_fr[(int)$start_date->format('n')] . ' ' . $start_date->format('Y');

    while($start_date <= $end_of_month) {
        $plan_dates[] = clone $start_date;
        $start_date->modify('+1 day');
    }
}

// --------------------------------------------------------
// PRÉPARATION DE LA GRILLE (Filtre des exclus)
// --------------------------------------------------------
$displayUsers = [];
if ($_SESSION['role'] === 'admin' || $canDashboard) {
    if ($_SESSION['role'] === 'admin') $displayUsers['admin'] = 'Administrateur';
    foreach($allUsers as $id => $u) {
        if (empty($u['is_excluded'])) $displayUsers[$id] = $u['name'];
    }
} else {
    $displayUsers[$_SESSION['user_id']] = $_SESSION['name'];
}

$grid = [];
foreach($displayUsers as $uid => $uname) {
    $grid[$uid] = array_fill_keys(array_map(fn($d) => $d->format('Y-m-d'), $plan_dates), []);
}

foreach($allData as $e) {
    $uid = $e['user_id'];
    $date = $e['date'];
    if (isset($grid[$uid][$date])) {
        $grid[$uid][$date][] = $e;
    }
}

// --------------------------------------------------------
// PREPARATION DU DASHBOARD GLOBAL (Aggrégation Semaine/Mois)
// --------------------------------------------------------
$dash_columns = [];
$mois_fr = [1=>'Jan',2=>'Fév',3=>'Mar',4=>'Avr',5=>'Mai',6=>'Juin',7=>'Juil',8=>'Aoû',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Déc'];

if ($view_mode === 'month') {
    // Vue Mois : Affichage de 6 mois glissants
    $start_dash = clone $anchor_date;
    $start_dash->modify('first day of this month');
    for ($i = 0; $i < 6; $i++) {
        $end_dash = (clone $start_dash)->modify('last day of this month');
        $dash_columns[] = [
            'label' => $mois_fr[(int)$start_dash->format('n')] . ' ' . $start_dash->format('Y'),
            'start' => clone $start_dash,
            'end' => clone $end_dash,
            'working_days' => 0,
            'consumed_per_user' => array_fill_keys(array_keys($displayUsers), 0),
            'total_consumed' => 0
        ];
        $start_dash->modify('+1 month');
    }
} else {
    // Vue Semaine : Affichage de 8 semaines glissantes
    $start_dash = clone $anchor_date;
    $start_dash->modify('Monday this week');
    for ($i = 0; $i < 8; $i++) {
        $end_dash = (clone $start_dash)->modify('Sunday this week');
        $dash_columns[] = [
            'label' => 'Sem ' . $start_dash->format('W') . '<br><small class="text-muted fw-normal">(' . $start_dash->format('d/m') . ')</small>',
            'start' => clone $start_dash,
            'end' => clone $end_dash,
            'working_days' => 0,
            'consumed_per_user' => array_fill_keys(array_keys($displayUsers), 0),
            'total_consumed' => 0
        ];
        $start_dash->modify('+1 week');
    }
}

// Calcul de la capacité par colonne (jours ouvrés)
foreach ($dash_columns as &$col) {
    $curr = clone $col['start'];
    while ($curr <= $col['end']) {
        if (!in_array($curr->format('N'), [6, 7])) {
            $col['working_days']++;
        }
        $curr->modify('+1 day');
    }
    $col['total_capacity'] = $col['working_days'] * count($displayUsers);
}
unset($col); // Rompre la référence

// Remplissage des consommations pour le Dashboard
foreach ($allData as $e) {
    $uid = $e['user_id'];
    if (!isset($displayUsers[$uid])) continue;
    $date = $e['date'];
    
    foreach ($dash_columns as &$col) {
        if ($date >= $col['start']->format('Y-m-d') && $date <= $col['end']->format('Y-m-d')) {
            $col['consumed_per_user'][$uid] += $e['valeur_j'];
            $col['total_consumed'] += $e['valeur_j'];
            break;
        }
    }
    unset($col);
}

// Fonction utilitaire pour générer la couleur Heatmap
function getHeatmapStyle($perc) {
    if ($perc == 0) return 'background-color: #ffffff;';
    if ($perc > 0 && $perc < 100) return 'background-color: #a7f3d0; color: #065f46;';
    if ($perc == 100) return 'background-color: #34d399; color: #064e3b; font-weight: bold;';
    return 'background-color: #fed7aa; color: #9a3412; font-weight: bold; box-shadow: inset 0 0 0 1px #f97316;';
}

// Variables KPI du haut (basées sur la toute première colonne affichée pour rester cohérent)
$kpi_cap_max = $dash_columns[0]['total_capacity'];
$kpi_consumed = $dash_columns[0]['total_consumed'];
$kpi_percent = $kpi_cap_max > 0 ? round(($kpi_consumed / $kpi_cap_max) * 100) : 0;

?>

<div class="d-flex justify-content-between align-items-center mb-3 bg-white p-2 rounded shadow-sm border">
    <div>
        <a href="?action=home&view=<?= $view_mode ?>&date=<?= $nav_prev ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-left"></i></a>
        <a href="?action=home&view=<?= $view_mode ?>&date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-light border mx-1">Aujourd'hui</a>
        <a href="?action=home&view=<?= $view_mode ?>&date=<?= $nav_next ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-right"></i></a>
        <span class="ms-3 fw-bold text-dark"><i class="bi bi-calendar-event text-success"></i> <?= $display_period_text ?></span>
    </div>
    <div class="btn-group">
        <a href="?action=home&view=week&date=<?= $anchor_date_str ?>" class="btn btn-sm <?= $view_mode === 'week' ? 'btn-success' : 'btn-outline-secondary' ?>">Semaine</a>
        <a href="?action=home&view=month&date=<?= $anchor_date_str ?>" class="btn btn-sm <?= $view_mode === 'month' ? 'btn-success' : 'btn-outline-secondary' ?>">Mois</a>
    </div>
</div>

<ul class="nav nav-tabs" id="viewTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning" type="button">Allocation Quotidienne</button>
  </li>
  <?php if($canSaisie): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="saisie-tab" data-bs-toggle="tab" data-bs-target="#saisie" type="button">Saisie Avancée</button>
  </li>
  <?php endif; ?>
  <?php if($canDashboard): ?>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="dashboard-tab" data-bs-toggle="tab" data-bs-target="#dashboard" type="button">Dashboard Global</button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content" id="viewTabsContent">

    <div class="tab-pane fade show active" id="planning" role="tabpanel">
        <?php if(isset($_GET['success'])) echo "<div class='alert alert-success py-2 small'>Action enregistrée.</div>"; ?>
        
        <div class="planning-container">
            <table class="table planning-table mb-0">
                <thead>
                    <tr>
                        <th class="user-cell align-middle text-muted small text-uppercase ps-3">Équipe</th>
                        <?php foreach($plan_dates as $d): 
                            $isWeekend = in_array($d->format('N'), [6,7]);
                            $isToday = $d->format('Y-m-d') === date('Y-m-d');
                        ?>
                            <th class="text-center day-cell <?= $isWeekend ? 'weekend' : '' ?>">
                                <div class="small fw-bold text-uppercase" style="letter-spacing: 1px; color: <?= $isToday ? '#10b981' : '#64748b' ?>">
                                    <?= substr(["Dim","Lun","Mar","Mer","Jeu","Ven","Sam"][$d->format('w')], 0, 3) ?>
                                </div>
                                <div class="fs-5 <?= $isToday ? 'fw-bold text-success' : 'fw-light' ?>"><?= $d->format('d/m') ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($displayUsers as $uid => $uname): ?>
                    <tr>
                        <td class="user-cell align-middle ps-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-secondary text-white rounded-circle text-center me-2 shadow-sm" style="width:30px; height:30px; line-height:30px; font-weight:bold; font-size: 0.8rem;">
                                    <?= strtoupper(substr($uname, 0, 1)) ?>
                                </div>
                                <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                            </div>
                        </td>
                        <?php foreach($plan_dates as $d): 
                            $dateStr = $d->format('Y-m-d');
                            $dayTasks = $grid[$uid][$dateStr];
                            $isWeekend = in_array($d->format('N'), [6,7]);
                            $totalJ = array_sum(array_column($dayTasks, 'valeur_j'));
                        ?>
                            <td class="day-cell <?= $isWeekend ? 'weekend' : '' ?>">
                                
                                <?php if($totalJ > 0): ?>
                                    <div class="d-flex justify-content-between mb-1 small fw-bold <?= $totalJ > 1.0 ? 'text-danger' : 'text-success' ?>" style="font-size: 0.65rem;">
                                        <span class="text-uppercase text-muted">Total</span> 
                                        <span><?= $totalJ ?>j <?= $totalJ > 1.0 ? '⚠️' : '' ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php foreach($dayTasks as $t): 
                                    $taskDef = $tasks[$t['task_id']] ?? ['title'=>'Inconnu'];
                                    $bgColor = htmlspecialchars($taskDef['color'] ?? '#e2e8f0');
                                ?>
                                    <div class="task-block shadow-sm" style="background-color: <?= $bgColor ?>;">
                                        <div class="task-title" title="<?= htmlspecialchars($taskDef['title']) ?>">
                                            <?= htmlspecialchars($taskDef['title']) ?>
                                        </div>
                                        <div class="task-duration"><?= $t['valeur_j'] ?>j</div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if(($canSaisie || $_SESSION['role'] === 'admin') && !$isWeekend && $totalJ < 1.0): ?>
                                    <div class="cell-add-btn" onclick="openFastModal('<?= $uid ?>', '<?= htmlspecialchars($uname) ?>', '<?= $dateStr ?>')">
                                        <i class="bi bi-plus-circle-fill"></i> Add
                                    </div>
                                <?php endif; ?>

                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($canSaisie): ?>
    <div class="tab-pane fade mt-4" id="saisie" role="tabpanel">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white">Déclarer des activités complexes (Congés, Récurrence)</div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Activité / Projet</label>
                                <select name="task_id" class="form-select" required>
                                    <?php foreach($tasks as $id => $t): ?>
                                        <option value="<?= $id ?>"><?= htmlspecialchars($t['title']) ?> (<?= htmlspecialchars($t['itbm']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Type de planification</label>
                                <select name="saisie_mode" id="saisieMode" class="form-select" onchange="toggleForm()">
                                    <option value="unique">Journée unique</option>
                                    <option value="continue">Période continue (hors WE)</option>
                                    <option value="recurrence">Récurrence spécifique</option>
                                </select>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Date de début</label>
                                    <input type="date" name="date_start" class="form-control" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-6 d-none" id="dateEndDiv">
                                    <label class="form-label small fw-bold">Date de fin</label>
                                    <input type="date" name="date_end" class="form-control">
                                </div>
                            </div>
                            <div class="mb-3 d-none" id="daysDiv">
                                <label class="form-label small fw-bold d-block">Jours concernés</label>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days[]" value="1"> <label class="form-check-label small">Lun</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days[]" value="2"> <label class="form-check-label small">Mar</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days[]" value="3"> <label class="form-check-label small">Mer</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days[]" value="4"> <label class="form-check-label small">Jeu</label></div>
                                <div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="days[]" value="5"> <label class="form-check-label small">Ven</label></div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-bold">Charge par jour</label>
                                <select name="valeur" class="form-select">
                                    <option value="1.0">1.0 jour (Journée complète)</option>
                                    <option value="0.5" selected>0.5 jour (Demi-journée)</option>
                                    <option value="0.25">0.25 jour (Quart)</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-save"></i> Enregistrer dans le plan de charge</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if($canDashboard): ?>
    <div class="tab-pane fade mt-4" id="dashboard" role="tabpanel">
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Capacité Max (<?= strip_tags($dash_columns[0]['label']) ?>)</h6>
                        <h2 class="text-dark mb-0"><?= $kpi_cap_max ?> Jours</h2>
                        <small class="text-muted">Total de jours ouvrés disponibles</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Charge Allouée (<?= strip_tags($dash_columns[0]['label']) ?>)</h6>
                        <h2 class="text-primary mb-0"><?= $kpi_consumed ?> Jours</h2>
                        <small class="text-muted">Tâches affectées sur la période</small>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold text-uppercase mb-1">Staffing Global</h6>
                        <h2 class="<?= $kpi_percent > 100 ? 'text-danger' : 'text-success' ?> mb-0"><?= $kpi_percent ?> %</h2>
                        <div class="progress mt-2" style="height: 10px;">
                            <div class="progress-bar <?= $kpi_percent > 100 ? 'bg-danger' : 'bg-success' ?>" role="progressbar" style="width: <?= min(100, $kpi_percent) ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="planning-container shadow-sm border-0">
            <table class="table planning-table mb-0 text-center align-middle">
                <thead>
                    <tr class="bg-light">
                        <th class="user-cell align-middle text-muted small text-uppercase text-start ps-3" style="border-bottom: 2px solid #cbd5e1;">Membres Actifs</th>
                        <?php foreach($dash_columns as $col): ?>
                            <th class="day-cell align-middle" style="min-width: 110px; width: 110px; border-bottom: 2px solid #cbd5e1;">
                                <div class="fs-6 fw-bold text-dark"><?= $col['label'] ?></div>
                                <div class="small text-muted" style="font-size: 0.65rem;">Capacité: <?= $col['working_days'] ?>j</div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    
                    <tr class="bg-white">
                        <td class="user-cell align-middle text-start ps-3" style="border-bottom: 3px solid #94a3b8;">
                            <div class="fw-bold text-dark text-uppercase"><i class="bi bi-people-fill me-2 text-primary"></i> Total Équipe</div>
                        </td>
                        <?php foreach($dash_columns as $col): 
                            $totalJ = $col['total_consumed'];
                            $capMax = $col['total_capacity'];
                            $perc = $capMax > 0 ? round(($totalJ / $capMax) * 100) : 0;
                            $bgStyle = getHeatmapStyle($perc);
                        ?>
                            <td style="<?= $bgStyle ?> border-bottom: 3px solid #94a3b8;">
                                <div style="font-size: 0.9rem;"><?= $perc ?>%</div>
                                <div style="font-size: 0.65rem; opacity: 0.8;"><?= $totalJ ?>j / <?= $capMax ?>j</div>
                            </td>
                        <?php endforeach; ?>
                    </tr>

                    <?php foreach($displayUsers as $uid => $uname): ?>
                    <tr>
                        <td class="user-cell align-middle text-start ps-3">
                            <div class="d-flex align-items-center">
                                <div class="bg-dark text-white rounded-circle text-center me-2 shadow-sm" style="width:30px; height:30px; line-height:30px; font-weight:bold; font-size: 0.8rem;">
                                    <?= strtoupper(substr($uname, 0, 1)) ?>
                                </div>
                                <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                            </div>
                        </td>
                        <?php foreach($dash_columns as $col): 
                            $totalJ = $col['consumed_per_user'][$uid];
                            $capMax = $col['working_days'];
                            $perc = $capMax > 0 ? round(($totalJ / $capMax) * 100) : 0;
                            $bgStyle = getHeatmapStyle($perc);
                        ?>
                            <td style="<?= $bgStyle ?> transition: background-color 0.2s;">
                                <div style="font-size: 0.85rem;"><?= $perc ?>%</div>
                                <div style="font-size: 0.65rem; opacity: 0.7;"><?= $totalJ ?>j / <?= $capMax ?>j</div>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    
                </tbody>
            </table>
        </div>
        
    </div>
    <?php endif; ?>

</div>

<div class="modal fade" id="fastAddModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content">
      <div class="modal-header bg-success text-white py-2">
        <h6 class="modal-title mb-0">Nouvelle Allocation</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="?action=home<?= isset($_GET['view']) ? '&view='.$_GET['view'] : '' ?><?= isset($_GET['date']) ? '&date='.$_GET['date'] : '' ?>">
            <input type="hidden" name="saisie_mode" value="unique">
            <input type="hidden" name="target_user_id" id="modal_uid" value="">
            
            <div class="mb-2 text-center text-muted small">
                Consultant : <strong id="modal_uname" class="text-dark"></strong><br>
                Date : <strong id="modal_date_display" class="text-dark"></strong>
                <input type="hidden" name="date_start" id="modal_date" value="">
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold">Projet / Activité</label>
                <select name="task_id" class="form-select form-select-sm" required>
                    <?php foreach($tasks as $id => $t): ?>
                        <option value="<?= $id ?>"><?= htmlspecialchars($t['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold">Charge</label>
                <select name="valeur" class="form-select form-select-sm">
                    <option value="1.0">1.0 j (Journée)</option>
                    <option value="0.5" selected>0.5 j (Demi)</option>
                    <option value="0.25">0.25 j (Quart)</option>
                </select>
            </div>
            
            <button type="submit" class="btn btn-success btn-sm w-100">Allouer</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleForm() {
    const mode = document.getElementById('saisieMode').value;
    const endDiv = document.getElementById('dateEndDiv');
    const daysDiv = document.getElementById('daysDiv');
    endDiv.classList.toggle('d-none', mode === 'unique');
    daysDiv.classList.toggle('d-none', mode !== 'recurrence');
}

document.addEventListener("DOMContentLoaded", function() {
    let activeTab = localStorage.getItem('activeTab');
    if (activeTab && document.querySelector(activeTab)) {
        let tab = new bootstrap.Tab(document.querySelector(activeTab));
        tab.show();
    }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', '#' + e.target.id);
        });
    });
});

function openFastModal(uid, uname, dateStr) {
    document.getElementById('modal_uid').value = uid;
    document.getElementById('modal_uname').innerText = uname;
    document.getElementById('modal_date').value = dateStr;
    
    const d = new Date(dateStr);
    document.getElementById('modal_date_display').innerText = d.toLocaleDateString('fr-FR');
    
    var myModal = new bootstrap.Modal(document.getElementById('fastAddModal'));
    myModal.show();
}
</script>
