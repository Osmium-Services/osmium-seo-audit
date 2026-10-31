(function () {
    'use strict';

    const config = window.seoAuditConfig || {};
    const form = document.getElementById('seo-settings-form');
    const result = document.getElementById('seo-settings-result');

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

        result.className = 'small ' + (data.success ? 'text-success' : 'text-danger');
        result.textContent = data.success ? 'Saved' : (data.error || 'Could not save');
    });
})();
