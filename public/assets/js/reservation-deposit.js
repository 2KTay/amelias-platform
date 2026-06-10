/* Reservation deposit — mounts the Stripe Payment Element and confirms the
 * deposit PaymentIntent. The webhook flips the deposit order to paid and the
 * tokenized manage page promotes the held booking to confirmed. */
(function () {
  'use strict';

  var form = document.getElementById('deposit-form');
  if (!form || typeof Stripe === 'undefined') { return; }

  var pk = form.getAttribute('data-stripe-pk');
  var clientSecret = form.getAttribute('data-client-secret');
  var returnUrl = form.getAttribute('data-return-url');
  var msg = document.getElementById('deposit-msg');
  var submit = document.getElementById('deposit-submit');
  if (!pk || !clientSecret) { return; }

  var stripe = Stripe(pk);
  var elements = stripe.elements({ clientSecret: clientSecret });
  var paymentElement = elements.create('payment');
  paymentElement.mount('#payment-element');

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (submit) { submit.disabled = true; }
    if (msg) { msg.textContent = 'Processing your deposit…'; }
    stripe.confirmPayment({
      elements: elements,
      confirmParams: { return_url: returnUrl }
    }).then(function (result) {
      if (result.error) {
        if (msg) { msg.textContent = result.error.message || 'Payment could not be completed.'; }
        if (submit) { submit.disabled = false; }
      }
    });
  });
})();
