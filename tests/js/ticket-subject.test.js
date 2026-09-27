import assert from "node:assert/strict";
import { test } from "node:test";

import ticketSubject from "../../resources/js/components/helpers/ticket-subject.js";

test("keeps a first message that fits whole", () => {
	assert.equal(ticketSubject("<p>Rent is wrong</p>"), "Rent is wrong");
});

test("cuts a longer message at the last whole word that fits, with an ellipsis", () => {
	const subject = ticketSubject(
		"<p>Recert rent is wrong for household 14. The rent after the 10/1 recert is $200 too high.</p>",
	);

	assert.equal(subject, "Recert rent is wrong for household 14…");
	assert.ok(subject.length <= 40);
});

test("keeps words in separate paragraphs apart", () => {
	assert.equal(ticketSubject("<p>Rent</p><p>is wrong</p>"), "Rent is wrong");
});

test("cuts a single word longer than the budget where the budget ends", () => {
	const subject = ticketSubject(
		"Supercalifragilisticexpialidocious-antidisestablishmentarianism",
	);

	assert.equal(subject, "Supercalifragilisticexpialidocious-anti…");
	assert.equal(subject.length, 40);
});

test("never ends on half an HTML entity", () => {
	assert.equal(
		ticketSubject(`${"a".repeat(36)}&amp;${"b".repeat(10)}`),
		`${"a".repeat(36)}…`,
	);
});

test("keeps the given budget", () => {
	assert.equal(ticketSubject("one two three four", 10), "one two…");
});
