<div class="row">
    <div class="col-md-6">
        <div class="card border-info">
            <div class="card-header bg-info text-white">Créer un Consultant</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_user" value="1">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Nom complet</label>
                            <input type="text" name="u_name" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="small fw-bold">Email de connexion</label>
                            <input type="email" name="u_email" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="small fw-bold">Mot de passe temporaire</label>
                            <input type="text" name="u_pass" class="form-control form-control-sm" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-info btn-sm w-100 text-white">Ajouter l'utilisateur</button>
                </form>
                
                <hr>
                <h6 class="fw-bold mt-3">Comptes Actifs</h6>
                <ul class="list-group list-group-flush small">
                    <?php foreach(getDb(FILE_USERS) as $u): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <?= htmlspecialchars($u['name']) ?> <span class="text-muted"><?= htmlspecialchars($u['email']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card border-secondary">
            <div class="card-header bg-secondary text-white">Créer une Tâche Référentiel</div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_task" value="1">
                    <div class="row">
                        <div class="col-8 mb-2">
                            <label class="small fw-bold">Titre de l'activité</label>
                            <input type="text" name="t_title" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-4 mb-2">
                            <label class="small fw-bold">Code ITBM</label>
                            <input type="text" name="t_itbm" class="form-control form-control-sm" placeholder="PRJ-XXX" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label class="small fw-bold">Description courte</label>
                            <input type="text" name="t_desc" class="form-control form-control-sm">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-secondary btn-sm w-100">Ajouter au catalogue</button>
                </form>

                <hr>
                <h6 class="fw-bold mt-3">Catalogue en vigueur</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-striped small">
                        <tbody>
                            <?php foreach(getDb(FILE_TASKS) as $t): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($t['title']) ?></strong></td>
                                    <td><span class="badge badge-itbm"><?= htmlspecialchars($t['itbm']) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
