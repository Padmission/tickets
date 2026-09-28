import assert from "node:assert/strict";
import { test } from "node:test";

import isComposerShown from "../../resources/js/components/helpers/composer-shown.js";

test("shows the reply box while the ticket is open and the viewer may reply", () => {
	assert.equal(isComposerShown("true", false), true);
});

test("keeps it on a closed ticket while its writer is offered a reopen or a new ticket", () => {
	assert.equal(isComposerShown("true", true, ["reopen"]), true);
	assert.equal(isComposerShown(undefined, true, ["new"]), true);
});

test("hides it on a closed ticket that offers its reader nothing, and brings it back when it reopens", () => {
	assert.equal(isComposerShown("true", true, []), false);
	assert.equal(isComposerShown("true", true), false);
	assert.equal(isComposerShown("true", false), true);
});

test("hides it from a viewer who may not reply, open or not", () => {
	assert.equal(isComposerShown("false", false), false);
	assert.equal(isComposerShown("false", true, ["reopen"]), false);
});

test("leaves a chat given no answer, such as the widget's, to the ticket's state", () => {
	assert.equal(isComposerShown(undefined, false), true);
	assert.equal(isComposerShown(undefined, true), false);
});
