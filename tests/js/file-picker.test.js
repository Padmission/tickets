import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";

import acceptAttribute from "../../resources/js/components/helpers/accept-attribute.js";

test("takes the file picker's types from the chat's config, escaped", () => {
	assert.equal(
		acceptAttribute({
			acceptedFileTypes: "application/pdf,.pdf,text/csv,.csv",
		}),
		'accept="application/pdf,.pdf,text/csv,.csv"',
	);
	assert.equal(
		acceptAttribute({ acceptedFileTypes: 'x" onclick="alert(1)' }),
		'accept="x&quot; onclick=&quot;alert(1)"',
	);
	assert.equal(acceptAttribute({}), "");
});

test("the chat's file picker no longer hard-codes its types", () => {
	const source = readFileSync(
		new URL("../../resources/js/components/chat-component.js", import.meta.url),
		"utf8",
	);

	assert.ok(!source.includes('accept="video/*,image/*,.pdf"'));
	assert.ok(source.includes("acceptAttribute(config)"));
});
