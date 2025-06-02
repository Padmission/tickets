import BaseElement from "../helpers/base-element";
import render from "../helpers/render";

customElements.define('chat-create-ticket', class extends BaseElement {
    get stylesheet ()  {
        return '/css/padmission-tickets/chat-widget.css';
    }

    closeDialog() {
        this.dispatch('close-chat-widget');
    }

    back() {
        this.changeView('chat-list-tickets');
    }

    async submit(event) {
        event.preventDefault();

        const formData = new FormData(event.currentTarget);
        const data = Object.fromEntries(formData.entries());
        const json = JSON.stringify(data);

        let resp = await fetch('/padmission-tickets/api/tickets', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            },
            body: json,
        });


        if (resp.ok) {
            const data = await resp.json();

            this.changeView('chat-view-ticket', {
                ticketId: data.id,
                subject: data.subject
            });
        } else {
            // Handle error
            console.error('Failed to create ticket:', resp.statusText);
        }
    }

    render() {
        return render(`
            <div class="chat-create-ticket">
                <header part="header">
                    <button
                        class="button-icon"
                        data-back
                        @click="back"
                    >
                        <span class="sr-only">
                            Back to ticket list
                        </span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                    </button>

                    <h2>Create a ticket</h2>

                    <button
                        class="button-icon"
                        data-close-dialog
                        @click="closeDialog"
                    >
                        <span class="sr-only">Close modal</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                     </button>
                </header>


                <form
                    id="create-ticket-form"
                    class="form"
                    @submit="submit"
                >
                    <p>
                        Please provide a subject for your ticket. This will help us to categorize and respond to your request more efficiently.
                    </p>

                    <label for="ticket-subject">Subject</label>
                    <input type="text" id="ticket-subject" name="subject" required>

                    <button type="submit" class="button">Start Conversation</button>
                </form>
            </div>
        `);
    }
})
