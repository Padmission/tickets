import BaseElement from "../helpers/base-element";
import render from "../helpers/render";
import fetchJson from "../helpers/fetch-json.js";
import __ from "../helpers/trans.js";

customElements.define(
	"chat-otp-request",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}
        async verifyUser(event) {
            event.preventDefault();

            const email = this.querySelector('#email').value

            try {
                const data = await fetchJson("/padmission-tickets/api/otp-request", {email}, 'POST');
                console.log('data', data)
                this.changeView("chat-otp-verify");
            } catch (e) {
                const formField = this.querySelector('.form-field');
                formField.classList.add('has-error')
                formField.querySelector('.error').innerHTML = await e.error()
            }
        }
		async render() {
            // biome-ignore format: preserve template formatting
            return render(`
                <div class="chat-list-tickets">
                    <header>
                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                data-close-dialog
                                formmethod="dialog"
                            >
                                <span class="sr-only">${__('close_modal')}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>
                    </header>

                    <h2>
                        ${__('otp_request.heading')}
                    </h2>

                    <div class="form-container">
                        <p class="form-description">
                            ${__('otp_request.description')}
                        </p>

                        <form
                            @submit="verifyUser"
                            class="form"
                        >
                            <div class="form-field">
                                <label for="email" class="form-label">
                                    ${__('otp_request.email_label')}
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autocomplete="email"
                                    class="form-input"
                                    required
                                >
                                <span class="error"></span>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button-primary">
                                    ${__('otp_request.submit_button')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `);
		}
	},
);
