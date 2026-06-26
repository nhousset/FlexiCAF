<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(getDb(FILE_SETTINGS)['app_name'] ?? 'FlexiCAF') ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background-color: #f1f5f9; 
            color: #1e293b; 
            padding-top: 75px; 
        }
        .navbar { 
            background: linear-gradient(90deg, #1e1b4b 0%, #312e81 100%); 
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); 
            padding: 0.75rem 1rem;
        }
        .navbar-brand { 
            font-weight: 800; 
            letter-spacing: -0.5px; 
        }
        .nav-link {
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }
        .nav-link.active {
            color: #fff !important;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
        }
        .badge-itbm { 
            font-family: monospace; 
            font-size: 0.7rem; 
            background-color: #f8fafc; 
            color: #475569; 
            border: 1px solid #e2e8f0; 
        }
        .shadow-inner { 
            box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06); 
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top">
  <div class="container-fluid">
    <a class="navbar-brand d-flex align-items-center me-4" href="?action=home">
        <div class="bg-white text-indigo rounded me-2 d-flex align-items-center justify-content-center" style="width: 34px; height: 32px; color: #312e81; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <i class="bi bi-layers-fill fs-5"></i>
        </div>
        <span><?= htmlspecialchars(getDb(FILE_SETTINGS)['app_name'] ?? 'FlexiCAF') ?></span>
    </a>
    
    <?php if (isset($_SESSION['user_id'])): ?>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-1">
        <li class="nav-item">
            <a class="nav-link <?= ($_GET['action'] ?? 'home') === 'home' ? 'active' : '' ?>" href="?action=home">
                <i class="bi bi-grid-1x2-fill me-1"></i> Espace de travail
            </a>
        </li>
        <?php if ($_SESSION['role'] === 'admin' || hasPermission('can_manage_tasks')): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($_GET['action'] ?? '') === 'admin' ? 'active' : '' ?>" href="?action=admin">
                    <i class="bi bi-sliders2-vertical me-1"></i> Console d'administration
                </a>
            </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">
                <i class="bi bi-info-circle-fill me-1"></i> À propos
            </a>
        </li>
      </ul>
      
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item">
            <div class="dropdown">
                <button class="btn btn-dark btn-sm dropdown-toggle rounded-pill px-3 py-1.5 fw-bold d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" style="background-color: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.15);">
                    <div class="bg-white text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold text-uppercase" style="width: 22px; height: 22px; font-size: 0.65rem; color: #1e1b4b !important;">
                        <?= mb_strtoupper(mb_substr($_SESSION['name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8') ?>
                    </div>
                    <span><?= htmlspecialchars($_SESSION['name'] ?? 'Utilisateur') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end p-2 mt-2 border-0">
                    <li>
                        <div class="px-3 py-2">
                            <span class="d-block small text-muted text-uppercase fw-bold" style="font-size: 0.6rem; letter-spacing: 0.5px;">Rôle système</span>
                            <span class="badge w-100 mt-1 text-start d-block p-2 text-uppercase font-monospace bg-light text-dark border border-secondary bg-opacity-25" style="font-size: 0.7rem;">
                                <i class="bi bi-shield-lock me-1"></i> <?= $_SESSION['role'] === 'admin' ? 'Administrateur' : 'Collaborateur' ?>
                            </span>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-2"></li>
                    <li>
                        <a class="dropdown-item text-danger fw-bold rounded-2 d-flex align-items-center gap-2" href="?action=logout">
                            <i class="bi bi-box-arrow-right fs-5"></i> Déconnexion
                        </a>
                    </li>
                </ul>
            </div>
        </li>
      </ul>
    </div>
    <?php endif; ?>
  </div>
</nav>

<div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <div class="modal-header text-white border-0 py-3" style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); border-top-left-radius: 12px; border-top-right-radius: 12px;">
        <h6 class="modal-title fw-bold id="aboutModalLabel" style="text-shadow: 0 1px 2px rgba(0,0,0,0.2);"><i class="bi bi-info-circle me-2"></i>À propos de l'application</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4 bg-light">
        <div class="text-center mb-4">
            <div class="bg-indigo text-white rounded-3 d-inline-flex align-items-center justify-content-center mb-2 shadow" style="width: 54px; height: 54px; background-color: #312e81;">
                <i class="bi bi-layers-fill fs-2"></i>
            </div>
            <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars(getDb(FILE_SETTINGS)['app_name'] ?? 'FlexiCAF') ?></h5>
            <span class="badge bg-secondary font-monospace" style="font-size: 0.75rem;">Version 2.4.0 (Stable)</span>
        </div>
        
        <p class="small text-secondary mb-3" style="line-height: 1.5; text-align: justify;">
            <strong>FlexiCAF</strong> est un outil agile conçu pour simplifier le processus de **Capacity Planning** et le suivi des imputations de charge en Jours-Hommes (JH). Son moteur <i>Flat-File</i> ultra-léger élimine le besoin d'infrastructures de bases de données relationnelles lourdes au profit d'une portabilité totale via des structures de fichiers JSON.
        </p>
        
        <div class="bg-white p-3 rounded border mb-0 shadow-sm">
            <h6 class="fw-bold small text-dark mb-2"><i class="bi bi-cpu text-primary me-2"></i>Spécifications Techniques :</h6>
            <ul class="list-unstyled small text-muted mb-0 lh-lg">
                <li><i class="bi bi-check2-short text-success"></i> Framework d'interface : <b>Bootstrap 5</b></li>
                <li><i class="bi bi-check2-short text-success"></i> Moteur graphique : <b>Chart.js (CDN Head)</b></li>
                <li><i class="bi bi-check2-short text-success"></i> Environnement requis : <b>PHP 8.0+ / Conteneurisé</b></li>
            </ul>
        </div>
      </div>
      <div class="modal-footer border-0 bg-light justify-content-center pt-0 pb-3">
        <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold shadow-sm" data-bs-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<div class="container-fluid px-4 py-3">
