/**
 * Payment Status Component
 *
 * Displays payment status and polls for updates on pending payments
 *
 * Features:
 * - Display success/pending/error states
 * - Poll payment status for pending payments
 * - Auto-redirect on status change
 *
 * Routes:
 * - /payment/success - Successful payment
 * - /payment/pending - Pending payment
 * - /payment/error - Failed payment
 *
 * Usage:
 * 1. Copy to: src/app/components/payment-status/payment-status.component.ts
 * 2. Configure routes in app-routing.module.ts
 * 3. Create corresponding templates for each status
 */

import { Component, OnInit, OnDestroy } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { PaymentService, PaymentStatusResponse } from '../../services/payment.service';
import { interval, Subscription } from 'rxjs';
import { switchMap, takeWhile, finalize } from 'rxjs/operators';

// ============================================================================
// COMPONENT
// ============================================================================

@Component({
  selector: 'app-payment-status',
  templateUrl: './payment-status.component.html',
  styleUrls: ['./payment-status.component.scss']
})
export class PaymentStatusComponent implements OnInit, OnDestroy {
  // Status type
  statusType: 'success' | 'pending' | 'error' = 'pending';

  // Payment data
  orderId: string = '';
  transactionId: string = '';
  paymentStatus: string = '';
  confirmedAt: string = '';
  domainExpiresAt: string = '';

  // UI state
  loading = true;
  errorMessage = '';

  // Polling
  private pollingSubscription?: Subscription;
  private readonly MAX_POLLING_ATTEMPTS = 20; // 20 attempts * 3 seconds = 1 minute
  private pollingAttempts = 0;
  private readonly POLLING_INTERVAL = 3000; // 3 seconds

  constructor(
    private route: ActivatedRoute,
    private router: Router,
    private paymentService: PaymentService
  ) {}

  ngOnInit(): void {
    this.loadPaymentData();
    this.determineStatusType();
    this.checkPaymentStatus();
  }

  ngOnDestroy(): void {
    this.stopPolling();
  }

  /**
   * Load payment data from query parameters
   */
  private loadPaymentData(): void {
    const params = this.route.snapshot.queryParams;
    this.orderId = params['order_id'] || '';
    this.transactionId = params['transaction_id'] || '';
    this.confirmedAt = params['confirmed_at'] || '';
  }

  /**
   * Determine status type from current route
   */
  private determineStatusType(): void {
    const url = this.router.url;

    if (url.includes('/payment/success')) {
      this.statusType = 'success';
    } else if (url.includes('/payment/pending')) {
      this.statusType = 'pending';
      this.startPolling();
    } else if (url.includes('/payment/error')) {
      this.statusType = 'error';
    }
  }

  /**
   * Check current payment status
   */
  private checkPaymentStatus(): void {
    if (!this.orderId) {
      this.loading = false;
      this.errorMessage = 'No order ID provided';
      return;
    }

    this.paymentService.checkPaymentStatus(this.orderId)
      .pipe(finalize(() => this.loading = false))
      .subscribe({
        next: (response) => {
          this.updatePaymentData(response);
        },
        error: (error) => {
          console.error('Failed to check payment status:', error);
          this.errorMessage = error.message || 'Failed to check payment status';
        }
      });
  }

  /**
   * Update payment data from response
   */
  private updatePaymentData(response: PaymentStatusResponse): void {
    this.paymentStatus = response.payment_status;
    this.transactionId = response.data.transaction_id || '';
    this.confirmedAt = response.data.payment_confirmed_at || '';
    this.domainExpiresAt = response.data.domain_expires_at || '';

    // Update status type based on response
    if (response.payment_status === 'paid') {
      if (this.statusType !== 'success') {
        this.statusType = 'success';
        this.stopPolling();
        this.navigateToSuccess();
      }
    } else if (response.payment_status === 'failed') {
      if (this.statusType !== 'error') {
        this.statusType = 'error';
        this.stopPolling();
        this.navigateToError();
      }
    }
  }

  /**
   * Start polling for payment status updates
   */
  private startPolling(): void {
    console.log('Starting payment status polling...');

    this.pollingSubscription = interval(this.POLLING_INTERVAL)
      .pipe(
        takeWhile(() => this.pollingAttempts < this.MAX_POLLING_ATTEMPTS),
        switchMap(() => {
          this.pollingAttempts++;
          console.log(`Polling attempt ${this.pollingAttempts}/${this.MAX_POLLING_ATTEMPTS}`);
          return this.paymentService.checkPaymentStatus(this.orderId);
        })
      )
      .subscribe({
        next: (response) => {
          console.log('Polling response:', response);
          this.updatePaymentData(response);

          // Stop polling if payment is confirmed or failed
          if (response.payment_status === 'paid' || response.payment_status === 'failed') {
            this.stopPolling();
          }
        },
        error: (error) => {
          console.error('Polling error:', error);
          // Don't stop polling on error, will stop after max attempts
        },
        complete: () => {
          console.log('Polling completed');
          if (this.paymentStatus === 'pending') {
            this.errorMessage = 'Payment is taking longer than expected. Please check back later.';
          }
        }
      });
  }

  /**
   * Stop polling
   */
  private stopPolling(): void {
    if (this.pollingSubscription) {
      console.log('Stopping payment status polling');
      this.pollingSubscription.unsubscribe();
      this.pollingSubscription = undefined;
    }
  }

  /**
   * Navigate to success page
   */
  private navigateToSuccess(): void {
    this.router.navigate(['/payment/success'], {
      queryParams: {
        order_id: this.orderId,
        transaction_id: this.transactionId,
        confirmed_at: this.confirmedAt
      },
      replaceUrl: true // Replace current URL in history
    });
  }

  /**
   * Navigate to error page
   */
  private navigateToError(): void {
    this.router.navigate(['/payment/error'], {
      queryParams: { order_id: this.orderId },
      replaceUrl: true
    });
  }

  /**
   * Retry payment status check manually
   */
  retryCheck(): void {
    this.loading = true;
    this.errorMessage = '';
    this.checkPaymentStatus();
  }

  /**
   * Go to dashboard
   */
  goToDashboard(): void {
    this.router.navigate(['/dashboard']);
  }

  /**
   * Go to invitations
   */
  goToInvitations(): void {
    this.router.navigate(['/invitations']);
  }

  /**
   * Contact support
   */
  contactSupport(): void {
    // Open support page or modal
    window.open('https://wa.me/6281234567890?text=I need help with order ' + this.orderId, '_blank');
  }

  /**
   * Get status message
   */
  get statusMessage(): string {
    switch (this.statusType) {
      case 'success':
        return 'Payment Successful!';
      case 'pending':
        return 'Payment Processing...';
      case 'error':
        return 'Payment Failed';
      default:
        return 'Checking Payment Status...';
    }
  }

  /**
   * Get status description
   */
  get statusDescription(): string {
    switch (this.statusType) {
      case 'success':
        return 'Your payment has been confirmed. Your wedding invitation is now active.';
      case 'pending':
        return 'We are processing your payment. This may take a few moments.';
      case 'error':
        return 'Your payment could not be processed. Please try again or contact support.';
      default:
        return 'Please wait while we verify your payment status.';
    }
  }

  /**
   * Get polling progress percentage
   */
  get pollingProgress(): number {
    if (this.MAX_POLLING_ATTEMPTS === 0) return 0;
    return Math.round((this.pollingAttempts / this.MAX_POLLING_ATTEMPTS) * 100);
  }
}
