// A message that the list's top edge cuts through is hidden until it scrolls fully into
// view, so the list never shows half a bubble. One taller than half the list stays shown,
// because it may never fit and hiding it would leave the list blank.
export default function isCutOffAtTop(box, listTop, listHeight) {
	return box.top < listTop && box.bottom > listTop && box.bottom - box.top <= listHeight / 2;
}
