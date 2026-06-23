<?php
// Récupération sécurisée du nom de l'équipe depuis les paramètres
$appSettings = [];
if (function_exists('getDb') && defined('FILE_SETTINGS') && file_exists(FILE_SETTINGS)) {
    $appSettings = getDb(FILE_SETTINGS);
}

// Si un nom est défini on l'utilise, sinon on garde FlexiCAF par défaut
$appName = !empty($appSettings['app_name']) ? htmlspecialchars($appSettings['app_name']) : 'FlexiCAF';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?> - Capacité À Faire</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Notre style CSS Moderne avec anti-cache -->
    <link href="css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>
<body>

<?php if (isset($_SESSION['user_id'])): 
    // Récupération de l'initiale pour l'avatar
    $initial = mb_strtoupper(mb_substr($_SESSION['name'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<!-- Navbar modernisée -->
<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container-fluid px-4">
        
        <!-- Logo Moderne & Nom de l'équipe dynamique -->
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="?action=home">
            <span class="brand-logo-icon"><i class="bi bi-layers-fill"></i></span>
            <span class="brand-logo-text"><?= $appName ?></span>
        </a>

        <div class="d-flex align-items-center ms-auto">
            
            <!-- Cloche de notification factice (pour le style) -->
            <a href="#" class="text-white text-decoration-none me-4 position-relative opacity-75 hover-opacity-100">
                <i class="bi bi-bell-fill fs-5"></i>
                <span class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                    <span class="visually-hidden">Nouvelles alertes</span>
                </span>
            </a>

            <!-- Menu Déroulant Utilisateur -->
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle user-dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar-circle"><?= $initial ?></div>
                    <span class="ms-2 fw-bold d-none d-sm-inline"><?= htmlspecialchars($_SESSION['name']) ?></span>
                </a>
                
                <!-- Contenu du menu -->
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2" aria-labelledby="userDropdown">
                    
                    <!-- En-tête du menu (Profil) -->
                    <li class="px-4 py-2 border-bottom mb-2">
                        <span class="d-block fw-bold text-dark fs-6"><?= htmlspecialchars($_SESSION['name']) ?></span>
                        <span class="d-block small text-muted"><?= $_SESSION['role'] === 'admin' ? 'Administrateur Système' : 'Consultant' ?></span>
                    </li>
                    
                    <!-- Liens de navigation -->
                    <li>
                        <a class="dropdown-item" href="?action=home">
                            <i class="bi bi-calendar3 text-primary me-2"></i> Mon Planning
                        </a>
                    </li>
                    
                    <?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): ?>
                    <li>
                        <a class="dropdown-item" href="?action=admin">
                            <i class="bi bi-gear-fill text-info me-2"></i> Administration
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <li><hr class="dropdown-divider my-2"></li>
                    
                    <!-- Bouton Déconnexion -->
                    <li class="px-2">
                        <a class="dropdown-item text-danger fw-bold rounded" href="?action=logout">
                            <i class="bi bi-box-arrow-right me-2"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
            
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Conteneur principal de l'application -->
<div class="container-fluid px-4">
