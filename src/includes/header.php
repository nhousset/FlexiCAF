<?php
// Récupération sécurisée du nom de l'équipe depuis les paramètres
$appSettings = [];
if (function_exists('getDb') && defined('FILE_SETTINGS') && file_exists(FILE_SETTINGS)) {
    $appSettings = getDb(FILE_SETTINGS);
}

// Si un nom est défini on l'utilise, sinon on garde FlexiCAF par défaut
$appName = !empty($appSettings['app_name']) ? htmlspecialchars($appSettings['app_name']) : 'FlexiCAF';

// =========================================================================
// CALCULS DES DATES POUR LE "À PROPOS"
// =========================================================================

// 1. Version : Date du fichier PHP le plus récent
$phpFiles = array_merge(
    glob(__DIR__ . '/../*.php') ?: [], 
    glob(__DIR__ . '/*.php') ?: [], 
    glob(__DIR__ . '/../views/*.php') ?: []
);
$maxMtimePhp = 0;
foreach ($phpFiles as $f) {
    if (file_exists($f)) {
        $m = filemtime($f);
        if ($m > $maxMtimePhp) $maxMtimePhp = $m;
    }
}
$versionDate = $maxMtimePhp > 0 ? date('d/m/Y', $maxMtimePhp) : 'Inconnue';

// 2. Données : Date de modification de data.json
$dataDate = (defined('FILE_DATA') && file_exists(FILE_DATA)) ? date('d/m/Y à H:i', filemtime(FILE_DATA)) : 'Inconnue';
// =========================================================================
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?> - Capacité À Faire</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="css/style.css?v=<?= time() ?>" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): 
    // Récupération de l'initiale pour l'avatar
    $initial = mb_strtoupper(mb_substr($_SESSION['name'], 0, 1, 'UTF-8'), 'UTF-8');
?>
<nav class="navbar navbar-expand-lg navbar-custom mb-4">
    <div class="container-fluid px-4">
        
        <a class="navbar-brand d-flex align-items-center text-decoration-none" href="?action=home">
            <span class="brand-logo-icon"><i class="bi bi-layers-fill"></i></span>
            <span class="brand-logo-text"><?= $appName ?></span>
        </a>

        <div class="d-flex align-items-center ms-auto">
            
            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle user-dropdown-toggle" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <div class="avatar-circle"><?= $initial ?></div>
                    <span class="ms-2 fw-bold d-none d-sm-inline"><?= htmlspecialchars($_SESSION['name']) ?></span>
                </a>
                
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-custom mt-2" aria-labelledby="userDropdown">
                    
                    <li class="px-4 py-2 border-bottom mb-2">
                        <span class="d-block fw-bold text-dark fs-6"><?= htmlspecialchars($_SESSION['name']) ?></span>
                        <span class="d-block small text-muted"><?= $_SESSION['role'] === 'admin' ? 'Administrateur Système' : 'Consultant' ?></span>
                    </li>
                    
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

                    <li>
                        <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">
                            <i class="bi bi-info-circle text-secondary me-2"></i> À propos
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider my-2"></li>
                    
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

<div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-header text-white" style="background: var(--primary-grad);">
        <h6 class="modal-title fw-bold" id="aboutModalLabel"><i class="bi bi-info-circle-fill me-2"></i> À propos</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fermer"></button>
      </div>
      <div class="modal-body text-center p-4">
        <div class="mb-3">
            <span class="brand-logo-icon" style="font-size: 2rem; padding: 10px 16px;"><i class="bi bi-layers-fill"></i></span>
        </div>
        <h5 class="fw-bold mb-1">FlexiCAF</h5>
        <p class="text-muted small mb-3">Outil de Pilotage et de Capacity Planning.</p>
        
        <div class="bg-light rounded p-2 mb-0 border text-start">
            <span class="d-block small text-dark"><i class="bi bi-code-slash me-1"></i> Version du : <strong><?= $versionDate ?></strong></span>
            <span class="d-block small text-dark mt-1"><i class="bi bi-database me-1"></i> Planning MAJ : <strong><?= $dataDate ?></strong></span>
            <hr class="my-2">
            <span class="d-block small fw-bold text-primary text-center">&copy; <?= date('Y') ?> Nicolas Housset</span>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="container-fluid px-4">
