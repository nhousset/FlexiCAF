<?php
$chartDatasetsType = [];
$chartDatasetsUser = [];

// Dataset commun (La Ligne de Capacité Globale)
$capData = [];
foreach($dash_months as $m_key => $m_data) {
    $capData[] = $m_data['working_days'] * $real_users_count;
}
$capDataset = [
    'type' => 'line',
    'label' => 'Capacité de l\'équipe',
    'data' => $capData,
    'borderColor' => '#ef4444', 
    'backgroundColor' => '#ef4444',
    'borderWidth' => 3, // Plus épais
    'fill' => false,
    'tension' => 0.4, // Courbe adoucie
    'pointRadius' => 5,
    'pointBackgroundColor' => '#ffffff',
    'pointBorderColor' => '#ef4444',
    'pointBorderWidth' => 2,
    'pointHoverRadius' => 7,
    'order' => 0 
];

$chartDatasetsType[] = $capDataset;
$chartDatasetsUser[] = $capDataset;

// Données : VUE PAR TYPE (Global)
foreach($chart_type_month as $type => $monthsData) {
    if(array_sum($monthsData) > 0) {
        $chartDatasetsType[] = [
            'type' => 'bar',
            'label' => $type,
            'data' => array_values($monthsData),
            'backgroundColor' => $typeColors[$type] ?? '#cbd5e1', 
            'borderColor' => 'rgba(0,0,0,0.05)',
            'borderWidth' => 1,
            'borderRadius' => 6, // Barres arrondies (plus moderne)
            'order' => 1
        ];
    }
}

// Données : VUE PAR CONSULTANT (Global)
$cIndex = 0;
foreach($displayUsers as $uid => $uname) {
    if (isset($pivot_user_month[$uid]) && array_sum($pivot_user_month[$uid]) > 0) {
        $chartDatasetsUser[] = [
            'type' => 'bar',
            'label' => $uname,
            'data' => array_values($pivot_user_month[$uid]),
            'backgroundColor' => $userColors[$cIndex % count($userColors)], 
            'borderColor' => 'rgba(0,0,0,0.05)',
            'borderWidth' => 1,
            'borderRadius' => 6,
            'order' => 1
        ];
        $cIndex++;
    }
}
?>

<div class="d-flex flex-column flex-md-row mb-4 bg-white rounded shadow-sm border overflow-hidden">
    <div class="bg-light border-end d-flex flex-column justify-content-center p-4" style="width: 25%; min-width: 220px;">
        <h6 class="fw-bold text-dark mb-2"><i class="bi bi-bar-chart-fill text-primary me-2"></i>Vue Globale</h6>
        <p class="small text-muted mb-4" style="line-height: 1.4;">Répartition de l'effort et capacité de l'équipe.</p>
        
        <div class="btn-group-vertical w-100 shadow-sm" role="group">
            <input type="radio" class="btn-check" name="chartToggle" id="chartType" autocomplete="off" checked onchange="updateChartView('type')">
            <label class="btn btn-outline-primary btn-sm fw-bold text-start p-2" for="chartType"><i class="bi bi-layers me-2"></i>Par Type</label>

            <input type="radio" class="btn-check" name="chartToggle" id="chartUser" autocomplete="off" onchange="updateChartView('user')">
            <label class="btn btn-outline-primary btn-sm fw-bold text-start p-2" for="chartUser"><i class="bi bi-people me-2"></i>Par Consultant</label>
        </div>
    </div>
    
    <div class="p-3" style="width: 75%;">
        <div style="height: 280px; width: 100%;">
            <canvas id="capacityChart"></canvas>
        </div>
    </div>
</div>

<script>
window.chartDatasetsType = <?= json_encode($chartDatasetsType) ?>;
window.chartDatasetsUser = <?= json_encode($chartDatasetsUser) ?>;
window.capacityChartInstance = null;

document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('capacityChart');
    if (ctx) {
        window.capacityChartInstance = new Chart(ctx.getContext('2d'), {
            data: {
                labels: <?= json_encode(array_column($dash_months, 'label')) ?>,
                datasets: window.chartDatasetsType
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 10, bottom: 10 } },
                scales: {
                    x: { 
                        stacked: true,
                        grid: { display: false } // Retire les lignes verticales pour épurer
                    },
                    y: { 
                        stacked: true, 
                        beginAtZero: true, 
                        title: { display: false },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            borderDash: [5, 5] // Lignes horizontales en pointillés
                        },
                        border: { display: false }
                    }
                },
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: { usePointStyle: true, boxWidth: 8, font: { family: "'Plus Jakarta Sans', sans-serif", weight: '600' } }
                    },
                    tooltip: { 
                        mode: 'index', 
                        intersect: false,
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: { size: 13, family: "'Plus Jakarta Sans', sans-serif" },
                        bodyFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }
});

function updateChartView(viewType) {
    if (window.capacityChartInstance) {
        if (viewType === 'user') {
            window.capacityChartInstance.data.datasets = window.chartDatasetsUser;
        } else {
            window.capacityChartInstance.data.datasets = window.chartDatasetsType;
        }
        window.capacityChartInstance.update();
    }
}
</script>
