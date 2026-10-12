import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['backdrop', 'family', 'familyPanel', 'panel', 'trigger'];

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
        this.backdropTarget.classList.remove('hidden');
        this.triggerTarget.setAttribute('aria-expanded', 'true');
        this.selectFamily(this.familyTargets[0]?.dataset.familyId);
    }

    close() {
        this.panelTarget.classList.add('hidden');
        this.backdropTarget.classList.add('hidden');
        this.triggerTarget.setAttribute('aria-expanded', 'false');
    }

    scheduleClose() {
        this.closeTimer = window.setTimeout(() => this.close(), 180);
    }

    cancelClose() {
        window.clearTimeout(this.closeTimer);
    }

    closeOnEscape(event) {
        if (event.key !== 'Escape' || this.panelTarget.classList.contains('hidden')) return;

        this.close();
        this.triggerTarget.focus();
    }

    activateFamily(event) {
        this.selectFamily(event.currentTarget.dataset.familyId);
    }

    trapFocus(event) {
        if (event.key !== 'Tab') return;

        const focusable = [...this.panelTarget.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')]
            .filter((element) => !element.closest('[hidden]'));

        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    selectFamily(familyId) {
        if (!familyId) return;

        this.familyTargets.forEach((family) => {
            const active = family.dataset.familyId === familyId;
            family.classList.toggle('bg-[#F6F0FF]', active);
            family.classList.toggle('text-primary', active);
            family.setAttribute('aria-current', active ? 'true' : 'false');
        });

        this.familyPanelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.familyId !== familyId;
        });
    }
}
