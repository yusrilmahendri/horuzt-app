# FRONTEND INVOICE & PAYMENT IMPLEMENTATION GUIDE

Complete guide untuk membuat halaman invoice/tagihan dan implementasi payment button dengan Midtrans.

**Target Framework:** Angular (with React/Vue variations)
**Backend:** Laravel 10 + Sanctum + Midtrans
**Last Updated:** 2025-11-02

---

## 📋 TABLE OF CONTENTS

1. [UI/UX Requirements](#uiux-requirements)
2. [Angular Implementation (Complete)](#angular-implementation-complete)
3. [React Implementation (Variant)](#react-implementation-variant)
4. [Vue Implementation (Variant)](#vue-implementation-variant)
5. [Payment Flow Testing](#payment-flow-testing)
6. [Troubleshooting](#troubleshooting)

---

## 🎨 UI/UX REQUIREMENTS

### Invoice Page Layout

```
┌────────────────────────────────────────────────────────────┐
│  LOGO                                    [User Menu ▼]      │
├────────────────────────────────────────────────────────────┤
│                                                              │
│  ← Kembali                                                   │
│                                                              │
│  ┌──────────────────────────────────────────────────────┐  │
│  │  INVOICE PEMBAYARAN                                   │  │
│  │                                                        │  │
│  │  Order ID: INV-550e8400-e29b-41d4-a716-446655440000 │  │
│  │  Tanggal: 02 November 2025                           │  │
│  │                                                        │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  DETAIL PAKET                                   │  │  │
│  │  │                                                  │  │  │
│  │  │  📦 Paket Platinum                              │  │  │
│  │  │     Jenis: Premium                              │  │  │
│  │  │     Masa Aktif: 12 bulan                        │  │  │
│  │  │                                                  │  │  │
│  │  │  ✓ Halaman Buku Tamu: Unlimited                │  │  │
│  │  │  ✓ Kirim WhatsApp: Unlimited                   │  │  │
│  │  │  ✓ Bebas Pilih Tema                            │  │  │
│  │  │  ✓ Fitur Kirim Hadiah                          │  │  │
│  │  │  ✓ Import Data Tamu                            │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  ┌────────────────────────────────────────────────┐  │  │
│  │  │  RINCIAN BIAYA                                  │  │  │
│  │  │                                                  │  │  │
│  │  │  Harga Paket                    Rp 299.000     │  │  │
│  │  │  ─────────────────────────────────────────────  │  │  │
│  │  │  Total Pembayaran               Rp 299.000     │  │  │
│  │  └────────────────────────────────────────────────┘  │  │
│  │                                                        │  │
│  │  [STATUS: Menunggu Pembayaran]  🟡                  │  │
│  │                                                        │  │
│  │  ┌──────────────────────────────────────────────┐    │  │
│  │  │  [💳 BAYAR SEKARANG]                          │    │  │
│  │  └──────────────────────────────────────────────┘    │  │
│  │                                                        │  │
│  │  Metode Pembayaran: Kartu Kredit, Transfer Bank,     │  │
│  │                     E-Wallet, dan lainnya             │  │
│  └──────────────────────────────────────────────────────┘  │
│                                                              │
└────────────────────────────────────────────────────────────┘
```

### Payment Status Indicators

| Status | Badge | Color | Icon |
|--------|-------|-------|------|
| `pending` | Menunggu Pembayaran | Yellow/Orange | 🟡 |
| `paid` | Sudah Dibayar | Green | ✅ |
| `failed` | Pembayaran Gagal | Red | ❌ |
| `refunded` | Dana Dikembalikan | Blue | 🔄 |

---

## 🅰️ ANGULAR IMPLEMENTATION (COMPLETE)

### Project Structure

```
src/
├── app/
│   ├── models/
│   │   ├── user.model.ts
│   │   ├── invitation.model.ts
│   │   ├── package.model.ts
│   │   └── payment.model.ts
│   ├── services/
│   │   ├── auth.service.ts
│   │   ├── api.service.ts
│   │   └── midtrans.service.ts
│   ├── components/
│   │   ├── invoice/
│   │   │   ├── invoice.component.ts
│   │   │   ├── invoice.component.html
│   │   │   ├── invoice.component.scss
│   │   │   └── invoice.component.spec.ts
│   │   └── payment-status/
│   │       ├── payment-status.component.ts
│   │       └── payment-status.component.html
│   └── app.module.ts
├── environments/
│   ├── environment.ts
│   └── environment.prod.ts
└── index.html (with Snap.js script)
```

---

### 1. Environment Configuration

```typescript
// src/environments/environment.ts

export const environment = {
  production: false,
  apiUrl: 'https://www.sena-digital.com/api',
  midtrans: {
    clientKey: 'SB-Mid-client-NjshfjUODw5Zt75',
    snapUrl: 'https://app.sandbox.midtrans.com/snap/snap.js'
  }
};

// src/environments/environment.prod.ts

export const environment = {
  production: true,
  apiUrl: 'https://www.sena-digital.com/api',
  midtrans: {
    clientKey: 'Mid-client-xxxxxxxxxxxxx',
    snapUrl: 'https://app.midtrans.com/snap/snap.js'
  }
};
```

---

### 2. Models (TypeScript Interfaces)

```typescript
// src/app/models/package.model.ts

export interface PaketUndangan {
  id: number;
  name_paket: string;
  jenis_paket: 'basic' | 'standard' | 'premium';
  price: number;
  masa_aktif: number;
  halaman_buku: string | number;
  kirim_wa: boolean;
  bebas_pilih_tema: boolean;
  kirim_hadiah: boolean;
  import_data: boolean;
}

// src/app/models/invitation.model.ts

export interface Invitation {
  id: number;
  user_id: number;
  paket_undangan_id: number;
  status: string;
  order_id: string | null;
  midtrans_transaction_id: string | null;
  payment_status: 'pending' | 'paid' | 'failed' | 'refunded';
  domain_expires_at: string | null;
  payment_confirmed_at: string | null;
  package_price_snapshot: string;
  package_duration_snapshot: number;
  created_at: string;
  updated_at: string;
  paket_undangan?: PaketUndangan;
}

// src/app/models/user.model.ts

export interface User {
  id: number;
  name: string;
  email: string;
  phone?: string;
  kode_pemesanan?: string;
  invitation?: Invitation;
}

// src/app/models/payment.model.ts

export interface CreateSnapTokenRequest {
  invitation_id: number;
  amount: number;
  customer_details?: {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
  };
  item_details?: Array<{
    id: string;
    name: string;
    price: number;
    quantity: number;
  }>;
}

export interface SnapTokenResponse {
  success: boolean;
  data: {
    snap_token: string;
    order_id: string;
    gross_amount: number;
    invitation_id: number;
    expires_at: string;
  };
  message: string;
}
```

---

### 3. Services

```typescript
// src/app/services/api.service.ts

import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { User, Invitation, PaketUndangan } from '../models';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  private getHeaders(): HttpHeaders {
    const token = localStorage.getItem('auth_token');
    return new HttpHeaders({
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    });
  }

  /**
   * Get user profile with invitation data
   */
  getUserProfile(): Observable<{ data: User }> {
    return this.http.get<{ data: User }>(
      `${this.apiUrl}/v1/user-profile`,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Get available packages (public endpoint)
   */
  getPackages(): Observable<{ data: PaketUndangan[] }> {
    return this.http.get<{ data: PaketUndangan[] }>(
      `${this.apiUrl}/v1/paket-undangan`,
      { headers: new HttpHeaders({ 'Accept': 'application/json' }) }
    );
  }
}

// src/app/services/midtrans.service.ts

import { Injectable } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';
import { CreateSnapTokenRequest, SnapTokenResponse } from '../models/payment.model';

// Declare snap for TypeScript
declare var snap: any;

@Injectable({
  providedIn: 'root'
})
export class MidtransService {
  private apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  private getHeaders(): HttpHeaders {
    const token = localStorage.getItem('auth_token');
    return new HttpHeaders({
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    });
  }

  /**
   * Create Snap Token from backend
   */
  createSnapToken(payload: CreateSnapTokenRequest): Observable<SnapTokenResponse> {
    return this.http.post<SnapTokenResponse>(
      `${this.apiUrl}/midtrans/create-snap-token`,
      payload,
      { headers: this.getHeaders() }
    );
  }

  /**
   * Open Midtrans Snap Payment Popup
   */
  pay(
    snapToken: string,
    callbacks?: {
      onSuccess?: (result: any) => void;
      onPending?: (result: any) => void;
      onError?: (result: any) => void;
      onClose?: () => void;
    }
  ): void {
    if (typeof snap === 'undefined') {
      console.error('Midtrans Snap.js not loaded! Check index.html');
      alert('Payment system is not ready. Please refresh the page.');
      return;
    }

    snap.pay(snapToken, {
      onSuccess: (result: any) => {
        console.log('✅ Payment success:', result);
        if (callbacks?.onSuccess) callbacks.onSuccess(result);
      },
      onPending: (result: any) => {
        console.log('⏳ Payment pending:', result);
        if (callbacks?.onPending) callbacks.onPending(result);
      },
      onError: (result: any) => {
        console.error('❌ Payment error:', result);
        if (callbacks?.onError) callbacks.onError(result);
      },
      onClose: () => {
        console.log('🚪 Payment popup closed');
        if (callbacks?.onClose) callbacks.onClose();
      }
    });
  }
}
```

---

### 4. Invoice Component

```typescript
// src/app/components/invoice/invoice.component.ts

import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router } from '@angular/router';
import { Subscription, interval } from 'rxjs';
import { switchMap, take } from 'rxjs/operators';
import { ApiService } from '../../services/api.service';
import { MidtransService } from '../../services/midtrans.service';
import { User, Invitation, PaketUndangan } from '../../models';

@Component({
  selector: 'app-invoice',
  templateUrl: './invoice.component.html',
  styleUrls: ['./invoice.component.scss']
})
export class InvoiceComponent implements OnInit, OnDestroy {
  user: User | null = null;
  invitation: Invitation | null = null;
  package: PaketUndangan | null = null;

  isLoading = true;
  isProcessingPayment = false;
  errorMessage = '';

  private subscriptions = new Subscription();

  constructor(
    private apiService: ApiService,
    private midtransService: MidtransService,
    private router: Router
  ) {}

  ngOnInit(): void {
    this.loadInvoiceData();
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  /**
   * Load user profile and invitation data
   */
  loadInvoiceData(): void {
    this.isLoading = true;
    this.errorMessage = '';

    const sub = this.apiService.getUserProfile().subscribe({
      next: (response) => {
        this.user = response.data;
        this.invitation = response.data.invitation || null;
        this.package = this.invitation?.paket_undangan || null;

        if (!this.invitation) {
          this.errorMessage = 'Data undangan tidak ditemukan. Silakan hubungi admin.';
        } else if (this.invitation.payment_status === 'paid') {
          // Already paid, redirect to dashboard
          this.router.navigate(['/dashboard']);
        }

        this.isLoading = false;
      },
      error: (error) => {
        console.error('Failed to load invoice data:', error);
        this.errorMessage = 'Gagal memuat data invoice. Silakan refresh halaman.';
        this.isLoading = false;
      }
    });

    this.subscriptions.add(sub);
  }

  /**
   * Get payment status badge configuration
   */
  getStatusBadge(): { label: string; class: string; icon: string } {
    switch (this.invitation?.payment_status) {
      case 'paid':
        return { label: 'Sudah Dibayar', class: 'badge-success', icon: '✅' };
      case 'failed':
        return { label: 'Pembayaran Gagal', class: 'badge-danger', icon: '❌' };
      case 'refunded':
        return { label: 'Dana Dikembalikan', class: 'badge-info', icon: '🔄' };
      case 'pending':
      default:
        return { label: 'Menunggu Pembayaran', class: 'badge-warning', icon: '🟡' };
    }
  }

  /**
   * Format price to IDR
   */
  formatPrice(price: number): string {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0
    }).format(price);
  }

  /**
   * Handle payment button click
   */
  processPayment(): void {
    if (!this.invitation || !this.package || !this.user) {
      alert('Data tidak lengkap. Silakan refresh halaman.');
      return;
    }

    if (this.isProcessingPayment) return;

    this.isProcessingPayment = true;
    this.errorMessage = '';

    // Prepare customer details
    const nameParts = this.user.name.split(' ');
    const firstName = nameParts[0] || 'Guest';
    const lastName = nameParts.slice(1).join(' ') || '';

    // Prepare payment request payload
    const payload = {
      invitation_id: this.invitation.id,
      amount: this.package.price,
      customer_details: {
        first_name: firstName,
        last_name: lastName,
        email: this.user.email,
        phone: this.user.phone || '08123456789'
      },
      item_details: [
        {
          id: `paket-${this.invitation.paket_undangan_id}`,
          name: this.package.name_paket,
          price: this.package.price,
          quantity: 1
        }
      ]
    };

    // Step 1: Request snap token from backend
    const sub = this.midtransService.createSnapToken(payload).subscribe({
      next: (response) => {
        console.log('✅ Snap token created:', response.data.order_id);

        // Step 2: Open Midtrans Snap popup
        this.midtransService.pay(response.data.snap_token, {
          onSuccess: (result) => {
            console.log('✅ Payment completed:', result);
            this.handlePaymentSuccess(result);
          },
          onPending: (result) => {
            console.log('⏳ Payment pending:', result);
            this.handlePaymentPending(result);
          },
          onError: (result) => {
            console.error('❌ Payment failed:', result);
            this.handlePaymentError(result);
          },
          onClose: () => {
            console.log('🚪 User closed payment popup');
            this.isProcessingPayment = false;
          }
        });
      },
      error: (error) => {
        console.error('❌ Failed to create snap token:', error);
        this.handleApiError(error);
        this.isProcessingPayment = false;
      }
    });

    this.subscriptions.add(sub);
  }

  /**
   * Handle successful payment
   */
  private handlePaymentSuccess(result: any): void {
    console.log('Payment success callback received');

    // Don't trust client-side callback alone!
    // Verify payment status from backend
    this.router.navigate(['/payment-success'], {
      queryParams: { order_id: result.order_id }
    });
  }

  /**
   * Handle pending payment (e.g., bank transfer waiting confirmation)
   */
  private handlePaymentPending(result: any): void {
    console.log('Payment pending callback received');

    this.router.navigate(['/payment-pending'], {
      queryParams: { order_id: result.order_id }
    });
  }

  /**
   * Handle payment error
   */
  private handlePaymentError(result: any): void {
    this.errorMessage = result.status_message || 'Pembayaran gagal. Silakan coba lagi.';
    this.isProcessingPayment = false;

    // Show error for 5 seconds
    setTimeout(() => {
      this.errorMessage = '';
    }, 5000);
  }

  /**
   * Handle API errors (validation, etc)
   */
  private handleApiError(error: any): void {
    console.error('API Error:', error);

    if (error.error?.errors) {
      // Validation errors from backend
      const errors = error.error.errors;
      const errorMessages = Object.values(errors).flat();
      this.errorMessage = errorMessages.join(', ');
    } else if (error.error?.message) {
      this.errorMessage = error.error.message;
    } else if (error.status === 0) {
      this.errorMessage = 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.';
    } else {
      this.errorMessage = 'Gagal memproses pembayaran. Silakan coba lagi.';
    }
  }

  /**
   * Check if payment button should be enabled
   */
  canPay(): boolean {
    return (
      !this.isLoading &&
      !this.isProcessingPayment &&
      this.invitation?.payment_status === 'pending' &&
      !!this.package
    );
  }
}
```

---

### 5. Invoice Template (HTML)

```html
<!-- src/app/components/invoice/invoice.component.html -->

<div class="invoice-container">
  <!-- Loading State -->
  <div *ngIf="isLoading" class="loading-state">
    <div class="spinner"></div>
    <p>Memuat data invoice...</p>
  </div>

  <!-- Error State -->
  <div *ngIf="!isLoading && errorMessage && !invitation" class="error-state">
    <div class="alert alert-danger">
      <strong>⚠️ Error:</strong> {{ errorMessage }}
    </div>
    <button class="btn btn-primary" (click)="loadInvoiceData()">
      🔄 Coba Lagi
    </button>
  </div>

  <!-- Invoice Content -->
  <div *ngIf="!isLoading && invitation && package" class="invoice-content">
    <!-- Back Button -->
    <div class="back-navigation">
      <a routerLink="/dashboard" class="btn-back">
        ← Kembali ke Dashboard
      </a>
    </div>

    <!-- Invoice Card -->
    <div class="invoice-card">
      <!-- Header -->
      <div class="invoice-header">
        <h1>Invoice Pembayaran</h1>
        <div class="invoice-meta">
          <div class="meta-item">
            <span class="label">Order ID:</span>
            <code>{{ invitation.order_id || 'Belum dibuat' }}</code>
          </div>
          <div class="meta-item">
            <span class="label">Tanggal:</span>
            <span>{{ invitation.created_at | date:'dd MMMM yyyy' }}</span>
          </div>
        </div>
      </div>

      <!-- Package Details -->
      <div class="package-section">
        <h2>📦 Detail Paket</h2>
        <div class="package-details">
          <div class="package-name">
            {{ package.name_paket }}
          </div>
          <div class="package-type">
            Jenis: {{ package.jenis_paket | titlecase }}
          </div>
          <div class="package-duration">
            Masa Aktif: {{ package.masa_aktif }} bulan
          </div>

          <!-- Features List -->
          <div class="features-list">
            <h3>Fitur yang Didapat:</h3>
            <ul>
              <li class="feature-item">
                <span class="icon">{{ package.halaman_buku === 'unlimited' || package.halaman_buku > 0 ? '✓' : '✗' }}</span>
                Halaman Buku Tamu: {{ package.halaman_buku }}
              </li>
              <li class="feature-item" [class.disabled]="!package.kirim_wa">
                <span class="icon">{{ package.kirim_wa ? '✓' : '✗' }}</span>
                Kirim WhatsApp: {{ package.kirim_wa ? 'Unlimited' : 'Tidak Tersedia' }}
              </li>
              <li class="feature-item" [class.disabled]="!package.bebas_pilih_tema">
                <span class="icon">{{ package.bebas_pilih_tema ? '✓' : '✗' }}</span>
                Bebas Pilih Tema
              </li>
              <li class="feature-item" [class.disabled]="!package.kirim_hadiah">
                <span class="icon">{{ package.kirim_hadiah ? '✓' : '✗' }}</span>
                Fitur Kirim Hadiah
              </li>
              <li class="feature-item" [class.disabled]="!package.import_data">
                <span class="icon">{{ package.import_data ? '✓' : '✗' }}</span>
                Import Data Tamu
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Price Breakdown -->
      <div class="price-section">
        <h2>💰 Rincian Biaya</h2>
        <div class="price-breakdown">
          <div class="price-row">
            <span class="label">Harga Paket</span>
            <span class="value">{{ formatPrice(package.price) }}</span>
          </div>
          <!-- You can add discounts, taxes, etc here -->
          <div class="price-divider"></div>
          <div class="price-row total">
            <span class="label">Total Pembayaran</span>
            <span class="value">{{ formatPrice(package.price) }}</span>
          </div>
        </div>
      </div>

      <!-- Payment Status -->
      <div class="status-section">
        <div class="status-badge" [ngClass]="getStatusBadge().class">
          <span class="icon">{{ getStatusBadge().icon }}</span>
          <span class="label">{{ getStatusBadge().label }}</span>
        </div>

        <!-- Additional info based on status -->
        <div *ngIf="invitation.payment_status === 'paid'" class="status-info success">
          <p>
            <strong>✅ Pembayaran berhasil dikonfirmasi</strong><br>
            Domain aktif hingga: <strong>{{ invitation.domain_expires_at | date:'dd MMMM yyyy' }}</strong>
          </p>
        </div>

        <div *ngIf="invitation.payment_status === 'pending'" class="status-info warning">
          <p>
            Silakan lanjutkan pembayaran untuk mengaktifkan semua fitur undangan Anda.
          </p>
        </div>

        <div *ngIf="invitation.payment_status === 'failed'" class="status-info error">
          <p>
            Pembayaran sebelumnya gagal. Silakan coba lagi dengan metode pembayaran lain.
          </p>
        </div>
      </div>

      <!-- Payment Button -->
      <div class="payment-action">
        <button
          *ngIf="invitation.payment_status === 'pending' || invitation.payment_status === 'failed'"
          class="btn-pay"
          [disabled]="!canPay()"
          (click)="processPayment()">
          <span *ngIf="!isProcessingPayment">💳 Bayar Sekarang</span>
          <span *ngIf="isProcessingPayment">
            <span class="spinner-small"></span> Memproses...
          </span>
        </button>

        <button
          *ngIf="invitation.payment_status === 'paid'"
          class="btn-dashboard"
          routerLink="/dashboard">
          🏠 Ke Dashboard
        </button>

        <!-- Error Message -->
        <div *ngIf="errorMessage" class="error-message">
          ⚠️ {{ errorMessage }}
        </div>

        <!-- Payment Methods Info -->
        <div class="payment-methods-info">
          <p>Metode pembayaran yang tersedia:</p>
          <div class="payment-methods">
            <span class="method">💳 Kartu Kredit</span>
            <span class="method">🏦 Transfer Bank</span>
            <span class="method">📱 E-Wallet</span>
            <span class="method">🏪 Convenience Store</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
```

---

### 6. Invoice Styles (SCSS)

```scss
// src/app/components/invoice/invoice.component.scss

.invoice-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem 1rem;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

// Loading State
.loading-state {
  text-align: center;
  padding: 3rem;

  .spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
  }

  @keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
}

// Error State
.error-state {
  text-align: center;
  padding: 2rem;

  .alert {
    margin-bottom: 1.5rem;
  }
}

// Back Navigation
.back-navigation {
  margin-bottom: 1.5rem;

  .btn-back {
    display: inline-flex;
    align-items: center;
    color: #3498db;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s;

    &:hover {
      color: #2980b9;
    }
  }
}

// Invoice Card
.invoice-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

// Invoice Header
.invoice-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 2rem;

  h1 {
    margin: 0 0 1rem;
    font-size: 1.75rem;
  }

  .invoice-meta {
    display: flex;
    gap: 2rem;
    flex-wrap: wrap;

    .meta-item {
      .label {
        font-size: 0.875rem;
        opacity: 0.9;
        display: block;
        margin-bottom: 0.25rem;
      }

      code {
        background: rgba(255, 255, 255, 0.2);
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.875rem;
      }
    }
  }
}

// Package Section
.package-section {
  padding: 2rem;
  border-bottom: 1px solid #e0e0e0;

  h2 {
    font-size: 1.25rem;
    margin: 0 0 1rem;
    color: #333;
  }

  .package-details {
    .package-name {
      font-size: 1.5rem;
      font-weight: 600;
      color: #667eea;
      margin-bottom: 0.5rem;
    }

    .package-type,
    .package-duration {
      color: #666;
      margin-bottom: 0.25rem;
    }

    .features-list {
      margin-top: 1.5rem;

      h3 {
        font-size: 1rem;
        margin-bottom: 0.75rem;
        color: #555;
      }

      ul {
        list-style: none;
        padding: 0;
        margin: 0;

        .feature-item {
          padding: 0.5rem 0;
          display: flex;
          align-items: center;

          .icon {
            display: inline-block;
            width: 24px;
            height: 24px;
            background: #4caf50;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 24px;
            margin-right: 0.75rem;
            font-weight: bold;
            flex-shrink: 0;
          }

          &.disabled {
            opacity: 0.5;

            .icon {
              background: #ccc;
            }
          }
        }
      }
    }
  }
}

// Price Section
.price-section {
  padding: 2rem;
  border-bottom: 1px solid #e0e0e0;

  h2 {
    font-size: 1.25rem;
    margin: 0 0 1rem;
    color: #333;
  }

  .price-breakdown {
    .price-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.75rem 0;

      .label {
        font-weight: 500;
        color: #555;
      }

      .value {
        font-weight: 600;
        color: #333;
        font-size: 1.125rem;
      }

      &.total {
        padding-top: 1rem;

        .label,
        .value {
          font-size: 1.25rem;
          color: #667eea;
        }
      }
    }

    .price-divider {
      border-top: 2px solid #e0e0e0;
      margin: 0.5rem 0;
    }
  }
}

// Status Section
.status-section {
  padding: 2rem;
  border-bottom: 1px solid #e0e0e0;

  .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 1rem;

    &.badge-success {
      background: #d4edda;
      color: #155724;
    }

    &.badge-warning {
      background: #fff3cd;
      color: #856404;
    }

    &.badge-danger {
      background: #f8d7da;
      color: #721c24;
    }

    &.badge-info {
      background: #d1ecf1;
      color: #0c5460;
    }
  }

  .status-info {
    margin-top: 1rem;
    padding: 1rem;
    border-radius: 8px;

    p {
      margin: 0;
    }

    &.success {
      background: #d4edda;
      color: #155724;
    }

    &.warning {
      background: #fff3cd;
      color: #856404;
    }

    &.error {
      background: #f8d7da;
      color: #721c24;
    }
  }
}

// Payment Action
.payment-action {
  padding: 2rem;
  text-align: center;

  .btn-pay,
  .btn-dashboard {
    width: 100%;
    max-width: 400px;
    padding: 1rem 2rem;
    font-size: 1.125rem;
    font-weight: 600;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }

  .btn-pay {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;

    &:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
  }

  .btn-dashboard {
    background: #4caf50;
    color: white;

    &:hover {
      background: #45a049;
    }
  }

  .spinner-small {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin-right: 0.5rem;
  }

  .error-message {
    margin-top: 1rem;
    padding: 1rem;
    background: #f8d7da;
    color: #721c24;
    border-radius: 8px;
    font-weight: 500;
  }

  .payment-methods-info {
    margin-top: 2rem;
    color: #666;

    p {
      margin-bottom: 0.75rem;
      font-size: 0.875rem;
    }

    .payment-methods {
      display: flex;
      flex-wrap: wrap;
      gap: 0.5rem;
      justify-content: center;

      .method {
        background: #f5f5f5;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.875rem;
      }
    }
  }
}

// Responsive
@media (max-width: 768px) {
  .invoice-container {
    padding: 1rem 0.5rem;
  }

  .invoice-header {
    padding: 1.5rem;

    h1 {
      font-size: 1.5rem;
    }

    .invoice-meta {
      flex-direction: column;
      gap: 1rem;
    }
  }

  .package-section,
  .price-section,
  .status-section,
  .payment-action {
    padding: 1.5rem;
  }
}
```

---

### 7. Add Snap.js to index.html

```html
<!-- src/index.html -->

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Wedding Invitation App</title>
  <base href="/">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/x-icon" href="favicon.ico">

  <!-- Midtrans Snap.js - SANDBOX -->
  <script
    type="text/javascript"
    src="https://app.sandbox.midtrans.com/snap/snap.js"
    data-client-key="SB-Mid-client-NjshfjUODw5Zt75">
  </script>

  <!-- Production: Change to production URL and client key
  <script
    type="text/javascript"
    src="https://app.midtrans.com/snap/snap.js"
    data-client-key="Mid-client-xxxxxxxxxxxxx">
  </script>
  -->
</head>
<body>
  <app-root></app-root>
</body>
</html>
```

---

### 8. Payment Success Component (Optional)

```typescript
// src/app/components/payment-status/payment-status.component.ts

import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { Subscription, interval } from 'rxjs';
import { switchMap, take } from 'rxjs/operators';
import { ApiService } from '../../services/api.service';

@Component({
  selector: 'app-payment-status',
  template: `
    <div class="status-container">
      <div *ngIf="isChecking" class="checking">
        <div class="spinner"></div>
        <h2>Memverifikasi Pembayaran...</h2>
        <p>Mohon tunggu sebentar</p>
      </div>

      <div *ngIf="!isChecking && status === 'paid'" class="success">
        <div class="icon">✅</div>
        <h2>Pembayaran Berhasil!</h2>
        <p>Terima kasih. Pembayaran Anda telah dikonfirmasi.</p>
        <button class="btn" (click)="goToDashboard()">
          Ke Dashboard
        </button>
      </div>

      <div *ngIf="!isChecking && status === 'pending'" class="pending">
        <div class="icon">⏳</div>
        <h2>Pembayaran Sedang Diproses</h2>
        <p>Kami sedang menunggu konfirmasi dari bank.</p>
        <button class="btn" (click)="checkAgain()">
          Cek Lagi
        </button>
      </div>

      <div *ngIf="!isChecking && status === 'failed'" class="failed">
        <div class="icon">❌</div>
        <h2>Pembayaran Gagal</h2>
        <p>Mohon maaf, pembayaran Anda tidak berhasil.</p>
        <button class="btn" (click)="retry()">
          Coba Lagi
        </button>
      </div>
    </div>
  `,
  styles: [`
    .status-container {
      max-width: 600px;
      margin: 4rem auto;
      padding: 3rem;
      text-align: center;
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .icon {
      font-size: 5rem;
      margin-bottom: 1.5rem;
    }

    h2 {
      font-size: 2rem;
      margin-bottom: 1rem;
      color: #333;
    }

    p {
      color: #666;
      margin-bottom: 2rem;
    }

    .btn {
      padding: 1rem 3rem;
      font-size: 1.125rem;
      background: #667eea;
      color: white;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: all 0.3s;
    }

    .btn:hover {
      background: #5568d3;
      transform: translateY(-2px);
    }

    .spinner {
      width: 60px;
      height: 60px;
      border: 6px solid #f3f3f3;
      border-top: 6px solid #667eea;
      border-radius: 50%;
      animation: spin 1s linear infinite;
      margin: 0 auto 2rem;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  `]
})
export class PaymentStatusComponent implements OnInit, OnDestroy {
  orderId: string | null = null;
  status: string | null = null;
  isChecking = true;

  private subscriptions = new Subscription();
  private maxAttempts = 10; // 30 seconds
  private currentAttempt = 0;

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private apiService: ApiService
  ) {}

  ngOnInit(): void {
    this.orderId = this.route.snapshot.queryParamMap.get('order_id');
    this.pollPaymentStatus();
  }

  ngOnDestroy(): void {
    this.subscriptions.unsubscribe();
  }

  /**
   * Poll payment status from backend
   */
  pollPaymentStatus(): void {
    this.isChecking = true;

    const sub = interval(3000) // every 3 seconds
      .pipe(
        take(this.maxAttempts),
        switchMap(() => this.apiService.getUserProfile())
      )
      .subscribe({
        next: (response) => {
          const paymentStatus = response.data.invitation?.payment_status;
          this.currentAttempt++;

          if (paymentStatus === 'paid') {
            this.status = 'paid';
            this.isChecking = false;
            this.subscriptions.unsubscribe();
          } else if (this.currentAttempt >= this.maxAttempts) {
            // Timeout - still pending
            this.status = paymentStatus || 'pending';
            this.isChecking = false;
          }
        },
        error: (error) => {
          console.error('Failed to check payment status:', error);
          this.isChecking = false;
        }
      });

    this.subscriptions.add(sub);
  }

  checkAgain(): void {
    this.currentAttempt = 0;
    this.pollPaymentStatus();
  }

  goToDashboard(): void {
    this.router.navigate(['/dashboard']);
  }

  retry(): void {
    this.router.navigate(['/invoice']);
  }
}
```

---

## 📘 SIMPLIFIED EXAMPLES

### React Implementation (Brief)

```tsx
// React Hook Component

import { useState, useEffect } from 'react';
import axios from 'axios';

declare var snap: any;

export default function InvoicePage() {
  const [invitation, setInvitation] = useState<any>(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    loadInvoiceData();
  }, []);

  const loadInvoiceData = async () => {
    try {
      const token = localStorage.getItem('auth_token');
      const response = await axios.get('/api/v1/user-profile', {
        headers: { Authorization: `Bearer ${token}` }
      });
      setInvitation(response.data.data.invitation);
    } catch (error) {
      console.error(error);
    }
  };

  const processPayment = async () => {
    setLoading(true);

    try {
      const token = localStorage.getItem('auth_token');
      const response = await axios.post(
        '/api/midtrans/create-snap-token',
        {
          invitation_id: invitation.id,
          amount: invitation.paket_undangan.price
        },
        {
          headers: { Authorization: `Bearer ${token}` }
        }
      );

      snap.pay(response.data.data.snap_token, {
        onSuccess: (result: any) => {
          window.location.href = '/payment-success';
        },
        onError: (result: any) => {
          alert('Payment failed');
          setLoading(false);
        },
        onClose: () => {
          setLoading(false);
        }
      });
    } catch (error) {
      console.error(error);
      setLoading(false);
    }
  };

  return (
    <div>
      {invitation && (
        <div>
          <h1>{invitation.paket_undangan.name_paket}</h1>
          <p>Rp {invitation.paket_undangan.price.toLocaleString('id-ID')}</p>
          <button onClick={processPayment} disabled={loading}>
            {loading ? 'Processing...' : 'Bayar Sekarang'}
          </button>
        </div>
      )}
    </div>
  );
}
```

---

### Vue 3 Implementation (Brief)

```vue
<!-- Vue 3 Composition API -->

<template>
  <div class="invoice">
    <div v-if="invitation">
      <h1>{{ invitation.paket_undangan.name_paket }}</h1>
      <p>Rp {{ formatPrice(invitation.paket_undangan.price) }}</p>
      <button @click="processPayment" :disabled="loading">
        {{ loading ? 'Processing...' : 'Bayar Sekarang' }}
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import axios from 'axios';

declare var snap: any;

const invitation = ref<any>(null);
const loading = ref(false);

onMounted(() => {
  loadInvoiceData();
});

const loadInvoiceData = async () => {
  try {
    const token = localStorage.getItem('auth_token');
    const response = await axios.get('/api/v1/user-profile', {
      headers: { Authorization: `Bearer ${token}` }
    });
    invitation.value = response.data.data.invitation;
  } catch (error) {
    console.error(error);
  }
};

const processPayment = async () => {
  loading.value = true;

  try {
    const token = localStorage.getItem('auth_token');
    const response = await axios.post(
      '/api/midtrans/create-snap-token',
      {
        invitation_id: invitation.value.id,
        amount: invitation.value.paket_undangan.price
      },
      {
        headers: { Authorization: `Bearer ${token}` }
      }
    );

    snap.pay(response.data.data.snap_token, {
      onSuccess: () => {
        window.location.href = '/payment-success';
      },
      onError: () => {
        alert('Payment failed');
        loading.value = false;
      },
      onClose: () => {
        loading.value = false;
      }
    });
  } catch (error) {
    console.error(error);
    loading.value = false;
  }
};

const formatPrice = (price: number) => {
  return price.toLocaleString('id-ID');
};
</script>
```

---

## 🧪 PAYMENT FLOW TESTING

### Local Testing Steps

1. **Start Backend:**
```bash
php artisan serve
```

2. **Login as User:**
```
Email: tas@gmail.com
Password: 123123
```

3. **Navigate to Invoice Page:**
```
http://localhost:4200/invoice
```

4. **Click "Bayar Sekarang":**
- Snap popup should open
- Select Credit Card
- Use test card: `4811 1111 1111 1114`
- CVV: `123`
- OTP: `112233`

5. **Complete Payment:**
- Should redirect to success page
- Check payment status in database

6. **Verify Webhook:**
```bash
# Check Laravel logs
tail -f storage/logs/laravel.log

# Check payment_logs table
SELECT * FROM payment_logs ORDER BY created_at DESC LIMIT 5;

# Check invitation status
SELECT id, payment_status, payment_confirmed_at, domain_expires_at
FROM invitations WHERE id = 5;
```

---

## 🐛 TROUBLESHOOTING

### Issue: "snap is not defined"

**Solution:** Snap.js not loaded. Check:
1. Script tag in index.html
2. Client key is correct
3. No ad blocker blocking Midtrans

### Issue: Payment status not updating

**Solution:** Webhook not configured. Check:
1. Payment Notification URL set in Midtrans dashboard
2. URL is publicly accessible (not localhost)
3. Check webhook logs in payment_logs table

### Issue: Amount validation error

**Solution:** Amount mismatch. Check:
1. Package price in database matches request amount
2. Field name is `price` not `harga`

### Issue: Invalid signature error

**Solution:** Server key mismatch. Check:
1. MIDTRANS_SERVER_KEY in .env matches dashboard
2. Environment (sandbox vs production)

---

## ✅ CHECKLIST BEFORE GO LIVE

Frontend:
- [ ] Snap.js script updated to production URL
- [ ] Client key updated to production
- [ ] API base URL updated to production
- [ ] Error handling implemented
- [ ] Loading states implemented
- [ ] Success/error messages user-friendly
- [ ] Mobile responsive
- [ ] Cross-browser tested

Backend:
- [ ] Payment Notification URL configured in dashboard
- [ ] HTTPS enabled (required for production)
- [ ] Valid SSL certificate
- [ ] Webhook signature verification enabled
- [ ] Payment logs enabled
- [ ] Error monitoring enabled

---

**Ready to code?** Start with Angular implementation above or adapt for React/Vue!

**Questions?** Check `FRONTEND_API_CONTRACT.md` for API details.

**Backend issues?** Check `MIDTRANS_DASHBOARD_SETUP.md` for configuration.
