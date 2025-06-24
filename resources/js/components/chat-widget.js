import BaseElement from "./helpers/base-element.js";
import render from "./helpers/render.js";
import config from "./helpers/config.js";
import __ from "./helpers/trans.js";

import "./chat-component.js";

import "./chat-widget/view.js";
import "./chat-widget/list.js";
import "./chat-widget/otp-request.js";
import "./chat-widget/otp-verify.js";

customElements.define(
	"chat-widget",
	class extends BaseElement {
		get stylesheet() {
			return "/css/padmission-tickets/chat-widget.css";
		}
		beforeRender() {
			config.setConfig(JSON.parse(this.config));
		}

		renderedCallback() {
			this.shadowRoot
				.querySelector("button")
				.addEventListener("click", (event) => {
					if (!config.userId) {
						this.changeView("chat-otp-request");
					} else {
						this.changeView("chat-list-tickets");
					}

					this.shadowRoot.querySelector("dialog").show();
				});

			window.addEventListener("close-chat-widget", (event) => {
				const dialog = this.shadowRoot.querySelector("dialog");

				if (dialog) {
					dialog.close();
				}
			});

			window.addEventListener("change-view", (event) => {
				const viewName = event.detail.viewName;
				const attributes = event.detail.attributes || {};

				this.changeView(viewName, attributes);
			});

			if (config.userId) {
				this.openTicketByHash();
			}
		}

		openTicketByHash() {
			const hash = window.location.hash;

			if (!hash || !hash.startsWith("#ticket-")) {
				return;
			}

			const ticketId = hash.substring(8);

			if (!ticketId) {
				return;
			}

			this.changeView("chat-view-ticket", {
				ticketId: ticketId,
			});

			this.shadowRoot.querySelector("dialog").show();
		}

		changeView(viewName, attributes = {}) {
			const view = document.createElement(viewName);

			for (const [key, value] of Object.entries(attributes)) {
				const kebabCaseKey = key
					.replace(/([a-z])([A-Z])/g, "$1-$2")
					.toLowerCase();

				view.setAttribute(kebabCaseKey, value);
			}

			this.shadowRoot
				.querySelector("[data-dialog-content]")
				.replaceChildren(view);
		}

		render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <style>
                    :host {
                        display: none;
                    }
                </style>

                <button
                    class="button-icon"
                    data-open-dialog
                >
                    <span class="sr-only">${__('open_modal')}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle-question-icon lucide-message-circle-question"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                </button>

                <dialog
                    id="chat-widget-dialog"
                    closedBy="closerequest"
                >
                    <div data-dialog-content>

                    </div>
                </dialog>
            `);
		}
	},
);
