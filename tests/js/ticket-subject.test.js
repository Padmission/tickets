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

test("never ends on half an HTML entity, since the editor's entities are read as characters first", () => {
	assert.equal(
		ticketSubject(`${"a".repeat(36)}&amp;${"b".repeat(10)}`),
		`${"a".repeat(36)}&bb…`,
	);
});

test("sends what was typed, not the editor's escaping", () => {
	assert.equal(
		ticketSubject('<p>Household 14\'s rent &amp; "utility" &lt; last year</p>'),
		'Household 14\'s rent & "utility" < last…',
	);
	assert.equal(
		ticketSubject("<p>Tom&nbsp;&amp;&#160;Jerry&#39;s &#x27;show&#x27;</p>"),
		"Tom & Jerry's 'show'",
	);
});

test("leaves text that only looks like an entity alone", () => {
	assert.equal(
		ticketSubject("<p>AT&amp;T &amp;c; fees &amp;amp;</p>"),
		"AT&T &c; fees &amp;",
	);
});

test("measures the cut in typed characters, not escaped ones", () => {
	const subject = ticketSubject(
		"<p>Rent &amp; utilities &amp; deposits &amp; fees &amp; tax</p>",
	);

	assert.equal(subject, "Rent & utilities & deposits & fees & tax");
	assert.equal(subject.length, 40);
});

test("keeps the given budget", () => {
	assert.equal(ticketSubject("one two three four", 10), "one two…");
});
