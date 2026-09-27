import assert from "node:assert/strict";
import { test } from "node:test";

import isCutOffAtTop from "../../resources/js/components/helpers/cut-off-at-top.js";

const list = { top: 500, height: 400 };
const box = (top, height) => ({ top, bottom: top + height });

test("a message crossing the list's top edge is cut off", () => {
	assert.equal(isCutOffAtTop(box(470, 60), list.top, list.height), true);
});

test("a message that starts at or below the top edge is not cut off", () => {
	assert.equal(isCutOffAtTop(box(500, 60), list.top, list.height), false);
	assert.equal(isCutOffAtTop(box(620, 60), list.top, list.height), false);
});

test("a message already scrolled out of view is not cut off", () => {
	assert.equal(isCutOffAtTop(box(380, 60), list.top, list.height), false);
	assert.equal(isCutOffAtTop(box(440, 60), list.top, list.height), false);
});

test("a message taller than half the list stays shown so it can still be read", () => {
	assert.equal(isCutOffAtTop(box(300, 400), list.top, list.height), false);
	assert.equal(isCutOffAtTop(box(420, 200), list.top, list.height), true);
});
