import assert from "node:assert/strict";
import { test } from "node:test";

import lockScrollWhileOpen from "../../resources/js/components/helpers/scroll-lock.js";

const page = () => {
	const classes = new Set();

	return {
		classList: {
			add: (name) => classes.add(name),
			remove: (name) => classes.delete(name),
			contains: (name) => classes.has(name),
		},
	};
};

test("locks the page's scroll while the preview is open and lets it go when the preview closes", () => {
	const root = page();
	const dialog = new EventTarget();

	lockScrollWhileOpen(dialog, root);

	assert.equal(root.classList.contains("has-open-dialog"), true);

	// Escape, the close button and the page all end in the dialog's close event.
	dialog.dispatchEvent(new Event("close"));

	assert.equal(root.classList.contains("has-open-dialog"), false);
});

test("releases the lock each time the preview is opened again and closed", () => {
	const root = page();
	const dialog = new EventTarget();

	for (let i = 0; i < 3; i++) {
		lockScrollWhileOpen(dialog, root);
		dialog.dispatchEvent(new Event("close"));
	}

	assert.equal(root.classList.contains("has-open-dialog"), false);
});
