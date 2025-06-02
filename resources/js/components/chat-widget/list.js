import BaseElement from "../helpers/base-element";
import render from "../helpers/render";

customElements.define(
	"chat-list-tickets",
	class extends BaseElement {
		get stylesheet() {
			return "/css/padmission-tickets/chat-widget.css";
		}

		closeDialog() {
			this.dispatch("close-chat-widget");
		}

		async renderedCallback() {
			const tickets = await this.fetchTickets();

			const node = render(`
            <ul class="ticket-list">
                ${tickets
									.map(
										(ticket) => `
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
                                ${ticket.latest_activity ? ticket.latest_activity : "No messages yet"}
                            </span>
                        </button>
                    </li>
                `,
									)
									.join("")}
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

			this.shadowRoot.querySelector("[data-ticket-list]").replaceChildren(node);
		}

		createTicket() {
			this.changeView("chat-create-ticket");
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
				const response = await fetch("/padmission-tickets/api/tickets");

				if (!response.ok) {
					throw new Error("Network response was not ok");
				}

				const data = await response.json();
				this.tickets = data.tickets || [];

				return this.tickets;
			} catch (error) {
				console.error("Failed to fetch tickets:", error);
				return [];
			}
		}

		async render() {
			return render(`
            <div class="chat-list-tickets">
                <header>
                    <h2>
                        How can we help you?
                    </h2>

                    <button
                        class="button-icon"
                        data-close-dialog
                        @click="closeDialog"
                    >
                        <span class="sr-only">Close modal</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                     </button>
                </header>

                <button
                    class="button"
                    @click="createTicket"
                >
                    <span>Open New Ticket</span>

                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <h3>Your tickets</h3>

                <div data-ticket-list>
                </div>
            </div>
        `);
		}
	},
);
