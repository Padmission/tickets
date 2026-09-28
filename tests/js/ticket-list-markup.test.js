import assert from "node:assert/strict";
import { test } from "node:test";

import config from "../../resources/js/components/helpers/config.js";
import ticketListMarkup from "../../resources/js/components/helpers/ticket-list-markup.js";

config.setConfig({
	lang: {
		"list.no_tickets": "No tickets yet",
		"list.no_messages": "No messages yet",
		"list.needs_attention": "Needs attention",
	},
});

const ticket = {
	id: 14,
	is_unread: false,
	needs_attention: false,
	subject: "Rent <500 is wrong",
	status: { color: "#3b82f6", display_name: "Open" },
	updated_at: "Today",
	latest_message: null,
};

test("says there are no tickets yet instead of leaving the section blank", () => {
	const markup = ticketListMarkup([]);

	assert.match(markup, /<p class="ticket-list-empty">No tickets yet<\/p>/);
	assert.doesNotMatch(markup, /ticket-list"/);
});

test("lists tickets without the empty line when there are some", () => {
	const markup = ticketListMarkup([ticket]);

	assert.match(markup, /data-open-ticket="14"/);
	assert.match(markup, /Rent &lt;500 is wrong/);
	assert.match(markup, /No messages yet/);
	assert.doesNotMatch(markup, /No tickets yet/);
});
