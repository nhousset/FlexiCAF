<div class="row">
    
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="col-md-6">
        <div class="card border-info shadow-sm mb-4">
            <div class="card-header bg-info text-white"><i class="bi bi-person-plus-fill"></i> Ajouter / Gérer les accès</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Nom (Ex: Kévin L.)</label>
                            <input type="text" name="u_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Email (Login)</label>
                            <input type="email" name="u_email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="small fw-bold">Mot de passe temporaire</label>
                            <input type="text" name="u_pass" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    
                    <div class="bg-light p-2 mb-3 rounded border">
                        <label class="small fw-bold d-block mb-2 text-muted">Droits et Paramètres</label>
                        
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="u_can_saisie" id="canSaisie" checked>
                          <label class="form-check-label small" for="canSaisie">
                              Autoriser la Saisie 
                              <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="L'utilisateur pourra déclarer de la charge (Jours-Hommes) sur le planning mensuel."></i>
                          </label>
                        </div>
                        
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="u_can_dashboard" id="canDash">
                          <label class="form-check-label small" for="canDash">
                              Autoriser le Dashboard Manager 
                              <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="Donne accès aux vues croisées (Projet/Consultant) et agrège les données de toute l'équipe."></i>
                          </label>
                        </div>
                        
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="u_can_manage_tasks" id="canManageTasks">
                          <label class="form-check-label small" for="canManageTasks">
                              Administrateur des activités 
                              <i class="bi bi-info-circle text-primary ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="Permet d'accéder à l'espace Admin pour ajouter ou modifier les projets dans le Catalogue."></i>
                          </label>
                        </div>
                        
                        <hr class="my-2">
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="u_is_excluded" id="isExcluded">
                          <label class="form-check-label small text-danger" for="isExcluded">
                              Masquer du Capacity Planning 
                              <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" data-bs-placement="right" title="Idéal pour les comptes managers purs : l'utilisateur n'apparaîtra plus dans les tableaux d'allocation (Heatmap)."></i>
                          </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-info btn-sm w-100 text-white fw-bold">Créer le profil</button>
                </form>
                
                <hr class="mt-4">
                <h6 class="fw-bold text-muted small text-uppercase">Comptes Actifs</h6>
                <ul class="list-group list-group-flush small">
                    <?php foreach(getDb(FILE_USERS) as $id => $u): 
                        $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true;
                        $has_dash = isset($u['can_dashboard']) ? $u['can_dashboard'] : false;
                        $has_tasks_admin = isset($u['can_manage_tasks']) ? $u['can_manage_tasks'] : false;
                        $is_excluded = isset($u['is_excluded']) ? $u['is_excluded'] : false;
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong><?= htmlspecialchars($u['name'] ?? 'Inconnu') ?></strong>
                                <?php if($is_excluded): ?><span class="badge bg-danger ms-1" style="font-size:0.6rem;">Masqué</span><?php endif; ?>
                                <br>
                                <span class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($u['email'] ?? 'Pas d\'email') ?></span>
                            </div>
                            <div class="text-end">
                                <?php if($has_saisie): ?><span class="badge bg-success me-1" title="Saisie Autorisée"><i class="bi bi-pencil"></i></span><?php endif; ?>
                                <?php if($has_dash): ?><span class="badge bg-primary me-1" title="Dashboard Autorisé"><i class="bi bi-bar-chart"></i></span><?php endif; ?>
                                <?php if($has_tasks_admin): ?><span class="badge bg-dark me-2" title="Admin Activités"><i class="bi bi-tags-fill"></i></span><?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $id ?>" title="Modifier">
                                    <i class="bi bi-gear-fill"></i>
                                </button>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="col-md-<?= ($_SESSION['role'] === 'admin') ? '6' : '12' ?>">
        <div class="card border-secondary shadow-sm mb-4">
            <div class="card-header bg-secondary text-white"><i class="bi bi-tags-fill"></i> Catalogue des Activités</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_task" value="1">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Titre affiché au planning</label>
                            <input type="text" name="t_title" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-4 mb-2">
                            <label class="small fw-bold">Type</label>
                            <select name="t_type" class="form-select form-select-sm" required>
                                <option value="Fonctionnel">Fonctionnel</option>
                                <option value="Technique">Technique</option>
                                <option value="Structure">Structure</option>
                                <option value="Formation">Formation</option>
                                <option value="Absences">Absences</option>
                            </select>
                        </div>
                        <div class="col-2 mb-2">
                            <label class="small fw-bold">Couleur</label>
                            <input type="color" name="t_color" class="form-control form-control-sm p-1" value="#bae6fd" style="height: 31px;">
                        </div>
                        <div class="col-4 mb-3">
                            <label class="small fw-bold">Code Projet</label>
                            <input type="text" name="t_itbm" class="form-control form-control-sm" placeholder="PRJ-XXX" required>
                        </div>
                        <div class="col-8 mb-3">
                            <label class="small fw-bold">Description courte</label>
                            <input type="text" name="t_desc" class="form-control form-control-sm">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm w-100 fw-bold">Ajouter au référentiel</button>
                </form>

                <hr class="mt-4">
                <div class="table-responsive">
                    <table class="table table-sm align-middle small">
                        <tbody>
                            <?php foreach(getDb(FILE_TASKS) as $t): 
                                $color = $t['color'] ?? '#e2e8f0';
                                $type = $t['type'] ?? 'Technique';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-inline-block rounded me-2" style="width: 15px; height: 15px; background-color: <?= htmlspecialchars($color) ?>; border: 1px solid rgba(0,0,0,0.1);"></div>
                                        <strong><?= htmlspecialchars($t['title']) ?></strong>
                                        <span class="badge bg-light text-dark border ms-1" style="font-size: 0.6rem;"><?= htmlspecialchars($type) ?></span>
                                    </td>
                                    <td class="text-end"><span class="badge badge-itbm"><?= htmlspecialchars($t['itbm']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
// Rendu des modales uniquement pour le Super-Admin
if ($_SESSION['role'] === 'admin'): 
    foreach(getDb(FILE_USERS) as $id => $u): 
        $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true;
        $has_dash = isset($u['can_dashboard']) ? $u['can_dashboard'] : false;
        $has_tasks_admin = isset($u['can_manage_tasks']) ? $u['can_manage_tasks'] : false;
        $is_excluded = isset($u['is_excluded']) ? $u['is_excluded'] : false;
?>
<div class="modal fade" id="editUserModal-<?= $id ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light py-2">
        <h6 class="modal-title mb-0">Modifier : <?= htmlspecialchars($u['name'] ?? '') ?></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="user_id" value="<?= $id ?>">
            <div class="mb-2">
                <label class="small fw-bold">Nom</label>
                <input type="text" name="u_name" class="form-control form-control-sm" value="<?= htmlspecialchars($u['name'] ?? '') ?>" required>
            </div>
            <div class="mb-2">
                <label class="small fw-bold">Email</label>
                <input type="email" name="u_email" class="form-control form-control-sm" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="small fw-bold">Nouveau mot de passe <span class="text-muted fw-normal">(laisser vide pour conserver)</span></label>
                <input type="password" name="u_pass" class="form-control form-control-sm">
            </div>
            
            <div class="bg-light p-2 mb-3 rounded border">
                <div class="form-check form-switch">
                  <input class="form-check-input" type="checkbox" name="u_can_saisie" id="editSaisie-<?= $id ?>" <?= $has_saisie ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="editSaisie-<?= $id ?>">Autoriser la Saisie</label>
                </div>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input" type="checkbox" name="u_can_dashboard" id="editDash-<?= $id ?>" <?= $has_dash ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="editDash-<?= $id ?>">Autoriser le Dashboard Manager</label>
                </div>
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input" type="checkbox" name="u_can_manage_tasks" id="editTasks-<?= $id ?>" <?= $has_tasks_admin ? 'checked' : '' ?>>
                  <label class="form-check-label small" for="editTasks-<?= $id ?>">Administrateur des activités</label>
                </div>
                <hr class="my-2">
                <div class="form-check form-switch mt-1">
                  <input class="form-check-input" type="checkbox" name="u_is_excluded" id="editExcl-<?= $id ?>" <?= $is_excluded ? 'checked' : '' ?>>
                  <label class="form-check-label small text-danger" for="editExcl-<?= $id ?>">Masquer du Capacity Planning</label>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-sm w-100">Enregistrer les modifications</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php 
    endforeach; 
endif; 
?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
