import BaseElement from "../helpers/base-element";
import render from "../helpers/render";
import fetchJson from "../helpers/fetch-json.js";
import __ from "../helpers/trans.js";

customElements.define(
	"chat-otp-submit",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}


        async verifyUser(event) {
            event.preventDefault();

            const otp = this.querySelector('#otp').value

            const data = await fetchJson("/padmission-tickets/api/otp-verify", {otp}, 'POST');
            console.log('data', data)

            this.changeView("chat-list-tickets");
        }


		async render() {
            // biome-ignore format: preserve template formatting
            return render(`
                <div class="chat-list-tickets">
                    <header>
                        <h2>
                            ${__('otp_verify.heading')}
                        </h2>

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

                    <form
                        @submit="verifyUser"
                    >
                        <label for="otp">
                             ${__('otp_verify.label')}
                        </label>

                        <input id="otp" type="text" name="otp" autocomplete="one-time-code">

                        <button type="submit">
                             ${__('otp_verify.submit_button')}
                        </button>
                    </form>
                </div>
            `);
		}
	},
);
