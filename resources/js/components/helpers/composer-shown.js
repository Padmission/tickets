// The reply box shows while the viewer may reply and the ticket is open, or, once it has closed,
// while the server still offers them a way to write, a reopen or a new ticket. A chat given no
// can-reply answer, as in the widget, leaves that to the server.
export default function isComposerShown(
	canReply,
	isClosed,
	reopenChoices = [],
) {
	return canReply !== "false" && (!isClosed || reopenChoices.length > 0);
}
