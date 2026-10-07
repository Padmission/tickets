import assert from "node:assert/strict";
import { test } from "node:test";
import { build } from "esbuild";
import { parseHTML } from "linkedom";

const { window } = parseHTML(
	'<html><head><meta name="csrf-token" content="test"></head><body></body></html>',
);
for (const key of [
	"document",
	"HTMLElement",
	"customElements",
	"CustomEvent",
	"MutationObserver",
]) {
	globalThis[key] = window[key];
}
globalThis.window = window;
globalThis.NodeFilter = { SHOW_ELEMENT: 1, FILTER_ACCEPT: 1, FILTER_REJECT: 2 };

// Load the real components, including their production import graph.
const bundle = await build({
	stdin: {
		contents: `import config from './resources/js/components/helpers/config.js';
            import './resources/js/components/chat-widget.js';
            export { config };`,
		resolveDir: process.cwd(),
	},
	bundle: true,
	format: "esm",
	write: false,
});
const { config } = await import(
	`data:text/javascript;base64,${Buffer.from(bundle.outputFiles[0].text).toString("base64")}`
);
config.setConfig({
	userId: 7,
	panelId: "admin",
	lang: {
		"list.show_closed": "Show closed",
		"list.no_open_tickets": "No open tickets",
		"list.no_tickets": "No tickets yet",
	},
});

const open = {
	id: 1,
	subject: "Current question",
	is_closed: false,
	status: { color: "blue", display_name: "Open" },
};
const closed = {
	id: 2,
	subject: "Old answer",
	is_closed: true,
	status: { color: "green", display_name: "Closed" },
};
let requests;
let answerList;
function resetFetch() {
	requests = [];
	answerList = (includeClosed) => (includeClosed ? [open, closed] : [open]);
	globalThis.fetch = async (url, options) => {
		requests.push({ url, options });
		const parsed = new URL(url, "https://example.test");
		const includeClosed = parsed.searchParams.get("include_closed") === "1";
		return {
			ok: true,
			json: async () =>
				parsed.pathname.endsWith("unread-count")
					? { unread_count: includeClosed ? 2 : 1 }
					: { tickets: answerList(includeClosed) },
		};
	};
}

async function mountList(showClosed = "false") {
	const list = document.createElement("chat-list-tickets");
	list.showClosed = showClosed;
	const node = await list.render();
	list._configureEventListeners(node);
	list.appendChild(node);
	await list.renderedCallback();
	return list;
}

async function changeToggle(list, checked) {
	const toggle = list.querySelector("[data-show-closed]");
	assert.ok(toggle, 'The list has a "Show closed" control');
	toggle.checked = checked;
	toggle.dispatchEvent(new window.Event("change"));
	await new Promise((resolve) => setTimeout(resolve, 0));
}

test("the real toggle requests open-only by default, includes closed on change, and returns to open-only", async () => {
	resetFetch();
	const list = await mountList();
	const toggle = list.querySelector("[data-show-closed]");
	assert.ok(toggle);
	assert.equal(toggle.hasAttribute("checked"), false);
	assert.match(list.textContent, /Show closed/);
	assert.deepEqual(
		[...list.querySelectorAll("[data-open-ticket]")].map(
			(el) => el.dataset.openTicket,
		),
		["1"],
	);

	await changeToggle(list, true);
	assert.deepEqual(
		[...list.querySelectorAll("[data-open-ticket]")].map(
			(el) => el.dataset.openTicket,
		),
		["1", "2"],
	);
	list.querySelector('[data-open-ticket="2"]').click();
	await changeToggle(list, false);
	assert.equal(list.querySelector('[data-open-ticket="2"]'), null);
	assert.deepEqual(
		requests.map(({ url }) =>
			new URL(url, "https://example.test").searchParams.get("include_closed"),
		),
		["0", "1", "0"],
	);
});

test("an empty open list explains the filter and still lets the requester find an old answer", async () => {
	resetFetch();
	answerList = (includeClosed) => (includeClosed ? [closed] : []);
	const list = await mountList();
	assert.match(list.textContent, /No open tickets/);
	await changeToggle(list, true);
	assert.match(list.textContent, /Old answer/);
	assert.doesNotMatch(list.textContent, /No open tickets/);
});

test("late responses cannot put closed tickets back after the toggle is turned off", async () => {
	resetFetch();
	const list = await mountList();
	const resolvers = [];
	list.fetchTickets = () => new Promise((resolve) => resolvers.push(resolve));
	const show = list.toggleClosed({ currentTarget: { checked: true } });
	const hide = list.toggleClosed({ currentTarget: { checked: false } });
	resolvers[1]([open]);
	await hide;
	resolvers[0]([open, closed]);
	await show;
	assert.equal(list.querySelector('[data-open-ticket="2"]'), null);
	assert.deepEqual(list.tickets, [open]);
});

test("the widget keeps the toggle when returning to the list, and its badge follows the same setting", async () => {
	resetFetch();
	window.location = { hash: "" };
	const widget = document.createElement("chat-widget");
	widget.startUnreadPolling = () => {};
	widget.shadowRoot.appendChild(widget.render());
	widget.renderedCallback();
	await widget.updateUnreadBadge();
	assert.equal(
		widget.shadowRoot.querySelector("[data-unread-badge]").textContent,
		"1",
	);

	const list = await mountList();
	widget.shadowRoot.querySelector("[data-dialog-content]").appendChild(list);
	await changeToggle(list, true);
	assert.equal(
		widget.shadowRoot.querySelector("[data-unread-badge]").textContent,
		"2",
	);
	assert.equal(
		requests.at(-1).options.headers["X-Padmission-Tickets-Panel"],
		"admin",
	);
	widget.changeView("chat-view-ticket", { ticketId: 2 });
	widget.changeView("chat-list-tickets");
	const returningList = widget.shadowRoot.querySelector("chat-list-tickets");
	assert.equal(returningList.getAttribute("show-closed"), "true");
	returningList._initializeAttributes();
	assert.equal(
		(await returningList.render())
			.querySelector("[data-show-closed]")
			.hasAttribute("checked"),
		true,
	);

	widget.showClosed = false;
	await widget.updateUnreadBadge();
	assert.equal(
		widget.shadowRoot.querySelector("[data-unread-badge]").textContent,
		"1",
	);
	widget.changeView("chat-list-tickets");
	assert.equal(
		widget.shadowRoot
			.querySelector("chat-list-tickets")
			.getAttribute("show-closed"),
		"false",
	);
	assert.equal(document.createElement("chat-widget").showClosed, false);
});

test("a hash link opens a closed ticket directly while the widget list defaults to open-only", () => {
	resetFetch();
	window.location = { hash: "#ticket-2" };
	const widget = document.createElement("chat-widget");
	widget.shadowRoot.appendChild(widget.render());
	widget.shadowRoot.querySelector("dialog").show = () => {};
	assert.equal(widget.showClosed, false);
	widget.openTicketByHash();
	const view = widget.shadowRoot.querySelector("chat-view-ticket");
	assert.equal(view.getAttribute("ticket-id"), "2");
	view._initializeAttributes();
	assert.equal(
		view.render().querySelector("chat-component").getAttribute("ticket-id"),
		"2",
	);
	assert.equal(requests.length, 0);
});
