import assert from "node:assert/strict";
import { test } from "node:test";

import replyBox, {
	errorMessageOf,
} from "../../resources/js/components/helpers/reply-box.js";

test("leaves the reply box as it is when the host gives no reason", () => {
	assert.deepEqual(replyBox(null, "Write a reply"), {
		disabled: false,
		placeholder: "Write a reply",
	});
	assert.deepEqual(replyBox("", "Write a reply"), {
		disabled: false,
		placeholder: "Write a reply",
	});
	assert.deepEqual(replyBox(undefined, "Write a reply").disabled, false);
});

test("disables it and shows the host's reason in place of the placeholder", () => {
	assert.deepEqual(replyBox("You are only viewing as Alex.", "Write a reply"), {
		disabled: true,
		placeholder: "You are only viewing as Alex.",
	});
});

test("reads a refusal's reason from message, then error", () => {
	assert.equal(errorMessageOf({ message: "Read only", error: "Old" }), "Read only");
	assert.equal(errorMessageOf({ error: "Old" }), "Old");
	assert.equal(errorMessageOf({}), null);
	assert.equal(errorMessageOf(null), null);
});
