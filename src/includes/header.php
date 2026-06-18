<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Capacité À Faire - IT Ops</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="?action=home">⚡ FlexiCAF</a>
        <div class="d-flex align-items-center">
            <span class="text-light me-3 small">Connecté : <strong><?= $_SESSION['name'] ?></strong></span>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="?action=admin" class="btn btn-outline-info btn-sm me-2">Console Admin</a>
            <?php endif; ?>
            <a href="?action=logout" class="btn btn-danger btn-sm">Quitter</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<div class="container">
