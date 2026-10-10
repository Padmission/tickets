import escapeHtml from "./escape-html.js";
import sanitizeHtml from "./sanitize-html.js";

// Read-only background shown above the conversation, each section collapsed under its heading.
// The heading and html come from the host, so the heading is escaped and the html cleaned.
export default function contextHtml(sections) {
	return sections
		.map(
			(section) => `
				<details class="context">
					<summary>${escapeHtml(section.heading)}</summary>
					<div class="markdown">${sanitizeHtml(section.html)}</div>
				</details>
			`,
		)
		.join("");
}
