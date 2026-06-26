<?php
// --- CALCUL DES KPI GLOBAUX SUR LA PERIODE AFFICHEE ---

$kpi_total_cap = 0;
foreach($dash_months as $m) {
    $kpi_total_cap += $m['working_days'];
}
// Capacité totale théorique des humains (hors "_virtual_unassigned_")
$kpi_total_cap *= $real_users_count;

$kpi_total_load = 0;
$kpi_total_unassigned = 0;

foreach($dash_months as $m_key => $m_data) {
    foreach($displayUsers as $uid => $uname) {
        $val = $pivot_user_month[$uid][$m_key] ?? 0;
        if ($uid === '_virtual_unassigned_') {
            $kpi_total_unassigned += $val;
        } else {
            $kpi_total_load += $val;
        }
    }
}

$kpi_taux_charge = $kpi_total_cap > 0 ? round(($kpi_total_load / $kpi_total_cap) * 100) : 0;

// Utilisation des données par type déjà calculées pour le graphique
$kpi_build = array_sum($chart_type_month['Fonctionnel'] ?? []);
$kpi_run = array_sum($chart_type_month['Technique'] ?? []);
$kpi_abs = array_sum($chart_type_month['Absences'] ?? []) + array_sum($chart_type_month['Formation'] ?? []);
$kpi_total_demand = $kpi_build + $kpi_run + $kpi_abs + array_sum($chart_type_month['Structure'] ?? []);

$kpi_pct_build = $kpi_total_demand > 0 ? round(($kpi_build / $kpi_total_demand) * 100) : 0;
$kpi_pct_run = $kpi_total_demand > 0 ? round(($kpi_run / $kpi_total_demand) * 100) : 0;

// Couleurs conditionnelles pour le taux de charge (Alerte visuelle)
$charge_color = 'text-success'; // Vert si tout va bien
if ($kpi_taux_charge < 70) $charge_color = 'text-info'; // Bleu si sous-charge
if ($kpi_taux_charge > 95) $charge_color = 'text-warning'; // Orange si tendu
if ($kpi_taux_charge > 100) $charge_color = 'text-danger'; // Rouge si surcharge
?>

<div class="row g-3 mb-4">
    <!-- KPI 1 : Taux d'Occupation -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #3b82f6 !important; border-radius: 8px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 p-3 rounded-circle me-3 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-speedometer2 fs-5"></i>
                </div>
                <div>
                    <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Taux d'Occupation</div>
                    <div class="fs-4 fw-bolder <?= $charge_color ?> lh-1"><?= $kpi_taux_charge ?> %</div>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;"><?= $kpi_total_load ?> JH affectés sur <?= $kpi_total_cap ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 2 : Reste à Planifier -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #f59e0b !important; border-radius: 8px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 p-3 rounded-circle me-3 text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-inbox-fill fs-5"></i>
                </div>
                <div>
                    <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Reste à Planifier</div>
                    <div class="fs-4 fw-bolder text-dark lh-1"><?= $kpi_total_unassigned ?> <span class="fs-6 text-muted fw-normal">JH</span></div>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;">Backlog non affecté</div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 3 : Effort BUILD -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #10b981 !important; border-radius: 8px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="bg-success bg-opacity-10 p-3 rounded-circle me-3 text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                    <i class="bi bi-rocket-takeoff-fill fs-5"></i>
                </div>
                <div>
                    <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Effort Projet (Build)</div>
                    <div class="fs-4 fw-bolder text-dark lh-1"><?= $kpi_pct_build ?> %</div>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;">Fonctionnel (<?= $kpi_build ?> JH)</div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI 4 : Effort RUN -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100" style="border-left: 4px solid #8b5cf6 !important; border-radius: 8px;">
            <div class="card-body p-3 d-flex align-items-center">
                <div class="bg-info bg-opacity-10 p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="color: #8b5cf6; width: 48px; height: 48px;">
                    <i class="bi bi-headset fs-5"></i>
                </div>
                <div>
                    <div class="text-muted fw-bold text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Effort Support (Run)</div>
                    <div class="fs-4 fw-bolder text-dark lh-1"><?= $kpi_pct_run ?> %</div>
                    <div class="text-muted mt-1" style="font-size: 0.7rem;">Technique (<?= $kpi_run ?> JH)</div>
                </div>
            </div>
        </div>
    </div>
</div>
