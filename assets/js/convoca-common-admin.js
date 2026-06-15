/**
 * Convoca Common Admin JS Library
 * Version 1.2.0
 */
window.convocaAdmin = window.convocaAdmin || {};

(function (convAdmin) {
  'use strict';
  
  /**
   * Helper DOM Selector
   */
  convAdmin.$ = (sel, ctx = document) => ctx.querySelector(sel);
  convAdmin.$$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

  /**
   * Simplifies making an Admin WP Ajax request efficiently.
   * Native JS (Fetch), compatible with new WP versions without relying strictly on internal jQuery.
   * @param {string} action - The ajax action hook.
   * @param {FormData|Object} data - Payload.
   * @param {string} nonce - Security token.
   * @param {function} onSuccess - Fires containing parsed JSON data object.
   * @param {function} onError - Fires on fail.
   */
  convAdmin.ajaxPost = function (action, data, nonce, onSuccess, onError) {
      const url = window.ajaxurl || '/wp-admin/admin-ajax.php';
      
      let fd = data instanceof FormData ? data : new FormData();
      if (!(data instanceof FormData)) {
          for (let key in data) {
              fd.append(key, data[key]);
          }
      }

      if (!fd.has('action')) fd.append('action', action);
      if (nonce && !fd.has('nonce')) fd.append('nonce', nonce);

      fetch(url, { method: 'POST', body: fd })
      .then(r => {
          if (!r.ok && r.status !== 200 && r.status !== 204) {
              if (r.status === 403 || r.status === 401) {
                  return { success: false, data: 'Authentication required. Please log in.' };
              }
              if (r.status === 503) {
                  return { success: false, data: 'Service temporarily unavailable.' };
              }
          }
          if (r.status === 204 || r.status === 0) {
              return { success: true, data: 'No content' };
          }
          const contentType = r.headers.get('content-type');
          if (!contentType || !contentType.includes('application/json')) {
              if (r.redirected) {
                  return { success: false, data: 'Redirect detected. Please log in.' };
              }
              return r.text().then(text => ({ 
                  success: false, 
                  data: text.substring(0, 200) + (text.length > 200 ? '...' : '') 
              }));
          }
          return r.json();
      })
      .then(res => {
          if (res.success && onSuccess) {
              onSuccess(res);
          } else if (onError) {
              onError(res);
          }
      })
      .catch(err => {
          console.error("Convoca JS.ajaxPost Admin Fetch Error:', err);
          if (onError) onError({ success: false, data: 'Error HTTP Fetch al portal wp-admin /admin-ajax.php' });
      });
  };

  /**
   * Utility to copy text to clipboard with optional callback.
   * @param {string} text - The string to copy.
   * @param {function} [onSuccess] - Callback after success.
   */
  convAdmin.copyToClipboard = function (text, onSuccess) {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
      if (onSuccess) onSuccess();
    }).catch(err => {
      console.error("Convoca JS.copyToClipboard Admin Error:', err);
    });
  };

})(window.convocaAdmin);

/**
 * Convoca Bulk Action Confirmation
 * Applies to all WP_List_Table forms with [name="action"] selects.
 */
(function () {
  'use strict';

  // Intercept bulk action form submissions
  document.addEventListener('submit', function (e) {
    const form = e.target;
    const select = form.querySelector('select[name="action"], select[name="action2"]');
    if (!select) return;

    const action = select.value;
    if (!action || action === '-1' || action === '') return;

    // Don't confirm if already confirmed (skip flag)
    if (form.dataset.convConfirmed) return;

    const messages = {
      'trash': '¿Mover los elementos seleccionados a la papelera?',
      'delete': '¿Eliminar permanentemente los elementos seleccionados? Esta acción no se puede deshacer.',
      'untrash': '¿Restaurar los elementos seleccionados?',
      'mark_realizado': '¿Marcar como realizados los turnos seleccionados?',
      'mark_no_asistio': '¿Marcar como no asistidos los turnos seleccionados?',
    };

    const message = messages[action] || '¿Confirmas la acción seleccionada sobre los elementos marcados?';

    if (!confirm(message)) {
      e.preventDefault();
      return;
    }

    form.dataset.convConfirmed = '1';
  });

  /**
   * Show a Convoca notification at the top of the page.
   */
  window.convocaNotify = function (message, type) {
    type = type || 'success';
    const existing = document.querySelector('.convoca-notification');
    if (existing) existing.remove();

    const div = document.createElement('div');
    div.className = 'convoca-notification convoca-alert convoca-alert--' + type;
    div.setAttribute('role', 'alert');
    div.style.cssText = 'display:block;margin-bottom:20px;padding:15px;border-radius:8px;font-weight:600;';
    const msgP = document.createElement('p');
    msgP.style.margin = '0';
    msgP.textContent = message;
    div.appendChild(msgP);

    const wrap = document.querySelector('.wrap');
    if (wrap) {
      wrap.insertBefore(div, wrap.firstChild);
    }

    setTimeout(function () {
      div.style.transition = 'opacity 0.5s';
      div.style.opacity = '0';
      setTimeout(function () { if (div.parentNode) div.parentNode.removeChild(div); }, 500);
    }, 5000);
  };

  // Replace standard WP admin notices with convoca notices inside .wrap
  document.querySelectorAll('.notice:not(.convoca-notification)').forEach(function (notice) {
    const wrap = notice.closest('.wrap');
    if (!wrap) return;
    const text = notice.textContent.trim();
    if (!text) return;

    let type = 'info';
    if (notice.classList.contains('notice-success')) type = 'success';
    else if (notice.classList.contains('notice-error')) type = 'danger';
    else if (notice.classList.contains('notice-warning')) type = 'warning';

    window.convocaNotify(text, type);
    notice.style.display = 'none';
  });
})();

/**
 * Convoca Live Email Preview
 * Attaches to .conv-email-preview-init containers and binds real-time preview.
 */
(function () {
  'use strict';

  const SAMPLE_DATA = {
    '{nombre}': 'María García López',
    '{email}': 'maria@ejemplo.com',
    '{plan}': 'Lugg',
    '{importe}': '50€',
    '{link_pago}': 'https://ejemplo.com/pago/ABC123',
    '{numero_socio}': 'S-0042',
    '{fecha_baja}': '31/12/2026',
    '{fecha_actividad}': '15/06/2026',
    '{nombre_actividad}': 'Taller de Permacultura',
    '{fecha_renovacion}': '01/01/2027',
    '{link_evaluacion}': 'https://ejemplo.com/evaluar/XYZ',
    '{evaluador_nombre}': 'María García',
  };

  function debounce(fn, delay) {
    let timer;
    return function () {
      const ctx = this; const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(ctx, args), delay);
    };
  }

  function replaceVariables(text) {
    let result = text || '';
    for (const [key, val] of Object.entries(SAMPLE_DATA)) {
      result = result.split(key).join(val);
    }
    return result;
  }

  function updatePreview(slug) {
    const subjectEl = document.querySelector('[name$="[' + slug + '][subject]"], [name="tpl_' + slug + '_subject"]');
    const bodyEl = document.querySelector('[name$="[' + slug + '][body]"], [name="tpl_' + slug + '_body"]');
    const previewSubject = document.getElementById('conv-preview-subject-' + slug);
    const previewBody = document.getElementById('conv-preview-body-' + slug);

    if (subjectEl && previewSubject) {
      previewSubject.textContent = replaceVariables(subjectEl.value);
    }
    if (bodyEl && previewBody) {
      previewBody.innerHTML = replaceVariables(bodyEl.value);
    }
  }

  function initPreview(slug) {
    const bodyEl = document.querySelector('[name$="[' + slug + '][body]"], [name="tpl_' + slug + '_body"]');
    const subjectEl = document.querySelector('[name$="[' + slug + '][subject]"], [name="tpl_' + slug + '_subject"]');
    if (!bodyEl) return;

    // Add preview container after the body textarea or after the card
    const card = bodyEl.closest('.conv-template-card, .convoca-template-card');
    if (!card) return;

    // Check if preview already exists
    if (card.querySelector('.convoca-email-preview-wrap')) return;

    const editorCol = document.createElement('div');
    editorCol.className = 'convoca-email-editor';

    const previewCol = document.createElement('div');
    previewCol.className = 'convoca-email-preview';

    const previewLabel = document.createElement('strong');
    previewLabel.textContent = '📧 Previsualización';
    previewCol.appendChild(previewLabel);

    if (subjectEl) {
      const subjLine = document.createElement('p');
      subjLine.innerHTML = '<strong>Asunto:</strong> <span id="conv-preview-subject-' + slug + '">' + replaceVariables(subjectEl.value) + '</span>';
      previewCol.appendChild(subjLine);
    }

    const frame = document.createElement('div');
    frame.id = 'conv-preview-body-' + slug;
    frame.className = 'convoca-email-preview-frame';
    frame.innerHTML = replaceVariables(bodyEl.value);
    previewCol.appendChild(frame);

    // Mobile toggle button
    const toggleBtn = document.createElement('button');
    toggleBtn.type = 'button';
    toggleBtn.className = 'convoca-btn convoca-btn-outline convoca-preview-toggle';
    toggleBtn.textContent = '📱 Alternar vista previa';
    toggleBtn.addEventListener('click', function () {
      if (previewCol.style.display === 'none') {
        previewCol.style.display = 'block';
        editorCol.style.display = 'none';
        toggleBtn.textContent = '📝 Volver al editor';
      } else {
        previewCol.style.display = 'none';
        editorCol.style.display = 'block';
        toggleBtn.textContent = '📱 Alternar vista previa';
      }
    });

    // Wrap the body field in the editor column
    const bodyParent = bodyEl.parentNode;
    const subjectParent = subjectEl ? subjectEl.parentNode : null;

    // Create wrap
    const wrap = document.createElement('div');
    wrap.className = 'convoca-email-preview-wrap';

    // Move subject and body into editor column
    editorCol.appendChild(toggleBtn.cloneNode(true));
    if (subjectParent && subjectEl && subjectParent.contains(subjectEl)) {
      editorCol.appendChild(subjectEl.parentNode);
    }
    editorCol.appendChild(bodyEl.parentNode);

    // Fix: move the attachment row if present
    const attachRow = card.querySelector('.convoca-attachment-row');
    if (attachRow) {
      editorCol.appendChild(attachRow);
    }

    wrap.appendChild(editorCol);
    wrap.appendChild(previewCol);
    card.appendChild(wrap);

    // Hide the toggled button in desktop
    // Bind events
    const debouncedUpdate = debounce(function () { updatePreview(slug); }, 300);

    if (subjectEl) subjectEl.addEventListener('input', debouncedUpdate);
    bodyEl.addEventListener('input', debouncedUpdate);

    // Initial update
    updatePreview(slug);
  }

  // Auto-init on DOM ready and observe dynamic content
  function initAll() {
    document.querySelectorAll('.conv-template-card, .convoca-template-card').forEach(function (card) {
      const bodyEl = card.querySelector('textarea[name*="body"]');
      if (!bodyEl) return;
      // Extract slug from name attribute
      const nameAttr = bodyEl.getAttribute('name');
      let slug = '';

      // Members: tpl_{slug}_body
      const flatMatch = nameAttr.match(/tpl_(.+)_body$/);
      if (flatMatch) slug = flatMatch[1];
      if (!slug) {
        // Enroll: tpl[{slug}][body]
        const arrMatch = nameAttr.match(/tpl\[([^\]]+)\]\[body\]/);
        if (arrMatch) slug = arrMatch[1];
      }
      if (slug) initPreview(slug);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();

/**
 * Keyboard Accessibility: Escape key closes modals, focus trap.
 */
(function () {
  'use strict';

  // Close modals with Escape
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      document.querySelectorAll('.convoca-modal.is-active, .conv-modal.is-active, .convoca-modal.is-active').forEach(function (modal) {
        modal.classList.remove('is-active');
        modal.setAttribute('aria-hidden', 'true');
      });
    }
  });

  // Add aria-labels to WP_List_Table checkboxes
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.wp-list-table th.check-column input[type="checkbox"]').forEach(function (cb) {
      if (!cb.hasAttribute('aria-label')) {
        cb.setAttribute('aria-label', cb.getAttribute('aria-label') || 'Seleccionar elemento');
      }
    });
    document.querySelectorAll('.wp-list-table th#cb input[type="checkbox"]').forEach(function (cb) {
      cb.setAttribute('aria-label', 'Seleccionar todos los elementos');
    });
  });
})();

/**
 * Convoca Chips Component
 * Enhances a <select multiple> by converting it into a searchable chips UI.
 * Usage: convocaChips(document.getElementById('my-select'), '/wp-json/.../search?term=');
 */
function convocaChips(select, searchUrl) {
  if (!select || select.tagName !== 'SELECT' || !select.multiple) return;

  var container = document.createElement('div');
  container.className = 'conv-chips-container';
  container.setAttribute('role', 'listbox');
  container.setAttribute('aria-label', select.getAttribute('aria-label') || select.getAttribute('name'));

  var input = document.createElement('input');
  input.type = 'text';
  input.className = 'conv-chips-input';
  input.placeholder = select.getAttribute('data-placeholder') || 'Buscar...';
  container.appendChild(input);

  var dropdown = document.createElement('div');
  dropdown.className = 'conv-chips-dropdown';

  var wrapper = document.createElement('div');
  wrapper.style.position = 'relative';
  select.parentNode.insertBefore(wrapper, select);
  wrapper.appendChild(container);
  wrapper.appendChild(dropdown);
  select.style.display = 'none';

  function renderChips() {
    var chips = container.querySelectorAll('.conv-chip');
    chips.forEach(function (c) { c.remove(); });
    Array.from(select.options).forEach(function (opt) {
      if (opt.selected) {
        var chip = document.createElement('span');
        chip.className = 'conv-chip';
        chip.innerHTML = opt.text + ' <span class="conv-chip-remove" data-value="' + opt.value + '">✕</span>';
        container.insertBefore(chip, input);
      }
    });
    updateDropdownPosition();
  }

  function updateDropdownPosition() {
    var rect = container.getBoundingClientRect();
    dropdown.style.left = '0';
    dropdown.style.top = (container.offsetHeight + 4) + 'px';
  }

  // Remove chip on click
  container.addEventListener('click', function (e) {
    if (e.target.classList.contains('conv-chip-remove')) {
      var val = e.target.dataset.value;
      var opt = select.querySelector('option[value="' + val + '"]');
      if (opt) { opt.selected = false; renderChips(); }
      input.focus();
    }
    if (e.target === container) input.focus();
  });

  // Search
  var timer;
  input.addEventListener('input', function () {
    clearTimeout(timer);
    var term = this.value.trim();
    if (term.length < 2) { dropdown.style.display = 'none'; return; }
    timer = setTimeout(function () {
      fetch(searchUrl + encodeURIComponent(term))
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (!data || !data.length) { dropdown.style.display = 'none'; return; }
          dropdown.innerHTML = '';
          data.forEach(function (item) {
            var opt = select.querySelector('option[value="' + item.id + '"]');
            if (opt && opt.selected) return;
            var el = document.createElement('div');
            el.className = 'conv-chips-dropdown-item';
            el.textContent = item.name + (item.email ? ' (' + item.email + ')' : '');
            el.addEventListener('click', function () {
              var opt = select.querySelector('option[value="' + item.id + '"]');
              if (!opt) {
                opt = new Option(item.name, item.id, false, true);
                select.add(opt);
              } else {
                opt.selected = true;
              }
              renderChips();
              input.value = '';
              dropdown.style.display = 'none';
              input.focus();
            });
            dropdown.appendChild(el);
          });
          dropdown.style.display = 'block';
        }).catch(function () { dropdown.style.display = 'none'; });
    }, 300);
  });

  input.addEventListener('blur', function () { setTimeout(function () { dropdown.style.display = 'none'; }, 200); });
  input.addEventListener('focus', function () { if (input.value.trim().length >= 2) dropdown.style.display = 'block'; });

  renderChips();
}

// Auto-init for any select with data-chips attribute
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('select[data-chips]').forEach(function (sel) {
    convocaChips(sel, sel.getAttribute('data-chips-url') || '/wp-json/convoca-enroll/v1/admin/users/search?term=');
  });
});
