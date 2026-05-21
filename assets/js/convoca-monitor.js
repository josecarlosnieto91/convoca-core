/**
 * Biodevas Monitor — Live polling dashboard metrics.
 * Vanilla JS, no jQuery dependencies.
 *
 * @package Biodevas\Common
 * @version 1.0.0
 */

(function () {
    'use strict';

    /* ── Configuration ──────────────────────────── */
    var POLL_INTERVAL = 30000;          // 30 seconds
    var FETCH_TIMEOUT = 10000;          // 10 seconds before timeout
    var MAX_FAILURES  = 3;              // consecutive failures before backoff
    var BACKOFF_MS    = 60000;          // 60 seconds backoff
    var FAIL_GRACE    = 3;              // failures needed before showing "desconectado"

    /* ── State ──────────────────────────────────── */
    var consecutiveFailures = 0;
    var isBackingOff = false;
    var pollTimer = null;
    var dotEl = document.getElementById('bdv-live-dot');
    var labelEl = document.getElementById('bdv-monitor-label');

    /* ── DOM helpers ─────────────────────────────── */
    function getMetricCard(key) {
        return document.querySelector('.bdv-metric-card[data-metric="' + key + '"] .bdv-card-value');
    }

    function getMetricCardInner(key) {
        return document.querySelector('.bdv-metric-card[data-metric="' + key + '"] .bdv-metric-card-inner');
    }

    function setStatus(connected) {
        if (!dotEl || !labelEl) return;
        if (connected) {
            dotEl.className = 'bdv-live-dot bdv-live-dot--online';
            labelEl.textContent = 'En vivo';
        } else {
            dotEl.className = 'bdv-live-dot bdv-live-dot--offline';
            if (isBackingOff) {
                labelEl.textContent = 'Reconectando en 60s…';
            } else {
                labelEl.textContent = 'Desconectado';
            }
        }
    }

    function setCardError(key, hasError) {
        var inner = getMetricCardInner(key);
        if (!inner) return;
        if (hasError) {
            inner.classList.add('bdv-card-error');
        } else {
            inner.classList.remove('bdv-card-error');
        }
    }

    function clearAllCardErrors() {
        document.querySelectorAll('.bdv-metric-card-inner.bdv-card-error').forEach(function (el) {
            el.classList.remove('bdv-card-error');
        });
    }

    function formatValue(key, raw) {
        // Payments: format as euros
        if (key === 'payments_month') {
            var num = parseFloat(raw);
            return isNaN(num) ? raw : num.toFixed(2) + '\u20AC';
        }
        return raw;
    }

    /* ── Render ─────────────────────────────────── */
    function renderMetrics(data) {
        if (!data) return;

        var keys = [
            'members_activos', 'members_new_month',
            'inscripciones_pendientes_pago', 'payments_month',
            'turnos_sin_cubrir'
        ];

        clearAllCardErrors();

        keys.forEach(function (key) {
            var el = getMetricCard(key);
            if (el) {
                var val = data[key] !== undefined ? data[key] : '\u2014';
                el.textContent = formatValue(key, val);
            } else {
                // Card doesn't exist yet, nothing to update
            }
        });

        // Update recent activity
        var activityEl = document.getElementById('bdv-recent-activity');
        if (activityEl && data.recent_activity && Array.isArray(data.recent_activity)) {
            if (data.recent_activity.length === 0) {
                activityEl.innerHTML = '<li class="bdv-empty">No hay actividad reciente.</li>';
            } else {
                var html = '';
                data.recent_activity.forEach(function (entry) {
                    var context = entry.context || '\u2014';
                    var message = (entry.message || '').substring(0, 80);
                    var time = entry.created_at || '';
                    html += '<li><span class="bdv-activity-context">[' + escapeHtml(context) + ']</span> <span class="bdv-activity-msg">' + escapeHtml(message) + '</span> <span class="bdv-activity-time">' + escapeHtml(time) + '</span></li>';
                });
                activityEl.innerHTML = html;
            }
        }

        // Update alerts
        var alertsEl = document.getElementById('bdv-alerts-list');
        if (alertsEl) {
            // We don't fetch alerts from REST currently, but we could if needed
            // For now keep the server-rendered alerts
        }

        // Update timestamp
        if (data.timestamp) {
            var tsEl = document.querySelector('.bdv-monitor-header .bdv-monitor-status');
            if (tsEl) {
                // optional: could show last update time
            }
        }
    }

    /* ── Utilities ───────────────────────────────── */
    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    /* ── Fetch Metrics ──────────────────────────── */
    function fetchMetrics() {
        if (isBackingOff) return;

        var url = (window.bdvMonitor && window.bdvMonitor.restUrl) || '/wp-json/convoca/v1/admin/metrics';
        var nonce = (window.bdvMonitor && window.bdvMonitor.nonce) || '';
        var controller = new AbortController();
        var timeoutId = setTimeout(function () {
            controller.abort();
        }, FETCH_TIMEOUT);

        fetch(url, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-WP-Nonce': nonce
            },
            signal: controller.signal,
            credentials: 'same-origin'
        })
        .then(function (response) {
            clearTimeout(timeoutId);
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            consecutiveFailures = 0;
            setStatus(true);
            renderMetrics(data);
        })
        .catch(function (err) {
            clearTimeout(timeoutId);
            consecutiveFailures++;
            if (consecutiveFailures >= FAIL_GRACE) {
                setStatus(false);
            }
            if (consecutiveFailures >= MAX_FAILURES) {
                enterBackoff();
            }
        });
    }

    /* ── Backoff ─────────────────────────────────── */
    function enterBackoff() {
        if (isBackingOff) return;
        isBackingOff = true;
        setStatus(false);

        if (pollTimer) {
            clearTimeout(pollTimer);
            pollTimer = null;
        }

        setTimeout(function () {
            isBackingOff = false;
            consecutiveFailures = 0;
            setStatus(true);
            // Immediately fetch after backoff
            fetchMetrics();
            scheduleNext();
        }, BACKOFF_MS);
    }

    /* ── Scheduling ──────────────────────────────── */
    function scheduleNext() {
        if (pollTimer) {
            clearTimeout(pollTimer);
        }
        pollTimer = setTimeout(function () {
            fetchMetrics();
            scheduleNext();
        }, POLL_INTERVAL);
    }

    /* ── Init ────────────────────────────────────── */
    function init() {
        // Immediate fetch on page load
        fetchMetrics();
        // Schedule periodic polling
        scheduleNext();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
