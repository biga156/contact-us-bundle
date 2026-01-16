import { Controller } from '@hotwired/stimulus';

/*
 * Contact form controller
 * Handles form enhancements and timing token
 */
export default class extends Controller {
    static targets = ['submit'];

    connect() {
        // Set form load time in hidden field
        const timingField = this.element.querySelector('.contact-timing-token');
        if (timingField && !timingField.value) {
            timingField.value = Math.floor(Date.now() / 1000).toString();
        }

        // Add submit handler
        this.element.addEventListener('submit', this.handleSubmit.bind(this));
    }

    handleSubmit(event) {
        // Disable submit button to prevent double submission
        if (this.hasSubmitTarget) {
            this.submitTarget.disabled = true;
            this.submitTarget.textContent = this.submitTarget.dataset.loadingText || 'Sending...';
        }

        // Check honeypot (additional client-side check)
        const honeypot = this.element.querySelector('.contact-honeypot input');
        if (honeypot && honeypot.value !== '') {
            event.preventDefault();
            console.warn('Honeypot field filled - potential spam');
            return false;
        }

        // Form will submit normally
        return true;
    }

    disconnect() {
        this.element.removeEventListener('submit', this.handleSubmit.bind(this));
    }
}
