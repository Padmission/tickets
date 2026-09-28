// The attachment preview locks the page's scroll while it is open. It is released when the
// dialog closes however it closes, its button, Escape or the page closing it, rather than on
// one button's click.
export default function lockScrollWhileOpen(dialog, root) {
	root.classList.add("has-open-dialog");

	dialog.addEventListener(
		"close",
		() => root.classList.remove("has-open-dialog"),
		{
			once: true,
		},
	);
}
