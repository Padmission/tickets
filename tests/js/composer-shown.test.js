import assert from "node:assert/strict";
import { test } from "node:test";

import isComposerShown from "../../resources/js/components/helpers/composer-shown.js";

test("shows the reply box while the ticket is open and the viewer may reply", () => {
	assert.equal(isComposerShown("true", false), true);
});

test("hides it once the ticket closes, and brings it back when it reopens", () => {
	assert.equal(isComposerShown("true", true), false);
	assert.equal(isComposerShown("true", false), true);
});

test("hides it from a viewer who may not reply, open or not", () => {
	assert.equal(isComposerShown("false", false), false);
	assert.equal(isComposerShown("false", true), false);
});

test("leaves a chat given no answer, such as the widget's, to the ticket's state", () => {
	assert.equal(isComposerShown(undefined, false), true);
	assert.equal(isComposerShown(undefined, true), false);
});
