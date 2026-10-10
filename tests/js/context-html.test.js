import assert from "node:assert/strict";
import { test } from "node:test";
import { parseHTML } from "linkedom";

import contextHtml from "../../resources/js/components/helpers/context-html.js";

const { document } = parseHTML("<!doctype html><html><body></body></html>");
globalThis.document = document;

test("shows each section collapsed under its heading", () => {
	const html = contextHtml([{ heading: "Earlier chat", html: "<p>Hello</p>" }]);

	assert.match(html, /<details class="context">/);
	assert.doesNotMatch(html, /<details[^>]* open/);
	assert.match(html, /<summary>Earlier chat<\/summary>/);
	assert.match(html, /<p>Hello<\/p>/);
});

test("escapes the heading and cleans the section's html", () => {
	const html = contextHtml([
		{
			heading: "<img src=x onerror=alert(1)>",
			html: '<p onclick="x()">Hi</p><script>alert(1)</script>',
		},
	]);

	assert.doesNotMatch(html, /<img/);
	assert.doesNotMatch(html, /onclick|<script/);
	assert.match(html, /<p>Hi<\/p>/);
});

test("is empty when there is nothing to show", () => {
	assert.equal(contextHtml([]), "");
});
