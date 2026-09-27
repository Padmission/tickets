const NAMED_ENTITIES = {
	amp: "&",
	lt: "<",
	gt: ">",
	quot: '"',
	apos: "'",
	nbsp: " ",
};

// The editor's HTML escapes what was typed, so its entities are read back as the characters
// they stand for before anything is measured or sent: the subject is stored as plain text.
function decodeEntities(text) {
	return text.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (entity, name) => {
		if (name[0] !== "#") {
			return NAMED_ENTITIES[name.toLowerCase()] ?? entity;
		}

		const code =
			name[1].toLowerCase() === "x"
				? Number.parseInt(name.slice(2), 16)
				: Number.parseInt(name.slice(1), 10);

		return code > 0 && code <= 0x10ffff ? String.fromCodePoint(code) : entity;
	});
}

// A ticket started from the widget is named after the start of its first message, cut at the
// last whole word that fits so it never ends mid-word, with an ellipsis when anything was cut.
// A single word too long to fit is cut where the budget ends instead.
export default function ticketSubject(html, maxLength = 40) {
	const text = decodeEntities(html.replace(/<[^>]*>/g, " "))
		.replace(/\s+/g, " ")
		.trim();

	if (text.length <= maxLength) {
		return text;
	}

	const room = text.slice(0, maxLength);
	const lastSpace = room.lastIndexOf(" ");
	const cut =
		lastSpace > 0 ? room.slice(0, lastSpace) : room.slice(0, maxLength - 1);

	return `${cut.replace(/[\s.,;:!?-]+$/, "")}…`;
}
