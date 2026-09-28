// A ticket opened by its id alone, as from an email's link, has no subject until its messages
// load, and is never a new chat, so it shows nothing rather than "New Chat" until then.
export default function ticketHeading(subject, ticketId, newChatLabel) {
	if (subject) {
		return subject;
	}

	return ticketId ? "" : newChatLabel;
}
