# Frontend Implementation Guide - Buku Tamu & Komentar API

Panduan step-by-step implementasi API untuk frontend developer dalam mengintegrasikan fitur Buku Tamu dan Komentar pada aplikasi undangan pernikahan digital.

**Version:** 1.0.0  
**Last Updated:** 5 Februari 2026

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Implementation Priority](#implementation-priority)
3. [Step-by-Step Integration](#step-by-step-integration)
4. [API Contracts](#api-contracts)
5. [Frontend Integration Examples](#frontend-integration-examples)
6. [Error Handling](#error-handling)
7. [Testing Checklist](#testing-checklist)

---

## Overview

Terdapat **3 fitur utama** yang perlu diimplementasikan:

### 1. Buku Tamu (Guest Book)
Fitur untuk tamu undangan memberikan ucapan, konfirmasi kehadiran, dan jumlah tamu yang hadir.

### 2. Komentar (Comments)
Fitur komentar sederhana untuk undangan (nama + komentar saja).

### 3. Wedding Profile Enhancement
Peningkatan response wedding profile dengan eager loading komentars.

---

## Implementation Priority

Urutan implementasi yang disarankan:

### Phase 1: Public Guest Features (Week 1)
```
1. Buku Tamu - Public Display ✓
2. Buku Tamu - Public Submit Form ✓
3. Buku Tamu - Public Statistics ✓
4. Komentar - Public Display ✓
5. Komentar - Public Submit Form ✓
```

### Phase 2: User Dashboard (Week 2)
```
6. Buku Tamu - User Dashboard List ✓
7. Buku Tamu - User Statistics Dashboard ✓
8. Buku Tamu - Moderation (Approve/Hide) ✓
9. Buku Tamu - Delete Actions ✓
10. Buku Tamu - Export Data ✓
```

### Phase 3: Admin Dashboard (Week 3)
```
11. Buku Tamu - Admin Dashboard List ✓
12. Buku Tamu - Admin Global Statistics ✓
13. Buku Tamu - Admin Bulk Actions ✓
14. Buku Tamu - Admin Delete by User ✓
```

### Phase 4: Enhancements (Week 4)
```
15. Wedding Profile - Load with Komentars ✓
16. Real-time Updates (Optional)
17. Performance Optimization
18. Analytics & Reporting
```

---

## Step-by-Step Integration

### STEP 1: Setup localStorage untuk user_id

**Kapan:** Setelah user membuat undangan atau login

**Di mana:** Setelah sukses create invitation atau login page

```javascript
// Simpan user_id ke localStorage setelah create invitation
const saveUserIdToLocalStorage = (userId) => {
  localStorage.setItem('wedding_user_id', userId.toString());
};

// Ambil user_id dari localStorage
const getUserIdFromLocalStorage = () => {
  return parseInt(localStorage.getItem('wedding_user_id'));
};

// Contoh implementasi setelah create invitation success
const handleInvitationCreated = (invitationData) => {
  saveUserIdToLocalStorage(invitationData.user_id);
  // ... redirect atau action lainnya
};
```

**⚠️ Penting:** `user_id` ini akan digunakan di semua public API (Buku Tamu & Komentar)

---

### STEP 2: Implementasi Buku Tamu Public Display

**Page:** Public Invitation Page (undangan publik)

**API:** `GET /v1/buku-tamu`

**Query Parameters:**
- `user_id` (required): dari localStorage
- `limit` (optional): default 50
- `status` (optional): filter by `hadir`, `tidak_hadir`, `ragu`

#### 2.1 Component: BukuTamuList

```javascript
// components/BukuTamu/BukuTamuList.jsx
import { useState, useEffect } from 'react';

const BukuTamuList = () => {
  const [entries, setEntries] = useState([]);
  const [loading, setLoading] = useState(true);
  const [statistics, setStatistics] = useState(null);
  const [filter, setFilter] = useState('all'); // all, hadir, tidak_hadir, ragu
  
  const userId = getUserIdFromLocalStorage();

  useEffect(() => {
    fetchBukuTamu();
  }, [filter]);

  const fetchBukuTamu = async () => {
    try {
      setLoading(true);
      const params = new URLSearchParams({
        user_id: userId,
        limit: 50
      });
      
      if (filter !== 'all') {
        params.append('status', filter);
      }

      const response = await fetch(`/api/v1/buku-tamu?${params}`);
      const data = await response.json();

      if (response.ok) {
        setEntries(data.data);
        // Statistics sudah include di response
      }
    } catch (error) {
      console.error('Error fetching buku tamu:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="buku-tamu-section">
      <h2>Buku Tamu & Ucapan</h2>
      
      {/* Filter */}
      <div className="filter-buttons">
        <button onClick={() => setFilter('all')}>Semua</button>
        <button onClick={() => setFilter('hadir')}>Hadir</button>
        <button onClick={() => setFilter('tidak_hadir')}>Tidak Hadir</button>
        <button onClick={() => setFilter('ragu')}>Masih Ragu</button>
      </div>

      {/* Loading State */}
      {loading && <LoadingSkeleton />}

      {/* Entry List */}
      {!loading && entries.map(entry => (
        <BukuTamuCard key={entry.id} entry={entry} />
      ))}
    </div>
  );
};
```

#### 2.2 Component: BukuTamuCard

```javascript
// components/BukuTamu/BukuTamuCard.jsx
const BukuTamuCard = ({ entry }) => {
  const getStatusBadge = (status) => {
    const badges = {
      'hadir': { color: 'green', label: 'Hadir' },
      'tidak_hadir': { color: 'red', label: 'Tidak Hadir' },
      'ragu': { color: 'yellow', label: 'Masih Ragu' }
    };
    return badges[status] || badges['ragu'];
  };

  const statusBadge = getStatusBadge(entry.status_kehadiran);

  return (
    <div className="buku-tamu-card">
      <div className="card-header">
        <div className="avatar">{entry.nama.charAt(0).toUpperCase()}</div>
        <div className="info">
          <h4>{entry.nama}</h4>
          <div className="status-line">
            <span className={`badge ${statusBadge.color}`}>
              {statusBadge.label}
            </span>
            {entry.jumlah_tamu > 0 && (
              <span className="guest-count">({entry.jumlah_tamu} orang)</span>
            )}
            <span className="timestamp">{entry.created_at_human}</span>
          </div>
        </div>
      </div>
      
      {entry.ucapan && (
        <div className="card-body">
          <p>{entry.ucapan}</p>
        </div>
      )}
    </div>
  );
};
```

---

### STEP 3: Implementasi Buku Tamu Statistics

**API:** `GET /v1/buku-tamu/statistics`

#### 3.1 Component: BukuTamuStatistics

```javascript
// components/BukuTamu/BukuTamuStatistics.jsx
const BukuTamuStatistics = () => {
  const [stats, setStats] = useState(null);
  const userId = getUserIdFromLocalStorage();

  useEffect(() => {
    fetchStatistics();
  }, []);

  const fetchStatistics = async () => {
    try {
      const response = await fetch(
        `/api/v1/buku-tamu/statistics?user_id=${userId}`
      );
      const data = await response.json();
      
      if (response.ok) {
        setStats(data.data);
      }
    } catch (error) {
      console.error('Error fetching statistics:', error);
    }
  };

  if (!stats) return null;

  return (
    <div className="statistics-grid">
      <StatCard 
        label="Hadir" 
        value={stats.total_hadir}
        percentage={stats.percentage_hadir}
        color="green"
      />
      <StatCard 
        label="Tidak Hadir" 
        value={stats.total_tidak_hadir}
        percentage={stats.percentage_tidak_hadir}
        color="red"
      />
      <StatCard 
        label="Masih Ragu" 
        value={stats.total_ragu}
        percentage={stats.percentage_ragu}
        color="yellow"
      />
      <StatCard 
        label="Total Tamu Hadir" 
        value={stats.total_tamu_hadir}
        sublabel="orang"
        color="blue"
      />
    </div>
  );
};

const StatCard = ({ label, value, percentage, sublabel, color }) => (
  <div className={`stat-card ${color}`}>
    <div className="stat-value">{value}</div>
    <div className="stat-label">{label}</div>
    {percentage && <div className="stat-percentage">({percentage}%)</div>}
    {sublabel && <div className="stat-sublabel">{sublabel}</div>}
  </div>
);
```

---

### STEP 4: Implementasi Buku Tamu Submit Form

**API:** `POST /v1/buku-tamu`

**Validation Rules:**
- `nama`: required, min 2, max 100
- `email`: optional, valid email, max 100
- `telepon`: optional, max 20
- `ucapan`: optional, min 5, max 1000
- `status_kehadiran`: required, enum (hadir, tidak_hadir, ragu)
- `jumlah_tamu`: optional, min 1, max 20 (required jika hadir)

#### 4.1 Component: BukuTamuForm

```javascript
// components/BukuTamu/BukuTamuForm.jsx
import { useState } from 'react';

const BukuTamuForm = ({ onSuccess }) => {
  const [formData, setFormData] = useState({
    nama: '',
    email: '',
    telepon: '',
    ucapan: '',
    status_kehadiran: '',
    jumlah_tamu: 1
  });
  
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  
  const userId = getUserIdFromLocalStorage();

  const handleChange = (e) => {
    const { name, value } = e.target;
    setFormData(prev => ({
      ...prev,
      [name]: value
    }));
    
    // Clear error for this field
    if (errors[name]) {
      setErrors(prev => ({ ...prev, [name]: null }));
    }
  };

  const validate = () => {
    const newErrors = {};
    
    if (!formData.nama || formData.nama.trim().length < 2) {
      newErrors.nama = 'Nama wajib diisi (minimal 2 karakter)';
    }
    
    if (formData.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.email)) {
      newErrors.email = 'Format email tidak valid';
    }
    
    if (!formData.status_kehadiran) {
      newErrors.status_kehadiran = 'Status kehadiran wajib dipilih';
    }
    
    if (formData.ucapan && formData.ucapan.length < 5) {
      newErrors.ucapan = 'Ucapan minimal 5 karakter';
    }
    
    if (formData.status_kehadiran === 'hadir' && !formData.jumlah_tamu) {
      newErrors.jumlah_tamu = 'Jumlah tamu wajib diisi jika hadir';
    }
    
    return newErrors;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    const validationErrors = validate();
    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }
    
    try {
      setSubmitting(true);
      
      const payload = {
        user_id: userId,
        ...formData
      };
      
      const response = await fetch('/api/v1/buku-tamu', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
      });
      
      const data = await response.json();
      
      if (response.ok) {
        // Success
        showToast('Ucapan berhasil disimpan!', 'success');
        
        // Reset form
        setFormData({
          nama: '',
          email: '',
          telepon: '',
          ucapan: '',
          status_kehadiran: '',
          jumlah_tamu: 1
        });
        
        // Callback to parent (refresh list)
        if (onSuccess) onSuccess(data.data);
        
      } else if (response.status === 422) {
        // Validation errors from server
        setErrors(data.errors);
      } else {
        showToast(data.message || 'Terjadi kesalahan', 'error');
      }
      
    } catch (error) {
      console.error('Submit error:', error);
      showToast('Koneksi bermasalah. Coba lagi.', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="buku-tamu-form">
      <h3>Berikan Ucapan & Konfirmasi Kehadiran</h3>
      
      {/* Nama */}
      <div className="form-group">
        <label>Nama Lengkap *</label>
        <input
          type="text"
          name="nama"
          value={formData.nama}
          onChange={handleChange}
          placeholder="Masukkan nama lengkap"
          className={errors.nama ? 'error' : ''}
        />
        {errors.nama && <span className="error-message">{errors.nama}</span>}
      </div>
      
      {/* Email */}
      <div className="form-group">
        <label>Email (Opsional)</label>
        <input
          type="email"
          name="email"
          value={formData.email}
          onChange={handleChange}
          placeholder="email@example.com"
          className={errors.email ? 'error' : ''}
        />
        {errors.email && <span className="error-message">{errors.email}</span>}
      </div>
      
      {/* Telepon */}
      <div className="form-group">
        <label>Nomor Telepon (Opsional)</label>
        <input
          type="tel"
          name="telepon"
          value={formData.telepon}
          onChange={handleChange}
          placeholder="08xxxxxxxxxx"
        />
      </div>
      
      {/* Status Kehadiran */}
      <div className="form-group">
        <label>Konfirmasi Kehadiran *</label>
        <div className="radio-group">
          <label className="radio-label">
            <input
              type="radio"
              name="status_kehadiran"
              value="hadir"
              checked={formData.status_kehadiran === 'hadir'}
              onChange={handleChange}
            />
            Hadir
          </label>
          <label className="radio-label">
            <input
              type="radio"
              name="status_kehadiran"
              value="tidak_hadir"
              checked={formData.status_kehadiran === 'tidak_hadir'}
              onChange={handleChange}
            />
            Tidak Hadir
          </label>
          <label className="radio-label">
            <input
              type="radio"
              name="status_kehadiran"
              value="ragu"
              checked={formData.status_kehadiran === 'ragu'}
              onChange={handleChange}
            />
            Masih Ragu
          </label>
        </div>
        {errors.status_kehadiran && (
          <span className="error-message">{errors.status_kehadiran}</span>
        )}
      </div>
      
      {/* Jumlah Tamu (conditional) */}
      {formData.status_kehadiran === 'hadir' && (
        <div className="form-group">
          <label>Jumlah Tamu *</label>
          <input
            type="number"
            name="jumlah_tamu"
            value={formData.jumlah_tamu}
            onChange={handleChange}
            min="1"
            max="20"
            className={errors.jumlah_tamu ? 'error' : ''}
          />
          {errors.jumlah_tamu && (
            <span className="error-message">{errors.jumlah_tamu}</span>
          )}
        </div>
      )}
      
      {/* Ucapan */}
      <div className="form-group">
        <label>Ucapan & Doa *</label>
        <textarea
          name="ucapan"
          value={formData.ucapan}
          onChange={handleChange}
          placeholder="Tulis ucapan dan doa untuk kedua mempelai..."
          rows="4"
          maxLength="1000"
          className={errors.ucapan ? 'error' : ''}
        />
        <div className="char-counter">
          {formData.ucapan.length}/1000
        </div>
        {errors.ucapan && <span className="error-message">{errors.ucapan}</span>}
      </div>
      
      {/* Submit Button */}
      <button 
        type="submit" 
        className="btn-submit"
        disabled={submitting}
      >
        {submitting ? 'MENGIRIM...' : 'KIRIM UCAPAN'}
      </button>
    </form>
  );
};
```

---

### STEP 5: Implementasi Komentar Public Display & Submit

**API:** 
- `GET /v1/komentars` - List
- `POST /v1/komentars` - Submit
- `GET /v1/komentars/statistics` - Stats

#### 5.1 Component: KomentarSection

```javascript
// components/Komentar/KomentarSection.jsx
const KomentarSection = () => {
  const [komentars, setKomentars] = useState([]);
  const [statistics, setStatistics] = useState(null);
  const [page, setPage] = useState(1);
  const [hasMore, setHasMore] = useState(true);
  
  const userId = getUserIdFromLocalStorage();

  useEffect(() => {
    fetchKomentars();
    fetchStatistics();
  }, [page]);

  const fetchKomentars = async () => {
    try {
      const response = await fetch(
        `/api/v1/komentars?user_id=${userId}&page=${page}&per_page=10`
      );
      const data = await response.json();
      
      if (response.ok) {
        setKomentars(prev => [...prev, ...data.data]);
        setHasMore(data.meta.current_page < data.meta.last_page);
      }
    } catch (error) {
      console.error('Error fetching komentars:', error);
    }
  };

  const fetchStatistics = async () => {
    try {
      const response = await fetch(
        `/api/v1/komentars/statistics?user_id=${userId}`
      );
      const data = await response.json();
      
      if (response.ok) {
        setStatistics(data.data);
      }
    } catch (error) {
      console.error('Error fetching statistics:', error);
    }
  };

  const handleNewKomentar = (newKomentar) => {
    // Prepend new comment
    setKomentars(prev => [newKomentar, ...prev]);
    
    // Update statistics
    if (statistics) {
      setStatistics(prev => ({
        ...prev,
        total_komentars: prev.total_komentars + 1
      }));
    }
  };

  return (
    <div className="komentar-section">
      <h2>Komentar ({statistics?.total_komentars || 0})</h2>
      
      {/* Submit Form */}
      <KomentarForm onSuccess={handleNewKomentar} />
      
      {/* Komentar List */}
      <div className="komentar-list">
        {komentars.map(komentar => (
          <KomentarCard key={komentar.id} komentar={komentar} />
        ))}
      </div>
      
      {/* Load More */}
      {hasMore && (
        <button onClick={() => setPage(p => p + 1)}>
          Muat Lebih Banyak
        </button>
      )}
    </div>
  );
};
```

#### 5.2 Component: KomentarForm

```javascript
// components/Komentar/KomentarForm.jsx
const KomentarForm = ({ onSuccess }) => {
  const [formData, setFormData] = useState({
    nama: '',
    komentar: ''
  });
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);
  
  const userId = getUserIdFromLocalStorage();

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    // Validation
    const newErrors = {};
    if (!formData.nama || formData.nama.length < 2) {
      newErrors.nama = 'Nama minimal 2 karakter';
    }
    if (!formData.komentar || formData.komentar.length < 5) {
      newErrors.komentar = 'Komentar minimal 5 karakter';
    }
    
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    
    try {
      setSubmitting(true);
      
      const response = await fetch('/api/v1/komentars', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          user_id: userId,
          ...formData
        })
      });
      
      const data = await response.json();
      
      if (response.ok) {
        showToast('Komentar berhasil dikirim!', 'success');
        setFormData({ nama: '', komentar: '' });
        if (onSuccess) onSuccess(data.data);
      } else if (response.status === 429) {
        showToast('Terlalu banyak komentar. Coba lagi nanti.', 'error');
      } else {
        showToast(data.message, 'error');
      }
      
    } catch (error) {
      showToast('Koneksi bermasalah', 'error');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="komentar-form">
      <input
        type="text"
        placeholder="Nama Anda"
        value={formData.nama}
        onChange={(e) => setFormData(prev => ({ ...prev, nama: e.target.value }))}
        className={errors.nama ? 'error' : ''}
      />
      {errors.nama && <span className="error-message">{errors.nama}</span>}
      
      <textarea
        placeholder="Tulis komentar..."
        value={formData.komentar}
        onChange={(e) => setFormData(prev => ({ ...prev, komentar: e.target.value }))}
        maxLength="500"
        className={errors.komentar ? 'error' : ''}
      />
      {errors.komentar && <span className="error-message">{errors.komentar}</span>}
      
      <button type="submit" disabled={submitting}>
        {submitting ? 'Mengirim...' : 'Kirim Komentar'}
      </button>
    </form>
  );
};
```

---

### STEP 6: User Dashboard - Buku Tamu Management

**API:** `GET /v1/user/result-bukutamu` (Requires Authentication)

#### 6.1 Component: BukuTamuDashboard

```javascript
// pages/dashboard/BukuTamuDashboard.jsx
import { useState, useEffect } from 'react';

const BukuTamuDashboard = () => {
  const [entries, setEntries] = useState([]);
  const [statistics, setStatistics] = useState(null);
  const [loading, setLoading] = useState(true);
  const [pagination, setPagination] = useState({});
  
  const [filters, setFilters] = useState({
    search: '',
    status: '',
    sort_by: 'created_at',
    sort_order: 'desc',
    limit: 15
  });

  useEffect(() => {
    fetchData();
  }, [filters]);

  const fetchData = async () => {
    try {
      setLoading(true);
      
      const params = new URLSearchParams(filters);
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`/api/v1/user/result-bukutamu?${params}`, {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        }
      });
      
      const data = await response.json();
      
      if (response.ok) {
        setEntries(data.data);
        setPagination(data.pagination);
        setStatistics(data.statistics);
      }
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setLoading(false);
    }
  };

  const handleDelete = async (id) => {
    if (!confirm('Yakin ingin menghapus data ini?')) return;
    
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`/api/v1/user/buku-tamu/${id}`, {
        method: 'DELETE',
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      
      if (response.ok) {
        showToast('Data berhasil dihapus', 'success');
        fetchData(); // Refresh
      }
    } catch (error) {
      showToast('Gagal menghapus data', 'error');
    }
  };

  const handleToggleApproval = async (id, currentStatus) => {
    try {
      const token = localStorage.getItem('auth_token');
      
      const response = await fetch(`/api/v1/user/buku-tamu/${id}/approval`, {
        method: 'PATCH',
        headers: {
          'Authorization': `Bearer ${token}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          is_approved: !currentStatus
        })
      });
      
      if (response.ok) {
        showToast(
          !currentStatus ? 'Ucapan disetujui' : 'Ucapan disembunyikan',
          'success'
        );
        fetchData(); // Refresh
      }
    } catch (error) {
      showToast('Gagal mengubah status', 'error');
    }
  };

  return (
    <div className="dashboard-container">
      <h1>Buku Tamu</h1>
      
      {/* Statistics Cards */}
      {statistics && (
        <div className="stats-grid">
          <StatCard label="Total" value={statistics.total_entries} />
          <StatCard 
            label="Hadir" 
            value={statistics.total_hadir} 
            percentage={statistics.percentage_hadir}
          />
          <StatCard 
            label="Tidak Hadir" 
            value={statistics.total_tidak_hadir}
            percentage={statistics.percentage_tidak_hadir}
          />
          <StatCard 
            label="Ragu" 
            value={statistics.total_ragu}
            percentage={statistics.percentage_ragu}
          />
        </div>
      )}
      
      {/* Filters */}
      <div className="filters">
        <input
          type="text"
          placeholder="Cari nama, email, ucapan..."
          value={filters.search}
          onChange={(e) => setFilters(prev => ({ ...prev, search: e.target.value }))}
        />
        
        <select
          value={filters.status}
          onChange={(e) => setFilters(prev => ({ ...prev, status: e.target.value }))}
        >
          <option value="">Semua Status</option>
          <option value="hadir">Hadir</option>
          <option value="tidak_hadir">Tidak Hadir</option>
          <option value="ragu">Masih Ragu</option>
        </select>
      </div>
      
      {/* Data Table */}
      <table className="data-table">
        <thead>
          <tr>
            <th>Nama</th>
            <th>Email</th>
            <th>Status</th>
            <th>Jumlah Tamu</th>
            <th>Ucapan</th>
            <th>Waktu</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          {loading ? (
            <tr><td colSpan="7">Loading...</td></tr>
          ) : entries.length === 0 ? (
            <tr><td colSpan="7">Belum ada data</td></tr>
          ) : (
            entries.map(entry => (
              <tr key={entry.id}>
                <td>{entry.nama}</td>
                <td>{entry.email || '-'}</td>
                <td>
                  <span className={`badge ${entry.status_kehadiran}`}>
                    {entry.status_kehadiran_label}
                  </span>
                </td>
                <td>{entry.jumlah_tamu || '-'}</td>
                <td>{entry.ucapan?.substring(0, 50)}...</td>
                <td>{entry.created_at_human}</td>
                <td>
                  <button 
                    onClick={() => handleToggleApproval(entry.id, entry.is_approved)}
                  >
                    {entry.is_approved ? 'Sembunyikan' : 'Setujui'}
                  </button>
                  <button 
                    onClick={() => handleDelete(entry.id)}
                    className="btn-delete"
                  >
                    Hapus
                  </button>
                </td>
              </tr>
            ))
          )}
        </tbody>
      </table>
      
      {/* Pagination */}
      {pagination && (
        <div className="pagination">
          <span>
            Showing {pagination.from} - {pagination.to} of {pagination.total}
          </span>
        </div>
      )}
    </div>
  );
};
```

---

## API Contracts

### 1. Buku Tamu APIs

#### 1.1 GET Buku Tamu List (Public)

**Endpoint:** `GET /api/v1/buku-tamu`

**Query Parameters:**

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| user_id   | int    | Yes*     | Wedding owner user ID                    |
| domain    | string | Yes*     | Wedding domain/slug (alternative)        |
| limit     | int    | No       | Items per page. Default: 50              |
| status    | string | No       | Filter by: hadir, tidak_hadir, ragu      |

*Either user_id or domain is required

**Response (200):**
```json
{
  "status": 200,
  "message": "string",
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "nama": "Budi Santoso",
      "email": "budi@example.com",
      "telepon": "08123456789",
      "ucapan": "Barakallah!",
      "status_kehadiran": "hadir",
      "status_kehadiran_label": "Hadir",
      "jumlah_tamu": 2,
      "is_approved": true,
      "created_at": "2026-02-05T10:30:00Z",
      "updated_at": "2026-02-05T10:30:00Z",
      "created_at_human": "2 jam yang lalu"
    }
  ],
  "pagination": {
    "total": 125,
    "per_page": 50,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

#### 1.2 POST Buku Tamu Entry (Public)

**Endpoint:** `POST /api/v1/buku-tamu`

**Request Body:**
```json
{
  "user_id": 123,
  "nama": "Budi Santoso",
  "email": "budi@example.com",
  "telepon": "08123456789",
  "ucapan": "Selamat menempuh hidup baru!",
  "status_kehadiran": "hadir",
  "jumlah_tamu": 2
}
```

**Validation:**
- `nama`: required, min:2, max:100
- `email`: nullable, email, max:100
- `telepon`: nullable, max:20
- `ucapan`: nullable, min:5, max:1000
- `status_kehadiran`: required, enum (hadir, tidak_hadir, ragu)
- `jumlah_tamu`: nullable, min:1, max:20

**Response (201):**
```json
{
  "status": 201,
  "message": "Ucapan dan konfirmasi kehadiran berhasil disimpan.",
  "data": {
    "id": 1,
    "user_id": 123,
    "nama": "Budi Santoso",
    "status_kehadiran": "hadir",
    "status_kehadiran_label": "Hadir",
    "jumlah_tamu": 2,
    "created_at": "2026-02-05T10:30:00Z"
  }
}
```

**Error Response (422):**
```json
{
  "status": 422,
  "message": "Validation failed",
  "errors": {
    "nama": ["Nama wajib diisi."],
    "status_kehadiran": ["Status kehadiran wajib dipilih."]
  }
}
```

---

#### 1.3 GET Buku Tamu Statistics (Public)

**Endpoint:** `GET /api/v1/buku-tamu/statistics`

**Query Parameters:**

| Parameter | Type   | Required | Description                        |
|-----------|--------|----------|------------------------------------|
| user_id   | int    | Yes*     | Wedding owner user ID              |
| domain    | string | Yes*     | Wedding domain/slug (alternative)  |

**Response (200):**
```json
{
  "status": 200,
  "message": "Statistik buku tamu berhasil diambil.",
  "data": {
    "total_entries": 125,
    "total_hadir": 87,
    "total_tidak_hadir": 23,
    "total_ragu": 15,
    "total_tamu_hadir": 145,
    "percentage_hadir": 69.6,
    "percentage_tidak_hadir": 18.4,
    "percentage_ragu": 12.0
  }
}
```

---

#### 1.4 GET Buku Tamu List (User Dashboard)

**Endpoint:** `GET /api/v1/user/result-bukutamu`

**Authentication:** Required (Bearer Token)

**Query Parameters:**

| Parameter  | Type   | Required | Description                         |
|------------|--------|----------|-------------------------------------|
| limit      | int    | No       | Items per page. Default: 15         |
| search     | string | No       | Search by nama, email, or ucapan    |
| status     | string | No       | Filter by: hadir, tidak_hadir, ragu |
| sort_by    | string | No       | Sort field                          |
| sort_order | string | No       | Sort direction: asc, desc           |

**Response (200):**
```json
{
  "data": [...],
  "pagination": {...},
  "statistics": {
    "total_entries": 125,
    "total_hadir": 87,
    "total_tidak_hadir": 23,
    "total_ragu": 15,
    "total_tamu_hadir": 145,
    "today_entries": 12,
    "approved_entries": 120,
    "pending_entries": 5,
    "percentage_hadir": 69.6,
    "percentage_tidak_hadir": 18.4,
    "percentage_ragu": 12.0
  }
}
```

---

#### 1.5 PATCH Update Approval Status (User)

**Endpoint:** `PATCH /api/v1/user/buku-tamu/{id}/approval`

**Authentication:** Required

**Request Body:**
```json
{
  "is_approved": true
}
```

**Response (200):**
```json
{
  "status": 200,
  "message": "Ucapan berhasil disetujui.",
  "data": {...}
}
```

---

#### 1.6 DELETE Single Entry (User)

**Endpoint:** `DELETE /api/v1/user/buku-tamu/{id}`

**Authentication:** Required

**Response (200):**
```json
{
  "status": 200,
  "message": "Data buku tamu berhasil dihapus."
}
```

---

#### 1.7 DELETE All Entries (User)

**Endpoint:** `DELETE /api/v1/user/buku-tamu/delete-all`

**Authentication:** Required

**Response (200):**
```json
{
  "status": 200,
  "message": "Semua data buku tamu berhasil dihapus (125 data).",
  "data": {
    "deleted_count": 125
  }
}
```

---

### 2. Komentar APIs

#### 2.1 GET Komentars List (Public)

**Endpoint:** `GET /api/v1/komentars`

**Query Parameters:**

| Parameter | Type   | Required | Description                   |
|-----------|--------|----------|-------------------------------|
| user_id   | int    | Yes*     | Wedding owner user ID         |
| domain    | string | Yes*     | Wedding domain (alternative)  |
| page      | int    | No       | Page number, default 1        |
| per_page  | int    | No       | Items per page, default 20    |

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "nama": "John Doe",
      "komentar": "Selamat ya!",
      "created_at": "2026-01-11 11:04:56"
    }
  ],
  "meta": {
    "total": 50,
    "current_page": 1,
    "per_page": 20,
    "last_page": 3
  }
}
```

---

#### 2.2 POST Create Komentar (Public)

**Endpoint:** `POST /api/v1/komentars`

**Request Body:**
```json
{
  "user_id": 1,
  "nama": "John Doe",
  "komentar": "Selamat ya! Bahagia selalu!"
}
```

**Validation:**
- `nama`: required, min:2, max:255
- `komentar`: required, min:5, max:500

**Response (201):**
```json
{
  "message": "Komentar berhasil disimpan!",
  "data": {
    "id": 1,
    "nama": "John Doe",
    "komentar": "Selamat ya! Bahagia selalu!",
    "created_at": "2026-01-11 11:04:56"
  }
}
```

**Error (429 - Rate Limit):**
```json
{
  "message": "Too many comments submitted. Please try again later (limit: 10 per hour)."
}
```

---

#### 2.3 GET Komentar Statistics (Public)

**Endpoint:** `GET /api/v1/komentars/statistics`

**Query Parameters:**

| Parameter | Type   | Required | Description |
|-----------|--------|----------|-------------|
| user_id   | int    | Yes*     | User ID     |
| domain    | string | Yes*     | Domain      |

**Response (200):**
```json
{
  "data": {
    "domain": "test-wedding",
    "total_komentars": 50
  }
}
```

---

### 3. Wedding Profile API Enhancement

#### 3.1 GET Wedding Profile (Authenticated User)

**Endpoint:** `GET /api/v1/wedding-profile`

**Authentication:** Required

**Response (200):**
```json
{
  "data": {
    "user": {...},
    "mempelai": {...},
    "invitation_package": {...},
    "events": [...],
    "stories": [...],
    "quotes": [...],
    "gallery": [...],
    "bank_accounts": [...],
    "settings": {...},
    "filter_undangan": {...},
    "guest_wishes": [...],
    "guest_book": [...],
    "komentars": [
      {
        "id": 1,
        "nama": "John Doe",
        "komentar": "Selamat!",
        "created_at": "2026-02-05 10:30:00"
      }
    ],
    "testimonials": [...],
    "themes": {...},
    "metadata": {
      "total_komentars": 50
    }
  }
}
```

---

## Frontend Integration Examples

### Complete Public Invitation Page

```javascript
// pages/PublicInvitationPage.jsx
import BukuTamuSection from '@/components/BukuTamu/BukuTamuSection';
import KomentarSection from '@/components/Komentar/KomentarSection';

const PublicInvitationPage = () => {
  const userId = getUserIdFromLocalStorage();
  
  // Load wedding profile
  const { data: weddingProfile, loading } = useWeddingProfile(userId);
  
  if (loading) return <LoadingScreen />;
  
  return (
    <div className="invitation-page">
      {/* Hero Section */}
      <HeroSection profile={weddingProfile} />
      
      {/* Mempelai Section */}
      <MempelaiSection data={weddingProfile.mempelai} />
      
      {/* Events Section */}
      <EventsSection events={weddingProfile.events} />
      
      {/* Gallery Section */}
      <GallerySection gallery={weddingProfile.gallery} />
      
      {/* Buku Tamu Section */}
      <BukuTamuSection userId={userId} />
      
      {/* Komentar Section */}
      <KomentarSection userId={userId} />
      
      {/* Footer */}
      <Footer />
    </div>
  );
};
```

---

## Error Handling

### Common Error Scenarios

#### 1. Network Error
```javascript
const handleNetworkError = (error) => {
  showToast('Koneksi bermasalah. Coba lagi.', 'error');
  console.error('Network error:', error);
};
```

#### 2. Validation Error (422)
```javascript
if (response.status === 422) {
  const data = await response.json();
  setErrors(data.errors);
}
```

#### 3. Rate Limit (429)
```javascript
if (response.status === 429) {
  showToast('Terlalu banyak permintaan. Coba lagi nanti.', 'error');
}
```

#### 4. Unauthorized (401)
```javascript
if (response.status === 401) {
  localStorage.removeItem('auth_token');
  window.location.href = '/login';
}
```

---

## Testing Checklist

### Public Features
- [ ] Display buku tamu list dengan pagination
- [ ] Filter by status kehadiran (hadir, tidak_hadir, ragu)
- [ ] Display statistics card dengan data real-time
- [ ] Submit buku tamu form dengan validation
- [ ] Character counter di textarea ucapan
- [ ] Conditional jumlah_tamu field (only if hadir)
- [ ] Success toast after submit
- [ ] Form reset after success submit
- [ ] Display komentars dengan pagination
- [ ] Submit komentar form
- [ ] Load more komentars

### User Dashboard
- [ ] Login required untuk akses dashboard
- [ ] Display statistics cards
- [ ] Search by nama/email/ucapan
- [ ] Filter by status kehadiran
- [ ] Sort by column (nama, created_at, status)
- [ ] Toggle approval status
- [ ] Delete single entry dengan confirmation
- [ ] Delete all entries dengan confirmation
- [ ] Pagination navigation
- [ ] Export to CSV/JSON

### Admin Dashboard
- [ ] Admin role required
- [ ] Display global statistics
- [ ] Filter by user
- [ ] Bulk approval
- [ ] Bulk delete
- [ ] Delete by user
- [ ] View IP address (admin only)

### Error Handling
- [ ] Network error toast
- [ ] Validation error display
- [ ] Rate limit toast
- [ ] 401 redirect to login
- [ ] 404 not found handling

---

## Recommendations

### Performance Optimization
1. Implement debounce untuk search input (300ms)
2. Use pagination dengan lazy loading
3. Cache API responses dengan React Query atau SWR
4. Optimize image loading (lazy load avatars)

### User Experience
1. Tambahkan loading skeletons
2. Implement optimistic UI updates
3. Add confirmation modals untuk destructive actions
4. Show toast notifications untuk semua actions
5. Implement real-time updates dengan WebSocket (optional)

### Security
1. Sanitize input untuk XSS prevention
2. Rate limiting di client-side juga (prevent spam clicks)
3. Validate file uploads (jika ada)
4. Implement CSRF token

---

## Support & Documentation

- **API Documentation:** `/docs/api/buku-tamu-api.md`
- **Komentar API:** `/docs/api/komentar-api.md`
- **UI Specification:** `/docs/ui/buku-tamu-ui-spec.md`

---

**Selamat mengimplementasikan! 🚀**
