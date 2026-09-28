import __ from "./trans.js";

export const REOPEN = "reopen";
export const NEW_TICKET = "new";
export const CANCEL = "cancel";

// What Send asks on a closed ticket, from the choices the server offers this writer: a requester
// may reopen it or start a new ticket, and only start one once the reopen window has passed;
// staff may only reopen it. Null when there is nothing to offer.
export default function reopenDialog(choices = [], windowDays = 30) {
	const canReopen = choices.includes(REOPEN);
	const canStartNew = choices.includes(NEW_TICKET);

	if (!canReopen && !canStartNew) {
		return null;
	}

	const kind = canStartNew ? (canReopen ? "same_problem" : "too_old") : "staff";

	return {
		heading: __(`chat.reopen_dialog.heading_${kind}`, { days: windowDays }),
		body: __(`chat.reopen_dialog.body_${kind}`, { days: windowDays }),
		buttons: [
			...(canReopen
				? [
						{
							choice: REOPEN,
							label: __("chat.reopen_dialog.reopen"),
							primary: true,
						},
					]
				: []),
			...(canStartNew
				? [
						{
							choice: NEW_TICKET,
							label: __("chat.reopen_dialog.new_ticket"),
							primary: !canReopen,
						},
					]
				: []),
			{
				choice: CANCEL,
				label: __("chat.reopen_dialog.cancel"),
				primary: false,
			},
		],
	};
}

export function closedTicketLine(
	closedAt,
	locale = undefined,
	timeZone = undefined,
) {
	if (!closedAt) {
		return "";
	}

	const date = new Date(closedAt).toLocaleDateString(locale, {
		dateStyle: "medium",
		timeZone,
	});

	return __("chat.closed_on", { date });
}
