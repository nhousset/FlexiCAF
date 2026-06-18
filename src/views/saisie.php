<?php 
$tasks = getDb(FILE_TASKS); 
$allData = getDb(FILE_DATA);

// --------------------------------------------------------
// PRÉPARATION DES DONNÉES POUR LE PLANNING (14 Jours)
// --------------------------------------------------------
$start_date_plan = new DateTime('monday this week');
$plan_dates = [];
for($i=0; $i<14; $i++) {
    $plan_dates[] = clone $start_date_plan;
    $start_date_plan->modify('+1 day');
}

// Détermination de qui afficher (L'Admin voit toute l'équipe)
$allUsers = getDb(FILE_USERS);
$displayUsers = [];
if ($_SESSION['role'] === 'admin') {
    $displayUsers['admin'] = 'Administrateur';
    foreach($allUsers as $id => $u) $displayUsers[$id] = $u['name'];
} else {
    $displayUsers[$_SESSION['user_id']] = $_SESSION['name'];
}

// Initialisation de la grille [Utilisateur][Date]
$grid = [];
foreach($displayUsers as $uid => $uname) {
    $grid[$uid] = array_fill_keys(array_map(fn($d) => $d->format('Y-m-d'), $plan_dates), []);
}

// Remplissage de la grille avec les datas
foreach($allData as $e) {
    $uid = $e['user_id'];
    $date = $e['date'];
    if (isset($grid[$uid][$date])) {
        $grid[$uid][$date][] = $e;
    }
}
?>

<ul class="nav nav-tabs mb-4" id="viewTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active" id="saisie-tab" data-bs-toggle="tab" data-bs-target="#saisie" type="button" role="tab">Ma Déclaration</button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link" id="planning-tab" data-bs-toggle="tab" data-bs-target="#planning" type="button" role="tab">Planning Équipe</button>
  </li>
</ul>

<div class="tab-content" id="viewTabsContent">

    <div class="tab-pane fade show active" id="saisie" role="tabpanel">
        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm border-primary">
                    <div class="card-header bg-primary text-white">Déclarer une activité</div>
                    <div class="card-body">
                        <?php if(isset($_GET['success'])) echo "<div class='alert alert-success py-2 small'>Saisie validée avec succès.</div>"; ?>
                        
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
                                    <option value="0.5">0.5 jour (Demi-journée)</option>
                                    <option value="1.0">1.0 jour (Journée complète)</option>
                                    <option value="0.25">0.25 jour (Quart)</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-success w-100">Enregistrer dans le plan de charge</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-header bg-dark text-white">Mon suivi récent</div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light"><tr><th>Date</th><th>Activité</th><th>ITBM</th><th>Charge</th></tr></thead>
                            <tbody>
                                <?php 
                                // On filtre les données de l'utilisateur courant, puis on réindexe pour usort
                                $myData = array_filter($allData, fn($e) => $e['user_id'] === $_SESSION['user_id']);
                                $myData = array_values($myData);
                                usort($myData, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                                
                                if(empty($myData)): ?>
                                    <tr><td colspan="4" class="text-center text-muted py-4">Aucune activité enregistrée.</td></tr>
                                <?php else: ?>
                                    <?php foreach(array_slice($myData, 0, 15) as $e): 
                                        $t = $tasks[$e['task_id']] ?? ['title' => 'Tâche Supprimée', 'itbm' => '-'];
                                    ?>
                                        <tr>
                                            <td class="align-middle"><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                            <td class="align-middle"><?= htmlspecialchars($t['title']) ?></td>
                                            <td class="align-middle"><span class="badge badge-itbm"><?= htmlspecialchars($t['itbm']) ?></span></td>
                                            <td class="align-middle text-end pe-3"><strong><?= $e['valeur_j'] ?> j</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="planning" role="tabpanel">
        <div class="planning-container">
            <table class="table planning-table mb-0">
                <thead>
                    <tr>
                        <th class="user-cell align-middle text-center border-bottom-0 pb-2 pt-3 text-muted text-uppercase small">Équipe OPS</th>
                        <?php foreach($plan_dates as $d): 
                            $isWeekend = in_array($d->format('N'), [6,7]);
                        ?>
                            <th class="text-center <?= $isWeekend ? 'bg-light text-muted' : '' ?>" style="min-width: 140px;">
                                <div class="small fw-bold text-uppercase" style="letter-spacing: 1px;">
                                    <?= substr(["Dim","Lun","Mar","Mer","Jeu","Ven","Sam"][$d->format('w')], 0, 3) ?>
                                </div>
                                <div class="fs-4 fw-light"><?= $d->format('d/m') ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($displayUsers as $uid => $uname): ?>
                    <tr>
                        <td class="user-cell align-middle">
                            <div class="d-flex align-items-center ms-2">
                                <div class="avatar bg-dark text-white rounded-circle text-center me-3 shadow-sm" style="width:36px; height:36px; line-height:36px; font-weight:bold; font-size: 0.9rem;">
                                    <?= strtoupper(substr($uname, 0, 1)) ?>
                                </div>
                                <span class="fw-bold text-dark"><?= htmlspecialchars($uname) ?></span>
                            </div>
                        </td>
                        <?php foreach($plan_dates as $d): 
                            $dateStr = $d->format('Y-m-d');
                            $dayTasks = $grid[$uid][$dateStr];
                            $isWeekend = in_array($d->format('N'), [6,7]);
                            $totalJ = array_sum(array_column($dayTasks, 'valeur_j'));
                        ?>
                            <td class="<?= $isWeekend ? 'bg-light' : '' ?> align-top p-2" style="position: relative;">
                                <?php if($totalJ > 0): ?>
                                    <div class="d-flex justify-content-between mb-2 small fw-bold <?= $totalJ > 1.0 ? 'text-danger' : 'text-success' ?>">
                                        <span style="font-size: 0.7rem;" class="text-uppercase text-muted">Total</span> 
                                        <span><?= $totalJ ?> j <?= $totalJ > 1.0 ? '⚠️' : '' ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php foreach($dayTasks as $t): 
                                    $taskDef = $tasks[$t['task_id']] ?? ['title'=>'Inconnu'];
                                ?>
                                    <div class="task-block shadow-sm">
                                        <div class="fw-bold text-truncate" title="<?= htmlspecialchars($taskDef['title']) ?>">
                                            <?= htmlspecialchars($taskDef['title']) ?>
                                        </div>
                                        <div class="mt-1 text-end">
                                            <span class="badge bg-white text-dark border border-secondary" style="font-size:0.7rem;"><?= $t['valeur_j'] ?>j</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if(empty($dayTasks) && !$isWeekend): ?>
                                    <div class="text-center text-muted" style="opacity: 0.1; font-size: 0.8rem; margin-top: 20px;">—</div>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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

// Conserver l'onglet actif après le rechargement de la page (suite à une saisie)
document.addEventListener("DOMContentLoaded", function() {
    let activeTab = localStorage.getItem('activeTab');
    if (activeTab) {
        let tab = new bootstrap.Tab(document.querySelector(activeTab));
        tab.show();
    }
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(btn => {
        btn.addEventListener('shown.bs.tab', function (e) {
            localStorage.setItem('activeTab', '#' + e.target.id);
        });
    });
});
</script>
