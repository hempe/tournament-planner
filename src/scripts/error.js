(function () {
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupError);
        } else {
            setupError();
        }
    }

    function setupError() {
        window.customError = function (message) {
            // Create the error popup element
            const errorPopup = document.createElement('div');
            errorPopup.className = 'custom-error';
            errorPopup.style.display = 'flex';

            // Use accent styling and match the UI structure
            const translations = window.translations?.error || {};
            errorPopup.innerHTML = `
                <div class="custom-error-content">
                    <div class="card accent">
                        <div class="card-title accent">${translations.title || 'Fehler'}</div>
                        <div class="custom-error-message">${message}</div>
                        <div class="custom-error-actions">
                            <button class="button" accent data-action="close">
                                <i class="fa-solid fa-x"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Add to DOM
            document.body.appendChild(errorPopup);

            // Get close button reference
            const closeButton = errorPopup.querySelector('[data-action="close"]');

            // Handle close actions
            const closePopup = () => {
                document.body.removeChild(errorPopup);
            };

            closeButton.onclick = closePopup;

            // Close on outside click
            errorPopup.onclick = (e) => {
                if (e.target === errorPopup) {
                    closePopup();
                }
            };

            // Close on Escape key
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    closePopup();
                    document.removeEventListener('keydown', handleEscape);
                }
            };

            // Handle Enter key as close action
            const handleEnter = (e) => {
                if (e.key === 'Enter') {
                    closePopup();
                    document.removeEventListener('keydown', handleEnter);
                }
            };

            document.addEventListener('keydown', handleEscape);
            document.addEventListener('keydown', handleEnter);
        };
    }

    // Initialize
    init();
})();
