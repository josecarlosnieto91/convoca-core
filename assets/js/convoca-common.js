/**
 * Biodevas Common JS Library
 * Version 1.2.1
 * 
 * Asset versioning: Assets are versioned via the plugin version constant.
 * When updating convoca-common, bump the version to force cache refresh.
 * 
 * @since 1.2.0 Initial release
 * @since 1.2.1 Fixed observeDynamicForms, showAlert whitelist, copyToClipboard fallback
 */
window.convoca = window.convoca || {};

(function (bdv) {
  'use strict';

  // Shorthand array DOM Selectors
  bdv.$ = function(sel, ctx) { ctx = ctx || document; return ctx.querySelector(sel); };
  bdv.$$ = function(sel, ctx) { ctx = ctx || document; return ctx.querySelectorAll(sel) ? Array.prototype.slice.call(ctx.querySelectorAll(sel)) : []; };

/**
    * Displays a stylized alert message in the given block element.
    * @param {HTMLElement} element - The DOM element acting as the alert container.
    * @param {string} message - HTML or simple text message.
    * @param {string} type - 'danger', 'success', or 'warning'. Defaults to 'danger'.
    */
  bdv.showAlert = function (element, message, type) {
    if (!element) return;
    type = type || 'danger';
    if (type !== 'danger' && type !== 'success' && type !== 'warning') {
      type = 'danger';
    }
    element.innerHTML = message;
    element.className = 'convoca-alert convoca-alert--' + type;
    element.style.display = 'block';
    element.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };

  /**
   * Hides the active alert.
   * @param {HTMLElement} element - The alert container to hide.
   */
  bdv.hideAlert = function (element) {
    if (element) {
        element.style.display = 'none';
        element.innerHTML = '';
    }
  };

  /**
   * Validates if the given string is a correct email sequence.
   * @param {string} email - Raw email string.
   * @returns {boolean}
   */
  bdv.validateEmail = function (email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
  };

  /**
   * Validates Spanish DNI/NIE formatting and control letter.
   * @param {string} dni - Raw DNI/NIE sequence.
   * @returns {boolean}
   */
  bdv.validateDNI = function (dni) {
    let raw = (dni || '').toUpperCase().trim();
    if (!/^[XYZ]?\d{7,8}[A-Z]$/.test(raw)) return false;
    raw = raw.replace(/^X/, '0').replace(/^Y/, '1').replace(/^Z/, '2');
    const letters = 'TRWAGMYFPDXBNJZSQVHLCKE';
    const number = parseInt(raw.slice(0, -1), 10);
    const letter = raw.slice(-1);
    return letters.charAt(number % 23) === letter;
  };

  /**
   * Harvests the query string for URL parameters.
   * @param {string} name - URL param name to lookup.
   * @returns {string|null} - Parameter value or null.
   */
  bdv.getUrlParameter = function (name) {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get(name);
  };

  /**
   * Sets UI loading state for a button submission.
   * @param {HTMLElement} button - The button to mutate.
   * @param {boolean} isLoading - Loading context status.
   * @param {string} [originalText] - String baseline to restore after loading.
   */
  bdv.setLoading = function (button, isLoading, originalText = 'Continuar') {
    if (!button) return;
    if (isLoading) {
      if (!button.dataset.originalText) {
          button.dataset.originalText = button.innerHTML || originalText;
      }
      button.disabled = true;
      button.textContent = 'Enviando...';
    } else {
      button.disabled = false;
      button.innerHTML = button.dataset.originalText || originalText;
    }
  };

  /**
   * Centralizes Biodevas fetch logic via AJAX/WordPress handling safely.
   * @param {string} action - WordPress API / Admin-ajax action name.
   * @param {FormData|Object} data - Context data. Form keys and values.
   * @param {string} nonce - The WP Nonce key verifying the request.
   * @param {function} onSuccess - Callback on JSON success payload.
   * @param {function} onError - Callback gracefully intercepting payload or HTTP error limit.
   */
  bdv.ajaxPost = function (action, data, nonce, onSuccess, onError) {
     let fd = data instanceof FormData ? data : new FormData();
     if (!(data instanceof FormData)) {
         for (let key in data) {
             fd.append(key, data[key]);
         }
     }
     
     if (!fd.has('action')) fd.append('action', action);
     if (nonce && !fd.has('nonce')) fd.append('nonce', nonce);

// Use standard WordPress AJAX endpoint.
      const endpoint = window.ajaxurl || '/wp-admin/admin-ajax.php';

      fetch(endpoint, {
         method: 'POST',
         body: fd
      })
      .then(r => {
          // Handle HTTP errors and special responses
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
         if(res.success) {
            if (onSuccess) onSuccess(res);
         } else {
            if (onError) onError(res);
         }
     })
     .catch(err => {
         console.error('Biodevas JS.ajaxPost Fetch Error:', err);
         if (onError) onError({ success: false, data: 'Error HTTP Fetch al portal wp-admin /admin-ajax.php' });
     });
  };

  /**
   * Basic Tab interaction functionality initialization.
   * Uses semantic .convoca-tab-btn and .convoca-tab-content data bindings.
   * Expects DOM struct: 
   *    <button class="convoca-tab-btn active" data-tab-target="pane-1">Tab</button>
   *    <div id="pane-1" class="convoca-tab-content">...</div>
   * @param {string} containerSelector - The DOM CSS selector restricting tab search area.
   */
  bdv.initTabs = function (containerSelector) {
     const container = bdv.$(containerSelector);
     if (!container) return;

     const tabBtns = bdv.$$('.convoca-tab-btn', container);
     const tabPanels = bdv.$$('.convoca-tab-content', container);

     tabBtns.forEach(btn => {
         btn.addEventListener('click', (e) => {
             e.preventDefault();
             // Reset UI state
             tabBtns.forEach(b => b.classList.remove('active'));
             tabPanels.forEach(p => p.style.display = 'none');

             // Display targeted state
             btn.classList.add('active');
             const target = bdv.$('#' + btn.dataset.tabTarget, container);
             if (target) target.style.display = 'block';
         });
     });
  };

/**
     * Uses MutationObserver to attach form listeners logically, avoiding early DOM binding loss. 
     * Useful when React/Preact or external AJAX injections happen after DOMContentLoaded.
     * @param {string} containerSelector - Main wrapper string ID or CSS path to watch.
     * @param {function} callback - Method triggered for every dynamically popped HTML Form. Example fn(formNode).
     */
    bdv.observeDynamicForms = function (containerSelector, callback) {
       var targetNode = bdv.$(containerSelector);
if (!targetNode || targetNode.dataset.bdvObserved) return;
        if (typeof targetNode.querySelectorAll !== 'function') return;
        targetNode.dataset.bdvObserved = 'true';

        var throttleTimeout = null;
        var THROTTLE_MS = 250;

       var safeCallback = function(frm) {
          if (frm.dataset.bdvProcessed) return;
          frm.dataset.bdvProcessed = 'true';
          callback(frm);
       };

       var processMutations = function(mutations) {
          var addedForms = [];
          mutations.forEach(function(mutation) {
             mutation.addedNodes.forEach(function(node) {
                 if (node.nodeType === Node.ELEMENT_NODE) {
                     if (node.tagName && node.tagName.toLowerCase() === 'form') {
                        addedForms.push(node);
                     }
                     try {
                         if (typeof node.querySelectorAll === 'function') {
                             var nestedForms = bdv.$$('form', node);
                             if (nestedForms.length > 0) {
                                addedForms.push.apply(addedForms, nestedForms);
                             }
                         }
                     } catch (e) {}
                 }
             });
          });
          
          if (addedForms.length > 0) {
              addedForms.forEach(function(frm) { safeCallback(frm); });
          }
       };

       var throttledCallback = function(mutations) {
          if (throttleTimeout) return;
          throttleTimeout = setTimeout(function() {
             throttleTimeout = null;
             processMutations(mutations);
          }, THROTTLE_MS);
       };

       var observer = new MutationObserver(throttledCallback);

       observer.observe(targetNode, { childList: true, subtree: true });
       
      // Store observer reference for potential cleanup
      targetNode._bdvFormObserver = observer;
      
      var runInitial = function() {
         var initStatic = bdv.$$('form', targetNode);
         initStatic.forEach(function(frm) { safeCallback(frm); });
      };

      if (document.readyState === 'complete' || document.readyState === 'interactive') {
         runInitial();
      } else {
         document.addEventListener('DOMContentLoaded', runInitial);
      }
   };

   /**
    * Disconnect all form observers for a container.
    * Call this when removing the container from DOM.
    * @param {string} containerSelector - Container selector to disconnect.
    */
   bdv.disconnectDynamicForms = function (containerSelector) {
      var targetNode = bdv.$(containerSelector);
      if (targetNode && targetNode._bdvFormObserver) {
         targetNode._bdvFormObserver.disconnect();
         delete targetNode._bdvFormObserver;
         delete targetNode.dataset.bdvObserved;
      }
   };

/**
    * Universal Form Validator.
    * Enhanced validation supporting all HTML5 field types.
    * Validates: input, textarea, select, checkbox groups, radios, email, pattern, min/max.
    */
   bdv.form = {
       validate: function (form) {
           let isOk = true;
           const fields = form.querySelectorAll('[required]');
           
           fields.forEach(f => {
               const tag = f.tagName.toLowerCase();
               const field = f.closest('.convoca-field');
               let valid = true;
               
               if (tag === 'textarea') {
                   valid = f.value.trim().length > 0;
               } else if (tag === 'select') {
                   valid = f.value !== '' && f.value !== null;
               } else if (f.type === 'checkbox') {
                   const groupName = f.name;
                   const groupChecked = form.querySelectorAll('input[name="'+groupName+'"]:checked');
                   valid = groupChecked.length > 0;
               } else if (f.type === 'radio') {
                   valid = f.checked;
               } else if (f.type === 'input' || f.type === 'text' || f.type === 'email' || f.type === 'tel' || f.type === 'number' || f.type === 'url' || f.type === 'password' || f.type === 'date' || f.type === 'time' || f.type === 'datetime-local') {
                   valid = f.value.trim().length > 0;
                   if (valid && f.type === 'email') {
                       valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.value);
                   }
                   if (valid && f.pattern) {
                       try {
                           valid = new RegExp(f.pattern).test(f.value);
                       } catch (e) {}
                   }
                   if (valid && f.minLength && f.value.length < f.minLength) {
                       valid = false;
                   }
                   if (valid && f.maxLength && f.value.length > f.maxLength) {
                       valid = false;
                   }
                   if (valid && f.type === 'number') {
                       const num = parseFloat(f.value);
                       if (isNaN(num)) {
                           valid = false;
                       } else {
                           if (f.min !== '' && num < parseFloat(f.min)) valid = false;
                           if (f.max !== '' && num > parseFloat(f.max)) valid = false;
                       }
                   }
               }
               
               if (!valid) {
                   if (field) field.classList.add('has-error');
                   isOk = false;
               } else {
                   if (field) field.classList.remove('has-error');
               }
           });
           return isOk;
       }
   };

/**
    * Utility to copy text to clipboard with optional callback.
    * Includes fallback for browsers without Clipboard API.
    * @param {string} text - The string to copy.
    * @param {function} [onSuccess] - Callback after success.
    */
   bdv.copyToClipboard = function (text, onSuccess) {
     if (!text) return;
     
     var copyFallback = function() {
       var textarea = document.createElement('textarea');
       textarea.value = text;
       textarea.style.position = 'fixed';
       textarea.style.left = '-9999px';
       document.body.appendChild(textarea);
       textarea.select();
       try {
         var successful = document.execCommand('copy');
         document.body.removeChild(textarea);
         if (successful && onSuccess) {
           onSuccess();
         } else if (!successful) {
           console.error('Biodevas JS.copyToClipboard: execCommand copy failed');
         }
       } catch (err) {
         document.body.removeChild(textarea);
         console.error('Biodevas JS.copyToClipboard Fallback Error:', err);
       }
     };
     
     if (navigator.clipboard && navigator.clipboard.writeText) {
       navigator.clipboard.writeText(text).then(function() {
         if (onSuccess) onSuccess();
       }).catch(function(err) {
         console.error('Biodevas JS.copyToClipboard Error:', err);
         copyFallback();
       });
     } else {
       copyFallback();
     }
   };

/**
    * Safe localStorage wrapper with versioned schema
    */
   bdv.storage = {
       VERSION_KEY: 'convoca_version',
       set: function(key, value) {
           try {
               localStorage.setItem('convoca_' + key, JSON.stringify(value));
           } catch(e) {}
       },
       get: function(key, defaultValue) {
           try {
               var raw = localStorage.getItem('convoca_' + key);
               if (!raw) return defaultValue;
               var parsed = JSON.parse(raw);
               return parsed;
           } catch(e) {
               try {
                   localStorage.removeItem('convoca_' + key);
               } catch(e2) {}
               return defaultValue;
           }
       },
       remove: function(key) {
           try {
               localStorage.removeItem('convoca_' + key);
           } catch(e) {}
       }
   };

  /**
   * Debounce execution of a function
   * @param {function} func - Function to execute
   * @param {number} wait - Wait time in milliseconds
   * @param {boolean} [immediate] - Trigger immediately
   */
  bdv.debounce = function(func, wait, immediate) {
      var timeout;
      return function() {
          var context = this, args = arguments;
          var later = function() {
              timeout = null;
              if (!immediate) func.apply(context, args);
          };
          var callNow = immediate && !timeout;
          clearTimeout(timeout);
          timeout = setTimeout(later, wait);
          if (callNow) func.apply(context, args);
      };
  };

  /**
   * Validates Spanish phone number (9 digits, mobile or landline).
   * @param {string} phone
   * @returns {boolean}
   */
  bdv.validatePhone = function (phone) {
    return /^\d{9}$/.test((phone || '').trim());
  };

  /**
   * Shows an inline error message under a .convoca-field element.
   * @param {HTMLElement} field - The .convoca-field wrapper.
   * @param {string} message - Error text to display.
   */
  bdv.showFieldError = function (field, message) {
    if (!field) return;
    field.classList.add('has-error');
    let msgEl = field.querySelector('.convoca-error-msg');
    if (!msgEl) {
      msgEl = document.createElement('small');
      msgEl.className = 'convoca-error-msg';
      field.appendChild(msgEl);
    }
    msgEl.textContent = message;
    msgEl.style.display = 'block';
  };

  /**
   * Clears the inline error for a .convoca-field element.
   * @param {HTMLElement} field
   */
  bdv.clearFieldError = function (field) {
    if (!field) return;
    field.classList.remove('has-error');
    const msgEl = field.querySelector('.convoca-error-msg');
    if (msgEl) {
      msgEl.textContent = '';
      msgEl.style.display = 'none';
    }
  };

  /**
   * Validates a single field and shows/clears inline error.
   * @param {HTMLElement} field - .convoca-field wrapper.
   * @returns {boolean}
   */
  bdv.validateField = function (field) {
    if (!field) return true;
    const input = field.querySelector('input, select, textarea');
    if (!input) return true;
    const required = input.hasAttribute('required');
    const val = (input.value || '').trim();

    // Clear any existing error
    bdv.clearFieldError(field);

    // Required check
    if (required && !val) {
      const label = field.querySelector('label');
      const name = label ? label.textContent.trim() : input.name;
      bdv.showFieldError(field, 'Este campo es obligatorio.');
      return false;
    }

    if (!val) return true; // Optional empty field is ok

    // Type-specific validation
    const type = input.type || '';
    const name = input.name || '';
    if (type === 'email' || name === 'email') {
      if (!bdv.validateEmail(val)) {
        bdv.showFieldError(field, 'Introduce un email válido.');
        return false;
      }
    }
    if (name === 'dni') {
      if (!bdv.validateDNI(val)) {
        bdv.showFieldError(field, 'El DNI/NIE no es válido. Revisa la letra.');
        return false;
      }
    }
    if (name === 'telefono') {
      if (!bdv.validatePhone(val)) {
        bdv.showFieldError(field, 'Introduce un teléfono de 9 dígitos.');
        return false;
      }
    }

    return true;
  };

  /**
   * Fallback for :has() CSS selector on rating stars.
   * Adds 'is-checked' class when radio is checked.
   */
  bdv.initRatingStars = function () {
    document.querySelectorAll('.convoca-rating-stars').forEach(function (group) {
      group.querySelectorAll('input[type="radio"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
          // Remove is-checked from all siblings in same group
          group.querySelectorAll('.convoca-rating-star').forEach(function (star) {
            star.classList.remove('is-checked');
          });
          // Add it to the clicked star's parent label
          var label = radio.closest('.convoca-rating-star');
          if (label) label.classList.add('is-checked');
        });
        // Set initial state
        if (radio.checked) {
          var label = radio.closest('.convoca-rating-star');
          if (label) label.classList.add('is-checked');
        }
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bdv.initRatingStars);
  } else {
    bdv.initRatingStars();
  }

})(window.convoca);
