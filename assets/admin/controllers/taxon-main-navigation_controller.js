import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['details', 'toggle'];

    connect() {
        this.toggle();
    }

    toggle() {
        this.detailsTarget.hidden = !this.toggleTarget.checked;
    }
}
