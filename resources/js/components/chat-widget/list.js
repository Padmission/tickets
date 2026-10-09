import BaseElement from "../helpers/base-element.js";
import render from "../helpers/render.js";
import fetchJson from "../helpers/fetch-json.js";
import config from "../helpers/config.js";
import ticketListMarkup from "../helpers/ticket-list-markup.js";
import escapeHtml from "../helpers/escape-html.js";
import __ from "../helpers/trans.js";

customElements.define(
	"chat-list-tickets",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}

		async renderedCallback() {
			await this.refreshTickets();
		}

		async toggleClosed(event) {
			this.showClosed = String(event.currentTarget.checked);
			this.dispatchEvent(
				new CustomEvent("ticket-list-filter-changed", {
					bubbles: true,
					detail: { showClosed: this.showClosed === "true" },
				}),
			);
			await this.refreshTickets();
		}

		async refreshTickets() {
			this.listRequestId = (this.listRequestId || 0) + 1;
			const requestId = this.listRequestId;
			let data;
			try {
				data = await this.fetchTickets();
			} catch (error) {
				console.error("Failed to fetch tickets:", error);
				return;
			}

			if (requestId !== this.listRequestId) {
				return;
			}

			this.tickets = data.tickets || [];
			const markup = ticketListMarkup(
				this.tickets,
				this.showClosed === "true",
				data.has_closed_tickets === true,
			);
			if (markup === this.listMarkup) {
				return;
			}
			this.listMarkup = markup;
			const node = render(markup);

			node.querySelectorAll("[data-open-ticket]").forEach((el) =>
				el.addEventListener("click", (event) => {
					const ticketId = event.currentTarget.dataset.openTicket;

					if (ticketId) {
						this.openTicket(ticketId);
					}
				}),
			);

			const list = this.querySelector("[data-ticket-list]");

			if (!list) {
				return;
			}

			// Polling refreshes the list under the reader, so their place is kept.
			const main = this.querySelector("main");
			const scrollTop = main?.scrollTop;
			list.replaceChildren(node);

			if (main) {
				main.scrollTop = scrollTop;
			}
		}

		createTicket() {
			this.changeView("chat-view-ticket");
		}

		openTicket(ticketId) {
			// A refresh can drop a ticket between rendering its row and the click.
			const ticket = this.tickets?.find(
				(ticket) => ticket.id === Number.parseInt(ticketId),
			);

			if (!ticket) {
				return;
			}

			this.changeView("chat-view-ticket", {
				ticketId: ticket.id,
				isClosed: ticket.is_closed,
				subject: ticket.subject,
			});
		}

		async fetchTickets() {
			return fetchJson("/padmission-tickets/api/tickets", {
				include_closed: this.showClosed === "true" ? 1 : 0,
			});
		}

		async render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <div class="chat-list-tickets">
                    <header>
                        <h2>${escapeHtml(__('list.heading'))}</h2>

                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                formmethod="dialog"
                            >
                                <span class="sr-only">${escapeHtml(__('close_modal'))}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>
                    </header>

                    <main>
                        <h3>
                            ${escapeHtml(__('list.subheading'))}
                        </h3>


                        ${config.documentationUrl ?
                            `<a
                                class="button"
                                href="${escapeHtml(config.documentationUrl)}"
                                target="_blank"
                            >
                                <span>${escapeHtml(__('list.go_to_docs'))}</span>

                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                            </a>` : ''
                        }

                        <button
                            class="button"
                            @click="createTicket"
                        >
                            <span>${escapeHtml(__('list.create_ticket'))}</span>

                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                        </button>

                        <h3>${escapeHtml(__('list.tickets_heading'))}</h3>

                        <label class="ticket-list-filter">
                            <input type="checkbox" data-show-closed @change="toggleClosed" ${this.showClosed === "true" ? 'checked' : ''}>
                            ${escapeHtml(__('list.show_closed'))}
                        </label>

                        <div data-ticket-list>
                        </div>
                    </main>
                </div>
            `);
		}
	},
);
