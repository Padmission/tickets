import assert from "node:assert/strict";
import { test } from "node:test";
import { parseHTML } from "linkedom";

const { document } = parseHTML("<!doctype html><html><body></body></html>");
globalThis.document = document;

const { default: sanitizeHtml } = await import(
	"../../resources/js/components/helpers/sanitize-html.js"
);
const { default: messageHtml } = await import(
	"../../resources/js/components/helpers/message-html.js"
);

// Rows stored before the server cleaned every path can hold anything.
const attacks = [
	'<img src=x onerror="window.__xss=1">',
	"<script>window.__xss=1</script>",
	'<p onclick="window.__xss=1">Hi</p>',
	'<a href="javascript:window.__xss=1">Hi</a>',
	'<a href=" jav&#x09;ascript:window.__xss=1">Hi</a>',
	'<iframe src="https://evil.example"></iframe>',
	'<svg><a href="javascript:1"><text>x</text></a></svg>',
	'<math><mi xlink:href="javascript:1">x</mi></math>',
	'<style>body{display:none}</style><p style="position:fixed">Hi</p>',
	'<form action="https://evil.example"><input name="password"></form>',
	'<noscript><p title="</noscript><img src=x onerror=window.__xss=1>"></noscript>',
];

test("drops every element and attribute that can run script or restyle the page", () => {
	for (const attack of attacks) {
		const clean = sanitizeHtml(attack).toLowerCase();

		for (const banned of [
			"<img",
			"<script",
			"onerror",
			"onclick",
			"javascript:",
			"<iframe",
			"<svg",
			"<math",
			"<style",
			"style=",
			"<form",
			"<input",
		]) {
			assert.ok(!clean.includes(banned), `${attack} kept ${banned}: ${clean}`);
		}
	}
});

test("keeps the text of what it drops", () => {
	assert.equal(
		sanitizeHtml('<p onclick="x">Hi <b>there</b></p>'),
		"<p>Hi <b>there</b></p>",
	);
	assert.equal(sanitizeHtml("<div><span>Hi</span></div>"), "Hi");
	assert.equal(sanitizeHtml("1 < 2 & 3 > 2"), "1 &lt; 2 &amp; 3 &gt; 2");
});

test("keeps what the composer writes and the server's own notes", () => {
	for (const html of [
		"<p><strong>Rent</strong> is <em>due</em></p><p>Line<br>break</p>",
		'<ul><li><p>One</p></li></ul><ol start="3"><li><p>Two</p></li></ol>',
		'<p><a target="_blank" rel="noopener noreferrer nofollow" href="https://example.com/a?b=1&amp;c=2">the form</a></p>',
		'<p>Escalated to <a href="/admin/tickets/3" title="Ticket #3">Platform Support</a> by Tess</p>',
		'<p><a href="mailto:help@example.com">help</a></p>',
		"<blockquote><p>Quoted</p></blockquote><pre><code>code</code></pre><hr><h2>Head</h2><s>old</s>",
	]) {
		assert.equal(sanitizeHtml(html), html);
	}
});

test("never opens a link in a new tab without cutting it off from this page", () => {
	assert.equal(
		sanitizeHtml('<a href="https://example.com" target="_blank">x</a>'),
		'<a href="https://example.com" target="_blank" rel="noopener noreferrer">x</a>',
	);
	assert.equal(
		sanitizeHtml('<a href="https://example.com" target="_top">x</a>'),
		'<a href="https://example.com">x</a>',
	);
});

test("the chat draws a message through it", () => {
	const html = messageHtml({
		id: 1,
		side: "other",
		content: '{"content":"<img src=x onerror=window.__xss=1>"}',
		user_name: "Aisha",
		attachments: [],
	});

	assert.ok(!html.includes("onerror"), html);
	assert.ok(!html.includes("<img"), html);
});
