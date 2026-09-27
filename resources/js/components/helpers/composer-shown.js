// The reply box shows only while the ticket is open and the viewer may reply in it. A chat
// given no can-reply answer, as in the widget, leaves that to the server.
export default function isComposerShown(canReply, isClosed) {
	return canReply !== "false" && !isClosed;
}
