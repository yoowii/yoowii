import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['backdrop', 'carouselSlide', 'family', 'familyNavigation', 'familyPanel', 'panel', 'servicePanel', 'trigger'];

    connect() {
        this.closeTimer = null;
        this.currentMenuId = null;
        this.lastTrigger = null;
        this.updateHeaderOffset = this.updateHeaderOffset.bind(this);
        this.updateHeaderOffset();
        window.addEventListener('resize', this.updateHeaderOffset);
    }

    disconnect() {
        window.clearTimeout(this.closeTimer);
        window.removeEventListener('resize', this.updateHeaderOffset);
    }

    toggle(event) {
        const menuId = event.params.menuId;
        this.lastTrigger = event.currentTarget;

        if (this.currentMenuId === menuId && this.isOpen()) {
            this.close();
            return;
        }

        this.open(menuId, event.currentTarget);
    }

    openFromTrigger(event) {
        this.open(event.params.menuId, event.currentTarget);
    }

    openFamily(event) {
        const familyId = event.params.familyId;
        this.open(`family-${familyId}`, this.triggerForFamily(familyId));
    }

    activateFamily(event) {
        const familyId = event.params.familyId;
        this.open(`family-${familyId}`, this.triggerForFamily(familyId));
    }

    open(menuId, trigger = null) {
        window.clearTimeout(this.closeTimer);
        this.currentMenuId = menuId;
        this.lastTrigger = trigger ?? this.lastTrigger;
        this.panelTarget.hidden = false;
        this.backdropTarget.hidden = false;
        this.triggerTargets.forEach((item) => item.setAttribute('aria-expanded', item.dataset.megaMenuMenuIdParam === menuId ? 'true' : 'false'));

        const familyId = menuId === 'all' ? this.familyTargets[0]?.dataset.familyId : menuId.replace('family-', '');
        this.selectFamily(familyId);
    }

    close() {
        window.clearTimeout(this.closeTimer);
        this.panelTarget.hidden = true;
        this.backdropTarget.hidden = true;
        this.triggerTargets.forEach((trigger) => trigger.setAttribute('aria-expanded', 'false'));
        this.currentMenuId = null;
    }

    closeOnEscape(event) {
        if (event.key !== 'Escape' || !this.isOpen()) return;

        this.close();
        this.lastTrigger?.focus();
    }

    closeOnFocusOut() {
        window.setTimeout(() => {
            if (this.isOpen() && !this.element.contains(document.activeElement)) this.close();
        });
    }

    scheduleClose() {
        window.clearTimeout(this.closeTimer);
        this.closeTimer = window.setTimeout(() => this.close(), 150);
    }

    cancelClose() {
        window.clearTimeout(this.closeTimer);
    }

    previousSlide(event) {
        this.showSlide(event.params.carouselId, -1);
    }

    nextSlide(event) {
        this.showSlide(event.params.carouselId, 1);
    }

    selectFamily(familyId) {
        if (!familyId) return;

        const focused = this.isFocusedMenu(familyId);
        this.familyNavigationTarget.hidden = focused;

        this.familyTargets.forEach((family) => {
            const active = family.dataset.familyId === familyId;
            family.classList.toggle('bg-base-200', active);
            family.classList.toggle('text-primary', active);
            family.setAttribute('aria-current', active ? 'true' : 'false');
        });
        this.familyPanelTargets.forEach((panel) => { panel.hidden = panel.dataset.familyId !== familyId; });
        this.servicePanelTarget.hidden = focused || this.familyPanelTargets.some((panel) => panel.dataset.familyId === familyId && panel.dataset.hasMediaFeatured === 'true');
    }

    showSlide(carouselId, offset) {
        const slides = this.carouselSlideTargets.filter((slide) => slide.dataset.carouselId === carouselId);
        const activeIndex = Math.max(0, slides.findIndex((slide) => !slide.hidden));
        const nextIndex = (activeIndex + offset + slides.length) % slides.length;
        slides.forEach((slide, index) => { slide.hidden = index !== nextIndex; });
    }

    triggerForFamily(familyId) {
        return this.triggerTargets.find((trigger) => trigger.dataset.megaMenuMenuIdParam === `family-${familyId}`) ?? null;
    }

    updateHeaderOffset() {
        this.element.style.setProperty('--header-h', `${this.element.getBoundingClientRect().bottom}px`);
    }

    isOpen() {
        return !this.panelTarget.hidden;
    }

    isFocusedMenu(menuId) {
        return menuId.startsWith('main-');
    }
}
