import { Controller } from '@hotwired/stimulus';

// The supplier script sets input#code_liaison programmatically without emitting
// an event. Polling only begins while this component is visible and is stopped
// as soon as a code has been converted into a Yoowii quote.
export default class extends Controller {
    static values = { quoteUrl: String, csrfToken: String };
    static targets = ['quoteResult', 'error'];

    connect() {
        this.lastCode = null;
        this.pending = false;
        this.timer = window.setInterval(() => this.captureCode(), 350);
    }

    disconnect() {
        window.clearInterval(this.timer);
    }

    async captureCode() {
        const field = this.element.querySelector('#code_liaison');
        const code = field?.value?.trim();
        if (!code || code === this.lastCode || this.pending) {
            return;
        }
        const quantity = this.quantity();
        if (!quantity) {
            this.showError('La quantité Préscript sélectionnée est introuvable.');
            return;
        }
        this.pending = true;
        this.lastCode = code;
        try {
            const response = await fetch(this.quoteUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ code, quantity, _token: this.csrfTokenValue }),
            });
            const payload = await response.json();
            if (!response.ok || !payload.quote_html) {
                throw new Error(payload.message || 'Le prix Préscript ne peut pas être calculé.');
            }
            this.quoteResultTarget.innerHTML = payload.quote_html;
            this.errorTarget.classList.add('d-none');
        } catch (error) {
            this.showError(error.message || 'Le prix Préscript ne peut pas être calculé.');
        } finally {
            this.pending = false;
        }
    }

    quantity() {
        const field = this.element.querySelector('[name="quantity"], [name="quantite"], #quantity, #quantite');
        const value = field?.value?.trim();
        return value && /^\d+$/.test(value) && Number(value) > 0 ? Number(value) : null;
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.classList.remove('d-none');
    }
}
