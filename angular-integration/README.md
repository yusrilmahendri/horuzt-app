# Angular Payment Integration Files

This directory contains ready-to-use Angular TypeScript files for integrating with the payment system.

## Files Included

1. **payment.service.ts** - Payment service with API calls
2. **payment.component.ts** - Payment processing component
3. **payment-status.component.ts** - Payment status display with polling
4. **environment.example.ts** - Environment configuration example

## Installation Steps

### 1. Copy Service File

```bash
cp angular-integration/payment.service.ts src/app/services/payment.service.ts
```

### 2. Copy Component Files

```bash
# Payment component
cp angular-integration/payment.component.ts src/app/components/payment/payment.component.ts

# Payment status component
cp angular-integration/payment-status.component.ts src/app/components/payment-status/payment-status.component.ts
```

### 3. Update Environment Files

Copy environment example and update with your values:

```bash
# Development
cp angular-integration/environment.example.ts src/environments/environment.ts

# Production
cp angular-integration/environment.example.ts src/environments/environment.prod.ts
```

Edit `environment.prod.ts`:
```typescript
export const environment = {
  production: true,
  apiUrl: 'https://www.sena-digital.com/api',
  midtrans: {
    clientKey: 'Mid-client-YOUR_PRODUCTION_KEY', // Get from Midtrans
    snapUrl: 'https://app.midtrans.com/snap/snap.js',
    isProduction: true,
  },
  // ... rest of configuration
};
```

### 4. Configure Routes

Add routes in `app-routing.module.ts`:

```typescript
import { PaymentComponent } from './components/payment/payment.component';
import { PaymentStatusComponent } from './components/payment-status/payment-status.component';

const routes: Routes = [
  // ... existing routes
  {
    path: 'payment',
    component: PaymentComponent
  },
  {
    path: 'payment/success',
    component: PaymentStatusComponent
  },
  {
    path: 'payment/pending',
    component: PaymentStatusComponent
  },
  {
    path: 'payment/error',
    component: PaymentStatusComponent
  },
];
```

### 5. Import HttpClientModule

Ensure `HttpClientModule` is imported in `app.module.ts`:

```typescript
import { HttpClientModule } from '@angular/common/http';

@NgModule({
  imports: [
    // ... other imports
    HttpClientModule,
  ],
  // ...
})
export class AppModule { }
```

## Usage Example

### Navigate to Payment

```typescript
// From your package selection component
constructor(private router: Router) {}

selectPackage(packageData: any): void {
  this.router.navigate(['/payment'], {
    queryParams: {
      invitation_id: this.invitationId,
      user_id: this.userId,
      amount: packageData.price,
      package_name: packageData.name
    }
  });

  // Or using state
  this.router.navigate(['/payment'], {
    state: {
      invitation_id: this.invitationId,
      user_id: this.userId,
      amount: packageData.price,
      package_name: packageData.name,
      customer_name: this.user.name,
      customer_email: this.user.email,
      customer_phone: this.user.phone
    }
  });
}
```

### Create Component Templates

#### payment.component.html

```html
<div class="payment-container">
  <div class="payment-header">
    <h1>Complete Payment</h1>
    <p>{{ packageName }} - Rp {{ amount | number }}</p>
  </div>

  <div class="payment-body">
    <!-- Loading state -->
    <div *ngIf="loading" class="loading">
      <mat-spinner></mat-spinner>
      <p>Processing...</p>
    </div>

    <!-- Error message -->
    <div *ngIf="errorMessage" class="error-message">
      <mat-icon>error</mat-icon>
      <p>{{ errorMessage }}</p>
    </div>

    <!-- Payment button -->
    <button
      mat-raised-button
      color="primary"
      (click)="processPayment()"
      [disabled]="loading || processingPayment"
      *ngIf="!errorMessage">
      <mat-icon>payment</mat-icon>
      Pay Now
    </button>

    <button
      mat-button
      (click)="cancel()"
      [disabled]="loading || processingPayment">
      Cancel
    </button>
  </div>
</div>
```

#### payment-status.component.html

```html
<div class="status-container">
  <div class="status-card" [ngClass]="statusType">
    <!-- Success state -->
    <div *ngIf="statusType === 'success'" class="status-success">
      <mat-icon>check_circle</mat-icon>
      <h1>{{ statusMessage }}</h1>
      <p>{{ statusDescription }}</p>

      <div class="status-details">
        <p><strong>Order ID:</strong> {{ orderId }}</p>
        <p><strong>Transaction ID:</strong> {{ transactionId }}</p>
        <p><strong>Confirmed At:</strong> {{ confirmedAt | date:'medium' }}</p>
        <p *ngIf="domainExpiresAt"><strong>Domain Expires:</strong> {{ domainExpiresAt | date:'medium' }}</p>
      </div>

      <button mat-raised-button color="primary" (click)="goToDashboard()">
        Go to Dashboard
      </button>
    </div>

    <!-- Pending state -->
    <div *ngIf="statusType === 'pending'" class="status-pending">
      <mat-spinner></mat-spinner>
      <h1>{{ statusMessage }}</h1>
      <p>{{ statusDescription }}</p>

      <div class="polling-info" *ngIf="pollingAttempts > 0">
        <p>Checking status... ({{ pollingAttempts }}/{{ MAX_POLLING_ATTEMPTS }})</p>
        <mat-progress-bar mode="determinate" [value]="pollingProgress"></mat-progress-bar>
      </div>

      <button mat-button (click)="retryCheck()" [disabled]="loading">
        <mat-icon>refresh</mat-icon>
        Retry Check
      </button>
    </div>

    <!-- Error state -->
    <div *ngIf="statusType === 'error'" class="status-error">
      <mat-icon>error</mat-icon>
      <h1>{{ statusMessage }}</h1>
      <p>{{ statusDescription }}</p>

      <div class="error-details" *ngIf="errorMessage">
        <p>{{ errorMessage }}</p>
      </div>

      <div class="error-actions">
        <button mat-raised-button color="primary" (click)="goToInvitations()">
          Try Again
        </button>
        <button mat-button (click)="contactSupport()">
          <mat-icon>support</mat-icon>
          Contact Support
        </button>
      </div>
    </div>
  </div>
</div>
```

## Testing

### Test in Development

1. Start Angular dev server:
   ```bash
   ng serve
   ```

2. Start Laravel backend:
   ```bash
   php artisan serve
   ```

3. Navigate to payment page and test with Midtrans sandbox cards:
   - Success: `4811 1111 1111 1114`, CVV: `123`, OTP: `112233`
   - Failed: `4911 1111 1111 1113`, CVV: `123`, OTP: `112233`

### Test Payment Flow

1. Select package
2. Click "Pay Now"
3. Enter test card details
4. Complete 3DS verification
5. Verify redirect to success page
6. Check payment status is "paid"
7. Verify database updated

## Production Deployment

### 1. Update Environment

Edit `src/environments/environment.prod.ts`:
- Set `production: true`
- Update `apiUrl` to production URL
- Update Midtrans `clientKey` to production key
- Update `snapUrl` to production URL

### 2. Build

```bash
ng build --configuration production
```

### 3. Deploy

Deploy the `dist/` folder to your web server.

### 4. Test

Test complete payment flow in production environment.

## Troubleshooting

### Snap.js not loading

**Solution:** Check network tab for script loading errors. Verify client key is correct.

### CORS errors

**Solution:** Ensure Laravel backend CORS is configured to allow your frontend domain.

### Payment status not updating

**Solution:**
1. Check network tab for API call errors
2. Verify order_id is correct
3. Check Laravel logs
4. Ensure database migration is run

### Polling not working

**Solution:**
1. Check console for errors
2. Verify `features.enablePaymentPolling` is true in environment
3. Check component is not destroyed during polling

## Support

For issues:
1. Check console for errors
2. Check network tab for API errors
3. Review Laravel logs
4. Refer to `PAYMENT_API_CONTRACT.md` for API details
5. Contact backend team with order_id

## References

- Complete API Documentation: `../PAYMENT_API_CONTRACT.md`
- Changes Summary: `../PAYMENT_CHANGES_SUMMARY.md`
- Midtrans Docs: https://docs.midtrans.com/
- Angular Docs: https://angular.io/docs
