/*
 * Stripe Payment Element + live order-status polling (Phase 2).
 *
 * SAQ-A: card fields are rendered by Stripe.js in an iframe; the card number
 * never touches our DOM or server. We confirm the PaymentIntent with the
 * client_secret created server-side; on success Stripe redirects back to the
 * order page (return_url) and our webhook flips the order to paid.
 */
(function () {
  'use strict';

  function mountPaymentElement() {
    var mount = document.getElementById('payment-element');
    var payBtn = document.getElementById('pay-button');
    if (!mount || !payBtn || typeof Stripe === 'undefined') {
      return;
    }

    var pk = mount.getAttribute('data-stripe-pk');
    var clientSecret = mount.getAttribute('data-client-secret');
    var returnUrl = mount.getAttribute('data-return-url');
    if (!pk || !clientSecret) {
      return;
    }

    var stripe = Stripe(pk);
    var elements = stripe.elements({ clientSecret: clientSecret });
    var paymentElement = elements.create('payment', { layout: 'tabs' });
    paymentElement.mount(mount);

    var messageEl = document.getElementById('payment-message');

    function showMessage(text) {
      if (!messageEl) { return; }
      messageEl.textContent = text;
      messageEl.hidden = false;
    }

    payBtn.addEventListener('click', function (e) {
      e.preventDefault();
      payBtn.disabled = true;
      showMessage('Processing your payment…');

      stripe.confirmPayment({
        elements: elements,
        confirmParams: returnUrl ? { return_url: returnUrl } : {}
      }).then(function (result) {
        if (result.error) {
          showMessage(result.error.message || 'Payment could not be completed.');
          payBtn.disabled = false;
        }
        // On success Stripe redirects to return_url; the webhook marks paid.
      });
    });
  }

  function pollStatus() {
    var stepper = document.querySelector('[data-order-status]');
    if (!stepper) {
      return;
    }
    var url = stepper.getAttribute('data-poll-url');
    if (!url) {
      return;
    }
    var order = ['received', 'preparing', 'ready'];

    function apply(step) {
      var idx = order.indexOf(step);
      if (idx < 0) { return; }
      var steps = stepper.querySelectorAll('.step');
      steps.forEach(function (el, i) {
        el.classList.remove('step--done', 'step--active');
        if (i < idx) { el.classList.add('step--done'); }
        else if (i === idx) { el.classList.add('step--active'); }
      });
    }

    function tick() {
      fetch(url, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.step) { apply(data.step); }
          if (data && (data.status === 'fulfilled' || data.status === 'ready')) {
            clearInterval(timer);
          }
        })
        .catch(function () { /* transient; keep polling */ });
    }

    var timer = setInterval(tick, 15000);
    tick();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      mountPaymentElement();
      pollStatus();
    });
  } else {
    mountPaymentElement();
    pollStatus();
  }
})();
