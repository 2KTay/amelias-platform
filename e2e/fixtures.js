// Shared test data for the Amelia's by EAT E2E specs (Task 7.3).
//
// Stripe test-card matrix (use ONLY in Stripe test mode — never live). Source:
// https://stripe.com/docs/testing. Any future expiry + any 3-digit CVC + any ZIP.

const STRIPE_TEST_CARDS = {
  // Happy path.
  success: { number: '4242 4242 4242 4242', label: 'Visa — succeeds' },
  successMastercard: { number: '5555 5555 5555 4444', label: 'Mastercard — succeeds' },
  // Declines / failures (assert graceful handling: order stays pending, hold honored).
  declineGeneric: { number: '4000 0000 0000 0002', label: 'Generic decline' },
  declineInsufficientFunds: { number: '4000 0000 0000 9995', label: 'Insufficient funds' },
  declineLostCard: { number: '4000 0000 0000 9987', label: 'Lost card' },
  declineExpired: { number: '4000 0000 0000 0069', label: 'Expired card' },
  declineIncorrectCvc: { number: '4000 0000 0000 0127', label: 'Incorrect CVC' },
  // 3D Secure (authentication required — assert the challenge appears).
  threeDSecureRequired: { number: '4000 0027 6000 3184', label: '3DS required' },
  threeDSecureOptional: { number: '4000 0000 0000 3220', label: '3DS supported' },
  // Dispute (assert order -> disputed + staff alert downstream).
  disputeFraudulent: { number: '4000 0000 0000 0259', label: 'Charge then disputed (fraudulent)' },
};

const TEST_EXPIRY = { month: '12', year: '34', cvc: '123', zip: '85001' };

// Whether a live/test Stripe is wired (set when the Stripe test keys are in
// Settings on the target env). Until then, the Stripe-step assertions skip.
const STRIPE_ENABLED = process.env.E2E_STRIPE === '1';

module.exports = { STRIPE_TEST_CARDS, TEST_EXPIRY, STRIPE_ENABLED };
