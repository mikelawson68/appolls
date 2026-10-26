(function () {
    'use strict';
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form.classList || !form.classList.contains('appolls-poll')) return;
        event.preventDefault();
        var button = form.querySelector('.appolls-submit');
        var feedback = form.querySelector('.appolls-feedback');
        button.disabled = true; button.textContent = 'Submitting…'; feedback.textContent = '';
        fetch((window.apPollsConfig && window.apPollsConfig.ajaxUrl) || form.action, {method:'POST', credentials:'same-origin', body:new URLSearchParams(new FormData(form))})
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                if (!payload.success) throw new Error(payload.data && payload.data.message ? payload.data.message : 'Could not record response.');
                var shell = form.closest('.appolls-shell'); shell.outerHTML = payload.data.html;
            }).catch(function (error) { feedback.textContent = error.message; button.disabled = false; button.textContent = 'Submit response'; });
    });
}());
