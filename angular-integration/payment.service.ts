/**
 * Payment Service
 *
 * Handles payment operations including:
 * - Creating Midtrans Snap tokens
 * - Checking payment status
 *
 * Usage:
 * 1. Copy this file to: src/app/services/payment.service.ts
 * 2. Import in your module or component
 * 3. Inject and use in your payment component
 */

import { Injectable } from '@angular/core';
import { HttpClient, HttpParams, HttpErrorResponse } from '@angular/common/http';
import { Observable, throwError } from 'rxjs';
import { catchError, retry } from 'rxjs/operators';
import { environment } from '../../environments/environment';

// ============================================================================
// INTERFACES
// ============================================================================

export interface SnapTokenRequest {
  invitation_id: number;
  amount: number;
  customer_details?: {
    first_name: string;
    last_name?: string;
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

export interface PaymentStatusResponse {
  success: boolean;
  payment_status: 'paid' | 'pending' | 'failed' | 'refunded';
  transaction_status?: string;
  message: string;
  data: {
    order_id: string;
    transaction_id?: string;
    payment_confirmed_at?: string;
    domain_expires_at?: string;
  };
}

export interface ErrorResponse {
  success: false;
  message: string;
  errors?: { [key: string]: string[] };
}

// ============================================================================
// SERVICE
// ============================================================================

@Injectable({
  providedIn: 'root'
})
export class PaymentService {
  private readonly apiUrl = environment.apiUrl;

  constructor(private http: HttpClient) {}

  /**
   * Create Snap Token
   *
   * Generates a Midtrans Snap token for payment processing
   *
   * @param userId - User ID performing the payment
   * @param request - Payment request details
   * @returns Observable with snap token response
   *
   * @example
   * this.paymentService.createSnapToken(userId, {
   *   invitation_id: 1,
   *   amount: 299000,
   *   customer_details: {
   *     first_name: 'John',
   *     email: 'john@example.com',
   *     phone: '+6281234567890'
   *   }
   * }).subscribe(response => {
   *   console.log('Snap token:', response.data.snap_token);
   * });
   */
  createSnapToken(userId: number, request: SnapTokenRequest): Observable<SnapTokenResponse> {
    const params = new HttpParams().set('user_id', userId.toString());

    return this.http.post<SnapTokenResponse>(
      `${this.apiUrl}/midtrans/create-snap-token`,
      request,
      { params }
    ).pipe(
      retry(1), // Retry once on failure
      catchError(this.handleError)
    );
  }

  /**
   * Check Payment Status
   *
   * Verifies payment status from Midtrans and updates database
   *
   * @param orderId - Order ID from snap token response
   * @returns Observable with payment status
   *
   * @example
   * this.paymentService.checkPaymentStatus(orderId).subscribe(response => {
   *   if (response.payment_status === 'paid') {
   *     console.log('Payment confirmed!');
   *   }
   * });
   */
  checkPaymentStatus(orderId: string): Observable<PaymentStatusResponse> {
    return this.http.post<PaymentStatusResponse>(
      `${this.apiUrl}/v1/midtrans/check-status`,
      { order_id: orderId }
    ).pipe(
      catchError(this.handleError)
    );
  }

  /**
   * Handle HTTP Errors
   *
   * @private
   */
  private handleError(error: HttpErrorResponse): Observable<never> {
    let errorMessage = 'An error occurred. Please try again.';

    if (error.error instanceof ErrorEvent) {
      // Client-side error
      errorMessage = error.error.message;
    } else {
      // Server-side error
      if (error.status === 0) {
        errorMessage = 'Network error. Please check your connection.';
      } else if (error.status === 422) {
        // Validation error
        const errors = error.error.errors;
        if (errors) {
          const firstError = Object.values(errors)[0] as string[];
          errorMessage = firstError[0];
        }
      } else if (error.error?.message) {
        errorMessage = error.error.message;
      }
    }

    console.error('Payment Service Error:', {
      status: error.status,
      message: errorMessage,
      details: error.error
    });

    return throwError(() => new Error(errorMessage));
  }
}
