import assert from "node:assert/strict";
import { test } from "node:test";

import messageHtml, {
	attachmentHtml,
	pendingAttachmentHtml,
} from "../../resources/js/components/helpers/message-html.js";

const payloads = [
	"<b>x</b>",
	'<img src=x onerror="window.__xss=1">',
	'O\'Neil & "Co"',
];

const message = (overrides = {}) => ({
	id: 7,
	side: "other",
	content: "<p>Hello</p>",
	user_name: "Aisha Brooks",
	attachments: [],
	...overrides,
});

test("escapes the sender's name, which they set themselves", () => {
	for (const name of payloads) {
		const html = messageHtml(message({ user_name: name }));

		assert.ok(!html.includes(name), name);
	}

	assert.match(
		messageHtml(message({ user_name: "<b>x</b>" })),
		/&lt;b&gt;x&lt;\/b&gt;/,
	);
	assert.match(
		messageHtml(message({ user_name: '<img src=x onerror="a">' })),
		/&lt;img src=x onerror=&quot;a&quot;&gt;/,
	);
});

test("keeps the server's own message HTML as it is", () => {
	assert.match(
		messageHtml(message({ content: '<p>See <a href="/x">the ticket</a></p>' })),
		/<a href="\/x">the ticket<\/a>/,
	);
});

test("escapes a sent attachment's file name in its text and every attribute", () => {
	for (const filename of payloads) {
		const html = attachmentHtml({
			type: "file",
			filename,
			filepath: `uploads/${filename}`,
			preview_url: null,
		});

		assert.ok(!html.includes(filename), filename);
	}

	const quoted = attachmentHtml({
		type: "file",
		filename: 'a" onclick="x.pdf',
		filepath: 'uploads/a" onclick="x.pdf',
		preview_url: null,
	});

	assert.match(quoted, /data-preview="uploads\/a&quot; onclick=&quot;x.pdf"/);
	assert.ok(!/data-preview="uploads\/a" onclick=/.test(quoted));
});

test("escapes an image attachment's preview address and name", () => {
	const html = attachmentHtml({
		type: "image",
		filename: '"><b>x</b>',
		filepath: "f",
		preview_url: 'https://x/"><img src=x onerror=a>',
	});

	assert.ok(!html.includes("<b>x</b>"));
	assert.ok(!html.includes("<img src=x onerror=a>"));
});

test("escapes a file waiting in the composer", () => {
	const html = pendingAttachmentHtml(
		{ type: "file", name: '<img src=x onerror="a">.pdf' },
		0,
	);

	assert.ok(!html.includes("<img src=x"));
	assert.match(html, /&lt;img src=x onerror=&quot;a&quot;&gt;\.pdf/);
});

test("escapes the message's own attributes", () => {
	const html = messageHtml(message({ side: 'me" onclick="x', id: '1" x="y' }));

	assert.ok(!html.includes('data-side="me" onclick'));
	assert.ok(!html.includes('data-message-id="1" x='));
});
