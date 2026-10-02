import escapeHtml from "./escape-html.js";

// What the composer and the server's notes write. Rows stored before the server cleaned
// every path can hold anything, so the chat keeps only these and rebuilds them itself.
const KEPT = new Set([
	"a",
	"b",
	"blockquote",
	"br",
	"code",
	"em",
	"h1",
	"h2",
	"h3",
	"h4",
	"h5",
	"h6",
	"hr",
	"i",
	"li",
	"ol",
	"p",
	"pre",
	"s",
	"strike",
	"strong",
	"ul",
]);

const VOID = new Set(["br", "hr"]);

// Dropped with everything inside: their content is code, a document of its own, or never text.
const DROPPED = new Set([
	"applet",
	"audio",
	"base",
	"canvas",
	"embed",
	"frame",
	"frameset",
	"head",
	"iframe",
	"link",
	"math",
	"meta",
	"noembed",
	"noframes",
	"noscript",
	"object",
	"plaintext",
	"script",
	"select",
	"style",
	"svg",
	"template",
	"textarea",
	"title",
	"video",
	"xmp",
]);

const HTML_NAMESPACE = "http://www.w3.org/1999/xhtml";

// Tiptap's own link check: the protocols it allows, or an address with no protocol at all.
// Browsers ignore whitespace and control characters inside a scheme, so they go before matching.
const ATTR_WHITESPACE =
	// biome-ignore lint/suspicious/noControlCharactersInRegex: the control characters are what it strips.
	/[\u0000-\u0020\u00A0\u1680\u180E\u2000-\u2029\u205F\u3000]/g;
const SAFE_URI =
	/^(?:(?:https?|ftps?|mailto|tel|callto|sms|cid|xmpp):|[^a-z]|[a-z0-9+.-]+(?:[^a-z+.\-:]|$))/i;

function isSafeUri(uri) {
	return SAFE_URI.test(uri.replace(ATTR_WHITESPACE, ""));
}

function attributesOf(element, tag) {
	const kept = [];

	if (tag === "a") {
		const href = element.getAttribute("href");
		const target = element.getAttribute("target");
		let rel = element.getAttribute("rel");

		for (const { name, value } of Array.from(element.attributes)) {
			if (name === "href" && isSafeUri(value)) kept.push([name, value]);
			if (name === "title") kept.push([name, value]);
			if (name === "target" && value === "_blank") kept.push([name, value]);
			if (name === "rel") kept.push([name, value]);
		}

		if (href !== null && target === "_blank") {
			const tokens = (rel ?? "").split(/\s+/).filter(Boolean);

			if (!tokens.includes("noopener") || !tokens.includes("noreferrer")) {
				rel = [...new Set([...tokens, "noopener", "noreferrer"])].join(" ");

				const existing = kept.find(([name]) => name === "rel");

				if (existing) existing[1] = rel;
				else kept.push(["rel", rel]);
			}
		}
	}

	if (tag === "ol" && /^\d+$/.test(element.getAttribute("start") ?? "")) {
		kept.push(["start", element.getAttribute("start")]);
	}

	return kept
		.map(([name, value]) => ` ${name}="${escapeHtml(value)}"`)
		.join("");
}

function serialize(node) {
	if (node.nodeType === 3) {
		return escapeHtml(node.nodeValue);
	}

	if (node.nodeType !== 1) {
		return "";
	}

	const tag = node.localName;

	if (
		DROPPED.has(tag) ||
		(node.namespaceURI && node.namespaceURI !== HTML_NAMESPACE)
	) {
		return "";
	}

	const inner = Array.from(node.childNodes).map(serialize).join("");

	if (!KEPT.has(tag)) {
		return inner;
	}

	if (VOID.has(tag)) {
		return `<${tag}>`;
	}

	return `<${tag}${attributesOf(node, tag)}>${inner}</${tag}>`;
}

// A message's HTML, rebuilt from an inert template: nothing in it loads or runs while it is
// read, and what comes back holds only the kept elements, their safe attributes and escaped text.
export default function sanitizeHtml(html) {
	const template = document.createElement("template");
	template.innerHTML = String(html ?? "");

	return Array.from(template.content.childNodes).map(serialize).join("");
}
