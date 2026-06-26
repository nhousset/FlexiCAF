<?php
$pastel_colors = ['#fca5a5', '#fdba74', '#fde047', '#86efac', '#5eead4', '#67e8f9', '#93c5fd', '#a5b4fc', '#d8b4fe', '#f9a8d4'];
?>
<style>
.color-picker-label {
    width: 28px; height: 28px; border-radius: 50%; cursor: pointer; border: 2px solid transparent; transition: all 0.2s ease; display: inline-block;
}
.color-picker-label:hover { transform: scale(1.15); }
.btn-check:checked + .color-picker-label { transform: scale(1.15); border-color: #ffffff !important; box-shadow: 0 0 0 3px #475569; }
</style>

<?php if(isset($_GET['zip_error'])): ?>
    <div class="alert alert-danger shadow-sm py-3 mb-4 fw-bold border-0" style="background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); color: #991b1b; border-left: 5px solid #ef4444 !important;">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 align-middle"></i> 
        Le module PHP <code>ZipArchive</code> est introuvable sur le serveur. 
        <div class="mt-2 fw-normal small text-dark">
            Pour activer la fonctionnalité de sauvegarde, connectez-vous à votre serveur Linux et exécutez la commande :<br>
            <code class="bg-dark text-light px-2 py-1 rounded d-inline-block mt-1">sudo dnf install php-pecl-zip && sudo systemctl restart httpd</code>
        </div>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm border">
    <h5 class="mb-0 text-dark fw-bold"><i class="bi bi-shield-lock-fill text-primary"></i> Console d'Administration</h5>
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="d-flex align-items-center">
        <span class="text-muted small fw-bold me-3"><i class="bi bi-cloud-arrow-down-fill"></i> Sauvegarde Globale :</span>
        <a href="?action=admin&download_all=1" class="btn btn-sm btn-outline-dark fw-bold shadow-sm" title="Télécharger la base complète au format ZIP">
            <i class="bi bi-file-earmark-zip-fill text-warning"></i> Archive ZIP
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================================= -->
<!-- PARAMÈTRES GLOBAUX                                        -->
<!-- ========================================================= -->
<?php if ($_SESSION['role'] === 'admin'): ?>
    <?php if(isset($_GET['settings_success'])): ?>
        <div class="alert alert-success small py-2 fw-bold"><i class="bi bi-check-circle"></i> Paramètres mis à jour avec succès.</div>
    <?php endif; ?>
    <div class="card border-primary shadow-sm mb-4">
        <div class="card-header bg-primary text-white"><i class="bi bi-sliders"></i> Configuration de l'application</div>
        <div class="card-body py-3">
            <form method="POST" class="row align-items-end">
                <input type="hidden" name="update_settings" value="1">
                <div class="col-md-9">
                    <label class="small fw-bold text-muted mb-1">Nom de l'équipe ou du Pôle (Affiché dans le menu en haut à gauche)</label>
                    <?php $current_settings = getDb(FILE_SETTINGS); ?>
                    <input type="text" name="app_name" class="form-control fw-bold" value="<?= htmlspecialchars($current_settings['app_name'] ?? 'FlexiCAF') ?>" required>
                    
                    <!-- EVOLUTION : Option pour activer/désactiver le mécanisme "Reste à planifier" -->
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="show_backlog" id="show_backlog" <?= (!isset($current_settings['show_backlog']) || $current_settings['show_backlog']) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-secondary" for="show_backlog">Activer le mécanisme de Backlog "Reste à planifier"</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="bi bi-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    <!-- GESTION UTILISATEURS -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="col-md-6">
        <div class="card border-info shadow-sm mb-4">
            <div class="card-header bg-info text-white"><i class="bi bi-person-plus-fill"></i> Gérer les accès & Ressources</div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="userAdminTabs" role="tablist">
                  <li class="nav-item" role="presentation"><button class="nav-link active" id="user-classic-tab" data-bs-toggle="tab" data-bs-target="#user-classic" type="button" role="tab">Interface Standard</button></li>
                  <li class="nav-item" role="presentation"><button class="nav-link text-warning fw-bold" id="user-json-tab" data-bs-toggle="tab" data-bs-target="#user-json" type="button" role="tab"><i class="bi bi-braces"></i> Éditeur JSON</button></li>
                </ul>
                <div class="tab-content" id="userAdminTabsContent">
                    <div class="tab-pane fade show active" id="user-classic" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="add_user" value="1">
                            <div class="row">
                                <div class="col-6 mb-2"><label class="small fw-bold">Nom (Ex: Kévin L.)</label><input type="text" name="u_name" class="form-control form-control-sm" required></div>
                                <div class="col-6 mb-2"><label class="small fw-bold">Email (Login)</label><input type="email" name="u_email" class="form-control form-control-sm" required></div>
                                <div class="col-12 mb-3"><label class="small fw-bold">Mot de passe temporaire</label><input type="text" name="u_pass" class="form-control form-control-sm" required></div>
                            </div>
                            <div class="bg-light p-2 mb-3 rounded border">
                                <label class="small fw-bold d-block mb-2 text-muted">Droits et Paramètres</label>
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="u_can_saisie" id="canSaisie" checked><label class="form-check-label small" for="canSaisie">Autoriser la Saisie</label></div>
                                <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_saisie_others" id="canSaisieOthers"><label class="form-check-label small text-primary fw-bold" for="canSaisieOthers">Saisie pour un Tiers</label></div>
                                <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_dashboard" id="canDash"><label class="form-check-label small" for="canDash">Autoriser le Dashboard Manager</label></div>
                                <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_manage_tasks" id="canManageTasks"><label class="form-check-label small" for="canManageTasks">Administrateur des activités</label></div>
                                <hr class="my-2">
                                <div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_is_excluded" id="isExcluded"><label class="form-check-label small text-danger" for="isExcluded">Masquer du Capacity Planning</label></div>
                            </div>
                            <button type="submit" class="btn btn-info btn-sm w-100 text-white fw-bold">Créer le profil</button>
                        </form>
                        <hr class="mt-4"><h6 class="fw-bold text-muted small text-uppercase">Comptes Actifs</h6>
                        <ul class="list-group list-group-flush small">
                            <?php foreach(getDb(FILE_USERS) as $id => $u): 
                                $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true;
                                $has_saisie_others = isset($u['can_saisie_others']) ? $u['can_saisie_others'] : false;
                                $has_dash = isset($u['can_dashboard']) ? $u['can_dashboard'] : false;
                                $has_tasks_admin = isset($u['can_manage_tasks']) ? $u['can_manage_tasks'] : false;
                                $is_excluded = isset($u['is_excluded']) ? $u['is_excluded'] : false;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <div><strong><?= htmlspecialchars($u['name'] ?? 'Inconnu') ?></strong><?php if($is_excluded): ?><span class="badge bg-danger ms-1" style="font-size:0.6rem;">Masqué</span><?php endif; ?><br><span class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($u['email'] ?? 'Pas d\'email') ?></span></div>
                                    <div class="text-end">
                                        <?php if($has_saisie): ?><span class="badge bg-success me-1" title="Saisie Autorisée"><i class="bi bi-pencil"></i></span><?php endif; ?>
                                        <?php if($has_saisie_others): ?><span class="badge bg-info text-white me-1" title="Saisie pour un Tiers"><i class="bi bi-people-fill"></i></span><?php endif; ?>
                                        <?php if($has_dash): ?><span class="badge bg-primary me-1" title="Dashboard Autorisé"><i class="bi bi-bar-chart"></i></span><?php endif; ?>
                                        <?php if($has_tasks_admin): ?><span class="badge bg-dark me-2" title="Admin Activités"><i class="bi bi-tags-fill"></i></span><?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $id ?>"><i class="bi bi-gear-fill"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="tab-pane fade" id="user-json" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="update_users_json" value="1">
                            <div class="mb-3"><textarea name="raw_users_json" class="form-control bg-dark text-light border-secondary" rows="18" style="font-family: monospace; font-size: 0.85rem;" spellcheck="false"><?= htmlspecialchars(json_encode(getDb(FILE_USERS), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea></div>
                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold"><i class="bi bi-save-fill"></i> Forcer la sauvegarde JSON</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- REFERENTIEL TACHES -->
    <div class="col-md-<?= ($_SESSION['role'] === 'admin') ? '6' : '12' ?>">
        <div class="card border-secondary shadow-sm mb-4">
            <div class="card-header bg-secondary text-white"><i class="bi bi-tags-fill"></i> Catalogue des Activités</div>
            <div class="card-body">
                <ul class="nav nav-tabs mb-3" id="taskAdminTabs" role="tablist">
                  <li class="nav-item" role="presentation"><button class="nav-link active" id="classic-tab" data-bs-toggle="tab" data-bs-target="#classic" type="button" role="tab">Interface Standard</button></li>
                  <li class="nav-item" role="presentation"><button class="nav-link text-warning fw-bold" id="json-tab" data-bs-toggle="tab" data-bs-target="#json" type="button" role="tab"><i class="bi bi-braces"></i> Éditeur JSON</button></li>
                </ul>
                <div class="tab-content" id="taskAdminTabsContent">
                    <div class="tab-pane fade show active" id="classic" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="add_task" value="1">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label class="small fw-bold">Titre affiché au planning</label>
                                    <input type="text" name="t_title" class="form-control form-control-sm" required>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label class="small fw-bold">Type</label>
                                    <select name="t_type" class="form-select form-select-sm" required>
                                        <option value="Fonctionnel">Fonctionnel</option>
                                        <option value="Technique">Technique</option>
                                        <option value="Structure">Structure</option>
                                        <option value="Formation">Formation</option>
                                        <option value="Absences">Absences</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-2">
                                    <label class="small fw-bold">Code Projet</label>
                                    <input type="text" name="t_itbm" class="form-control form-control-sm" placeholder="PRJ-XXX" required>
                                </div>
                                <div class="col-12 mb-2">
                                    <label class="small fw-bold">Description courte</label>
                                    <input type="text" name="t_desc" class="form-control form-control-sm">
                                </div>
                                <div class="col-12 mb-4 mt-2">
                                    <label class="small fw-bold mb-2">Couleur de la charte graphique</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach($pastel_colors as $index => $col): ?>
                                            <input type="radio" class="btn-check" name="t_color" id="add_color_<?= $index ?>" value="<?= $col ?>" <?= $index === 6 ? 'checked' : '' ?> required>
                                            <label class="color-picker-label shadow-sm" style="background-color: <?= $col ?>;" for="add_color_<?= $index ?>"></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-secondary btn-sm w-100 fw-bold">Ajouter au référentiel</button>
                        </form>
                        <hr class="mt-4">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle small">
                                <tbody>
                                    <?php foreach(getDb(FILE_TASKS) as $id => $t): 
                                        $color = $t['color'] ?? '#e2e8f0'; $type = $t['type'] ?? 'Technique';
                                    ?>
                                        <tr>
                                            <td><div class="d-inline-block rounded me-2" style="width: 15px; height: 15px; background-color: <?= htmlspecialchars($color) ?>; border: 1px solid rgba(0,0,0,0.1);"></div><strong><?= htmlspecialchars($t['title']) ?></strong><span class="badge bg-light text-dark border ms-1" style="font-size: 0.6rem;"><?= htmlspecialchars($type) ?></span></td>
                                            <td class="text-end"><span class="badge badge-itbm me-2"><?= htmlspecialchars($t['itbm']) ?></span><button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editTaskModal-<?= $id ?>"><i class="bi bi-pencil-fill"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="json" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="update_tasks_json" value="1">
                            <div class="mb-3"><textarea name="raw_tasks_json" class="form-control bg-dark text-light border-secondary" rows="18" style="font-family: monospace; font-size: 0.85rem;" spellcheck="false"><?= htmlspecialchars(json_encode(getDb(FILE_TASKS), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea></div>
                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold"><i class="bi bi-save-fill"></i> Forcer la sauvegarde JSON</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AUDIT LOG -->
<?php if ($_SESSION['role'] === 'admin'): ?>
<div class="row mt-4"><div class="col-12"><div class="card border-dark shadow-sm mb-4"><div class="card-header bg-dark text-white d-flex justify-content-between align-items-center"><div><i class="bi bi-clock-history"></i> Historique des Actions (Audit)</div><span class="badge bg-light text-dark">Dernières 500 actions</span></div><div class="card-body p-0"><div class="table-responsive" style="max-height: 400px;"><table class="table table-hover table-sm align-middle mb-0 small"><thead class="table-light" style="position: sticky; top: 0; z-index: 10;"><tr><th style="width: 150px; padding-left: 15px;">Date</th><th style="width: 150px;">Utilisateur</th><th style="width: 150px;">Type d'action</th><th>Détails</th></tr></thead><tbody><?php $auditLogs = getDb(FILE_AUDIT); if(empty($auditLogs)): ?><tr><td colspan="4" class="text-center text-muted py-4">Aucune action enregistrée.</td></tr><?php else: foreach($auditLogs as $log): ?><tr><td class="text-muted ps-3"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['date']))) ?></td><td class="fw-bold"><?= htmlspecialchars($log['user']) ?></td><td><span class="badge bg-secondary"><?= htmlspecialchars($log['action']) ?></span></td><td class="text-truncate" style="max-width: 400px;" title="<?= htmlspecialchars($log['details']) ?>"><?= htmlspecialchars($log['details']) ?></td></tr><?php endforeach; endif; ?></tbody></table></div></div></div></div></div>
<?php endif; ?>

<!-- MODALES DES EDITIONS ACCES -->
<?php if ($_SESSION['role'] === 'admin'): foreach(getDb(FILE_USERS) as $id => $u): 
    $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true; $has_saisie_others = isset($u['can_saisie_others']) ? $u['can_saisie_others'] : false;
    $has_dash = isset($u['can_dashboard']) ? $u['can_dashboard'] : false; $has_tasks_admin = isset($u['can_manage_tasks']) ? $u['can_manage_tasks'] : false; $is_excluded = isset($u['is_excluded']) ? $u['is_excluded'] : false;
?>
<div class="modal fade" id="editUserModal-<?= $id ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-light py-2"><h6 class="modal-title mb-0">Modifier : <?= htmlspecialchars($u['name'] ?? '') ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="edit_user" value="1"><input type="hidden" name="user_id" value="<?= $id ?>"><div class="mb-2"><label class="small fw-bold">Nom</label><input type="text" name="u_name" class="form-control form-control-sm" value="<?= htmlspecialchars($u['name'] ?? '') ?>" required></div><div class="mb-2"><label class="small fw-bold">Email</label><input type="email" name="u_email" class="form-control form-control-sm" value="<?= htmlspecialchars($u['email'] ?? '') ?>" required></div><div class="mb-3"><label class="small fw-bold">Nouveau mot de passe</label><input type="password" name="u_pass" class="form-control form-control-sm"></div><div class="bg-light p-2 mb-3 rounded border"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="u_can_saisie" id="editSaisie-<?= $id ?>" <?= $has_saisie ? 'checked' : '' ?>><label class="form-check-label small" for="editSaisie-<?= $id ?>">Autoriser la Saisie</label></div><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_saisie_others" id="editSaisieOthers-<?= $id ?>" <?= $has_saisie_others ? 'checked' : '' ?>><label class="form-check-label small text-primary fw-bold" for="editSaisieOthers-<?= $id ?>">Saisie pour un Tiers</label></div><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_dashboard" id="editDash-<?= $id ?>" <?= $has_dash ? 'checked' : '' ?>><label class="form-check-label small" for="editDash-<?= $id ?>">Autoriser le Dashboard Manager</label></div><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_can_manage_tasks" id="editTasks-<?= $id ?>" <?= $has_tasks_admin ? 'checked' : '' ?>><label class="form-check-label small" for="editTasks-<?= $id ?>">Administrateur des activités</label></div><hr class="my-2"><div class="form-check form-switch mt-1"><input class="form-check-input" type="checkbox" name="u_is_excluded" id="editExcl-<?= $id ?>" <?= $is_excluded ? 'checked' : '' ?>><label class="form-check-label small text-danger" for="editExcl-<?= $id ?>">Masquer du Capacity Planning</label></div></div><button type="submit" class="btn btn-primary btn-sm w-100">Enregistrer</button></form></div></div></div></div>
<?php endforeach; endif; ?>

<!-- MODALES DES EDITIONS ACTIVITES -->
<?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): foreach(getDb(FILE_TASKS) as $id => $t): $color = $t['color'] ?? '#e2e8f0'; $type = $t['type'] ?? 'Technique'; ?>
<div class="modal fade" id="editTaskModal-<?= $id ?>" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-light py-2"><h6 class="modal-title mb-0">Modifier : <?= htmlspecialchars($t['title']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="edit_task" value="1"><input type="hidden" name="task_id" value="<?= $id ?>"><div class="mb-2"><label class="small fw-bold">Titre au planning</label><input type="text" name="t_title" class="form-control form-control-sm" value="<?= htmlspecialchars($t['title']) ?>" required></div><div class="row"><div class="col-6 mb-2"><label class="small fw-bold">Type</label><select name="t_type" class="form-select form-select-sm" required><option value="Fonctionnel" <?= $type === 'Fonctionnel' ? 'selected' : '' ?>>Fonctionnel</option><option value="Technique" <?= $type === 'Technique' ? 'selected' : '' ?>>Technique</option><option value="Structure" <?= $type === 'Structure' ? 'selected' : '' ?>>Structure</option><option value="Formation" <?= $type === 'Formation' ? 'selected' : '' ?>>Formation</option><option value="Absences" <?= $type === 'Absences' ? 'selected' : '' ?>>Absences</option></select></div><div class="col-6 mb-2"><label class="small fw-bold">Code Projet (ITBM)</label><input type="text" name="t_itbm" class="form-control form-control-sm" value="<?= htmlspecialchars($t['itbm']) ?>" required></div></div><div class="mb-3"><label class="small fw-bold">Description courte</label><input type="text" name="t_desc" class="form-control form-control-sm" value="<?= htmlspecialchars($t['desc'] ?? '') ?>"></div><div class="mb-4"><label class="small fw-bold d-block mb-2">Couleur</label><div class="d-flex flex-wrap gap-2"><?php $found = in_array($color, $pastel_colors); foreach($pastel_colors as $idx => $col): $isChecked = ($color === $col || (!$found && $idx === 0)) ? 'checked' : ''; ?><input type="radio" class="btn-check" name="t_color" id="edit_color_<?= $id ?>_<?= $idx ?>" value="<?= $col ?>" <?= $isChecked ?>><label class="color-picker-label shadow-sm" style="background-color: <?= $col ?>;" for="edit_color_<?= $id ?>_<?= $idx ?>"></label><?php endforeach; ?></div></div><button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Enregistrer</button></form></div></div></div></div>
<?php endforeach; endif; ?>
