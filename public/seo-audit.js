(function () {
    'use strict';

    const config = window.seoAuditConfig || {};
    const runButton = document.getElementById('seo-run-btn');
    const card = document.getElementById('seo-progress-card');
    const label = document.getElementById('seo-progress-label');
    const count = document.getElementById('seo-progress-count');
    const bar = document.getElementById('seo-progress-bar');
    const errorBox = document.getElementById('seo-error');

    async function post(payload) {
        const response = await fetch(config.actionUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ csrf_token: window.csrfToken }, payload))
        });

        return response.json();
    }

    function showError(message) {
        errorBox.textContent = message;
        errorBox.classList.remove('d-none');
        runButton.disabled = false;
    }

    function showProgress(state) {
        const percent = state.total > 0 ? Math.round((state.done / state.total) * 100) : 0;
        label.textContent = 'Checking pages...';
        count.textContent = state.done + ' of ' + state.total + ' items';
        bar.style.width = percent + '%';
    }

    async function follow(runId) {
        card.classList.remove('d-none');
        runButton.disabled = true;

        for (;;) {
            const state = await post({ action: 'step', run: runId });
            if (!state.success) {
                showError(state.error || 'The audit stopped');
                return;
            }

            showProgress(state);
            if (state.finished) break;
        }

        label.textContent = 'Finished. Loading the report...';
        window.location.href = config.reportUrl + '?run=' + runId;
    }

    runButton.addEventListener('click', async function () {
        errorBox.classList.add('d-none');
        runButton.disabled = true;

        const started = await post({ action: 'start' });
        if (!started.success) {
            showError(started.error || 'Could not start the audit');
            return;
        }

        follow(started.run);
    });

    if (config.runningId) follow(config.runningId);
})();
