/* Admin behaviors: server-side integration connection tests (no secret leaves
   the browser — the server reads the stored key and reports ok/fail). */
(function () {
  'use strict';

  function csrf() {
    var el = document.querySelector('input[name="_csrf"]');
    return el ? el.value : '';
  }

  document.querySelectorAll('[data-test-integration]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var integration = btn.getAttribute('data-test-integration');
      var out = document.querySelector('[data-test-result]');
      if (out) out.textContent = 'Testing…';
      btn.disabled = true;
      var body = new URLSearchParams();
      body.set('_csrf', csrf());
      body.set('integration', integration);
      fetch(window.location.pathname.replace(/\/$/, '') + '/test', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      })
        .then(function (r) { return r.json(); })
        .then(function (d) { if (out) out.textContent = (d.ok ? '✓ ' : '✗ ') + d.message; })
        .catch(function () { if (out) out.textContent = '✗ Test failed.'; })
        .finally(function () { btn.disabled = false; });
    });
  });
})();
