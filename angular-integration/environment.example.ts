/**
 * Environment Configuration Example
 *
 * Copy this file to:
 * - src/environments/environment.ts (for development)
 * - src/environments/environment.prod.ts (for production)
 *
 * Update values according to your environment
 */

export const environment = {
  production: false, // Set to true in environment.prod.ts

  // API Configuration
  apiUrl: 'http://localhost:8000/api', // Production: 'https://www.sena-digital.com/api'

  // Midtrans Configuration
  midtrans: {
    // Client Key - Get from Midtrans Dashboard
    clientKey: 'SB-Mid-client-NjshfjtIODw5zt75', // Sandbox key
    // Production: 'Mid-client-YOUR_PRODUCTION_KEY'

    // Snap.js URL
    snapUrl: 'https://app.sandbox.midtrans.com/snap/snap.js', // Sandbox
    // Production: 'https://app.midtrans.com/snap/snap.js'

    // Environment
    isProduction: false, // Set to true in production
  },

  // App Configuration
  app: {
    name: 'Sena Digital',
    url: 'http://localhost:4200', // Production: 'https://www.sena-digital.com'
    version: '1.0.0',
  },

  // Features
  features: {
    enablePaymentPolling: true,
    pollingInterval: 3000, // milliseconds
    maxPollingAttempts: 20,
  },

  // Logging
  logging: {
    enableConsoleLog: true, // Disable in production
    enableErrorTracking: false, // Enable Sentry/etc in production
  }
};

/**
 * For production environment (environment.prod.ts):
 *
 * export const environment = {
 *   production: true,
 *   apiUrl: 'https://www.sena-digital.com/api',
 *   midtrans: {
 *     clientKey: 'Mid-client-YOUR_PRODUCTION_KEY',
 *     snapUrl: 'https://app.midtrans.com/snap/snap.js',
 *     isProduction: true,
 *   },
 *   app: {
 *     name: 'Sena Digital',
 *     url: 'https://www.sena-digital.com',
 *     version: '1.0.0',
 *   },
 *   features: {
 *     enablePaymentPolling: true,
 *     pollingInterval: 3000,
 *     maxPollingAttempts: 20,
 *   },
 *   logging: {
 *     enableConsoleLog: false,
 *     enableErrorTracking: true,
 *   }
 * };
 */
