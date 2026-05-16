const parseSse = async (response, handlers) => {
	const reader = response.body.getReader();
	const decoder = new TextDecoder();
	let buffer = "";

	while (true) {
		const { value, done } = await reader.read();

		if (done) {
			break;
		}

		buffer += decoder.decode(value, { stream: true });
		const events = buffer.split("\n\n");
		buffer = events.pop() || "";

		for (const rawEvent of events) {
			const lines = rawEvent.split("\n");
			const event = lines
				.find((line) => line.startsWith("event:"))
				?.slice(6)
				.trim();
			const dataLine = lines
				.find((line) => line.startsWith("data:"))
				?.slice(5)
				.trim();
			const data = dataLine ? JSON.parse(dataLine) : {};

			handlers[event]?.(data);
		}
	}
};

window.PadmissionSupportStream = {
	async start(payload) {
		const token = document
			.querySelector('meta[name="csrf-token"]')
			?.getAttribute("content");

		try {
			const response = await fetch(payload.url, {
				method: "POST",
				headers: {
					Accept: "text/event-stream",
					"Content-Type": "application/json",
					...(token ? { "X-CSRF-TOKEN": token } : {}),
				},
				body: JSON.stringify({
					message: payload.message,
					conversation_id: payload.conversation_id,
					panel_id: payload.panel_id,
					context: {
						page_title: document.title || null,
						page_url: window.location.href,
						...(payload.context || {}),
					},
				}),
			});

			if (!response.ok) {
				window.Livewire?.dispatch("padmission-support-stream-error", {
					message:
						"Support AI could not start. Please try again or open a ticket.",
				});
				window.Livewire?.dispatch("padmission-support-stream-complete");
				return;
			}

			await parseSse(response, {
				start: (data) => {
					if (data.conversation_id && data.ai_activity_id && data.ticket_id) {
						window.Livewire?.dispatch("padmission-support-stream-started", {
							conversationId: data.conversation_id,
							activityId: data.ai_activity_id,
							ticketId: data.ticket_id,
						});
					}
				},
				complete: () =>
					window.Livewire?.dispatch("padmission-support-stream-complete"),
				error: (data) => {
					window.Livewire?.dispatch("padmission-support-stream-error", {
						message:
							data.message || "Support AI could not finish the response.",
					});
					window.Livewire?.dispatch("padmission-support-stream-complete");
				},
				done: () =>
					window.Livewire?.dispatch("padmission-support-stream-complete"),
			});
		} catch (error) {
			window.Livewire?.dispatch("padmission-support-stream-error", {
				message:
					"Support AI could not connect. Please try again or open a ticket.",
			});
			window.Livewire?.dispatch("padmission-support-stream-complete");
			return;
		}
	},
};
