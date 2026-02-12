(function () {
    function init() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupSuccess);
        } else {
            setupSuccess();
        }
    }

    function setupSuccess() {
        window.customSuccess = function (message) {
            // Create the success popup element
            const successPopup = document.createElement('div');
            successPopup.className = 'custom-success';
            successPopup.style.display = 'flex';

            // Use primary styling and match the UI structure
            successPopup.innerHTML = `
                <div class="custom-success-content">
                    <div class="card primary">
                        <div class="card-title primary">Erfolg</div>
                        <div class="custom-success-message">${message}</div>
                        <div class="custom-success-actions">
                            <button class="button" primary data-action="close">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Add to DOM
            document.body.appendChild(successPopup);

            // Get close button reference
            const closeButton = successPopup.querySelector('[data-action="close"]');

            // Handle close actions
            const closePopup = () => {
                document.body.removeChild(successPopup);
            };

            closeButton.onclick = closePopup;

            // Close on outside click
            successPopup.onclick = (e) => {
                if (e.target === successPopup) {
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
