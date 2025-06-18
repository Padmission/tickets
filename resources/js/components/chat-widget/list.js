import BaseElement from "../helpers/base-element";
import render from "../helpers/render";
import fetchJson from "../helpers/fetch-json.js";
import config from "../helpers/config.js";
import __ from "../helpers/trans.js";

customElements.define(
	"chat-list-tickets",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}

		async renderedCallback() {
			const tickets = await this.fetchTickets();

			// biome-ignore format: preserve template formatting
			const node = render(`
                <ul class="ticket-list">
                    ${tickets.map((ticket) => `
                        <li>
                            <button
                                data-open-ticket="${ticket.id}"
                                class="ticket"
                            >
                                <span class="ticket__id">#${ticket.id}</span>

                                <span class="ticket__header">
                                    <h4 class="ticket__title">${ticket.subject}</h4>
                                    <date class="ticket__date">${ticket.updated_at}</date>
                                </span>
                                <span class="ticket__description">
                                    ${ticket.latest_message ? ticket.latest_message : "No messages yet"}
                                </span>
                            </button>
                        </li>
                    `,).join("")}
                </ul>
            `);

			node.querySelectorAll("[data-open-ticket]").forEach((el) =>
				el.addEventListener("click", (event) => {
					const ticketId = event.currentTarget.dataset.openTicket;

					if (ticketId) {
						this.openTicket(ticketId);
					}
				}),
			);

			this.querySelector("[data-ticket-list]").replaceChildren(node);
		}

		createTicket() {
			this.changeView("chat-view-ticket");
		}

		openTicket(ticketId) {
			const ticket = this.tickets.find(
				(ticket) => ticket.id === Number.parseInt(ticketId),
			);

			this.changeView("chat-view-ticket", {
				ticketId: ticket.id,
				isClosed: ticket.is_closed,
				subject: ticket.subject,
			});
		}

		async fetchTickets() {
			try {
				const data = await fetchJson("/padmission-tickets/api/tickets");

				this.tickets = data.tickets || [];

				return this.tickets;
			} catch (error) {
				console.error("Failed to fetch tickets:", error);
				return [];
			}
		}

		async render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <div class="chat-list-tickets">
                    <header>
                        <h2>
                            ${__('list.heading')}
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

                    <button
                        class="button"
                        @click="createTicket"
                    >
                        <span>${__('list.create_ticket')}</span>

                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                    <h3>${__('list.tickets_heading')}</h3>

                    <div data-ticket-list>
                    </div>
                </div>
            `);
		}
	},
);
