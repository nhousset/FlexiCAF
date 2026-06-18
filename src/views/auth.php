<div class="row justify-content-center mt-5">
    <div class="col-md-5">
        <div class="card shadow-lg">
            <div class="card-header bg-dark text-white text-center py-3">
                <h4 class="mb-0">Infrastructure CAF</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if ($action === 'init_admin'): ?>
                    <div class="alert alert-info small">Premier lancement détecté. Veuillez définir le mot de passe du compte Administrateur système.</div>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mot de passe Administrateur</label>
                            <input type="password" name="admin_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Initialiser l'instance</button>
                    </form>

                <?php else: ?>
                    <?php if (isset($error)) echo "<div class='alert alert-danger small'>$error</div>"; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Email ou Login</label>
                            <input type="text" name="login" class="form-control" placeholder="Ex: admin ou email consultant" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Mot de passe</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Ouvrir la session</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
