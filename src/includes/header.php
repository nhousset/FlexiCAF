<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capacité À Faire - IT Ops</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand text-success" href="?action=home"><i class="bi bi-layers-fill"></i> FlexiCAF</a>
        <div class="d-flex align-items-center">
            <span class="text-light me-3 small">Profil : <strong><?= $_SESSION['name'] ?></strong></span>
            
            <?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): ?>
                <a href="?action=admin" class="btn btn-outline-info btn-sm me-2"><i class="bi bi-gear"></i> Admin</a>
            <?php endif; ?>
            
            <a href="?action=logout" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right"></i> Quitter</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<div class="container-fluid px-4">
