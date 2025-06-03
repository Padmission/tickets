import BaseElement from "../helpers/base-element";
import render from "../helpers/render";

customElements.define(
	"chat-view-ticket",
	class extends BaseElement {
		get useShadowDom() {
			return false;
		}

		renderedCallback() {
			window.addEventListener("ticket-created", (event) => {
				this.querySelector("[data-chat-subject]").innerHTML =
					event.detail.subject;
			});
		}

		back() {
			this.changeView("chat-list-tickets");
		}

		render() {
            // biome-ignore format: preserve template formatting
			return render(`
                <div class="chat-view-ticket">
                    <header>
                        <button
                            data-back
                            class="button-icon"
                            @click="back"
                        >
                            <span class="sr-only">
                                Back to ticket list
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                        </button>

                        <h2 data-chat-subject>
                            ${this.subject || "New Chat"}
                        </h2>

                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                formmethod="dialog"
                            >
                                <span class="sr-only">Close modal</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                         </form>
                    </header>

                    <chat-component
                        ticket-id="${this.ticketId || ""}"
                        scroll-threshold="100"
                        polling-interval="10000"
                    />

                    <style>
                        chat-component {
                            --color-primary: inherit;
                        }
                    </style>
                </div>
            `);
		}
	},
);
