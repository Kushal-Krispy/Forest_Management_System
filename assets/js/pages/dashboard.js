(function () {
    const d = window.DASHBOARD_DATA || {};
    if (d.categories?.length) {
        new Chart(document.getElementById('incidentChart'), {
            type: 'doughnut',
            data: {
                labels: d.categories,
                datasets: [{ data: d.counts, backgroundColor: ['#ef4444','#f97316','#eab308','#22c55e','#3b82f6','#8b5cf6','#6b7280'] }],
            },
            options: { responsive: true, plugins: { legend: { position: 'right' } } },
        });
    }
})();
