// The error comes from the server and can repeat what was typed, so it is shown as text.
export default function showFieldError(formField, message) {
	formField.classList.add("has-error");
	formField.querySelector(".error").textContent = message ?? "";
}
