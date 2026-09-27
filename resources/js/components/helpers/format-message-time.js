// Same as the ticket page's MESSAGE_TIME_FORMAT, e.g. "Sep 25, 7:15 AM", whatever the browser's
// language, and in the page's timezone when it gives one, so the chat agrees with the rest of the page.
export default function formatMessageTime(date, timeZone = null) {
	return date.toLocaleString("en-US", {
		month: "short",
		day: "numeric",
		hour: "numeric",
		minute: "2-digit",
		hour12: true,
		...(timeZone ? { timeZone } : {}),
	});
}
