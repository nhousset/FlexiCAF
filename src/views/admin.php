<div class="row">
    <div class="col-md-6">
        <div class="card border-info shadow-sm">
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
                        <label class="small fw-bold d-block mb-2 text-muted">Droits spécifiques au profil</label>
                        <div class="form-check form-switch">
                          <input class="form-check-input" type="checkbox" name="u_can_saisie" id="canSaisie" checked>
                          <label class="form-check-label small" for="canSaisie">Autoriser la Saisie (Déclaratif d'activité)</label>
                        </div>
                        <div class="form-check form-switch mt-1">
                          <input class="form-check-input" type="checkbox" name="u_can_dashboard" id="canDash">
                          <label class="form-check-label small" for="canDash">Autoriser le Dashboard Manager (Vue Globale)</label>
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
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <strong><?= htmlspecialchars($u['name'] ?? 'Inconnu') ?></strong><br>
                                <span class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($u['email'] ?? 'Pas d\'email') ?></span>
                            </div>
                            <div class="text-end">
                                <?php if($has_saisie): ?><span class="badge bg-success me-1" title="Saisie Autorisée"><i class="bi bi-pencil"></i></span><?php endif; ?>
                                <?php if($has_dash): ?><span class="badge bg-primary me-2" title="Dashboard Autorisé"><i class="bi bi-bar-chart"></i></span><?php endif; ?>
                                
                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $id ?>" title="Modifier">
                                    <i class="bi bi-gear-fill"></i>
                                </button>
                            </div>
                        </li>

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
                                          <label class="form-check-label small" for="editDash-<?= $id ?>">Autoriser le Dashboard</label>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary btn-sm w-100">Enregistrer les modifications</button>
                                </form>
                              </div>
                            </div>
                          </div>
                        </div>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-secondary shadow-sm">
            <div class="card-header bg-secondary text-white"><i class="bi bi-tags-fill"></i> Catalogue des Activités</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_task" value="1">
                    <div class="row">
                        <div class="col-8 mb-2">
                            <label class="small fw-bold">Titre affiché au planning</label>
                            <input type="text" name="t_title" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-4 mb-2">
                            <label class="small fw-bold">Couleur Label</label>
                            <input type="color" name="t_color" class="form-control form-control-sm p-1" value="#bae6fd" style="height: 31px;">
                        </div>
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Code ITBM</label>
                            <input type="text" name="t_itbm" class="form-control form-control-sm" placeholder="PRJ-XXX" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="small fw-bold">Description technique</label>
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
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-inline-block rounded me-2" style="width: 15px; height: 15px; background-color: <?= htmlspecialchars($color) ?>; border: 1px solid rgba(0,0,0,0.1);"></div>
                                        <strong><?= htmlspecialchars($t['title']) ?></strong>
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
