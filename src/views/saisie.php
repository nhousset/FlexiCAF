<?php $tasks = getDb(FILE_TASKS); ?>
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header bg-primary text-white">Déclarer une activité</div>
            <div class="card-body">
                <?php if(isset($_GET['success'])) echo "<div class='alert alert-success py-2 small'>Saisie validée.</div>"; ?>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Activité / Projet</label>
                        <select name="task_id" class="form-select" required>
                            <?php foreach($tasks as $id => $t): ?>
                                <option value="<?= $id ?>"><?= $t['title'] ?> (<?= $t['itbm'] ?>)</option>
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
        <div class="card">
            <div class="card-header bg-dark text-white">Mon suivi récent</div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Date</th><th>Activité</th><th>ITBM</th><th>Charge</th></tr></thead>
                    <tbody>
                        <?php 
                        $allData = getDb(FILE_DATA);
                        $myData = array_filter($allData, fn($e) => $e['user_id'] === $_SESSION['user_id']);
                        usort($myData, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));
                        foreach(array_slice($myData, 0, 15) as $e): 
                            $t = $tasks[$e['task_id']] ?? ['title' => 'Supprimée', 'itbm' => '-'];
                        ?>
                            <tr>
                                <td><?= date('d/m/Y', strtotime($e['date'])) ?></td>
                                <td><?= $t['title'] ?></td>
                                <td><span class="badge badge-itbm"><?= $t['itbm'] ?></span></td>
                                <td><strong><?= $e['valeur_j'] ?> j</strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleForm() {
    const mode = document.getElementById('saisieMode').value;
    const endDiv = document.getElementById('dateEndDiv');
    const daysDiv = document.getElementById('daysDiv');
    
    endDiv.classList.toggle('d-none', mode === 'unique');
    daysDiv.classList.toggle('d-none', mode !== 'recurrence');
}
</script>
