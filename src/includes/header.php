<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(getDb(FILE_SETTINGS)['app_name'] ?? 'FlexiCAF') ?></title>
    
    <!-- Polices -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- CSS Bootstrap et Icones -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <!-- JS : Bootstrap Bundle et Chart.js (chargés ici pour être dispos globalement) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #f1f5f9; color: #1e293b; padding-top: 70px; }
        .navbar { background: linear-gradient(90deg, #1e1b4b 0%, #312e81 100%); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .navbar-brand { font-weight: 800; letter-spacing: -0.5px; }
        .badge-itbm { font-family: monospace; font-size: 0.7rem; background-color: #f8fafc; color: #475569; border: 1px solid #e2e8f0; }
        .shadow-inner { box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06); }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center" href="?action=home">
        <div class="bg-white text-indigo rounded me-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; color: #312e81;">
            <i class="bi bi-layers-fill fs-5"></i>
        </div>
        <?= htmlspecialchars(getDb(FILE_SETTINGS)['app_name'] ?? 'FlexiCAF') ?>
    </a>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): ?>
            <li class="nav-item me-2">
                <a class="nav-link <?= $_GET['action'] === 'admin' ? 'active fw-bold' : '' ?>" href="?action=admin"><i class="bi bi-gear-fill"></i> Administration</a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <div class="dropdown">
                <button class="btn btn-dark btn-sm dropdown-toggle rounded-pill px-3 py-1 fw-bold" type="button" data-bs-toggle="dropdown" style="background-color: rgba(255,255,255,0.1); border-color: rgba(255,255,255,0.2);">
                    <div class="bg-white text-dark rounded-circle d-inline-flex align-items-center justify-content-center me-1" style="width: 20px; height: 20px; font-size: 0.6rem;">
                        <?= mb_strtoupper(mb_substr($_SESSION['name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </div>
                    <?= htmlspecialchars($_SESSION['name'] ?? '') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                    <li><h6 class="dropdown-header text-uppercase small" style="font-size: 0.65rem;">Profil connecté</h6></li>
                    <li><a class="dropdown-item text-danger fw-bold" href="?action=logout"><i class="bi bi-box-arrow-right"></i> Déconnexion</a></li>
                </ul>
            </div>
        </li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</nav>

<div class="container-fluid px-4 py-3">
