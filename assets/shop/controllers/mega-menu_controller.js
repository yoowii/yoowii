import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'trigger'];

    connect() {
        this.closeTimer = null;
    }

    disconnect() {
        window.clearTimeout(this.closeTimer);
    }

    toggle() {
        this.panelTarget.classList.contains('hidden') ? this.open() : this.close();
    }

    open() {
        window.clearTimeout(this.closeTimer);
        this.panelTarget.classList.remove('hidden');
        this.triggerTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.panelTarget.classList.add('hidden');
        this.triggerTarget.setAttribute('aria-expanded', 'false');
    }

    scheduleClose() {
        this.closeTimer = window.setTimeout(() => this.close(), 180);
    }

    cancelClose() {
        window.clearTimeout(this.closeTimer);
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') this.close();
    }
}
