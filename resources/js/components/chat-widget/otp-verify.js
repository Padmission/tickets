import BaseElement from "../helpers/base-element";
import render from "../helpers/render";
import fetchJson from "../helpers/fetch-json.js";
import __ from "../helpers/trans.js";
import config from "../helpers/config.js";

customElements.define(
	"chat-otp-verify",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}

		back() {
			this.changeView("chat-otp-request");
		}

		async verifyUser(event) {
			event.preventDefault();

			const otp = this.querySelector("#otp").value;

			try {
				const data = await fetchJson(
					"/padmission-tickets/api/otp-verify",
					{ otp },
					"POST",
				);
				this.changeView("chat-list-tickets");
				config.userId = data.user_key;
			} catch (e) {
				const formField = this.querySelector(".form-field");
				formField.classList.add("has-error");
				formField.querySelector(".error").innerHTML = await e.error();
			}
		}

		async render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <div class="chat-list-tickets">
                    <header>
                        <button
                            data-back
                            class="button-icon"
                            @click="back"
                        >
                            <span class="sr-only">
                                ${__('view.back')}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                        </button>


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
                        ${__('otp_verify.heading')}
                    </h2>

                    <div class="form-container">
                        <p class="form-description">
                            ${__('otp_verify.description')}
                        </p>

                        <form
                            @submit="verifyUser"
                            class="form"
                        >
                            <div class="form-field">
                                <label for="otp" class="form-label">
                                    ${__('otp_verify.label')}
                                </label>

                                <input
                                    id="otp"
                                    type="text"
                                    name="otp"
                                    autocomplete="one-time-code"
                                    class="form-input otp-input"
                                    maxlength="6"
                                    pattern="[0-9]*"
                                    inputmode="numeric"
                                    required
                                >
                                <div class="error"></div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button-primary">
                                    ${__('otp_verify.submit_button')}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `);
		}
	},
);
