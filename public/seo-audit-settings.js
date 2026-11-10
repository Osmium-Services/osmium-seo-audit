(function () {
    'use strict';

    const config = window.seoAuditConfig || {};
    const form = document.getElementById('seo-settings-form');
    const alertSlot = document.getElementById('seo-settings-alert');

    // Same markup as the standard settings-saved banner the other settings pages print
    function showAlert(type, message) {
        const box = document.createElement('div');
        box.className = 'alert alert-' + type + ' alert-dismissible fade show';
        box.setAttribute('role', 'alert');
        box.appendChild(document.createTextNode(message));
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close';
        close.setAttribute('data-bs-dismiss', 'alert');
        close.setAttribute('aria-label', 'Close');
        box.appendChild(close);
        alertSlot.replaceChildren(box);
    }

    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const thresholds = {};
        form.querySelectorAll('.seo-threshold').forEach(function (input) {
            thresholds[input.dataset.name] = parseInt(input.value, 10);
        });

        const response = await fetch(config.actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                csrf_token: window.csrfToken,
                action: 'save_settings',
                pageCap: parseInt(document.getElementById('pageCap').value, 10),
                delayMs: parseInt(document.getElementById('delayMs').value, 10),
                baseUrl: document.getElementById('baseUrl').value,
                thresholds: thresholds
            })
        });
        const data = await response.json();

        showAlert(data.success ? 'success' : 'danger', data.success ? 'Settings saved successfully!' : (data.error || 'Could not save'));
    });
})();
