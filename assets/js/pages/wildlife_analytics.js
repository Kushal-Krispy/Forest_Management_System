(function () {
    let birthDeathChart;

    async function load() {
        const forestId = document.getElementById('forestSelect').value;
        if (!forestId) {
            document.getElementById('currentPop').textContent = '--';
            document.getElementById('totalBirths').textContent = '--';
            document.getElementById('totalDeaths').textContent = '--';
            if (birthDeathChart) birthDeathChart.destroy();
            return;
        }

        const params = new URLSearchParams({ action: 'forest_population', forest_id: forestId });
        const res = await fetch(BASE_URL + '/api/analytics.php?' + params);
        const data = await res.json();

        document.getElementById('currentPop').textContent = data.current_population ?? 0;
        document.getElementById('totalBirths').textContent = data.total_births ?? 0;
        document.getElementById('totalDeaths').textContent = data.total_deaths ?? 0;

        if (birthDeathChart) birthDeathChart.destroy();
        birthDeathChart = new Chart(document.getElementById('birthDeathChart'), {
            type: 'bar',
            data: {
                labels: ['Birth', 'Death'],
                datasets: [{
                    label: 'Animal Count',
                    data: [data.total_births ?? 0, data.total_deaths ?? 0],
                    backgroundColor: ['#22c55e', '#ef4444'],
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Animal Count' },
                    },
                    x: {
                        title: { display: true, text: 'Event Type' },
                    },
                },
            },
        });
    }

    document.getElementById('loadAnalytics').addEventListener('click', load);
})();
