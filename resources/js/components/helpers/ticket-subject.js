// A ticket started from the widget is named after the start of its first message, cut at the
// last whole word that fits so it never ends mid-word, with an ellipsis when anything was cut.
// A single word too long to fit is cut where the budget ends instead.
export default function ticketSubject(html, maxLength = 40) {
	const text = html
		.replace(/<[^>]*>/g, " ")
		.replace(/\s+/g, " ")
		.trim();

	if (text.length <= maxLength) {
		return text;
	}

	const room = text.slice(0, maxLength);
	const lastSpace = room.lastIndexOf(" ");
	const cut =
		lastSpace > 0 ? room.slice(0, lastSpace) : room.slice(0, maxLength - 1);

	// Never leave half an HTML entity, such as the "&am" of "&amp;", at the end.
	return `${cut.replace(/&[^;\s]*$/, "").replace(/[\s.,;:!?-]+$/, "")}…`;
}
