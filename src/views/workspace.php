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
// PRÉPARATION DES ACTEURS
// --------------------------------------------------------
$displayUsers = [];
if ($_SESSION['role'] === 'admin' || $canDashboard) {
    // CORRECTION : On réintègre l'Admin dans le tableau pour qu'il voie ses propres saisies
    if ($_SESSION['role'] === 'admin') $displayUsers['admin'] = 'Administrateur';
    
    foreach($allUsers as $id => $u) {
        if (empty($u['is_excluded'])) $displayUsers[$id] = $u['name'];
    }
} else {
    $displayUsers[$_SESSION['user_id']] = $_SESSION['name'];
}

// --------------------------------------------------------
// INITIALISATION DES 3 VUES
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
// AGRÉGATION DES DONNÉES
// --------------------------------------------------------
foreach($allData as $e) {
    $uid = $e['user_id'];
    $tid = $e['task_id'];
    if (!isset($displayUsers[$uid])) continue;
    
    $dt = new DateTime($e['date']);
    $m_key = $dt->format('Y-m');
    
    if (isset($dash_months[$m_key])) {
        $pivot_user_month[$uid][$m_key] += $e['valeur_j'];
        if(isset($pivot_task_month[$tid])) $pivot_task_month[$tid][$m_key] += $e['valeur_j'];
        if(isset($pivot_task_user[$tid][$uid])) $pivot_task_user[$tid][$uid] += $e['valeur_j'];
    }
}

function getMonthlyHeatmapStyle($valeur, $capacite_max) {
    if ($valeur == 0) return 'background-color: #ffffff;';
    $perc = $capacite_max > 0 ? round(($valeur / $capacite_max) * 100) : 0;
    
    if ($perc > 0 && $perc < 100) return 'background-color: #d1fae5; color: #065f46; border: 1px solid #a7f3d0;';
    if ($perc == 100) return 'background-color: #34d399; color: #064e3b; font-weight: bold; border: 1px solid #10b981;';
    return 'background-color: #fed7aa; color: #9a3412; font-weight: bold; box-shadow: inset 0 0 0 2px #f97316;';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3 bg-white p-2 rounded shadow-sm border">
    <div>
        <a href="?action=home&date=<?= $nav_prev ?>" class="btn btn-sm btn-light border"><i class="bi bi-chevron-double-left"></i> 6 Mois Précédents</a>
        <a href="?action=home&date=<?= date('Y-m-01') ?>" class="btn btn-sm btn-light border mx-1">Mois en cours</a>
        <a href="?action=home&date=<?= $nav_next ?>" class="btn btn-sm btn-light border">6 Mois Suivants <i class="bi bi-chevron-double-right"></i></a>
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
    <button class="nav-link" id="vue2-tab" data-bs-toggle="tab" data-bs-target="#vue2" type="button"><i class="bi bi-folder-fill"></i> Projet / Mois</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="vue3-tab" data-bs-toggle="tab" data-bs-target="#vue3" type="button"><i class="bi bi-grid-3x3-gap-fill"></i> Projet / Consultant</button>
  </li>
  <?php if($canSaisie): ?>
  <li class="nav-item ms-auto" role="presentation">
    <button class="nav-link text-success fw-bold" id="saisie-tab" data-bs-toggle="tab" data-bs-target="#saisie" type="button"><i class="bi bi-plus-circle"></i> Saisie Manuelle</button>
  </li>
  <?php endif; ?>
</ul>

<div class="tab-content bg-white border border-top-0 p-3 rounded-bottom shadow-sm" id="viewTabsContent">

    <div class="tab-pane fade show active" id="vue1" role="tabpanel">
        <div class="table-responsive">
            <table class="table table-bordered table-hover text-center align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-start text-muted text-uppercase small w-25">Ressources (<?= count($displayUsers) ?>)</th>
                        <?php foreach($dash_months as $m_key => $m_data): ?>
                            <th style="min-width: 120px;">
                                <div class="fs-6 fw-bold text-dark"><?= $m_data['label'] ?></div>
                                <div class="small text-muted fw-normal" style="font-size: 0.65rem;">Max: <?= $m_data['working_days'] ?> JH</div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($displayUsers as $uid => $uname): ?>
                    <tr>
                        <td class="text-start">
                            <div class="d-flex align-items-center">
                                <div class="bg-dark text-white rounded-circle text-center me-2" style="width:28px; height:28px; line-height:28px; font-weight:bold; font-size: 0.75rem;">
                                    <?= strtoupper(substr($uname, 0, 1)) ?>
                                </div>
                                <span class="fw-bold text-dark small"><?= htmlspecialchars($uname) ?></span>
                            </div>
                        </td>
                        <?php foreach($dash_months as $m_key => $m_data): 
                            $valeur = $pivot_user_month[$uid][$m_key];
                            $cap_max = $m_data['working_days'];
                            $style = getMonthlyHeatmapStyle($valeur, $cap_max);
                        ?>
                            <td style="<?= $style ?> position: relative; cursor: pointer;" onclick="openFastModal('<?= $uid ?>', '<?= htmlspecialchars($uname) ?>', '<?= $m_key ?>')">
                                <?php if($valeur > 0): ?>
                                    <div class="fs-6"><?= $valeur ?> <small>JH</small></div>
                                    <div style="font-size: 0.65rem; opacity: 0.8;"><?= round(($valeur/$cap_max)*100) ?>% Alloué</div>
                                <?php else: ?>
                                    <span class="text-muted" style="opacity: 0.3;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-2 small text-muted"><i class="bi bi-info-circle"></i> Cliquez sur une cellule pour ajouter rapidement de la charge à un consultant pour ce mois.</div>
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
                            <td class="text-center" style="<?= $valeur > 0 ? 'background-color: #f8fafc;' : '' ?>">
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
            </table>
        </div>
    </div>

    <?php if($canSaisie): ?>
    <div class="tab-pane fade mt-3" id="saisie" role="tabpanel">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card shadow-sm border-success">
                    <div class="card-header bg-success text-white"><i class="bi bi-pencil-square"></i> Déclarer une charge mensuelle</div>
                    <div class="card-body">
                        <form method="POST">
                            
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-primary"><i class="bi bi-person-fill"></i> Consultant ciblé (Mode Admin)</label>
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
                            
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Mois d'imputation</label>
                                    <input type="month" name="month_saisie" class="form-control fw-bold text-primary" value="<?= date('Y-m') ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Volume de Jours-Hommes</label>
                                    <div class="input-group">
                                        <input type="number" name="valeur" class="form-control fw-bold text-center" step="0.25" min="0.25" max="31" value="1" required>
                                        <span class="input-group-text bg-light">JH</span>
                                    </div>
                                </div>
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
        <h6 class="modal-title mb-0"><i class="bi bi-lightning-charge-fill text-warning"></i> Allocation Rapide</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light">
        <form method="POST" action="?action=home<?= isset($_GET['date']) ? '&date='.$_GET['date'] : '' ?>">
            <input type="hidden" name="target_user_id" id="modal_uid" value="">
            <input type="hidden" name="month_saisie" id="modal_month" value="">
            
            <div class="text-center mb-3">
                <div class="fw-bold text-dark fs-6" id="modal_uname"></div>
                <div class="badge bg-secondary" id="modal_month_display"></div>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Affecter sur le projet :</label>
                <select name="task_id" class="form-select form-select-sm" required>
                    <?php foreach($tasks as $id => $t): 
                        $type = htmlspecialchars($t['type'] ?? 'Technique');
                    ?>
                        <option value="<?= $id ?>">[<?= $type ?>] <?= htmlspecialchars($t['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label small fw-bold text-muted">Volume de charge (Jours-Hommes) :</label>
                <div class="input-group input-group-sm">
                    <input type="number" name="valeur" class="form-control text-center fw-bold" step="0.25" min="0.25" max="31" value="1" required>
                    <span class="input-group-text">JH</span>
                </div>
            </div>
            
            <button type="submit" class="btn btn-dark btn-sm w-100 fw-bold">Valider l'affectation</button>
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

function openFastModal(uid, uname, monthKey) {
    document.getElementById('modal_uid').value = uid;
    document.getElementById('modal_uname').innerText = uname;
    document.getElementById('modal_month').value = monthKey; 
    
    const parts = monthKey.split('-');
    const dateObj = new Date(parts[0], parts[1] - 1);
    const monthName = dateObj.toLocaleString('fr-FR', { month: 'long', year: 'numeric' });
    document.getElementById('modal_month_display').innerText = monthName.charAt(0).toUpperCase() + monthName.slice(1);
    
    var myModal = new bootstrap.Modal(document.getElementById('fastAddModal'));
    myModal.show();
}
</script>
