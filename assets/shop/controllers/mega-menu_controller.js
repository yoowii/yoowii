import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['backdrop', 'family', 'familyPanel', 'panel', 'trigger'];

    connect() {
        this.closeTimer = null;
        this.currentMenuId = null;
    }

    disconnect() {
        window.clearTimeout(this.closeTimer);
    }

    toggle(event) {
        const menuId = event.params.menuId;

        if (this.currentMenuId === menuId && this.isOpen()) {
            this.close();

            return;
        }

        this.open(menuId);
    }

    openFromTrigger(event) {
        this.open(event.params.menuId);
    }

    open(menuId) {
        window.clearTimeout(this.closeTimer);
        this.currentMenuId = menuId;
        this.panelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.megaMenuMenuId !== menuId;
        });
        this.triggerTargets.forEach((trigger) => {
            trigger.setAttribute('aria-expanded', trigger.dataset.megaMenuMenuIdParam === menuId ? 'true' : 'false');
        });
        this.backdropTarget.hidden = false;
        this.selectFamily(this.familyTargets[0]?.dataset.familyId);
    }

    close() {
        window.clearTimeout(this.closeTimer);
        this.panelTargets.forEach((panel) => { panel.hidden = true; });
        this.triggerTargets.forEach((trigger) => { trigger.setAttribute('aria-expanded', 'false'); });
        this.backdropTarget.hidden = true;
        this.currentMenuId = null;
    }

    scheduleClose() {
        window.clearTimeout(this.closeTimer);
        this.closeTimer = window.setTimeout(() => this.close(), 150);
    }

    closeOnEscape(event) {
        if (event.key === 'Escape' && this.isOpen()) {
            this.close();
            this.triggerTargets[0]?.focus();
        }
    }

    activateFamily(event) {
        this.selectFamily(event.currentTarget.dataset.familyId);
    }

    trapFocus(event) {
        if (event.key !== 'Tab') return;

        const panel = this.panelTargets.find((item) => !item.hidden);
        const focusable = panel ? [...panel.querySelectorAll('a[href], button:not([disabled])')].filter((item) => !item.closest('[hidden]')) : [];
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
            family.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        this.familyPanelTargets.forEach((panel) => {
            panel.hidden = panel.dataset.familyId !== familyId;
        });
    }

    isOpen() {
        return this.panelTargets.some((panel) => !panel.hidden);
    }
}
