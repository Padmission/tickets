export default async function fetchJson(url, data = {}, method = "GET") {
	if (method === "GET") {
		url += "?" + new URLSearchParams(data).toString();
	}

	const response = await fetch(url, {
		method: method,
		headers: {
			"Content-Type": "application/json",
			Accept: "application/json",
			"X-Requested-With": "XMLHttpRequest",
			"X-CSRF-TOKEN":
				document
					.querySelector('meta[name="csrf-token"]')
					?.getAttribute("content") || "",
		},
		credentials: "same-origin",
		body: method === "POST" ? JSON.stringify(data) : null,
	});

	if (!response.ok) {
		const errorText = await response.text();
		console.error("HTTP error response", {
			status: response.status,
			text: errorText,
		});

		throw new Error("HTTP Error");
	}

	return await response.json();
}
