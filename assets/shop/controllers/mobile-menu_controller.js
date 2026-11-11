import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['screen'];
    static values = { checkboxId: String };

    connect() {
        this.checkbox = document.getElementById(this.checkboxIdValue);
        this.onCheckboxChange = this.handleCheckboxChange.bind(this);
        this.checkbox?.addEventListener('change', this.onCheckboxChange);
        this.onKeydown = this.handleKeydown.bind(this);
        window.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        this.checkbox?.removeEventListener('change', this.onCheckboxChange);
        window.removeEventListener('keydown', this.onKeydown);
    }

    goTo(event) {
        this.show(event.params.screen);
    }

    close() {
        if (this.checkbox) this.checkbox.checked = false;
        this.show('root');
    }

    handleCheckboxChange() {
        if (this.checkbox?.checked) this.show('root');
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.checkbox?.checked) this.close();
    }

    show(screenId) {
        this.screenTargets.forEach((screen) => {
            screen.hidden = screen.dataset.mobileMenuScreenId !== screenId;
        });

        this.element.querySelector(`[data-mobile-menu-screen-id="${screenId}"] button, [data-mobile-menu-screen-id="${screenId}"] a`)?.focus();
    }
}
