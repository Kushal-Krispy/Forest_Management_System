(function () {
    const map = L.map('forestMap').setView([42.0, -100.0], 4);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    const cluster = L.markerClusterGroup();
    map.addLayer(cluster);

    const forestIcon = L.divIcon({ className: 'map-marker forest-marker', html: '<i class="fas fa-tree"></i>', iconSize: [30, 30] });
    const wildlifeIcon = L.divIcon({ className: 'map-marker wildlife-marker', html: '<i class="fas fa-paw"></i>', iconSize: [28, 28] });
    const incidentIcon = L.divIcon({ className: 'map-marker incident-marker', html: '<i class="fas fa-exclamation"></i>', iconSize: [28, 28] });
    const statusEl = document.getElementById('mapStatus');
    const deleteBtn = document.getElementById('deleteForestBtn');

    function ratingColor(score) {
        if (score >= 81) return 'var(--success)';
        if (score >= 61) return 'var(--info)';
        if (score >= 41) return 'var(--warning)';
        return 'var(--danger)';
    }

    function setStatus(message, type = 'info') {
        if (!statusEl) return;
        statusEl.textContent = message || '';
        statusEl.dataset.type = type;
    }

    async function loadMap() {
        cluster.clearLayers();
        if (deleteBtn) {
            deleteBtn.classList.add('is-hidden');
            deleteBtn.onclick = null;
        }
        setStatus('');

        const search = document.getElementById('mapSearch').value;
        const forestId = document.getElementById('forestIdSearch').value;
        const layer = document.getElementById('mapLayer').value;
        const incidentType = document.getElementById('incidentType').value;
        const params = new URLSearchParams({ type: layer, search });
        if (incidentType) params.set('incident_type', incidentType);
        if (forestId) params.set('forest_id', forestId);

        const res = await fetch(BASE_URL + '/api/map_data.php?' + params);
        if (!res.ok) {
            setStatus('Map data could not be loaded. Please refresh and try again.', 'error');
            return;
        }
        const data = await res.json();

        const showForests = layer === 'all' || layer === 'forests' || Boolean(forestId) || Boolean(search);

        if (showForests) {
            data.forests.forEach(f => {
                if (!f.latitude || !f.longitude) return;
                const popup = `<div class="map-popup">
                    <h4>${f.forest_name}</h4>
                    <p><strong>ID:</strong> ${f.forest_id}</p>
                    <p><strong>Code:</strong> ${f.forest_code || 'N/A'}</p>
                    <p><strong>Area:</strong> ${f.area_sq_km} sq km</p>
                    <p><strong>Type:</strong> ${f.ecosystem_type}</p>
                    <p><strong>Population:</strong> ${f.animal_population}</p>
                    <p><strong>Incidents:</strong> ${f.incident_count}</p>
                    <p><strong>Health:</strong> <span style="color:${ratingColor(f.health_score)}">${f.health_score}%</span></p>
                    <p><strong>Fire Risk:</strong> ${f.fire_risk_score}%</p>
                </div>`;
                const marker = L.marker([f.latitude, f.longitude], { icon: forestIcon }).bindPopup(popup);
                marker.on('click', () => {
                    document.getElementById('forestIdSearch').value = f.forest_id;
                    if (deleteBtn) {
                        deleteBtn.classList.remove('is-hidden');
                        deleteBtn.onclick = () => deleteForest(f.forest_id, f.forest_name);
                    }
                });
                cluster.addLayer(marker);
            });
        }

        if (layer === 'all' || layer === 'wildlife') {
            data.wildlife.forEach(a => {
                if (!a.latitude || !a.longitude) return;
                const popup = `<div class="map-popup"><h4>${a.common_name}</h4>
                    <p>${a.species_name}</p><p>Forest: ${a.forest_name}</p>
                    <p>Health: ${a.health_status}</p></div>`;
                cluster.addLayer(L.marker([a.latitude, a.longitude], { icon: wildlifeIcon }).bindPopup(popup));
            });
        }

        if (layer === 'all' || layer === 'incidents') {
            data.incidents.forEach(i => {
                if (!i.latitude || !i.longitude) return;
                const popup = `<div class="map-popup"><h4>${i.category}</h4>
                    <p>Severity: ${i.severity}</p><p>Forest: ${i.forest_name || 'N/A'}</p>
                    <p>Date: ${i.incident_date}</p></div>`;
                cluster.addLayer(L.marker([i.latitude, i.longitude], { icon: incidentIcon }).bindPopup(popup));
            });
        }

        if (cluster.getLayers().length) {
            map.fitBounds(cluster.getBounds().pad(0.2));
            if (forestId && data.forests.length) {
                const f = data.forests[0];
                map.setView([f.latitude, f.longitude], Math.max(map.getZoom(), 12));
                setStatus(`Showing forest #${f.forest_id}: ${f.forest_name}`, 'success');
            } else if (search && data.forests.length) {
                setStatus(`Showing ${data.forests.length} matching forest${data.forests.length === 1 ? '' : 's'}.`, 'success');
            }
        } else {
            setStatus('No map results found for the selected filters.', 'warning');
        }
    }

    document.getElementById('mapRefresh').addEventListener('click', loadMap);
    document.getElementById('mapSearch').addEventListener('keyup', e => { if (e.key === 'Enter') loadMap(); });
    document.getElementById('forestIdSearch').addEventListener('keyup', e => { if (e.key === 'Enter') loadMap(); });
    document.getElementById('mapLayer').addEventListener('change', loadMap);
    document.getElementById('incidentType').addEventListener('change', loadMap);
    loadMap();

    async function deleteForest(forestId, forestName) {
        if (!confirm(`Are you sure you want to delete "${forestName}"? This action cannot be undone.`)) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('forest_id', forestId);
            formData.append('_csrf', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

            const res = await fetch(BASE_URL + '/actions/forest_action.php', {
                method: 'POST',
                body: formData
            });

            if (res.ok) {
                alert('Forest deleted successfully.');
                document.getElementById('forestIdSearch').value = '';
                if (deleteBtn) deleteBtn.classList.add('is-hidden');
                loadMap();
            } else {
                alert('Failed to delete forest. Please try again.');
            }
        } catch (error) {
            console.error('Error deleting forest:', error);
            alert('An error occurred while deleting the forest.');
        }
    }
})();
