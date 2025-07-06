import { Editor, Extension } from "@tiptap/core";
import Placeholder from "@tiptap/extension-placeholder";
import StarterKit from "@tiptap/starter-kit";
import Link from "@tiptap/extension-link";
import fetchJson from "./helpers/fetch-json";

import BaseElement from "./helpers/base-element";
import render from "./helpers/render";
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
			this.pollingIntervalMs = 5000;

			this.ticketId = null;
			this.ticket = null;

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

			this.isNearBottom = true;
            this.dropIndex = 0
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
				if (message.content === null && message.attachments.length === 0) {
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
                            ? ` <time datetime="${absoluteDate}" class="message-date">${absoluteDate}</time>`
                            : ""
                    }

                    <div
                        class="message"
                        data-side="${message.side}"
                        data-message-id="${message.id}"
                    >
                        <div class="message__content">
                            <div class="markdown">
                                ${message.content || ''}
                            </div>

                            ${message.attachments ? `
                                <div class="message__attachments">
                                    ${message.attachments?.map((attachment) =>
                                        `
                                            <a
                                                href="${attachment.url}"
                                                class="attachment"
                                                data-preview="${attachment.type}"
                                                target="_blank"
                                            >
                                                ${
                                                    attachment.type === 'image'
                                                        ? `<img src="${attachment.preview_url}">`
                                                        : `<span>${attachment.filename}</span>`
                                                }
                                            </a>
                                        `
                                        ).join('') ?? ''}
                                </div>
                            ` : ''}
                        </div>
                        <div class="message__sender">
                            ${message.user_name}
                        </div>
                    </div>
                `);

                renderedHtml.querySelectorAll('[data-preview]').forEach(el => el.addEventListener('click', (event) => {
                    const el = event.currentTarget;
                    const type = el.dataset.preview

                    if (! ['image', 'video'].includes(type)) {
                        return;
                    }

                    event.preventDefault()

                    const previewSource = event.currentTarget.getAttribute('href');
                    const dialog = this.rootNode().querySelector('[data-preview-popup]')
                    const dialogContent = dialog.querySelector('[data-preview-popup-content]')

                    const previewEl = type === 'image'
                        ? render(`<img src="${previewSource}" alt="">`)
                        : render(`<video src="${previewSource}" controls>`)

                    dialogContent.replaceChildren(previewEl)
                    dialog.showModal()
                }))

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
            this.isSending = isSending
            const button = this.shadowRoot.querySelector("[data-chat-submit]")

            if (isSending) {
                button.classList.add('is-sending');
                button.toggleAttribute('disabled');
            } else {
               button.classList.remove('is-sending');
                button.removeAttribute('disabled');
            }
        }

        addAttachments(event) {
			this.attachments = this.attachments.concat(Array.from(event.target.files));

            this.renderAttachments();
		}

        removeAttachment(event) {
            let index = event.currentTarget.dataset.index

            this.attachments.splice(index, 1)
            this.renderAttachments()
        }

        async generateThumbnail(file) {
            console.log({file, indexOf: file.type.indexOf('image/')})
            if (file.type.indexOf('image/') < 0) {
                return null;
            }

            return new Promise((resolve, reject) => {
                const img = new Image();
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                canvas.width = 100;
                canvas.height = 100;

                img.onload = () => {
                    const {width, height} = img;
                    const canvasAspect = 1;
                    const imageAspect = width / height;

                    let drawWidth, drawHeight, offsetX = 0, offsetY = 0;

                    if (imageAspect > canvasAspect) {
                        drawHeight = 100;
                        drawWidth = (width / height) * 100;
                        offsetX = (100 - drawWidth) / 2;
                    } else {
                        drawWidth = 100;
                        drawHeight = (height / width) * 100;
                        offsetY = (100 - drawHeight) / 2;
                    }

                    ctx.drawImage(img, offsetX, offsetY, drawWidth, drawHeight);

                    const thumbnailDataUrl = canvas.toDataURL('image/png', 0.8);
                    resolve(thumbnailDataUrl);
                };

                img.onerror = () => {
                    reject(new Error('Failed to load image for thumbnail generation'));
                };

                img.src = URL.createObjectURL(file);
            });
        }

        renderAttachments() {
            // biome-ignore format: preserve template formatting
            const node = render(`
                <div class="attachments">
                    ${this.attachments.map((attachment, index) => `
                        <div class="attachment">
                            <button
                                class="button-icon"
                                @click="removeAttachment"
                                data-index="${index}"
                            >
                                <span class="sr-only">Remove</span>
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
                            </button>

                            ${
                                attachment.type.startsWith('image/')
                                    ? `<img src="${URL.createObjectURL(attachment)}">`
                                    : `<span>${attachment.name}</span>`
                            }
                        </div>
                    `,).join("")}
                </div>
            `);

            this._configureEventListeners(node);

            this.shadowRoot.querySelector('[data-attachments]').replaceChildren(node);
        }

        clearAttachments() {
            this.attachments = []
            this.renderAttachments()
        }

        async uploadAttachments() {
            if (! this.attachments) {
                return [];
            }

            let uploadedAttachments = [];
            let pendingUploads = []

            for (let attachment of this.attachments) {
                pendingUploads.push(new Promise(async (resolve, reject) => {
                    try {
                        const thumbnailData = await this.generateThumbnail(attachment)
                        const {
                            attachment_id,
                            upload_url
                        } = await this.getSignedUploadUrl(attachment, thumbnailData);

                        await this.uploadAttachment(attachment, upload_url);
                        uploadedAttachments.push(attachment_id)
                        resolve();
                    } catch (error) {
                        reject(error)
                    }
                }))
            }

            await Promise.all(pendingUploads);

            return uploadedAttachments;
        }


        async getSignedUploadUrl(file, thumbnailData = null) {
            const payload = {
                filename: file.name,
                content_type: file.type,
            };

            if (thumbnailData) {
                payload.thumbnail = thumbnailData;
            }

            return fetchJson(
                `/padmission-tickets/api/tickets/${this.ticketId}/upload-url`,
                payload,
                'POST',
            );
        }

        async uploadAttachment(file, uploadUrl) {
            const response = await fetch(uploadUrl, {
                method: 'PUT',
                headers: {
                    'Content-Type': file.type,
                },
                body: file,
            });

            if (!response.ok) {
                throw new Error(`File upload failed: ${response.statusText}`);
            }
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

			if (!this.messageContent.trim() && this.attachments.length === 0) {
				return;
			}

            if (this.isSending) {
                return;
            }

            this.clearError()
            this.setIsSending(true)

			try {
                if (!this.ticketId) {
                    this.ticketId = await this.createTicket();
                }

                const attachment_ids = await this.uploadAttachments();

				const data = await fetchJson(
					`/padmission-tickets/api/tickets/${this.ticketId}/messages`,
					{
						content: this.messageContent || '',
						lock_turn: lockTurn,
                        attachment_ids: attachment_ids
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

                this.clearAttachments()
				this.renderMessages(messages);
				this.scrollToBottom();
			} catch (error) {
                console.log('Sending failed', error)
                this.setError(__('chat.error'))
			}

            this.setIsSending(false)
		}

        setError(message) {
            const el = this.rootNode().querySelector('[data-chat-error]');

            el.innerHTML = message
            el.removeAttribute('hidden')
        }

        clearError() {
            this.rootNode().querySelector('[data-chat-error]').setAttribute('hidden', '');
        }

        enableDroparea(event) {
            if (! config.allowFileUploads) {
                return;
            }

            if (this.dropIndex++ === 0) {
                this.rootNode().querySelector('[data-droparea]').removeAttribute('hidden')
            }
        }

        disableDroparea() {
            if (--this.dropIndex === 0) {
                this.rootNode().querySelector('[data-droparea]').setAttribute('hidden', true)
            }
        }

        dragover(event) {
            event.preventDefault();
        }

        addDroppedFiles(event) {
            if (! config.allowFileUploads) {
                return;
            }

            event.preventDefault()


            const files = Array.from(event.dataTransfer.items)
                .filter(item => item.kind === "file")
                .map(item => item.getAsFile())

            this.attachments = this.attachments.concat(files);

            this.disableDroparea()
            this.renderAttachments();
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
                        @drop="addDroppedFiles"
                    >
                        <span>${__('chat.droparea')}</span>
                    </div>

                    <div class="message-list" data-chat-messages>

                    </div>

                    <div class="scroll-to-bottom-wrapper">
                        <button
                            class="scroll-to-bottom"
                            data-chat-scroll-to-bottom
                        >
                            <span class="chat__badge">${__('chat.new_messages')}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="6 9 12 15 18 9"></polyline>
                            </svg>
                        </button>
                    </div>

                    <form class="composer" data-composer style="position: relative;">
                       <div hidden class="composer__error" data-chat-error>Something went wrong</div>
                        <div class="composer__message">
                            <div data-chat-input></div>

                            <div data-attachments></div>


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
                                            @change="addAttachments"
                                            style="display: none;"
                                        >

                                        <span class="sr-only">${__('chat.add_files')}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-paperclip-icon lucide-paperclip"><path d="M13.234 20.252 21 12.3"/><path d="m16 6-8.414 8.586a2 2 0 0 0 0 2.828 2 2 0 0 0 2.828 0l8.414-8.586a4 4 0 0 0 0-5.656 4 4 0 0 0-5.656 0l-8.415 8.585a6 6 0 1 0 8.486 8.486"/></svg>
                                    </label>
                                `: ''}
                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleBold"
                                >
                                    <span class="sr-only">${__('chat.bold')}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-bold-icon lucide-bold"><path d="M6 12h9a4 4 0 0 1 0 8H7a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h7a4 4 0 0 1 0 8"/></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="setLink"
                                >
                                    <span class="sr-only">${__('chat.link')}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-link-icon lucide-link"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleList"
                                >
                                    <span class="sr-only">${__('chat.unordered_list')}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list"><path d="M3 12h.01"></path><path d="M3 18h.01"></path><path d="M3 6h.01"></path><path d="M8 12h13"></path><path d="M8 18h13"></path><path d="M8 6h13"></path></svg>
                                </button>

                                <button
                                    class="button-icon"
                                    type="button"
                                    @click="toggleOrderedList"
                                >
                                    <span class="sr-only">${__('chat.ordered_list')}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-list-ordered"><path d="M10 12h11"></path><path d="M10 18h11"></path><path d="M10 6h11"></path><path d="M4 10h2"></path><path d="M4 6h1v4"></path><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"></path></svg>
                                </button>

                                <button type="submit" data-chat-submit>
                                    <svg class="loading-indicator" fill="none" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                        <path clip-rule="evenodd" d="M12 19C15.866 19 19 15.866 19 12C19 8.13401 15.866 5 12 5C8.13401 5 5 8.13401 5 12C5 15.866 8.13401 19 12 19ZM12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" fill-rule="evenodd" fill="currentColor" opacity="0.2"></path>
                                        <path d="M2 12C2 6.47715 6.47715 2 12 2V5C8.13401 5 5 8.13401 5 12H2Z" fill="currentColor"></path>
                                    </svg>

                                    <span>${__('chat.send')}</span>

                                    <kbd>
                                        <span class="sr-only">${__('chat.command_key')}</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-command"><path d="M15 6v12a3 3 0 1 0 3-3H6a3 3 0 1 0 3 3V6a3 3 0 1 0-3 3h12a3 3 0 1 0-3-3"></path></svg>
                                    </kbd>
                                    <kbd>
                                        <span class="sr-only">${__('chat.enter_key')}</span>
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
                                            ${__('chat.lock_turn')}
                                        </label>
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
                            <span class="sr-only">${__('close_modal')}</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                         </button>
                    </form>

                    <div class="preview__inner" data-preview-popup-content>

                    </div>
                </dialog>
            `);
		}
	},
);
