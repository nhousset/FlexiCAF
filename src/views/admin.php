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

<!-- GESTION DE L'ERREUR D'EXTENSION ZIP -->
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

<!-- EN-TÊTE DE L'ADMINISTRATION -->
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
<!-- PARAMÈTRES GLOBAUX (Nom Application)                      -->
<!-- ========================================================= -->
<?php if ($_SESSION['role'] === 'admin'): ?>
    <?php if(isset($_GET['settings_success'])): ?>
        <div class="alert alert-success small py-2 fw-bold"><i class="bi bi-check-circle"></i> Le nom de l'application a bien été mis à jour.</div>
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
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="bi bi-save"></i> Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div class="row">
    
    <!-- ========================================================= -->
    <!-- GESTION UTILISATEURS (Restreint au Super-Admin)           -->
    <!-- ========================================================= -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="col-md-6">
        <div class="card border-info shadow-sm mb-4">
            <div class="card-header bg-info text-white"><i class="bi bi-person-plus-fill"></i> Gérer les accès & Ressources</div>
            <div class="card-body">
                
                <ul class="nav nav-tabs mb-3" id="userAdminTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="user-classic-tab" data-bs-toggle="tab" data-bs-target="#user-classic" type="button" role="tab">Interface Standard</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link text-warning fw-bold" id="user-json-tab" data-bs-toggle="tab" data-bs-target="#user-json" type="button" role="tab"><i class="bi bi-braces"></i> Éditeur JSON</button>
                  </li>
                </ul>

                <div class="tab-content" id="userAdminTabsContent">
                    
                    <div class="tab-pane fade show active" id="user-classic" role="tabpanel">
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
                                  <label class="form-check-label small" for="canSaisie">Autoriser la Saisie <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="L'utilisateur pourra déclarer de la charge pour lui-même."></i></label>
                                </div>
                                <div class="form-check form-switch mt-1">
                                  <input class="form-check-input" type="checkbox" name="u_can_saisie_others" id="canSaisieOthers">
                                  <label class="form-check-label small text-primary fw-bold" for="canSaisieOthers">Saisie pour un Tiers <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="Permet de saisir de la charge pour d'autres collaborateurs."></i></label>
                                </div>
                                <div class="form-check form-switch mt-1">
                                  <input class="form-check-input" type="checkbox" name="u_can_dashboard" id="canDash">
                                  <label class="form-check-label small" for="canDash">Autoriser le Dashboard Manager <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="Donne accès aux vues croisées et agrège les données."></i></label>
                                </div>
                                <div class="form-check form-switch mt-1">
                                  <input class="form-check-input" type="checkbox" name="u_can_manage_tasks" id="canManageTasks">
                                  <label class="form-check-label small" for="canManageTasks">Administrateur des activités <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="Permet de gérer le Catalogue de projets."></i></label>
                                </div>
                                <hr class="my-2">
                                <div class="form-check form-switch mt-1">
                                  <input class="form-check-input" type="checkbox" name="u_is_excluded" id="isExcluded">
                                  <label class="form-check-label small text-danger" for="isExcluded">Masquer du Capacity Planning <i class="bi bi-info-circle text-muted ms-1" data-bs-toggle="tooltip" title="N'apparaîtra plus dans les tableaux d'allocation."></i></label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-info btn-sm w-100 text-white fw-bold">Créer le profil</button>
                        </form>
                        
                        <hr class="mt-4">
                        <h6 class="fw-bold text-muted small text-uppercase">Comptes Actifs</h6>
                        <ul class="list-group list-group-flush small">
                            <?php foreach(getDb(FILE_USERS) as $id => $u): 
                                $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true;
                                $has_saisie_others = isset($u['can_saisie_others']) ? $u['can_saisie_others'] : false;
                                $has_dash = isset($u['can_dashboard']) ? $u['can_dashboard'] : false;
                                $has_tasks_admin = isset($u['can_manage_tasks']) ? $u['can_manage_tasks'] : false;
                                $is_excluded = isset($u['is_excluded']) ? $u['is_excluded'] : false;
                            ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0 bg-transparent">
                                    <div>
                                        <strong><?= htmlspecialchars($u['name'] ?? 'Inconnu') ?></strong>
                                        <?php if($is_excluded): ?><span class="badge bg-danger ms-1" style="font-size:0.6rem;">Masqué</span><?php endif; ?>
                                        <br>
                                        <span class="text-muted" style="font-size:0.7rem;"><?= htmlspecialchars($u['email'] ?? 'Pas d\'email') ?></span>
                                    </div>
                                    <div class="text-end">
                                        <?php if($has_saisie): ?><span class="badge bg-success me-1" title="Saisie Autorisée"><i class="bi bi-pencil"></i></span><?php endif; ?>
                                        <?php if($has_saisie_others): ?><span class="badge bg-info text-white me-1" title="Saisie pour un Tiers"><i class="bi bi-people-fill"></i></span><?php endif; ?>
                                        <?php if($has_dash): ?><span class="badge bg-primary me-1" title="Dashboard Autorisé"><i class="bi bi-bar-chart"></i></span><?php endif; ?>
                                        <?php if($has_tasks_admin): ?><span class="badge bg-dark me-2" title="Admin Activités"><i class="bi bi-tags-fill"></i></span><?php endif; ?>
                                        <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editUserModal-<?= $id ?>" title="Modifier"><i class="bi bi-gear-fill"></i></button>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="tab-pane fade" id="user-json" role="tabpanel">
                        <?php if(isset($_GET['json_user_success'])): ?>
                            <div class="alert alert-success small py-2"><i class="bi bi-check-circle"></i> Fichier users.json mis à jour.</div>
                        <?php endif; ?>

                        <div class="alert alert-light border small py-2 mb-3">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill text-info"></i> Guide des propriétés JSON :</h6>
                            <ul class="mb-0 ps-3">
                                <li><code>name</code> : Nom affiché dans le planning (Texte).</li>
                                <li><code>email</code> : Identifiant de connexion (Texte).</li>
                                <li><code class="text-danger">password</code> : Empreinte Bcrypt. Ne modifiez jamais cette chaîne manuellement.</li>
                                <li><code>can_saisie</code> : Droit de déclaration de charge (<code>true</code> / <code>false</code> sans guillemets).</li>
                                <li><code class="text-primary">can_saisie_others</code> : Droit de saisir pour d'autres collaborateurs (<code>true</code> / <code>false</code>).</li>
                                <li><code>can_dashboard</code> : Droit de voir toute l'équipe (<code>true</code> / <code>false</code>).</li>
                                <li><code>can_manage_tasks</code> : Droit d'éditer le catalogue (<code>true</code> / <code>false</code>).</li>
                                <li><code>is_excluded</code> : Masquer le compte du planning (<code>true</code> / <code>false</code>).</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="update_users_json" value="1">
                            <div class="mb-3">
                                <textarea name="raw_users_json" class="form-control bg-dark text-light border-secondary shadow-inner" rows="18" style="font-family: monospace; font-size: 0.85rem;" spellcheck="false"><?= htmlspecialchars(json_encode(getDb(FILE_USERS), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold"><i class="bi bi-save-fill"></i> Forcer la sauvegarde JSON</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- GESTION REFERENTIEL TACHES (Super-Admin & Admin Tâches)   -->
    <!-- ========================================================= -->
    <div class="col-md-<?= ($_SESSION['role'] === 'admin') ? '6' : '12' ?>">
        <div class="card border-secondary shadow-sm mb-4">
            <div class="card-header bg-secondary text-white"><i class="bi bi-tags-fill"></i> Catalogue des Activités</div>
            <div class="card-body">
                
                <ul class="nav nav-tabs mb-3" id="taskAdminTabs" role="tablist">
                  <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="classic-tab" data-bs-toggle="tab" data-bs-target="#classic" type="button" role="tab">Interface Standard</button>
                  </li>
                  <li class="nav-item" role="presentation">
                    <button class="nav-link text-warning fw-bold" id="json-tab" data-bs-toggle="tab" data-bs-target="#json" type="button" role="tab"><i class="bi bi-braces"></i> Éditeur JSON</button>
                  </li>
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
                                        $color = $t['color'] ?? '#e2e8f0';
                                        $type = $t['type'] ?? 'Technique';
                                    ?>
                                        <tr>
                                            <td>
                                                <div class="d-inline-block rounded me-2" style="width: 15px; height: 15px; background-color: <?= htmlspecialchars($color) ?>; border: 1px solid rgba(0,0,0,0.1);"></div>
                                                <strong><?= htmlspecialchars($t['title']) ?></strong>
                                                <span class="badge bg-light text-dark border ms-1" style="font-size: 0.6rem;"><?= htmlspecialchars($type) ?></span>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge badge-itbm me-2"><?= htmlspecialchars($t['itbm']) ?></span>
                                                <button class="btn btn-sm btn-outline-secondary py-0 px-1" data-bs-toggle="modal" data-bs-target="#editTaskModal-<?= $id ?>" title="Modifier l'activité"><i class="bi bi-pencil-fill"></i></button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="json" role="tabpanel">
                        <?php if(isset($_GET['json_success'])): ?>
                            <div class="alert alert-success small py-2"><i class="bi bi-check-circle"></i> Fichier tasks.json mis à jour avec succès.</div>
                        <?php endif; ?>

                        <div class="alert alert-light border small py-2 mb-3">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle-fill text-warning"></i> Guide des propriétés JSON :</h6>
                            <ul class="mb-0 ps-3">
                                <li><code>title</code> : Titre affiché au planning (Texte).</li>
                                <li><code>type</code> : Catégorie (Texte : <em>Fonctionnel, Technique, Structure, Formation, Absences</em>).</li>
                                <li><code>color</code> : Code couleur au format hexadécimal (Texte : ex. <code>#bae6fd</code>).</li>
                                <li><code>itbm</code> : Code Projet ou référence interne (Texte).</li>
                                <li><code>desc</code> : Description courte ou note (Texte).</li>
                            </ul>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="update_tasks_json" value="1">
                            <div class="mb-3">
                                <textarea name="raw_tasks_json" class="form-control bg-dark text-light border-secondary shadow-inner" rows="18" style="font-family: monospace; font-size: 0.85rem;" spellcheck="false"><?= htmlspecialchars(json_encode(getDb(FILE_TASKS), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></textarea>
                            </div>
                            <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold"><i class="bi bi-save-fill"></i> Forcer la sauvegarde JSON</button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- ZONES DES MODALES UTILISATEURS             -->
<!-- ========================================== -->
<?php 
if ($_SESSION['role'] === 'admin'): 
    foreach(getDb(FILE_USERS) as $id => $u): 
        $has_saisie = isset($u['can_saisie']) ? $u['can_saisie'] : true;
        $has_saisie_others = isset($u['can_saisie_others']) ? $u['can_saisie_others'] : false;
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
                  <input class="form-check-input" type="checkbox" name="u_can_saisie_others" id="editSaisieOthers-<?= $id ?>" <?= $has_saisie_others ? 'checked' : '' ?>>
                  <label class="form-check-label small text-primary fw-bold" for="editSaisieOthers-<?= $id ?>">Saisie pour un Tiers</label>
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

<!-- ========================================== -->
<!-- ZONES DES MODALES TACHES (CATALOGUE)       -->
<!-- ========================================== -->
<?php 
if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): 
    foreach(getDb(FILE_TASKS) as $id => $t): 
        $color = $t['color'] ?? '#e2e8f0';
        $type = $t['type'] ?? 'Technique';
?>
<div class="modal fade" id="editTaskModal-<?= $id ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-light py-2">
        <h6 class="modal-title mb-0">Modifier l'activité : <span class="text-primary"><?= htmlspecialchars($t['title']) ?></span></h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="edit_task" value="1">
            <input type="hidden" name="task_id" value="<?= $id ?>">
            
            <div class="mb-2">
                <label class="small fw-bold">Titre affiché au planning</label>
                <input type="text" name="t_title" class="form-control form-control-sm" value="<?= htmlspecialchars($t['title']) ?>" required>
            </div>
            
            <div class="row">
                <div class="col-6 mb-2">
                    <label class="small fw-bold">Type</label>
                    <select name="t_type" class="form-select form-select-sm" required>
                        <option value="Fonctionnel" <?= $type === 'Fonctionnel' ? 'selected' : '' ?>>Fonctionnel</option>
                        <option value="Technique" <?= $type === 'Technique' ? 'selected' : '' ?>>Technique</option>
                        <option value="Structure" <?= $type === 'Structure' ? 'selected' : '' ?>>Structure</option>
                        <option value="Formation" <?= $type === 'Formation' ? 'selected' : '' ?>>Formation</option>
                        <option value="Absences" <?= $type === 'Absences' ? 'selected' : '' ?>>Absences</option>
                    </select>
                </div>
                <div class="col-6 mb-2">
                    <label class="small fw-bold">Code Projet (ITBM)</label>
                    <input type="text" name="t_itbm" class="form-control form-control-sm" value="<?= htmlspecialchars($t['itbm']) ?>" required>
                </div>
            </div>
            
            <div class="mb-3">
                <label class="small fw-bold">Description courte</label>
                <input type="text" name="t_desc" class="form-control form-control-sm" value="<?= htmlspecialchars($t['desc'] ?? '') ?>">
            </div>

            <div class="mb-4">
                <label class="small fw-bold d-block mb-2">Couleur de la charte graphique</label>
                <div class="d-flex flex-wrap gap-2">
                    <?php 
                    $found = in_array($color, $pastel_colors);
                    foreach($pastel_colors as $index => $col): 
                        $isChecked = ($color === $col || (!$found && $index === 0)) ? 'checked' : '';
                    ?>
                        <input type="radio" class="btn-check" name="t_color" id="edit_color_<?= $id ?>_<?= $index ?>" value="<?= $col ?>" <?= $isChecked ?> required>
                        <label class="color-picker-label shadow-sm" style="background-color: <?= $col ?>;" for="edit_color_<?= $id ?>_<?= $index ?>"></label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold">Enregistrer les modifications</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php 
    endforeach; 
endif; 
?>

<!-- MODALE ERREUR JSON GLOBALE -->
<?php if((isset($_GET['json_error']) || isset($_GET['json_user_error'])) && !empty($_SESSION['json_error_msg'])): ?>
<div class="modal fade" id="jsonErrorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header bg-danger text-white py-2">
        <h6 class="modal-title mb-0 fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i> Échec de l'enregistrement</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light p-4 text-center">
          <i class="bi bi-x-circle text-danger mb-3" style="font-size: 3rem; display: block;"></i>
          <h6 class="fw-bold text-dark mb-3">Impossible de sauvegarder le fichier JSON</h6>
          <div class="text-danger small fw-bold bg-white border border-danger p-2 rounded text-start" style="font-family: monospace;">
              > <?= htmlspecialchars($_SESSION['json_error_msg']) ?>
          </div>
      </div>
      <div class="modal-footer border-0 bg-light justify-content-center pt-0">
        <button type="button" class="btn btn-secondary btn-sm fw-bold px-4 shadow-sm" data-bs-dismiss="modal">Fermer et corriger</button>
      </div>
    </div>
  </div>
</div>
<?php 
    unset($_SESSION['json_error_msg']); 
endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    <?php if(isset($_GET['json_error']) || isset($_GET['json_user_error'])): ?>
        <?php if(isset($_GET['json_error'])): ?>
            var jsonTab = new bootstrap.Tab(document.getElementById('json-tab'));
            jsonTab.show();
        <?php endif; ?>
        <?php if(isset($_GET['json_user_error'])): ?>
            var jsonUserTab = new bootstrap.Tab(document.getElementById('user-json-tab'));
            jsonUserTab.show();
        <?php endif; ?>
        
        var errorModal = new bootstrap.Modal(document.getElementById('jsonErrorModal'));
        errorModal.show();
    <?php endif; ?>
});
</script>
