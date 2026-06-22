<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlexiCAF - Capacité À Faire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container-fluid px-4">
        
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="?action=home">
            <span class="brand-logo-icon"><i class="bi bi-layers-fill"></i></span>
            <span class="brand-logo-text">FlexiCAF</span>
        </a>

        <div class="d-flex align-items-center ms-auto">
            <div class="bg-white bg-opacity-10 text-white rounded px-3 py-1 me-3 small border border-light border-opacity-25">
                <i class="bi bi-person-circle me-1 text-info"></i> Profil : <strong class="ms-1"><?= $_SESSION['name'] ?></strong>
            </div>
            
            <?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): ?>
                <a href="?action=admin" class="btn btn-light btn-sm me-2 fw-bold text-primary shadow-sm">
                    <i class="bi bi-gear-fill"></i> Admin
                </a>
            <?php endif; ?>
            
            <a href="?action=logout" class="btn btn-danger btn-sm shadow-sm fw-bold">
                <i class="bi bi-box-arrow-right"></i> Quitter
            </a>
        </div>
    </div>
</nav>
<?php endif; ?>

<div class="container-fluid px-4">
