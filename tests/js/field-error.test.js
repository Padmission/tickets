import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { test } from "node:test";
import { parseHTML } from "linkedom";

import showFieldError from "../../resources/js/components/helpers/field-error.js";

const { document } = parseHTML(
	'<!doctype html><html><body><div class="form-field"><span class="error"></span></div></body></html>',
);

test("shows the server's error as text, never as markup", () => {
	const field = document.querySelector(".form-field");
	const message = '<img src=x onerror="window.__xss=1">Too many requests';

	showFieldError(field, message);

	const error = field.querySelector(".error");

	assert.ok(field.classList.contains("has-error"));
	assert.equal(error.textContent, message);
	assert.equal(error.children.length, 0);
});

test("the code sign-in forms show their errors through it", () => {
	for (const file of ["otp-request.js", "otp-verify.js"]) {
		const source = readFileSync(
			new URL(
				`../../resources/js/components/chat-widget/${file}`,
				import.meta.url,
			),
			"utf8",
		);

		assert.ok(!source.includes(".innerHTML"), `${file} writes HTML`);
		assert.ok(
			source.includes("showFieldError("),
			`${file} does not use showFieldError`,
		);
	}
});
