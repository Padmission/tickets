import assert from "node:assert/strict";
import { test } from "node:test";

import config from "../../resources/js/components/helpers/config.js";
import reopenDialog, {
	closedTicketLine,
} from "../../resources/js/components/helpers/reopen-dialog.js";

config.setConfig({
	lang: {
		"chat.closed_on": "This ticket was closed on :date.",
		"chat.reopen_dialog.heading_same_problem":
			"This ticket is closed. Is this the same problem?",
		"chat.reopen_dialog.body_same_problem":
			"Reopen it to send your message, or start a new ticket with it that links back to this one.",
		"chat.reopen_dialog.heading_too_old":
			"This ticket closed more than :days days ago.",
		"chat.reopen_dialog.body_too_old":
			"Your message starts a new ticket that links back to this one.",
		"chat.reopen_dialog.heading_staff": "This ticket is closed.",
		"chat.reopen_dialog.body_staff": "Sending your message reopens it.",
		"chat.reopen_dialog.reopen": "Reopen and send",
		"chat.reopen_dialog.new_ticket": "Start a new ticket",
		"chat.reopen_dialog.cancel": "Cancel",
	},
});

const choicesOf = (dialog) => dialog.buttons.map((button) => button.choice);

test("asks a requester whether it is the same problem, offering a reopen, a new ticket or neither", () => {
	const dialog = reopenDialog(["reopen", "new"], 30);

	assert.equal(
		dialog.heading,
		"This ticket is closed. Is this the same problem?",
	);
	assert.deepEqual(choicesOf(dialog), ["reopen", "new", "cancel"]);
	assert.deepEqual(
		dialog.buttons.map((button) => button.label),
		["Reopen and send", "Start a new ticket", "Cancel"],
	);
	assert.equal(
		dialog.buttons.find((button) => button.primary).choice,
		"reopen",
	);
});

test("offers only a new ticket once the reopen window has passed, naming the window", () => {
	const dialog = reopenDialog(["new"], 30);

	assert.equal(dialog.heading, "This ticket closed more than 30 days ago.");
	assert.deepEqual(choicesOf(dialog), ["new", "cancel"]);
	assert.equal(dialog.buttons.find((button) => button.primary).choice, "new");
});

test("offers staff only a reopen", () => {
	const dialog = reopenDialog(["reopen"], 30);

	assert.equal(dialog.heading, "This ticket is closed.");
	assert.equal(dialog.body, "Sending your message reopens it.");
	assert.deepEqual(choicesOf(dialog), ["reopen", "cancel"]);
});

test("asks nothing when there is nothing to offer", () => {
	assert.equal(reopenDialog([], 30), null);
	assert.equal(reopenDialog(undefined, 30), null);
});

test("says when the ticket closed, and nothing before it has", () => {
	assert.equal(
		closedTicketLine("2026-09-01T15:04:00Z", "en-US", "UTC"),
		"This ticket was closed on Sep 1, 2026.",
	);
	assert.equal(closedTicketLine(null), "");
});
