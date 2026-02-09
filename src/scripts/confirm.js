(function () {
    // Wait for DOM to be ready if not already loaded
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupConfirm);
        } else {
            setupConfirm();
        }
    }

    function setupConfirm() {
        // Custom confirm function
        window.customConfirm = function (message, { warn = false } = {}) {
            return new Promise((resolve) => {
                // Create the dialog element
                const confirmDialog = document.createElement('div');
                confirmDialog.id = 'customConfirm';
                confirmDialog.className = 'custom-confirm';
                confirmDialog.style.display = 'flex';

                // Create the inner content
                confirmDialog.innerHTML = `
                    <div class="custom-confirm-content">
                        <div class="card">
                            <div class="card-title">Bestätigung</div>
                            <div class="custom-confirm-message">${message}</div>
                            <div class="custom-confirm-actions">
                                <button data-action="cancel">Abbrechen</button>
                                <button ${warn ? 'accent' : 'primary'} data-action="confirm">Bestätigen</button>
                            </div>
                        </div>
                    </div>
                `;

                // Add to DOM
                document.body.appendChild(confirmDialog);

                // Get button references
                const confirmButton = confirmDialog.querySelector('[data-action="confirm"]');
                const cancelButton = confirmDialog.querySelector('[data-action="cancel"]');

                // Handle button clicks
                confirmButton.onclick = () => {
                    document.body.removeChild(confirmDialog);
                    resolve(true);
                };

                cancelButton.onclick = () => {
                    document.body.removeChild(confirmDialog);
                    resolve(false);
                };

                // Close on outside click
                confirmDialog.onclick = (e) => {
                    if (e.target === confirmDialog) {
                        document.body.removeChild(confirmDialog);
                        resolve(false);
                    }
                };

                // Close on Escape key
                const handleEscape = (e) => {
                    if (e.key === 'Escape') {
                        document.body.removeChild(confirmDialog);
                        resolve(false);
                        document.removeEventListener('keydown', handleEscape);
                    }
                };

                // Handle Enter key
                const handleEnter = (e) => {
                    if (e.key === 'Enter') {
                        document.body.removeChild(confirmDialog);
                        resolve(true);
                        document.removeEventListener('keydown', handleEnter);
                    }
                };

                document.addEventListener('keydown', handleEscape);
                document.addEventListener('keydown', handleEnter);
            });
        };
    }

    // Initialize
    init();
})();
