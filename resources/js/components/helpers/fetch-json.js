import __ from "./trans.js";

class HttpError
{
    constructor(response) {
        this.response = response
    }

    async error(key) {
        try {
            const json = await this.response.json();

            return json.error || __('errors.unknown');
        } catch (e) {
            return __('errors.unknown');
        }
    }
}

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
		throw new HttpError(response);
	}

	return await response.json();
}
