/* OnTime booking form — vanilla JS, no dependencies. */
(function () {
  'use strict';

  var cfg = window.ONTIME_CONFIG;
  if (!cfg) { return; }
  var root = document.getElementById('ontime-booking');
  if (!root) { return; }

  var state = { service: null, staff: null, date: '', slot: '' };
  var steps = ['service', 'staff', 'datetime', 'info', 'confirm'];
  var step = 0;

  function el(tag, cls, html) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (html != null) { n.innerHTML = html; }
    return n;
  }

  function render() {
    root.innerHTML = '';
    var dots = el('div', 'ontime-steps');
    steps.forEach(function (_, i) {
      var d = el('div', 'ontime-step-dot' + (i === step ? ' is-active' : ''));
      dots.appendChild(d);
    });
    root.appendChild(dots);

    var panel = el('div', 'ontime-panel is-active');
    var msg = el('div', 'ontime-msg ontime-msg--error', '');
    msg.style.display = 'none';
    panel.appendChild(msg);

    if (step === 0) { renderService(panel); }
    else if (step === 1) { renderStaff(panel, msg); }
    else if (step === 2) { renderDateTime(panel, msg); }
    else if (step === 3) { renderInfo(panel, msg); }
    else if (step === 4) { renderConfirm(panel, msg); }

    root.appendChild(panel);
  }

  function actions(nextLabel, nextCb, prevCb) {
    var wrap = el('div', 'ontime-actions');
    var prev = el('button', 'ontime-btn ontime-btn--ghost', cfg.i18n.prev);
    prev.disabled = step === 0;
    prev.addEventListener('click', function () { if (step > 0) { step--; render(); } });
    wrap.appendChild(prev);
    var next = el('button', 'ontime-btn', nextLabel);
    next.addEventListener('click', nextCb);
    wrap.appendChild(next);
    return wrap;
  }

  function renderService(panel) {
    panel.appendChild(el('h3', 'ontime-title', cfg.i18n.chooseService));
    var grid = el('div', 'ontime-grid');
    cfg.services.forEach(function (s) {
      var card = el('div', 'ontime-card' + (state.service && state.service.id === s.id ? ' is-selected' : ''));
      var price = s.price > 0 ? s.price.toLocaleString('fa-IR') + ' تومان' : '—';
      card.appendChild(el('h4', '', s.name));
      card.appendChild(el('small', '', s.duration + ' دقیقه • ' + price));
      card.addEventListener('click', function () {
        state.service = s; state.staff = null; state.slot = '';
        Array.prototype.forEach.call(grid.children, function (c) { c.classList.remove('is-selected'); });
        card.classList.add('is-selected');
      });
      grid.appendChild(card);
    });
    panel.appendChild(grid);
    panel.appendChild(actions(cfg.i18n.next, function () {
      if (!state.service) { return; } step = 1; render();
    }));
  }

  function renderStaff(panel, msg) {
    panel.appendChild(el('h3', 'ontime-title', cfg.i18n.chooseStaff));
    var grid = el('div', 'ontime-grid');
    panel.appendChild(grid);
    var loading = el('p', 'ontime-muted', '<span class="ontime-spin"></span> ' + cfg.i18n.loading);
    panel.appendChild(loading);

    post('ontime_get_staff', {}, function (res) {
      loading.remove();
      if (!res || !res.success) { msg.style.display = 'block'; msg.textContent = cfg.i18n.error; return; }
      (res.data.staff || []).forEach(function (st) {
        var card = el('div', 'ontime-card' + (state.staff && state.staff.id === st.id ? ' is-selected' : ''));
        card.appendChild(el('h4', '', st.name));
        if (st.bio) { card.appendChild(el('small', '', st.bio)); }
        card.addEventListener('click', function () {
          state.staff = st; state.slot = '';
          Array.prototype.forEach.call(grid.children, function (c) { c.classList.remove('is-selected'); });
          card.classList.add('is-selected');
        });
        grid.appendChild(card);
      });
    });
    panel.appendChild(actions(cfg.i18n.next, function () {
      if (!state.staff) { return; } step = 2; render();
    }));
  }

  function renderDateTime(panel, msg) {
    panel.appendChild(el('h3', 'ontime-title', cfg.i18n.chooseDateTime));
    var field = el('div', 'ontime-field');
    field.appendChild(el('label', '', cfg.i18n.chooseDateTime));
    var dateInput = el('input', '');
    dateInput.type = 'date';
    dateInput.value = state.date;
    field.appendChild(dateInput);
    panel.appendChild(field);

    var slots = el('div', 'ontime-slots');
    panel.appendChild(slots);

    function loadSlots() {
      slots.innerHTML = '<span class="ontime-muted"><span class="ontime-spin"></span> ' + cfg.i18n.loading + '</span>';
      if (!dateInput.value) { slots.innerHTML = ''; return; }
      post('ontime_get_slots', { staff_id: state.staff.id, date: dateInput.value }, function (res) {
        slots.innerHTML = '';
        if (!res || !res.success || !res.data.slots.length) {
          slots.appendChild(el('p', 'ontime-muted', cfg.i18n.noSlots)); return;
        }
        res.data.slots.forEach(function (s) {
          var b = el('button', 'ontime-slot' + (state.slot === s.value ? ' is-selected' : ''), s.label);
          b.addEventListener('click', function () {
            state.slot = s.value;
            Array.prototype.forEach.call(slots.children, function (c) { c.classList.remove('is-selected'); });
            b.classList.add('is-selected');
          });
          slots.appendChild(b);
        });
      });
    }

    dateInput.addEventListener('change', function () { state.date = dateInput.value; state.slot = ''; loadSlots(); });
    panel.appendChild(actions(cfg.i18n.next, function () {
      if (!state.date || !state.slot) { return; } step = 3; render();
    }));
  }

  function renderInfo(panel, msg) {
    panel.appendChild(el('h3', 'ontime-title', cfg.i18n.yourInfo));
    var fields = [
      { name: 'customer_name', label: cfg.i18n.name, type: 'text' },
      { name: 'customer_phone', label: cfg.i18n.phone, type: 'tel' },
      { name: 'customer_email', label: cfg.i18n.email, type: 'email' },
    ];
    fields.forEach(function (f) {
      var wrap = el('div', 'ontime-field');
      wrap.appendChild(el('label', '', f.label));
      var input = el('input', '');
      input.type = f.type; input.name = f.name; input.value = state[f.name] || '';
      wrap.appendChild(input);
      panel.appendChild(wrap);
    });
    panel.appendChild(actions(cfg.i18n.next, function () {
      panel.querySelectorAll('input').forEach(function (i) { state[i.name] = i.value; });
      if (!state.customer_name || !state.customer_phone) {
        msg.style.display = 'block'; msg.textContent = cfg.i18n.error; return;
      }
      step = 4; render();
    }));
  }

  function renderConfirm(panel, msg) {
    panel.appendChild(el('h3', 'ontime-title', cfg.i18n.confirm));
    var list = el('div', '');
    list.appendChild(el('p', '', cfg.i18n.name + ': ' + (state.customer_name || '')));
    list.appendChild(el('p', '', cfg.i18n.phone + ': ' + (state.customer_phone || '')));
    if (state.customer_email) { list.appendChild(el('p', '', cfg.i18n.email + ': ' + state.customer_email)); }
    if (state.service) { list.appendChild(el('p', '', cfg.i18n.chooseService + ': ' + state.service.name)); }
    if (state.staff) { list.appendChild(el('p', '', cfg.i18n.chooseStaff + ': ' + state.staff.name)); }
    list.appendChild(el('p', '', cfg.i18n.chooseDateTime + ': ' + (state.date || '') + ' / ' + (state.slot || '')));
    panel.appendChild(list);

    var wrap = el('div', 'ontime-actions');
    var prev = el('button', 'ontime-btn ontime-btn--ghost', cfg.i18n.prev);
    prev.addEventListener('click', function () { step = 3; render(); });
    wrap.appendChild(prev);
    var submit = el('button', 'ontime-btn', cfg.i18n.confirm);
    submit.addEventListener('click', function () {
      submit.disabled = true; submit.textContent = cfg.i18n.loading;
      post('ontime_create_appointment', {
        service_id: state.service.id,
        staff_id: state.staff.id,
        date: state.date,
        slot: state.slot,
        customer_name: state.customer_name,
        customer_phone: state.customer_phone,
        customer_email: state.customer_email || '',
      }, function (res) {
        submit.disabled = false; submit.textContent = cfg.i18n.confirm;
        if (!res || !res.success) {
          msg.style.display = 'block';
          msg.textContent = (res && res.data && res.data.message) ? res.data.message : cfg.i18n.error;
          return;
        }
        if (res.data.redirect) { window.location.href = res.data.redirect; return; }
        panel.innerHTML = '<div class="ontime-msg ontime-msg--success">' + (res.data.message || cfg.i18n.success) + '</div>';
      });
    });
    wrap.appendChild(submit);
    panel.appendChild(wrap);
  }

  function post(action, data, cb) {
    var body = 'action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(cfg.nonce);
    Object.keys(data).forEach(function (k) {
      body += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
    });
    var xhr = new XMLHttpRequest();
    xhr.open('POST', cfg.ajaxUrl, true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    xhr.onload = function () {
      try { cb(JSON.parse(xhr.responseText)); } catch (e) { cb(null); }
    };
    xhr.onerror = function () { cb(null); };
    xhr.send(body);
  }

  render();
})();
