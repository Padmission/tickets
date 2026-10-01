import { Editor, Extension } from "@tiptap/core";
import Placeholder from "@tiptap/extension-placeholder";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";

import fetchJson from "./helpers/fetch-json";
import BaseElement from "./helpers/base-element";
import render from "./helpers/render";
import humanFileSize from "./helpers/human-file-size.js";
import isCutOffAtTop from "./helpers/cut-off-at-top.js";
import formatMessageTime from "./helpers/format-message-time.js";
import ticketSubject from "./helpers/ticket-subject.js";
import escapeHtml from "./helpers/escape-html.js";
import lockScrollWhileOpen from "./helpers/scroll-lock.js";
import messageHtml, { pendingAttachmentHtml } from "./helpers/message-html.js";
import isComposerShown from "./helpers/composer-shown.js";
import replyBox, { errorMessageOf } from "./helpers/reply-box.js";
import reopenDialog, {
	NEW_TICKET,
	REOPEN,
	closedTicketLine,
} from "./helpers/reopen-dialog.js";
import config from "./helpers/config.js";
import __ from "./helpers/trans.js";

customElements.define(
	"chat-component",
	class extends BaseElement {
		get stylesheet() {
			return "/css/padmission/tickets/chat-component.css";
		}

		constructor() {
			super();

			this.scrollThreshold = 100;
			this.pollingIntervalMs = 10000;

			this.ticketId = null;
			this.ticket = null;
			this.seenOpen = false;
			this.loadedSubject = null;

			this.reopenChoices = [];
			this.reopenWindowDays = 30;
			this.followsUp = null;

			this.messages = [];
			this.attachments = [];
			this.lastMessageId = 0;
			this.lastTimestamp = null;
			this.lastSeenMessageId = 0;

			this.editor = null;
			this.pollingInterval = null;
			this.messageContent = "";
			this.messageObserver = null;
			this.messageListObserver = null;
			this.messageListResizeObserver = null;

			this.isNearBottom = true;
			this.dropIndex = 0;

			this.markSeenDebounceTimer = null;
			this.markSeenDebounceMs = 2000; // 2 seconds
		}

		beforeRender() {
			if (this.config) {
				config.setConfig(JSON.parse(this.config));
			}
		}

		disconnectedCallback() {
			this.stopPolling();

			if (this.editor) {
				this.editor.destroy();
			}

			if (this.messageObserver) {
				this.messageObserver.disconnect();
			}

			if (this.messageListObserver) {
				this.messageListObserver.disconnect();
			}

			if (this.messageListResizeObserver) {
				this.messageListResizeObserver.disconnect();
			}

			// Flush pending mark-seen call before disconnecting
			if (this.markSeenDebounceTimer) {
				clearTimeout(this.markSeenDebounceTimer);
				this.markTicketSeen(this.lastSeenMessageId);
			}
		}

		afterRender(node) {
			this.messagesElement = node.querySelector("[data-chat-messages]");
			this.editorElement = node.querySelector("[data-chat-input]");
			this.sendButtonElement = node.querySelector("[data-chat-submit]");
			this.scrollToBottomBtn = node.querySelector(
				"[data-chat-scroll-to-bottom]",
			);
			this.lockTurnCheckbox = node.querySelector("[data-chat-lock-turn]");

			this.initNearBottomTracking();

			// Event listeners
			this.scrollToBottomBtn.addEventListener("click", () =>
				this.scrollToBottom(),
			);

			if (this.canReply === "false") {
				node.querySelector("[data-composer]").style.display = "none";
			}

			node
				.querySelector("[data-composer]")
				.addEventListener("submit", (event) => {
					this.sendMessage(
						event.submitter?.hasAttribute("data-chat-submit-keep-waiting") ??
							false,
					);
					event.preventDefault();
				});

			this.initTipTapEditor();
			this.applyReplyBox();
			this.initIntersectionObserver();

			if (!this.ticketId) {
				if (config.introMessage) {
					this.renderMessages([
						{
							content: config.introMessage,
							side: "system",
							created_at: new Date().toISOString(),
						},
					]);
				}

				return;
			}

			this.loadMessages().then(() => {
				this.scrollToBottom();
			});

			this.startPolling();
		}

		initNearBottomTracking() {
			this.scrollToBottomBtn.style.display = "none";

			this.messagesElement.addEventListener("scroll", () => {
				const { scrollTop, scrollHeight, clientHeight } = this.messagesElement;

				const distanceFromBottom = scrollHeight - scrollTop - clientHeight;

				this.isNearBottom = distanceFromBottom < this.scrollThreshold;
				this.scrollToBottomBtn.style.display = this.isNearBottom
					? "none"
					: "flex";

				this.hideMessagesCutOffAtTop();
			});

			this.messageListResizeObserver = new ResizeObserver(() =>
				this.hideMessagesCutOffAtTop(),
			);
			this.messageListResizeObserver.observe(this.messagesElement);
		}

		hideMessagesCutOffAtTop() {
			const list = this.messagesElement.getBoundingClientRect();

			for (const item of this.messagesElement.children) {
				item.toggleAttribute(
					"data-cut-off",
					isCutOffAtTop(item.getBoundingClientRect(), list.top, list.height),
				);
			}
		}

		initTipTapEditor() {
			const chat = this;

			this.editor = new Editor({
				element: this.editorElement,
				extensions: [
					StarterKit,
					Placeholder.configure({
						placeholder: () => chat.replyBoxState().placeholder,
					}),
					Link.configure({
						openOnClick: false,
						defaultProtocol: "https",
					}),
					Extension.create({
						addKeyboardShortcuts() {
							return {
								"Cmd-Enter": () => chat.sendMessage.call(chat),
								"Ctrl-Enter": () => chat.sendMessage.call(chat),
							};
						},
					}),
				],
				content: "",
				onUpdate: ({ editor }) => (this.messageContent = editor.getHTML()),
			});
		}

		async loadMessages() {
			try {
				const data = await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						offset: this.lastMessageId,
					},
				);

				const ticket = data.ticket;
				const messages = data.messages;

				if (ticket.subject && ticket.subject !== this.loadedSubject) {
					this.loadedSubject = ticket.subject;
					this.dispatchEvent(
						new CustomEvent("ticket-loaded", {
							detail: { subject: ticket.subject },
						}),
					);
				}

				this.ticket = ticket;
				this.reopenChoices = ticket.reopen_choices ?? [];
				this.reopenWindowDays =
					ticket.reopen_window_days ?? this.reopenWindowDays;
				this.showTicketState(ticket);

				if (ticket.is_closed) {
					// Closed by someone else while this chat was open, so the page around it can catch up.
					if (this.seenOpen) {
						this.seenOpen = false;
						this.dispatchEvent(new CustomEvent("ticket-closed"));
					}
				} else {
					this.seenOpen = true;
				}

				if (messages.length === 0) {
					if (
						ticket.is_closed &&
						this.closedEmptyMessage &&
						this.messages.length === 0
					) {
						this.renderMessages([
							{
								id: "closed-empty",
								content: this.closedEmptyMessage,
								side: "system",
								created_at: null,
							},
						]);
					}

					return;
				}

				const newestMessage = messages[messages.length - 1];
				const newestMessageId = messages.reduce(
					(maxId, message) => Math.max(maxId, message.id),
					this.lastMessageId,
				);

				this.lastTimestamp = newestMessage.created_at;
				this.lastMessageId = newestMessageId;

				if (this.lastSeenMessageId === 0) {
					this.lastSeenMessageId = newestMessageId;
				}

				this.ticket = ticket;

				this.renderMessages(messages);
				this.checkUnreadMessages();

				return messages;
			} catch (error) {
				console.error("Error loading messages:", error);
			}
		}

		checkUnreadMessages() {
			const hasNewMessages = this.lastMessageId > this.lastSeenMessageId;

			if (!hasNewMessages) {
				this.scrollToBottomBtn.dataset.chatHasNewMessages = "false";

				return;
			}

			this.scrollToBottomBtn.dataset.chatHasNewMessages = "true";

			if (this.isNearBottom) {
				this.scrollToBottom();
			}
		}

		renderMessages(messages = null) {
			const existingMessageIds = this.messages.map((message) => message.id);

			messages = messages || [];

			messages.forEach((message) => {
				if (message.content === null && message.attachments.length === 0) {
					return;
				}

				if (existingMessageIds.includes(message.id)) {
					return;
				}

				const lastDate =
					this.messages.length > 0
						? new Date(this.messages[this.messages.length - 1].created_at)
						: true;

				const formatter = (date) =>
					`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")} ${String(date.getHours()).padStart(2, "0")}:${String(date.getMinutes()).padStart(2, "0")}`;

				const messageDate = new Date(message.created_at);
				const hasDateChanged =
					Boolean(message.created_at) &&
					(lastDate === true || formatter(lastDate) !== formatter(messageDate));
				const absoluteDate = formatMessageTime(messageDate, this.timezone);

				const renderedHtml = render(
					messageHtml(message, {
						dateChanged: hasDateChanged,
						date: absoluteDate,
					}),
				);

				renderedHtml.querySelectorAll("[data-preview]").forEach((el) =>
					el.addEventListener("click", async (event) => {
						const el = event.currentTarget;
						const type = el.dataset.previewType;

						const temporaryUrl = await this.getTemporarySignedUrl(
							el.dataset.preview,
						);

						if (!["image", "video"].includes(type)) {
							window.open(temporaryUrl.url);
							event.preventDefault();

							return;
						}

						event.preventDefault();

						const dialog = this.rootNode().querySelector(
							"[data-preview-popup]",
						);
						const dialogContent = dialog.querySelector(
							"[data-preview-popup-content]",
						);

						const previewEl =
							type === "image"
								? render(`<img src="${escapeHtml(temporaryUrl.url)}" alt="">`)
								: render(
										`<video src="${escapeHtml(temporaryUrl.url)}" controls>`,
									);

						dialogContent.replaceChildren(previewEl);
						dialog.showModal();
						lockScrollWhileOpen(dialog, document.documentElement);
					}),
				);

				this.messagesElement.append(renderedHtml);
				this.messages.push(message);
			});

			this.observeMessages();
			this.hideMessagesCutOffAtTop();
		}

		replyBoxState() {
			return replyBox(
				this.replyDisabledReason,
				this.placeholder || __("chat.placeholder"),
			);
		}

		applyReplyBox() {
			const { disabled } = this.replyBoxState();
			const composer = this.rootNode().querySelector("[data-composer]");

			composer.classList.toggle("composer--disabled", disabled);
			composer.setAttribute("aria-disabled", disabled ? "true" : "false");

			for (const control of composer.querySelectorAll("button, input")) {
				control.disabled =
					disabled ||
					(this.isSending && control.hasAttribute("data-chat-submit"));
			}

			if (this.editor) {
				this.editor.setEditable(!disabled);
				// Draws the placeholder again, which reads the reason.
				this.editor.view.dispatch(this.editor.state.tr);
			}
		}

		showTicketState(ticket) {
			if (ticket && "reply_disabled_reason" in ticket) {
				this.replyDisabledReason = ticket.reply_disabled_reason ?? "";
				this.applyReplyBox();
			}

			this.rootNode().querySelector("[data-composer]").style.display =
				isComposerShown(
					this.canReply,
					ticket?.is_closed ?? false,
					this.reopenChoices,
				)
					? ""
					: "none";

			const line = this.rootNode().querySelector("[data-chat-closed-line]");
			const text = ticket?.is_closed
				? closedTicketLine(
						ticket.closed_at,
						undefined,
						this.timezone || undefined,
					)
				: "";

			line.textContent = text;
			line.hidden = text === "";
		}

		// Send on a closed ticket asks first what it should do: reopen the ticket, start a new
		// one that links back, or neither, keeping the typed message.
		askBeforeSendingOnClosedTicket(keepWaiting) {
			const model = reopenDialog(this.reopenChoices, this.reopenWindowDays);

			if (!model) {
				return;
			}

			const dialog = this.rootNode().querySelector("[data-reopen-dialog]");

			dialog.querySelector("[data-reopen-heading]").textContent = model.heading;
			dialog.querySelector("[data-reopen-body]").textContent = model.body;

			const actions = dialog.querySelector("[data-reopen-actions]");

			actions.replaceChildren(
				...model.buttons.map((button) => {
					const element = document.createElement("button");

					element.type = "button";
					element.textContent = button.label;
					element.dataset.reopenChoice = button.choice;
					element.className = button.primary
						? "reopen__button reopen__button--primary"
						: "reopen__button";
					element.addEventListener("click", () => {
						dialog.close();

						if (button.choice === REOPEN) {
							this.sendMessage(keepWaiting, { reopen: true });
						} else if (button.choice === NEW_TICKET) {
							this.startNewTicket();
						}
					});

					return element;
				}),
			);

			dialog.showModal();
			lockScrollWhileOpen(dialog, document.documentElement);
		}

		// The typed message opens a new ticket that follows up this one, shown in its place.
		async startNewTicket() {
			this.followsUp = this.ticketId;
			this.stopPolling();

			this.ticketId = null;
			this.ticket = null;
			this.reopenChoices = [];
			this.messages = [];
			this.lastMessageId = 0;
			this.lastSeenMessageId = 0;
			this.lastTimestamp = null;
			this.seenOpen = false;
			this.loadedSubject = null;
			this.messagesElement.replaceChildren();
			this.showTicketState(null);

			await this.sendMessage();

			this.followsUp = null;

			// The new ticket opens with its link back and intro, written before the message, so its
			// whole history is read rather than only what the send returned.
			if (this.ticketId) {
				this.messages = [];
				this.lastMessageId = 0;
				this.messagesElement.replaceChildren();
				await this.loadMessages();
				this.scrollToBottom();
			}
		}

		// The page this chat sits on calls it after an action that can change whether the viewer
		// may reply or what the conversation holds, rather than leave it to the next poll.
		refreshTicket(canReply) {
			this.canReply = canReply ? "true" : "false";

			return this.loadMessages();
		}

		startPolling() {
			this.pollingInterval = setInterval(
				() => this.loadMessages(),
				this.pollingIntervalMs,
			);
		}

		stopPolling() {
			if (this.pollingInterval) {
				clearInterval(this.pollingInterval);
				this.pollingInterval = null;
			}
		}

		scrollToBottom() {
			this.messagesElement.scrollTop = this.messagesElement.scrollHeight;
		}

		// Create an Intersection Observer to track visible messages
		initIntersectionObserver() {
			const options = {
				root: this.messagesElement,
				threshold: 1.0,
			};

			this.messageObserver = new IntersectionObserver((entries) => {
				entries.forEach((entry) => {
					if (!entry.isIntersecting) {
						return;
					}

					const messageId = Number.parseInt(entry.target.dataset.messageId);

					if (this.lastSeenMessageId > messageId) {
						return;
					}

					this.lastSeenMessageId = messageId;

					// Debounce the API call to mark as seen
					this.debouncedMarkSeen(messageId);

					if (this.lastSeenMessageId >= this.lastMessageId) {
						this.scrollToBottomBtn.dataset.chatHasNewMessages = "false";
					}
				});
			}, options);

			this.observeMessages();
		}

		observeMessages() {
			this.rootNode()
				.querySelectorAll(".message")
				.forEach((message) => {
					this.messageObserver.observe(message);
				});
		}

		debouncedMarkSeen(activityId) {
			// Clear existing timer
			if (this.markSeenDebounceTimer) {
				clearTimeout(this.markSeenDebounceTimer);
			}

			// Set new timer
			this.markSeenDebounceTimer = setTimeout(() => {
				this.markTicketSeen(activityId);
			}, this.markSeenDebounceMs);
		}

		async markTicketSeen(activityId) {
			if (!this.ticketId) {
				return;
			}

			try {
				await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/mark-seen`,
					{ last_seen_activity_id: activityId },
					"POST",
				);
			} catch (error) {
				console.error("Failed to mark ticket as seen:", error);
				// Silent failure - this is a background operation
			}
		}

		toggleBold(event) {
			this.editor.chain().focus().toggleBold().run();
		}

		toggleList(event) {
			this.editor.chain().focus().toggleBulletList().run();
		}

		toggleOrderedList(event) {
			this.editor.chain().focus().toggleOrderedList().run();
		}

		setLink(event) {
			const previousUrl = this.editor.getAttributes("link").href;
			let url = window.prompt("URL", previousUrl);

			// Cancelled
			if (url === null) {
				return;
			}

			if (url === "") {
				this.editor.chain().focus().extendMarkRange("link").unsetLink().run();

				return;
			}

			if (!url.startsWith("http://") && !url.startsWith("https://")) {
				url = `https://${url}`;
			}

			this.editor
				.chain()
				.focus()
				.extendMarkRange("link")
				.setLink({ href: url })
				.run();
		}

		setIsSending(isSending) {
			this.isSending = isSending;
			const button = this.shadowRoot.querySelector("[data-chat-submit]");

			if (isSending) {
				button.classList.add("is-sending");
				button.toggleAttribute("disabled");
			} else {
				button.classList.remove("is-sending");
				button.removeAttribute("disabled");
				this.applyReplyBox();
			}
		}

		addAttachments(attachments) {
			if (this.replyBoxState().disabled) {
				return;
			}

			this.clearError();

			for (let i in attachments) {
				if (attachments[i].size > config.maxUploadFileSize) {
					this.setError(
						__("chat.max_file_size", {
							size: humanFileSize(config?.maxUploadFileSize),
						}),
					);

					return;
				}
			}

			this.attachments = this.attachments.concat(attachments);

			this.renderAttachments();
		}

		handleFileSelect(event) {
			this.addAttachments(Array.from(event.target.files));
		}

		removeAttachment(event) {
			let index = event.currentTarget.dataset.index;

			this.attachments.splice(index, 1);
			this.renderAttachments();
		}

		async takeScreenshot(event) {
			event.preventDefault();
			document.querySelector("chat-widget").hidden = true;

			try {
				// Use Screen Capture API to get display media stream
				const stream = await navigator.mediaDevices.getDisplayMedia({
					video: {
						mediaSource: "screen",
					},
					audio: false,
					preferCurrentTab: true,
					surfaceSwitching: "exclude",
					monitorTypeSurfaces: "exclude",
				});

				// Create a video element to capture the frame
				const video = document.createElement("video");
				video.srcObject = stream;
				video.muted = true;

				// Wait for video to load metadata
				await new Promise((resolve) => {
					video.onloadedmetadata = () => {
						video.play();
						resolve();
					};
				});

				// Create canvas and capture the current frame
				const canvas = document.createElement("canvas");
				canvas.width = video.videoWidth;
				canvas.height = video.videoHeight;

				const ctx = canvas.getContext("2d");
				ctx.drawImage(video, 0, 0);

				// Stop the media stream
				stream.getTracks().forEach((track) => track.stop());

				// Convert canvas to blob and create file
				canvas.toBlob((blob) => {
					const file = new File([blob], `screenshot-${Date.now()}.webp`, {
						type: "image/webp",
						lastModified: Date.now(),
					});

					this.addAttachments([file]);
				}, "image/webp");
			} catch (error) {
				if (error.name === "NotAllowedError") {
					this.setError(__("chat.screenshot.permission_denied"));
				} else {
					this.setError(__("chat.screenshot.failed"));
				}
			}

			document.querySelector("chat-widget").hidden = false;
		}

		async generateThumbnail(file) {
			console.log({ file, indexOf: file.type.indexOf("image/") });
			if (file.type.indexOf("image/") < 0) {
				return null;
			}

			return new Promise((resolve, reject) => {
				const img = new Image();
				const canvas = document.createElement("canvas");
				const ctx = canvas.getContext("2d");

				canvas.width = 300;
				canvas.height = 300;

				img.onload = () => {
					const { width, height } = img;
					const canvasAspect = 1;
					const imageAspect = width / height;

					let drawWidth,
						drawHeight,
						offsetX = 0,
						offsetY = 0;

					if (imageAspect > canvasAspect) {
						drawHeight = 300;
						drawWidth = (width / height) * 300;
						offsetX = (300 - drawWidth) / 2;
					} else {
						drawWidth = 300;
						drawHeight = (height / width) * 300;
						offsetY = (300 - drawHeight) / 2;
					}

					ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight);

					const thumbnailDataUrl = canvas.toDataURL("image/png", 0.8);
					resolve(thumbnailDataUrl);
				};

				img.onerror = () => {
					reject(new Error("Failed to load image for thumbnail generation"));
				};

				img.src = URL.createObjectURL(file);
			});
		}

		renderAttachments() {
			// biome-ignore format: preserve template formatting
			const node = render(`
                <div class="attachments">
                    ${this.attachments.map((attachment, index) => pendingAttachmentHtml(
                        attachment,
                        index,
                        attachment.type.startsWith('image/') ? URL.createObjectURL(attachment) : null,
                    )).join("")}
                </div>
            `);

			this._configureEventListeners(node);

			this.shadowRoot.querySelector("[data-attachments]").replaceChildren(node);
		}

		clearAttachments() {
			this.attachments = [];
			this.renderAttachments();
		}

		async uploadAttachments() {
			if (!this.attachments) {
				return [];
			}

			let uploadedAttachments = [];
			let pendingUploads = [];

			for (let attachment of this.attachments) {
				pendingUploads.push(
					new Promise(async (resolve, reject) => {
						try {
							const thumbnailData = await this.generateThumbnail(attachment);
							const { attachment_id, upload_url } =
								await this.getSignedUploadUrl(attachment, thumbnailData);

							await this.uploadAttachment(attachment, upload_url);
							uploadedAttachments.push(attachment_id);
							resolve();
						} catch (error) {
							reject(error);
						}
					}),
				);
			}

			await Promise.all(pendingUploads);

			return uploadedAttachments;
		}

		async getSignedUploadUrl(file, thumbnailData = null) {
			const payload = {
				filename: file.name,
				content_type: file.type,
				content_length: file.size,
			};

			if (thumbnailData) {
				payload.thumbnail = thumbnailData;
			}

			return fetchJson(
				`/padmission-tickets/api/tickets/${this.ticketId}/upload-url`,
				payload,
				"POST",
			);
		}

		async getTemporarySignedUrl(filepath) {
			return fetchJson(
				`/padmission-tickets/api/tickets/${this.ticketId}/temporary-url`,
				{ filepath },
				"POST",
			);
		}

		async uploadAttachment(file, uploadUrl) {
			const response = await fetch(uploadUrl, {
				method: "PUT",
				headers: {
					"Content-Type": file.type,
				},
				body: file,
			});

			if (!response.ok) {
				throw new Error(`File upload failed: ${response.statusText}`);
			}
		}

		async createTicket() {
			const subject =
				ticketSubject(this.messageContent) || __("chat.default_subject");

			const url = window.location.origin + window.location.pathname;

			const data = await fetchJson(
				`/padmission-tickets/api/tickets/`,
				{
					subject,
					url,
					...(this.followsUp ? { follows_up: this.followsUp } : {}),
				},
				"POST",
			);

			this.setAttribute("ticket-id", data.id);
			this.dispatch("ticket-created", data);
			this.startPolling();

			return data.id;
		}

		async sendMessage(keepWaiting = false, { reopen = false } = {}) {
			const lockTurn = keepWaiting || this.lockTurnCheckbox?.checked || false;

			if (!this.messageContent.trim() && this.attachments.length === 0) {
				return;
			}

			if (this.isSending) {
				return;
			}

			if (this.replyBoxState().disabled) {
				return;
			}

			if (this.ticketId && this.ticket?.is_closed && !reopen) {
				this.askBeforeSendingOnClosedTicket(keepWaiting);

				return;
			}

			this.clearError();
			this.setIsSending(true);

			try {
				if (!this.ticketId) {
					this.ticketId = await this.createTicket();
				}

				const attachment_ids = await this.uploadAttachments();

				const data = await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						content: this.messageContent || "",
						lock_turn: lockTurn,
						attachment_ids: attachment_ids,
						...(reopen ? { reopen: true } : {}),
					},
					"POST",
				);

				if (reopen) {
					await this.loadMessages();
				}

				// Clear the editor
				this.messageContent = "";
				this.editor.commands.clearContent();

				const messages = data.messages;

				this.lastMessageId = messages.reduce(
					(maxId, message) => Math.max(maxId, message.id ?? 0),
					this.lastMessageId,
				);

				this.clearAttachments();
				this.renderMessages(messages);
				this.scrollToBottom();
				this.dispatchEvent(
					new CustomEvent("message-sent", { detail: { reopened: reopen } }),
				);
			} catch (error) {
				console.log("Sending failed", error);
				this.setError((await this.responseMessage(error)) || __("chat.error"));
			}

			this.setIsSending(false);
		}

		async responseMessage(error) {
			const status = error.response?.status ?? 0;

			if (status < 400 || status >= 500) {
				return null;
			}

			try {
				return errorMessageOf(await error.response.json());
			} catch (e) {
				return null;
			}
		}

		setError(message) {
			const el = this.rootNode().querySelector("[data-chat-error]");

			el.textContent = message;
			el.removeAttribute("hidden");
		}

		clearError() {
			this.rootNode()
				.querySelector("[data-chat-error]")
				.setAttribute("hidden", "");
		}

		enableDroparea(event) {
			if (!config.allowFileUploads) {
				return;
			}

			if (this.dropIndex++ === 0 && !this.replyBoxState().disabled) {
				this.rootNode()
					.querySelector("[data-droparea]")
					.removeAttribute("hidden");
			}
		}

		disableDroparea() {
			if (--this.dropIndex === 0) {
				this.rootNode()
					.querySelector("[data-droparea]")
					.setAttribute("hidden", true);
			}
		}

		dragover(event) {
			event.preventDefault();
		}

		handleDroppedFiles(event) {
			if (!config.allowFileUploads) {
				return;
			}

			event.preventDefault();

			const files = Array.from(event.dataTransfer.items)
				.filter((item) => item.kind === "file")
				.map((item) => item.getAsFile());

			this.addAttachments(files);
			this.disableDroparea();
		}

		render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <style>
                    :host {
                        display: none;
                    }
                </style>

                <div
                    class="chat"
                    data-chat
                    @dragenter="enableDroparea"
                    @dragleave="disableDroparea"
                    @dragover="dragover"
                >
                    <div
                        hidden
                        class="droparea"
                        data-droparea
                        @drop="handleDroppedFiles"
                    >
                        <span>${escapeHtml(__('chat.droparea'))}</span>
                    </div>

                    <div class="message-list" data-chat-messages>

                    </div>

                    <div class="scroll-to-bottom-wrapper">
                        <button
                            class="scroll-to-bottom"
                            data-chat-scroll-to-bottom
                        >
                            <span class="chat__badge">${escapeHtml(__('chat.new_messages'))}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                    </div>

                    <form class="composer" data-composer style="position: relative;">
                       <p hidden class="composer__closed" data-chat-closed-line></p>
                       <div hidden class="composer__error" data-chat-error>Something went wrong</div>

                        <div class="composer__message">
                            <div data-chat-input></div>

                            <div data-attachments></div>
                        </div>

                        <div class="composer__toolbar">
                            ${config.allowFileUploads ? `
                                <label
                                    role="button"
                                    class="button button-icon"

                                >
                                    <input
                                        type="file"
                                        id="attachments"
                                        multiple
                                        accept="video/*,image/*,.pdf"
                                        @change="handleFileSelect"
                                        style="display: none;"
                                    >

                                    <span class="sr-only">${escapeHtml(__('chat.add_attachments'))}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip-icon lucide-paperclip"><path d="M13.234 20.252 21 12.3"/><path d="m16 6-8.414 8.586a2 2 0 0 0 0 2.828 2 2 0 0 0 2.828 0l8.414-8.586a4 4 0 0 0 0-5.656 4 4 0 0 0-5.656 0l-8.415 8.585a6 6 0 1 0 8.486 8.486"/></svg>
                                </label>
                            `: ''}

                            ${config.allowFileUploads && config.allowScreenshots && Boolean(navigator.mediaDevices?.getDisplayMedia) ? `
                                <button
                                    role="button"
                                    class="button button-icon"
                                    @click="takeScreenshot"
                                >
                                    <span class="sr-only">${escapeHtml(__('chat.screenshot.capture'))}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-monitor"><rect width="20" height="14" x="2" y="3" rx="2"></rect><line x1="8" x2="16" y1="21" y2="21"></line><line x1="12" x2="12" y1="17" y2="21"></line></svg>
                                </button>
                            `: ''}

                            <button
                                class="button-icon"
                                type="button"
                                @click="toggleBold"
                            >
                                <span class="sr-only">${escapeHtml(__('chat.bold'))}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bold-icon lucide-bold"><path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/></svg>
                            </button>

                            <button
                                class="button-icon"
                                type="button"
                                @click="setLink"
                            >
                                <span class="sr-only">${escapeHtml(__('chat.link'))}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-icon lucide-link"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                            </button>

                            <button
                                class="button-icon"
                                type="button"
                                @click="toggleList"
                            >
                                <span class="sr-only">${escapeHtml(__('chat.unordered_list'))}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list"><path d="M3 12h.01"></path><path d="M3 18h.01"></path><path d="M3 6h.01"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M8 6h13"></path></svg>
                            </button>

                            <button
                                class="button-icon"
                                type="button"
                                @click="toggleOrderedList"
                            >
                                <span class="sr-only">${escapeHtml(__('chat.ordered_list'))}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-ordered"><path d="M10 12h11"></path><path d="M10 18h11"></path><path d="M10 6h11"></path><path d="M4 10h2"></path><path d="M4 6h1v4"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
                            </button>

                            ${
                                this.hasElevatedRights === "true" && this.keepWaitingStyle === "button"
                                    ? `
                                        <button
                                            type="submit"
                                            data-chat-submit-keep-waiting
                                            title="${escapeHtml(__('chat.send_keep_waiting_help'))}"
                                        >
                                            <span>${escapeHtml(__('chat.send_keep_waiting'))}</span>
                                        </button>
                                    `
                                    : ""
                            }

                            <button type="submit" data-chat-submit>
                                <svg class="loading-indicator" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path clip-rule="evenodd" d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill-rule="evenodd" fill="currentColor" opacity="0.2"></path>
                                    <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="currentColor"></path>
                                </svg>

                                <span>${escapeHtml(__('chat.send'))}</span>
                            </button>
                        </div>

                        ${
                            this.hasElevatedRights === "true" && this.keepWaitingStyle !== "button"
                                ? `
                                    <div class="composer__options">
                                        <label title="${escapeHtml(__('chat.lock_turn_help'))}">
                                            <input type="checkbox" data-chat-lock-turn aria-describedby="chat-lock-turn-help" />
                                            ${escapeHtml(__('chat.lock_turn'))}
                                        </label>
                                        <span id="chat-lock-turn-help" class="sr-only">${escapeHtml(__('chat.lock_turn_help'))}</span>
                                    </div>
                                `
                                : ""
                        }
                    </form>
                </div>

                <dialog
                    class="preview"
                    closedby="any"
                    data-preview-popup
                >
                    <form>
                        <button
                            class="button-icon"
                            formmethod="dialog"
                        >
                            <span class="sr-only">${escapeHtml(__('close_modal'))}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                         </button>
                    </form>

                    <div class="preview__inner" data-preview-popup-content>

                    </div>
                </dialog>

                <dialog class="reopen" closedby="any" data-reopen-dialog aria-labelledby="reopen-heading">
                    <h2 class="reopen__heading" id="reopen-heading" data-reopen-heading></h2>
                    <p class="reopen__body" data-reopen-body></p>
                    <div class="reopen__actions" data-reopen-actions></div>
                </dialog>
            `);
		}
	},
);
