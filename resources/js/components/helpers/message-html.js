import escapeHtml from "./escape-html.js";

export const FILE_ICON =
	'<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-file-type-2"><path d="M4 22h14a2 2 0 0 0 2-2V7l-5-5H6a2 2 0 0 0-2 2v4"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path><path d="M2 13v-1h6v1"></path><path d="M5 12v6"></path><path d="M4 18h2"></path></svg>';

// A sent attachment's button. Its file name comes from whoever uploaded it, so it and every
// other value are escaped, in the text and inside the quoted attributes alike.
export function attachmentHtml(attachment) {
	return `
		<button
			class="attachment"
			data-preview="${escapeHtml(attachment.filepath)}"
			data-preview-type="${escapeHtml(attachment.type)}"
			target="_blank"
		>
			${attachment.type === "file" ? FILE_ICON : ""}
			${
				attachment.type === "image"
					? `<img src="${escapeHtml(attachment.preview_url)}" alt="${escapeHtml(attachment.filename)}">`
					: `<span>${escapeHtml(attachment.filename)}</span>`
			}
		</button>
	`;
}

// One chat message. Its content is the server's sanitized HTML, which already escapes the
// names it quotes; the sender's name is whatever they set on their profile, so it is escaped.
export default function messageHtml(
	message,
	{ dateChanged = false, date = "" } = {},
) {
	return `
		${dateChanged ? ` <time datetime="${escapeHtml(date)}" class="message-date">${escapeHtml(date)}</time>` : ""}

		<div
			class="message"
			data-side="${escapeHtml(message.side)}"
			data-message-id="${escapeHtml(message.id)}"
		>
			<div class="message__content">
				<div class="markdown">
					${message.content || ""}
				</div>

				${
					message.attachments
						? `<div class="message__attachments">${message.attachments.map(attachmentHtml).join("")}</div>`
						: ""
				}
			</div>
			<div class="message__sender">
				${escapeHtml(message.user_name)}
			</div>
		</div>
	`;
}

// A file waiting in the composer, named by the user's own file system.
export function pendingAttachmentHtml(attachment, index, previewUrl = null) {
	return `
		<div class="attachment">
			<button
				class="button-icon"
				@click="removeAttachment"
				data-index="${escapeHtml(index)}"
			>
				<span class="sr-only">Remove</span>
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
			</button>

			${attachment.type === "file" ? FILE_ICON : ""}

			${
				previewUrl !== null
					? `<img src="${escapeHtml(previewUrl)}" alt="${escapeHtml(attachment.name)}">`
					: `<span>${escapeHtml(attachment.name)}</span>`
			}
		</div>
	`;
}
