import escapeHtml from "./escape-html.js";
import __ from "./trans.js";

export default function ticketListMarkup(tickets) {
	if (tickets.length === 0) {
		return `<p class="ticket-list-empty">${__("list.no_tickets")}</p>`;
	}

	// biome-ignore format: preserve template formatting
	return `
                <ul class="ticket-list">
                    ${tickets.map((ticket) => `
                            <li>
                                <button
                                    data-open-ticket="${ticket.id}"
                                    class="ticket ${ticket.is_unread ? 'ticket--unread' : ''}"
                                >
                                    <div class="ticket__header">
                                        <div>
                                            <span class="badge ticket__id">#${ticket.id}</span>
                                            <span class="badge" style="--color: ${escapeHtml(ticket.status.color)}">${escapeHtml(ticket.status.display_name)}</span>
                                            ${ticket.needs_attention ? `<span class="badge" style="--color: #f59e0b">${__('list.needs_attention')}</span>` : ''}
                                        </div>
                                        <div>
                                            <h4 class="ticket__title">${escapeHtml(ticket.subject)}</h4>
                                            <date class="ticket__date">${ticket.updated_at}</date>
                                        </div>
                                    </div>
                                    <div class="ticket__description">
                                        ${ticket.latest_message ? escapeHtml(ticket.latest_message) : __('list.no_messages')}
                                    </div>
                                </button>
                            </li>
                        `).join("")}
                </ul>
            `;
}
