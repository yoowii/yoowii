import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = { context: String };
    static targets = ['image', 'thumbnails', 'previous', 'next'];

    connect() {
        this.images = [...this.thumbnailsTarget.querySelectorAll('[data-gallery-image]')]
            .map((button) => this.imageFromButton(button))
            .filter(Boolean);
        this.activeIndex = 0;
        this.render();
    }

    update(event) {
        if (event.detail?.context !== this.contextValue || !Array.isArray(event.detail.images) || event.detail.images.length === 0) return;
        this.images = event.detail.images.filter((image) => this.validImage(image));
        this.activeIndex = Math.min(Math.max(event.detail.activeIndex || 0, 0), this.images.length - 1);
        this.render();
    }

    previous() { this.select(this.activeIndex - 1); }
    next() { this.select(this.activeIndex + 1); }

    choose(event) {
        this.select(Number(event.currentTarget.dataset.galleryIndex));
    }

    select(index) {
        if (this.images.length === 0) return;
        this.activeIndex = (index + this.images.length) % this.images.length;
        this.render();
    }

    render() {
        const active = this.images[this.activeIndex];
        if (!active) return;
        this.imageTarget.src = active.src;
        this.imageTarget.alt = active.alt || '';
        this.previousTarget.hidden = this.images.length < 2;
        this.nextTarget.hidden = this.images.length < 2;
        this.thumbnailsTarget.replaceChildren(...this.images.map((image, index) => this.thumbnail(image, index)));
    }

    thumbnail(image, index) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'yoowii-product-gallery__thumbnail';
        button.dataset.galleryIndex = String(index);
        button.setAttribute('aria-label', `Afficher le visuel ${index + 1}`);
        button.setAttribute('aria-current', String(index === this.activeIndex));
        button.addEventListener('click', this.choose.bind(this));
        const thumbnail = document.createElement('img');
        thumbnail.src = image.thumbnailSrc || image.src;
        thumbnail.alt = '';
        thumbnail.loading = 'lazy';
        button.append(thumbnail);

        return button;
    }

    imageFromButton(button) {
        const src = this.normalizedImageUrl(button.dataset.galleryImage);
        const thumbnailSrc = this.normalizedImageUrl(button.dataset.galleryThumbnail || button.dataset.galleryImage);
        if (!src || !thumbnailSrc) return null;

        return { src, thumbnailSrc, alt: button.dataset.galleryAlt || '' };
    }

    validImage(image) {
        return Boolean(this.normalizedImageUrl(image?.src));
    }

    normalizedImageUrl(value) {
        if (!value) return null;
        try {
            const url = new URL(value, window.location.href);

            return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
        } catch {
            return null;
        }
    }
}
