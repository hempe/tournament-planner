(function () {
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setup);
        } else {
            setup();
        }
    }

    function setup() {
        window.customSocialPrompt = function (data) {
            const t = window.translations?.social || {};
            const dialog = document.createElement('div');
            dialog.className = 'custom-confirm';
            dialog.style.display = 'flex';
            dialog.innerHTML = `
                <div class="custom-confirm-content">
                    <div class="card">
                        <div class="card-title">${data.message}</div>
                        <div class="custom-confirm-actions">
                            <button data-action="no">${t.prompt_no || 'Nein danke'}</button>
                            <button primary data-action="yes">${t.prompt_yes || 'Ja, anmelden'}</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(dialog);

            const close = () => document.body.removeChild(dialog);

            dialog.querySelector('[data-action="yes"]').onclick = () => {
                close();
                window.location.href = '/social-events/' + data.id;
            };
            dialog.querySelector('[data-action="no"]').onclick = close;
            dialog.onclick = (e) => { if (e.target === dialog) close(); };

            document.addEventListener('keydown', function esc(e) {
                if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
            });
        };

        window.customUnregisterChoice = function () {
            return new Promise((resolve) => {
                const t = window.translations?.social || {};
                const cancel = window.translations?.confirm?.cancel || 'Abbrechen';
                const dialog = document.createElement('div');
                dialog.className = 'custom-confirm';
                dialog.style.display = 'flex';
                dialog.innerHTML = `
                    <div class="custom-confirm-content">
                        <div class="card">
                            <div class="card-title">${t.unregister_title || 'Abmelden'}</div>
                            <div class="custom-confirm-message">${t.unregister_message || ''}</div>
                            <div class="custom-confirm-actions">
                                <button data-action="cancel">${cancel}</button>
                                <button accent data-action="tournament">${t.tournament_only || 'Nur Turnier'}</button>
                                <button accent data-action="both">${t.both || 'Turnier & Abendessen'}</button>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);

                const close = (value) => {
                    document.body.removeChild(dialog);
                    resolve(value);
                };

                dialog.querySelector('[data-action="cancel"]').onclick = () => close(null);
                dialog.querySelector('[data-action="tournament"]').onclick = () => close('tournament');
                dialog.querySelector('[data-action="both"]').onclick = () => close('both');
                dialog.onclick = (e) => { if (e.target === dialog) close(null); };

                document.addEventListener('keydown', function esc(e) {
                    if (e.key === 'Escape') { close(null); document.removeEventListener('keydown', esc); }
                });
            });
        };
    }

    init();
})();
