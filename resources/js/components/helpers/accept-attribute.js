import escapeHtml from "./escape-html.js";

// The file picker offers the types the server takes, which the chat's config lists.
export default function acceptAttribute(config) {
	return config?.acceptedFileTypes
		? `accept="${escapeHtml(config.acceptedFileTypes)}"`
		: "";
}
