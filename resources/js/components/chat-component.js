import { Editor, Extension } from "@tiptap/core";
import Placeholder from "@tiptap/extension-placeholder";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import fetchJson from "./helpers/fetch-json";

import BaseElement from "./helpers/base-element";
import render from "./helpers/render";

customElements.define(
	"chat-component",
	class extends BaseElement {
		get stylesheet() {
			return "/css/padmission-tickets/chat-component.css";
		}

		constructor() {
			super();

			this.scrollThreshold = 100;
			this.pollingIntervalMs = 5000;

			this.ticketId = null;
			this.ticket = null;

			this.messages = [];
			this.lastMessageId = 0;
			this.lastSeenMessageId = 0;
			this.lastTimestamp = null;

			this.editor = null;
			this.pollingInterval = null;
			this.messageContent = "";
			this.messageObserver = null;
			this.messageListObserver = null;

			this.isNearBottom = true;
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

			node
				.querySelector("[data-composer]")
				.addEventListener("submit", (event) => {
					this.sendMessage();
					event.preventDefault();
				});

			this.initTipTapEditor();
			this.initIntersectionObserver();

			if (!this.ticketId) {
				if (this.defaultMessage) {
					this.renderMessages([
						{
							content: this.defaultMessage,
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
			});
		}

		initTipTapEditor() {
			const chat = this;

			this.editor = new Editor({
				element: this.editorElement,
				extensions: [
					StarterKit,
					Placeholder.configure({
						placeholder: "Start typing …",
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
				const isInitialLoad = this.lastMessageId === null;

				const data = await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						offset: isInitialLoad ? 0 : this.lastMessageId,
					},
				);

				const ticket = data.ticket;
				const messages = data.messages;

				if (messages.length === 0) {
					return;
				}

				const lastMessage = messages[messages.length - 1];
				this.lastTimestamp = lastMessage.created_at;
				this.lastMessageId = lastMessage.id;

				if (this.lastSeenMessageId === 0) {
					this.lastSeenMessageId = lastMessage.id;
				}

				if (ticket.is_closed) {
					this.rootNode().querySelector("[data-composer]").style.display =
						"none";
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
			messages = messages || [];

			messages.forEach((message) => {
				if (message.content === null) {
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
					lastDate === true || formatter(lastDate) !== formatter(messageDate);
				const absoluteDate = messageDate.toLocaleTimeString([], {
					hour: "2-digit",
					minute: "2-digit",
				});

				// biome-ignore format: preserve template formatting
				const renderedHtml = render(`
                    ${
                        hasDateChanged
                            ? ` <time datetime="${absoluteDate}" class="message-date">${absoluteDate} </time>`
                            : ""
                    }

                    <div
                        class="message"
                        data-side="${message.side}"
                        data-message-id="${message.id}"
                    >
                        <div class="message__content markdown">
                            ${message.content}
                        </div>
                    </div>
                `);

				this.messagesElement.append(renderedHtml);
				this.messages.push(message);
			});

			this.observeMessages();
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

		addFiles(event) {
			// TODO: Implement file upload functionality
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

		async createTicket() {
			const subject = this.messageContent
				.trim()
				.replace(/(<([^>]+)>)/gi, "") // Strip HTML tags
				.substring(0, 40);

            const url = window.location.origin + window.location.pathname;

			const data = await fetchJson(
				`/padmission-tickets/api/tickets/`,
				{
                        subject,
                        url,
                    },
				"POST",
			);

			this.dispatch("ticket-created", data);
			this.startPolling();

			return data.id;
		}

		async sendMessage() {
			const lockTurn = this.lockTurnCheckbox?.checked || false;

			if (!this.messageContent.trim()) {
				return;
			}

			if (!this.ticketId) {
				this.ticketId = await this.createTicket();
				console.log("Created new ticket with ID:", this.ticketId);
			}

			try {
				const data = await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						content: this.messageContent,
						lock_turn: lockTurn,
					},
					"POST",
				);

				// Clear the editor
				this.messageContent = "";
				this.editor.commands.clearContent();

				const messages = data.messages;
				const lastMessage = messages[messages.length - 1];

				this.lastMessageId = lastMessage.id;
				this.lastTimestamp = lastMessage.created_at;

				this.renderMessages(messages);
				this.scrollToBottom();
			} catch (error) {
				console.error("Error sending message:", error);
			}
		}

		render() {
			// biome-ignore format: preserve template formatting
			return render(`
                <style>
                    :host {
                        display: none;
                    }
                </style>

                <div class="chat">
                    <div class="message-list" data-chat-messages>

                    </div>

                    <div class="scroll-to-bottom-wrapper">
                        <button
                            class="scroll-to-bottom"
                            data-chat-scroll-to-bottom
                        >
                            <span class="chat__badge">New messages</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                    </div>

                    <form class="composer" data-composer>
                        <div class="composer__message">
                            <div data-chat-input></div>

                            <div class="composer__toolbar">
                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="addFiles"
                                    style="display: none;"
                                >
                                    <span class="sr-only">Add files</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip-icon lucide-paperclip"><path d="M13.234 20.252 21 12.3"/><path d="m16 6-8.414 8.586a2 2 0 0 0 0 2.828 2 2 0 0 0 2.828 0l8.414-8.586a4 4 0 0 0 0-5.656 4 4 0 0 0-5.656 0l-8.415 8.585a6 6 0 1 0 8.486 8.486"/></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleBold"
                                >
                                    <span class="sr-only">Bold</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bold-icon lucide-bold"><path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="setLink"
                                >
                                    <span class="sr-only">Link</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-icon lucide-link"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleList"
                                >
                                    <span class="sr-only">Unordered List</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list"><path d="M3 12h.01"></path><path d="M3 18h.01"></path><path d="M3 6h.01"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M8 6h13"></path></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleOrderedList"
                                >
                                    <span class="sr-only">Ordered List</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-ordered"><path d="M10 12h11"></path><path d="M10 18h11"></path><path d="M10 6h11"></path><path d="M4 10h2"></path><path d="M4 6h1v4"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
                                </button>

                                <button type="submit" data-chat-submit>
                                    <span>Send</span>

                                    <kbd>
                                        <span class="sr-only">Command-Key</span>
                                        <span aria-hidden="true">⌘</span>
                                    </kbd>
                                    <kbd>
                                        <span class="sr-only">Enter-Key</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-corner-down-left-icon lucide-corner-down-left"><path d="M20 4v7a4 4 0 0 1-4 4H4"/><path d="m9 10-5 5 5 5"/></svg>
                                    </kbd>
                                </button>
                            </div>
                        </div>

                        ${
                            this.hasElevatedRights === "true"
                                ? `
                                    <div class="composer__options">
                                        <label>
                                            <input type="checkbox" data-chat-lock-turn />
                                            Lock turn to supporter
                                        </label>
                                    </div>
                                `
                                : ""
                        }
                    </form>
                </div>
            `);
		}
	},
);
