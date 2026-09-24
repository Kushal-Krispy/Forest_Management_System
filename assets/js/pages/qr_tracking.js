(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;

    async function lookup(qr) {
        const res = await fetch(BASE_URL + '/api/qr_scan.php?qr=' + encodeURIComponent(qr));
        const data = await res.json();
        const el = document.getElementById('qrResult');
        if (data.animal) {
            const a = data.animal;
            let scansHtml = '';
            if (a.recent_scans && a.recent_scans.length > 0) {
                scansHtml = '<div style="margin-top:10px;padding-top:10px;border-top:1px solid #ddd;"><strong>Recent Updates:</strong><ul style="margin:5px 0;padding-left:20px;font-size:0.85rem;">';
                a.recent_scans.forEach(s => {
                    if (s.scan_data) {
                        scansHtml += `<li><em>${s.scan_type}</em>: ${s.scan_data} (${s.scanned_at})</li>`;
                    }
                });
                scansHtml += '</ul></div>';
            }
            el.innerHTML = `<div class="alert alert-success">
                <strong>${a.common_name}</strong> (${a.species_name})<br>
                Forest: ${a.forest_name} | Health: ${a.health_status}<br>
                QR: ${a.qr_code}
                ${scansHtml}
            </div>`;
            document.getElementById('scanAnimalId').value = a.animal_id;
            document.getElementById('healthStatus').value = a.health_status;
        } else {
            el.innerHTML = '<div class="alert alert-danger">Animal not found</div>';
        }
    }

    document.getElementById('qrLookup').addEventListener('click', () => {
        lookup(document.getElementById('qrInput').value);
    });

    document.querySelectorAll('.select-animal').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('qrInput').value = btn.dataset.qr;
            document.getElementById('scanAnimalId').value = btn.dataset.id;
            lookup(btn.dataset.qr);
        });
    });

    document.getElementById('scanForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = {
            animal_id: document.getElementById('scanAnimalId').value,
            scan_type: document.getElementById('scanType').value,
            health_status: document.getElementById('healthStatus').value,
            notes: document.getElementById('scanNotes').value,
        };
        const res = await fetch(BASE_URL + '/api/qr_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (data.success) {
            Swal.fire('Success', data.message, 'success');
        } else {
            Swal.fire('Error', data.error || 'Failed', 'error');
        }
    });
})();
