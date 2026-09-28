import assert from "node:assert/strict";
import { execFileSync } from "node:child_process";
import { test } from "node:test";

import formatMessageTime from "../../resources/js/components/helpers/format-message-time.js";

const helper = new URL("../../resources/js/components/helpers/format-message-time.js", import.meta.url).href;

test("matches the ticket page's MESSAGE_TIME_FORMAT in the given timezone", () => {
	assert.equal(formatMessageTime(new Date("2026-09-25T11:15:00Z"), "America/New_York"), "Sep 25, 7:15 AM");
	assert.equal(formatMessageTime(new Date("2026-09-05T19:00:00Z"), "America/New_York"), "Sep 5, 3:00 PM");
	assert.equal(formatMessageTime(new Date("2026-01-01T05:05:00Z"), "America/New_York"), "Jan 1, 12:05 AM");
});

test("stays in US English when the browser's language is not", () => {
	const german = execFileSync(
		process.execPath,
		[
			"--input-type=module",
			"-e",
			`import format from ${JSON.stringify(helper)}; process.stdout.write(format(new Date("2026-09-25T11:15:00Z"), "America/New_York"));`,
		],
		{ env: { ...process.env, LC_ALL: "de_DE.UTF-8", LANG: "de_DE.UTF-8" } },
	).toString();

	assert.equal(german, "Sep 25, 7:15 AM");
});
