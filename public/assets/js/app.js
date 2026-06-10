/* Public storefront behaviors: nav toggle, menu day-part tabs, cookie consent.
   Vanilla JS, no dependencies. Progressive enhancement — pages work without it. */
(function () {
  'use strict';

  // ---- Mobile nav toggle (accessible) ----
  var toggle = document.querySelector('[data-nav-toggle]');
  var links = document.getElementById('primary-nav');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && links.classList.contains('is-open')) {
        links.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  // ---- Menu day-part tabs ----
  var tablists = document.querySelectorAll('[data-menu-tabs]');
  tablists.forEach(function (tablist) {
    var tabs = tablist.querySelectorAll('[role="tab"]');
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function () {
        tabs.forEach(function (t) {
          t.setAttribute('aria-selected', 'false');
          var p = document.getElementById(t.getAttribute('aria-controls'));
          if (p) p.hidden = true;
        });
        tab.setAttribute('aria-selected', 'true');
        var panel = document.getElementById(tab.getAttribute('aria-controls'));
        if (panel) panel.hidden = false;
      });
    });
  });

  // ---- Click-to-copy (demo credentials) ----
  document.querySelectorAll('[data-copy]').forEach(function (el) {
    el.addEventListener('click', function () {
      var text = el.textContent.trim();
      if (navigator.clipboard) navigator.clipboard.writeText(text);
      var prev = el.getAttribute('title');
      el.setAttribute('title', 'Copied!');
      setTimeout(function () { el.setAttribute('title', prev || ''); }, 1200);
    });
  });

  // ---- Auto-dismiss success flashes ----
  // Only targets opted-in success messages (marked data-autodismiss); error and
  // warning alerts are left untouched. No-ops on pages without a flash.
  document.querySelectorAll('[data-autodismiss]').forEach(function (flash) {
    setTimeout(function () {
      flash.classList.add('is-dismissing');
      var remove = function () { if (flash.parentNode) flash.parentNode.removeChild(flash); };
      flash.addEventListener('transitionend', remove, { once: true });
      // Fallback in case transitionend never fires (e.g. reduced motion).
      setTimeout(remove, 600);
    }, 4000);
  });

  // ---- Cookie consent (gates analytics; never loads on /admin) ----
  var bar = document.querySelector('[data-cookie-bar]');
  if (bar && !/^\/admin/.test(location.pathname)) {
    var choice = localStorage.getItem('cookie-consent');
    if (!choice) {
      bar.hidden = false;
      bar.classList.remove('hidden');
    } else if (choice === 'accept') {
      window.dispatchEvent(new CustomEvent('consent:granted'));
    }
    bar.querySelectorAll('[data-cookie]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var val = btn.getAttribute('data-cookie');
        localStorage.setItem('cookie-consent', val);
        bar.hidden = true;
        bar.classList.add('hidden');
        if (val === 'accept') window.dispatchEvent(new CustomEvent('consent:granted'));
      });
    });
  }

  // ---- Mini-cart drawer (progressive enhancement) ----
  // Adds-to-cart fetch instead of full-page redirect, slides in a drawer
  // (right-side on desktop, bottom sheet on mobile) and supports qty/remove.
  // Every hook degrades: forms still POST to /cart/* and the icon links to
  // /cart when JS is off or a request fails.
  var drawer = document.querySelector('[data-cart-drawer]');
  if (drawer) {
    var panel = drawer.querySelector('.cart-drawer__panel');
    var cartBody = drawer.querySelector('[data-cart-body]');
    var cartFoot = drawer.querySelector('[data-cart-foot]');
    var cartBar = document.querySelector('.cart-bar');
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
    var checkoutLink = drawer.querySelector('[data-cart-checkout]');
    var cartUrl = (checkoutLink && checkoutLink.getAttribute('href')) || '/cart';
    var base = cartUrl.replace(/\/$/, '');
    var updateUrl = base + '/update';
    var removeUrl = base + '/remove';
    var lastFocus = null;

    var esc = function (s) {
      return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
    };

    var setCount = function (n) {
      document.querySelectorAll('[data-cart-count]').forEach(function (el) {
        el.textContent = String(n);
        if (el.classList.contains('nav__cart-count')) el.hidden = n <= 0;
      });
      if (cartBar) cartBar.hidden = n <= 0;
    };

    var render = function (data) {
      setCount(data.count || 0);
      document.querySelectorAll('[data-cart-subtotal]').forEach(function (el) { el.textContent = data.subtotal || ''; });
      if (!data.lines || !data.lines.length) {
        cartBody.innerHTML = '<p class="cart-drawer__empty">Your cart is empty.</p>';
        if (cartFoot) cartFoot.hidden = true;
        return;
      }
      var html = '<ul class="cart-drawer__lines">';
      data.lines.forEach(function (l) {
        var img = l.image
          ? '<img class="cart-line__img" src="' + esc(l.image) + '" alt="" width="56" height="56">'
          : '<span class="cart-line__img cart-line__img--ph" aria-hidden="true"></span>';
        html += '<li class="cart-line" data-line-key="' + esc(l.key) + '">' + img +
          '<div class="cart-line__main">' +
            '<div class="cart-line__name">' + esc(l.name) + '</div>' +
            '<div class="cart-line__price">' + esc(l.unit_price) + ' each</div>' +
            '<div class="cart-line__qty" role="group" aria-label="Quantity for ' + esc(l.name) + '">' +
              '<button type="button" class="cart-line__step" data-cart-dec aria-label="Decrease quantity">−</button>' +
              '<span class="cart-line__num">' + (l.quantity | 0) + '</span>' +
              '<button type="button" class="cart-line__step" data-cart-inc aria-label="Increase quantity">+</button>' +
              '<button type="button" class="cart-line__remove" data-cart-remove>Remove</button>' +
            '</div>' +
          '</div>' +
          '<span class="cart-line__total">' + esc(l.line_total) + '</span>' +
        '</li>';
      });
      html += '</ul>';
      cartBody.innerHTML = html;
      if (cartFoot) cartFoot.hidden = false;
    };

    var jsonPost = function (url, params) {
      var fd = new URLSearchParams();
      fd.set('_csrf', csrf);
      Object.keys(params).forEach(function (k) { fd.set(k, params[k]); });
      return fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: fd.toString(),
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); });
    };

    var openDrawer = function () {
      lastFocus = document.activeElement;
      drawer.hidden = false;
      void drawer.offsetWidth; // reflow so the transition runs
      drawer.classList.add('is-open');
      document.body.classList.add('cart-open');
      if (panel) panel.focus();
    };
    var closeDrawer = function () {
      drawer.classList.remove('is-open');
      document.body.classList.remove('cart-open');
      var hide = function () { drawer.hidden = true; };
      var done = false;
      panel.addEventListener('transitionend', function te() { if (done) return; done = true; panel.removeEventListener('transitionend', te); hide(); }, { once: true });
      setTimeout(function () { if (!done) { done = true; hide(); } }, 320);
      if (lastFocus && lastFocus.focus) lastFocus.focus();
    };

    var loadAndOpen = function () {
      fetch(cartUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) { render(data); openDrawer(); })
        .catch(function () { window.location.href = cartUrl; });
    };

    // Open from the nav cart icon and the mobile cart bar.
    document.querySelectorAll('[data-cart-toggle]').forEach(function (el) {
      el.addEventListener('click', function (e) { e.preventDefault(); loadAndOpen(); });
    });

    // Close: backdrop, close button, Escape.
    drawer.querySelectorAll('[data-cart-close]').forEach(function (el) {
      el.addEventListener('click', closeDrawer);
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer();
    });

    // Intercept add-to-cart forms on the menu/market.
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!(form instanceof HTMLFormElement)) return;
      var action = form.getAttribute('action') || '';
      if (!/\/cart\/add$/.test(action)) return;
      e.preventDefault();
      var body = new URLSearchParams(new FormData(form)).toString();
      fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body,
        credentials: 'same-origin'
      }).then(function (r) { return r.json(); })
        .then(function (data) {
          if (data && data.ok) { render(data); openDrawer(); }
          else { form.submit(); }
        })
        .catch(function () { form.submit(); });
    });

    // Qty +/- and remove inside the drawer (event-delegated).
    cartBody.addEventListener('click', function (e) {
      var li = e.target.closest('.cart-line');
      if (!li) return;
      var key = li.getAttribute('data-line-key');
      var numEl = li.querySelector('.cart-line__num');
      var qty = numEl ? (parseInt(numEl.textContent, 10) || 1) : 1;
      var apply = function (data) { if (data && data.ok) render(data); };
      if (e.target.closest('[data-cart-inc]')) jsonPost(updateUrl, { line_key: key, quantity: qty + 1 }).then(apply);
      else if (e.target.closest('[data-cart-dec]')) jsonPost(updateUrl, { line_key: key, quantity: qty - 1 }).then(apply);
      else if (e.target.closest('[data-cart-remove]')) jsonPost(removeUrl, { line_key: key }).then(apply);
    });

    // Keep focus inside the open panel (basic trap).
    panel.addEventListener('keydown', function (e) {
      if (e.key !== 'Tab') return;
      var f = panel.querySelectorAll('button, a[href], input, [tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }
})();
