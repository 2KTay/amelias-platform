/* Reserve page enhancement: time-slot single-select + live availability refresh.
 * Progressive: the server renders slots and the form posts fine without this. */
(function () {
  'use strict';

  var form = document.querySelector('[data-reserve-form]');
  if (!form) { return; }

  var slotInput = form.querySelector('[data-slot-input]');
  var slotsWrap = form.querySelector('[data-slots]');
  var partySel = form.querySelector('[data-reserve-party]');
  var dateInp = form.querySelector('[data-reserve-date]');

  function bindSlots() {
    var slots = slotsWrap.querySelectorAll('.slot');
    slots.forEach(function (b) {
      if (b.disabled) { return; }
      b.addEventListener('click', function () {
        slots.forEach(function (s) { s.setAttribute('aria-pressed', 'false'); });
        b.setAttribute('aria-pressed', 'true');
        if (slotInput) { slotInput.value = b.getAttribute('data-slot-id') || ''; }
      });
    });
  }
  bindSlots();

  function basePath() {
    // Mirror the app mount base from the form action (handles subdir deploys).
    var action = form.getAttribute('action') || '/reserve';
    return action.replace(/\/reserve$/, '');
  }

  function refresh() {
    if (!slotsWrap || !partySel || !dateInp) { return; }
    var url = basePath() + '/reserve/availability?party=' +
      encodeURIComponent(partySel.value) + '&date=' + encodeURIComponent(dateInp.value);
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.slots) { return; }
        if (slotInput) { slotInput.value = ''; }
        if (data.slots.length === 0) {
          slotsWrap.innerHTML = '<p class="field__hint">No times available for this date. Try another date or join the waitlist below.</p>';
          return;
        }
        var html = '';
        data.slots.forEach(function (s) {
          var label = String(s.label).replace(/[<>&"]/g, '');
          html += '<button type="button" class="slot" data-slot-id="' + Number(s.id) +
            '" aria-pressed="false"' + (s.full ? ' disabled aria-disabled="true"' : '') + '>' +
            label + (s.full ? '<small>Full</small>' : '') + '</button>';
        });
        slotsWrap.innerHTML = html;
        bindSlots();
      })
      .catch(function () { /* keep server-rendered slots on failure */ });
  }

  if (partySel) { partySel.addEventListener('change', refresh); }
  if (dateInp) { dateInp.addEventListener('change', refresh); }
})();
