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
			return "/css/padmission/tickets/chat-widget.css";
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
					this.updateUnreadBadge();
				}
			});

			window.addEventListener("change-view", (event) => {
				const viewName = event.detail.viewName;
				const attributes = event.detail.attributes || {};

				this.changeView(viewName, attributes);
			});

			if (config.userId) {
				this.openTicketByHash();
				this.updateUnreadBadge();
				this.startUnreadPolling();
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

		async updateUnreadBadge() {
			if (!config.userId) {
				return;
			}

			try {
				const response = await fetch(
					"/padmission-tickets/api/tickets/unread-count",
					{
						headers: {
							"X-CSRF-TOKEN": document
								.querySelector('meta[name="csrf-token"]')
								.getAttribute("content"),
						},
					},
				);

				if (!response.ok) {
					return;
				}

				const data = await response.json();
				const badge = this.shadowRoot.querySelector("[data-unread-badge]");

				if (data.unread_count > 0) {
					badge.textContent = data.unread_count > 99 ? "99+" : data.unread_count;
					badge.style.display = "block";
				} else {
					badge.style.display = "none";
				}
			} catch (error) {
				console.error("Failed to fetch unread count:", error);
			}
		}

		startUnreadPolling() {
			setInterval(() => {
				this.updateUnreadBadge();
			}, 10_000);
		}

		render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <style>
                    :host {
                        display: none;
                    }

                    [data-unread-badge] {
                        position: absolute;
                        top: -4px;
                        right: -4px;
                        min-width: 20px;
                        height: 20px;
                        padding: 0 6px;
                        border-radius: 10px;
                        background-color: #dc2626;
                        color: white;
                        font-size: 11px;
                        font-weight: 600;
                        line-height: 20px;
                        text-align: center;
                        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                        display: none;
                    }
                </style>

                <button
                    class="button-icon"
                    data-open-dialog
                >
                    <span class="sr-only">${__('open_modal')}</span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle-question-icon lucide-message-circle-question"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
                    <span data-unread-badge></span>
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
