import assert from "node:assert/strict";
import { test } from "node:test";

import ticketHeading from "../../resources/js/components/helpers/ticket-heading.js";

test("heads a ticket with its subject", () => {
	assert.equal(
		ticketHeading("Recert rent is wrong", "12", "New Chat"),
		"Recert rent is wrong",
	);
});

test("never calls a ticket opened by its id alone, as from an email, a new chat", () => {
	assert.equal(ticketHeading(null, "12", "New Chat"), "");
	assert.equal(ticketHeading("", "12", "New Chat"), "");
});

test("calls a chat with no ticket yet a new chat", () => {
	assert.equal(ticketHeading(null, "", "New Chat"), "New Chat");
	assert.equal(ticketHeading(undefined, null, "New Chat"), "New Chat");
});
