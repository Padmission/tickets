import { Editor, Extension } from "@tiptap/core";
import Placeholder from "@tiptap/extension-placeholder";
import StarterKit from "@tiptap/starter-kit";

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

				let url = `/padmission-tickets/api/tickets/${this.ticketId}/messages`;

				if (!isInitialLoad) {
					url += `?offset=${encodeURIComponent(this.lastMessageId)}`;
				}

				const response = await fetch(url, {
					headers: {
						Accept: "application/json",
						"X-Requested-With": "XMLHttpRequest",
					},
					credentials: "same-origin",
				});

				if (!response.ok) {
					console.error("Error response:", await response.text());
					throw new Error(`Failed to load messages: ${response.status}`);
				}

				let data;

				try {
					data = await response.json();
				} catch (error) {
					console.error("Error parsing response:", error);
					return;
				}

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
					this.shadowRoot.querySelector("[data-composer]").style.display =
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

				const renderedHtml = render(`
                ${
									hasDateChanged
										? `<time datetime="${absoluteDate}" class="message-date">
                                ${absoluteDate}
                            </time>`
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
			this.shadowRoot.querySelectorAll(".message").forEach((message) => {
				this.messageObserver.observe(message);
			});
		}

		addFiles(event) {
			// TODO: Implement file upload functionality
		}

		toggleBold(event) {
			this.editor.chain().focus().toggleBold().run();
			event.currentTarget.classList.toggle("active");
		}
		toggleCode(event) {
			this.editor.chain().focus().toggleCode().run();
			event.currentTarget.classList.toggle("active");
		}

		async sendMessage() {
			const lockTurn = this.lockTurnCheckbox?.checked || false;

			if (!this.messageContent.trim()) {
				return;
			}

			try {
				const response = await fetch(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						method: "POST",
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
						body: JSON.stringify({
							content: this.messageContent,
							lock_turn: lockTurn,
						}),
					},
				);

				if (!response.ok) {
					const errorText = await response.text();
					console.error("Error response:", errorText);
					throw new Error(`Failed to send message: ${response.status}`);
				}

				// Clear the editor
				this.messageContent = "";
				this.editor.commands.clearContent();

				let data;

				try {
					data = await response.json();
				} catch (error) {
					await this.loadMessages();
					return;
				}

				const message = data.message;
				this.lastMessageId = message.id;
				this.lastTimestamp = message.created_at;

				this.renderMessages([message]);
				this.scrollToBottom();
			} catch (error) {
				console.error("Error sending message:", error);
			}
		}

		render() {
			return render(`
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
                                @click="toggleCode"
                            >
                                <span class="sr-only">Add Code</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-code-icon lucide-code"><path d="m16 18 6-6-6-6"/><path d="m8 6-6 6 6 6"/></svg>
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
