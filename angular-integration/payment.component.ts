/**
 * Payment Component
 *
 * Handles payment flow using Midtrans Snap.js
 *
 * Features:
 * - Load Midtrans Snap.js dynamically
 * - Create snap token
 * - Process payment
 * - Verify payment status
 * - Handle all payment states (success, pending, error)
 *
 * Usage:
 * 1. Copy this file to: src/app/components/payment/payment.component.ts
 * 2. Update the template and styles as needed
 * 3. Configure routes for success/pending/error pages
 */

import { Component, OnInit, OnDestroy } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { PaymentService, SnapTokenRequest } from '../../services/payment.service';
import { finalize } from 'rxjs/operators';

// Declare Snap.js global variable
declare const snap: any;

// ============================================================================
// COMPONENT
// ============================================================================

@Component({
  selector: 'app-payment',
  templateUrl: './payment.component.html',
  styleUrls: ['./payment.component.scss']
})
export class PaymentComponent implements OnInit, OnDestroy {
  // State
  loading = false;
  processingPayment = false;
  errorMessage = '';

  // Payment data
  invitationId: number;
  userId: number;
  amount: number;
  packageName: string;
  customerName: string;
  customerEmail: string;
  customerPhone: string;

  // Snap.js
  private snapLoaded = false;

  constructor(
    private paymentService: PaymentService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    this.loadPaymentData();
    this.loadSnapScript();
  }

  ngOnDestroy(): void {
    // Cleanup if needed
  }

  /**
   * Load payment data from route params or service
   */
  private loadPaymentData(): void {
    // Option 1: From query parameters
    const params = this.route.snapshot.queryParams;
    this.invitationId = Number(params['invitation_id']);
    this.userId = Number(params['user_id']);
    this.amount = Number(params['amount']);
    this.packageName = params['package_name'] || 'Wedding Package';

    // Option 2: From state (passed via router.navigate)
    const state = this.router.getCurrentNavigation()?.extras.state;
    if (state) {
      this.invitationId = state['invitation_id'];
      this.userId = state['user_id'];
      this.amount = state['amount'];
      this.packageName = state['package_name'];
      this.customerName = state['customer_name'];
      this.customerEmail = state['customer_email'];
      this.customerPhone = state['customer_phone'];
    }

    // Validate required data
    if (!this.invitationId || !this.userId || !this.amount) {
      this.errorMessage = 'Missing payment information. Please try again.';
      console.error('Missing payment data:', {
        invitationId: this.invitationId,
        userId: this.userId,
        amount: this.amount
      });
    }
  }

  /**
   * Load Midtrans Snap.js script dynamically
   */
  private loadSnapScript(): Promise<void> {
    return new Promise((resolve, reject) => {
      if (this.snapLoaded || typeof snap !== 'undefined') {
        this.snapLoaded = true;
        resolve();
        return;
      }

      // Check if script is already in DOM
      const existingScript = document.querySelector(
        'script[src*="snap.js"]'
      );

      if (existingScript) {
        existingScript.addEventListener('load', () => {
          this.snapLoaded = true;
          resolve();
        });
        return;
      }

      // Create and load script
      const script = document.createElement('script');
      script.src = 'https://app.sandbox.midtrans.com/snap/snap.js'; // Change to production URL when deploying
      script.setAttribute('data-client-key', 'SB-Mid-client-NjshfjtIODw5zt75'); // Replace with your client key

      script.onload = () => {
        this.snapLoaded = true;
        console.log('Snap.js loaded successfully');
        resolve();
      };

      script.onerror = () => {
        const error = 'Failed to load Midtrans Snap.js';
        console.error(error);
        this.errorMessage = error;
        reject(new Error(error));
      };

      document.head.appendChild(script);
    });
  }

  /**
   * Start payment process
   */
  processPayment(): void {
    if (!this.snapLoaded) {
      this.errorMessage = 'Payment system is loading. Please try again.';
      return;
    }

    if (!this.invitationId || !this.userId || !this.amount) {
      this.errorMessage = 'Missing payment information. Please try again.';
      return;
    }

    this.loading = true;
    this.errorMessage = '';

    const request: SnapTokenRequest = {
      invitation_id: this.invitationId,
      amount: this.amount,
      customer_details: {
        first_name: this.customerName || 'Customer',
        email: this.customerEmail || 'customer@example.com',
        phone: this.customerPhone || '+6281234567890'
      },
      item_details: [
        {
          id: `paket-${this.invitationId}`,
          name: this.packageName,
          price: this.amount,
          quantity: 1
        }
      ]
    };

    this.paymentService.createSnapToken(this.userId, request)
      .pipe(finalize(() => this.loading = false))
      .subscribe({
        next: (response) => {
          if (response.success) {
            console.log('Snap token created:', response.data);
            this.openSnapPayment(response.data.snap_token, response.data.order_id);
          } else {
            this.errorMessage = response.message || 'Failed to initialize payment';
          }
        },
        error: (error) => {
          console.error('Failed to create snap token:', error);
          this.errorMessage = error.message || 'Failed to initialize payment. Please try again.';
        }
      });
  }

  /**
   * Open Midtrans Snap payment popup
   */
  private openSnapPayment(snapToken: string, orderId: string): void {
    if (typeof snap === 'undefined') {
      this.errorMessage = 'Payment system not loaded. Please refresh the page.';
      return;
    }

    console.log('Opening Snap payment with token:', snapToken);

    snap.pay(snapToken, {
      onSuccess: (result: any) => {
        console.log('Payment success:', result);
        this.handlePaymentSuccess(orderId);
      },

      onPending: (result: any) => {
        console.log('Payment pending:', result);
        this.handlePaymentPending(orderId);
      },

      onError: (result: any) => {
        console.error('Payment error:', result);
        this.handlePaymentError(orderId);
      },

      onClose: () => {
        console.log('Payment popup closed');
        this.processingPayment = false;
      }
    });

    this.processingPayment = true;
  }

  /**
   * Handle successful payment
   * Verify payment status before redirecting
   */
  private handlePaymentSuccess(orderId: string): void {
    console.log('Verifying payment status for order:', orderId);
    this.loading = true;

    this.paymentService.checkPaymentStatus(orderId)
      .pipe(finalize(() => this.loading = false))
      .subscribe({
        next: (response) => {
          console.log('Payment status response:', response);

          if (response.success && response.payment_status === 'paid') {
            // Payment confirmed, redirect to success page
            this.router.navigate(['/payment/success'], {
              queryParams: {
                order_id: orderId,
                transaction_id: response.data.transaction_id,
                confirmed_at: response.data.payment_confirmed_at
              }
            });
          } else if (response.payment_status === 'pending') {
            // Still pending, redirect to pending page
            this.handlePaymentPending(orderId);
          } else {
            // Payment failed
            this.handlePaymentError(orderId);
          }
        },
        error: (error) => {
          console.error('Failed to verify payment status:', error);
          // Even if verification fails, redirect to success page
          // Webhook will handle the update
          this.router.navigate(['/payment/success'], {
            queryParams: { order_id: orderId }
          });
        }
      });
  }

  /**
   * Handle pending payment
   */
  private handlePaymentPending(orderId: string): void {
    this.router.navigate(['/payment/pending'], {
      queryParams: { order_id: orderId }
    });
  }

  /**
   * Handle payment error
   */
  private handlePaymentError(orderId: string): void {
    this.router.navigate(['/payment/error'], {
      queryParams: { order_id: orderId }
    });
  }

  /**
   * Cancel payment and go back
   */
  cancel(): void {
    this.router.navigate(['/packages']); // Or wherever you want to redirect
  }
}
