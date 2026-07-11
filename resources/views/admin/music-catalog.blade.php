<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Local Admin Music Catalog</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 24px; background: #f8fafc; color: #1f2937; }
        .card { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin-bottom: 16px; }
        .row { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
        input[type="text"], input[type="password"], input[type="number"], input[type="file"] {
            border: 1px solid #d1d5db; border-radius: 6px; padding: 8px; min-width: 220px;
        }
        button { border: 1px solid #9ca3af; background: #fff; border-radius: 6px; padding: 8px 10px; cursor: pointer; }
        button.primary { background: #111827; border-color: #111827; color: #fff; }
        button.warn { background: #fef2f2; border-color: #ef4444; color: #b91c1c; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; background: #fff; }
        th, td { border: 1px solid #e5e7eb; padding: 10px; text-align: left; font-size: 14px; }
        th { background: #f9fafb; }
        .muted { color: #6b7280; font-size: 12px; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; }
        .on { background: #dcfce7; color: #166534; }
        .off { background: #f3f4f6; color: #374151; }
        .default { background: #dbeafe; color: #1e40af; }
        .actions { display: flex; flex-wrap: wrap; gap: 6px; }
        #status { white-space: pre-wrap; font-size: 13px; }
    </style>
</head>
<body>
    <h2>Admin Music Catalog (Local)</h2>

    <div class="card">
        <div class="row">
            <label for="token"><strong>Bearer Token Admin</strong></label>
            <input id="token" type="password" placeholder="Paste token Sanctum admin">
            <button id="loadBtn" class="primary">Load Catalog</button>
        </div>
        <p class="muted">Endpoint: <code>/api/v1/admin/music-tracks</code></p>
    </div>

    <div class="card">
        <h3>Upload Lagu Katalog</h3>
        <div class="row">
            <input id="title" type="text" placeholder="Judul lagu">
            <input id="artist" type="text" placeholder="Artis (opsional)">
            <input id="musicFile" type="file" accept=".mp3,.wav,.ogg,.m4a,audio/*">
            <label><input id="isDefault" type="checkbox"> Jadikan default</label>
            <button id="uploadBtn" class="primary">Upload</button>
        </div>
    </div>

    <div class="card">
        <div class="row">
            <h3 style="margin: 0;">Daftar Lagu Katalog Admin</h3>
            <button id="saveOrderBtn">Simpan Urutan</button>
        </div>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Artist</th>
                    <th>Status</th>
                    <th>Default</th>
                    <th>Sort</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="trackTableBody"></tbody>
        </table>
    </div>

    <div class="card">
        <strong>Status:</strong>
        <div id="status" class="muted">Ready.</div>
    </div>

    <script>
        let tracks = [];

        const el = (id) => document.getElementById(id);
        const setStatus = (msg) => { el('status').textContent = msg; };

        const api = async (url, options = {}) => {
            const token = el('token').value.trim();
            const headers = options.headers || {};
            if (token) headers['Authorization'] = `Bearer ${token}`;
            options.headers = headers;

            const res = await fetch(url, options);
            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = data?.message || `HTTP ${res.status}`;
                throw new Error(msg);
            }
            return data;
        };

        const renderTable = () => {
            const tbody = el('trackTableBody');
            tbody.innerHTML = '';
            tracks.forEach((track, index) => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${index + 1}</td>
                    <td>
                        <div><strong>${track.title || '-'}</strong></div>
                        <div class="muted">${track.stream_url || track.audio_url || '-'}</div>
                    </td>
                    <td>${track.artist || '-'}</td>
                    <td><span class="badge ${track.is_active ? 'on' : 'off'}">${track.is_active ? 'aktif' : 'nonaktif'}</span></td>
                    <td>${track.is_default ? '<span class="badge default">default</span>' : '-'}</td>
                    <td>${track.sort_order ?? '-'}</td>
                    <td>
                        <div class="actions">
                            <button data-action="up" data-id="${track.id}">Up</button>
                            <button data-action="down" data-id="${track.id}">Down</button>
                            <button data-action="toggle" data-id="${track.id}">${track.is_active ? 'Nonaktifkan' : 'Aktifkan'}</button>
                            <button data-action="default" data-id="${track.id}">Jadikan Default</button>
                            <button data-action="delete" data-id="${track.id}" class="warn">Hapus</button>
                        </div>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        };

        const loadCatalog = async () => {
            setStatus('Memuat katalog...');
            const payload = await api('/api/v1/admin/music-tracks');
            tracks = payload?.data || [];
            renderTable();
            setStatus(`Katalog dimuat (${tracks.length} lagu).`);
        };

        const uploadTrack = async () => {
            const file = el('musicFile').files[0];
            const title = el('title').value.trim();
            const artist = el('artist').value.trim();
            const isDefault = el('isDefault').checked;

            if (!file || !title) {
                setStatus('Judul dan file musik wajib diisi.');
                return;
            }

            const formData = new FormData();
            formData.append('musik', file);
            formData.append('title', title);
            if (artist) formData.append('artist', artist);
            if (isDefault) formData.append('is_default', '1');

            setStatus('Mengunggah lagu...');
            await api('/api/v1/admin/music-tracks', {
                method: 'POST',
                body: formData,
            });

            el('title').value = '';
            el('artist').value = '';
            el('musicFile').value = '';
            el('isDefault').checked = false;
            await loadCatalog();
            setStatus('Upload lagu berhasil.');
        };

        const toggleActive = async (id) => {
            await api(`/api/v1/admin/music-tracks/${id}/toggle-active`, { method: 'PATCH' });
            await loadCatalog();
            setStatus(`Status lagu ${id} diperbarui.`);
        };

        const setDefault = async (id) => {
            await api(`/api/v1/admin/music-tracks/${id}/set-default`, { method: 'PATCH' });
            await loadCatalog();
            setStatus(`Lagu ${id} dijadikan default.`);
        };

        const deleteTrack = async (id) => {
            if (!window.confirm(`Hapus lagu ID ${id}?`)) return;
            await api(`/api/v1/admin/music-tracks/${id}`, { method: 'DELETE' });
            await loadCatalog();
            setStatus(`Lagu ${id} dihapus.`);
        };

        const moveTrack = (id, direction) => {
            const index = tracks.findIndex((t) => t.id === id);
            if (index < 0) return;
            const targetIndex = direction === 'up' ? index - 1 : index + 1;
            if (targetIndex < 0 || targetIndex >= tracks.length) return;
            [tracks[index], tracks[targetIndex]] = [tracks[targetIndex], tracks[index]];
            renderTable();
            setStatus('Urutan lokal berubah. Klik "Simpan Urutan".');
        };

        const saveOrder = async () => {
            const trackIds = tracks.map((track) => track.id);
            await api('/api/v1/admin/music-tracks/reorder', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ track_ids: trackIds }),
            });
            await loadCatalog();
            setStatus('Urutan lagu berhasil disimpan.');
        };

        el('loadBtn').addEventListener('click', async () => {
            try { await loadCatalog(); } catch (e) { setStatus(`Gagal memuat katalog: ${e.message}`); }
        });

        el('uploadBtn').addEventListener('click', async () => {
            try { await uploadTrack(); } catch (e) { setStatus(`Gagal upload: ${e.message}`); }
        });

        el('saveOrderBtn').addEventListener('click', async () => {
            try { await saveOrder(); } catch (e) { setStatus(`Gagal simpan urutan: ${e.message}`); }
        });

        el('trackTableBody').addEventListener('click', async (event) => {
            const button = event.target.closest('button[data-action]');
            if (!button) return;
            const id = Number(button.dataset.id);
            const action = button.dataset.action;

            try {
                if (action === 'up') moveTrack(id, 'up');
                if (action === 'down') moveTrack(id, 'down');
                if (action === 'toggle') await toggleActive(id);
                if (action === 'default') await setDefault(id);
                if (action === 'delete') await deleteTrack(id);
            } catch (e) {
                setStatus(`Aksi gagal: ${e.message}`);
            }
        });
    </script>
</body>
</html>
