import BaseElement from "./helpers/base-element";
import render from "./helpers/render";

import "./chat-widget/view";
import "./chat-widget/list";

customElements.define(
	"chat-widget",
	class extends BaseElement {
		get stylesheet() {
			return "/css/padmission-tickets/chat-widget.css";
		}

		renderedCallback() {
			this.shadowRoot
				.querySelector("button")
				.addEventListener("click", (event) => {
					this.changeView("chat-list-tickets");
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
                    <span class="sr-only">Open support chat</span>
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
