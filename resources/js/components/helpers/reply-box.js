// While the host says the viewer may not reply, the reply box stays in place, greyed, with the
// host's reason where the placeholder would be, so it is clear why nothing can be sent.
export default function replyBox(disabledReason, placeholder) {
	const reason =
		typeof disabledReason === "string" && disabledReason.trim() !== ""
			? disabledReason
			: null;

	return { disabled: reason !== null, placeholder: reason ?? placeholder };
}

// A refusal gives its reason under `message`, as Laravel's own errors do; older ones used `error`.
export function errorMessageOf(json) {
	return json?.message || json?.error || null;
}
