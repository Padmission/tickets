(()=>{var l=class extends HTMLElement{constructor(){super(),this.attachShadow({mode:"open"}),this._listeners=[]}async connectedCallback(){this._initializeAttributes(),this._initializeStylesheet(),await this._render()}disconnectedCallback(){for(let{elem:e,event:t,callback:o}of this._listeners)e?.removeEventListener(t,o)}dispatch(e,t={}){let o=new CustomEvent(e,{bubbles:!0,composed:!0,detail:t});window.dispatchEvent(o)}changeView(e,t={}){this.dispatch("change-view",{viewName:e,attributes:t})}_initializeStylesheet(){if(!this.stylesheet)return;let e=document.createElement("link");e.rel="stylesheet",e.href=this.stylesheet,console.warn("BaseElement: Using stylesheet:",this.stylesheet),this.shadowRoot.appendChild(e)}_initializeAttributes(){this.getAttributeNames().forEach(t=>{let o=t.replace(/-([a-z])/g,i=>i[1].toUpperCase()),n=this.getAttribute(t);n!==null&&(this[o]=n)})}_configureEventListeners(e){let t,o=[],n=document.createNodeIterator(e,NodeFilter.SHOW_ELEMENT,{acceptNode:i=>{if(!(i instanceof HTMLElement))return NodeFilter.FILTER_REJECT;if(i.tagName.includes("-")&&i.tagName!==this.tagName)return o.push(i),NodeFilter.FILTER_REJECT;for(let r of o)if(r.contains(i))return NodeFilter.FILTER_REJECT;return NodeFilter.FILTER_ACCEPT}});for(;t=n.nextNode();){if(!t)return;for(let i of t.attributes)i.name.startsWith("@")&&this._processEventHandler(i)}for(let{elem:i,event:r,callback:d}of this._listeners)i?.addEventListener(r,d)}_processEventHandler(e){let t=e.ownerElement,{name:o,value:n}=e;this._listeners.push({elem:t,event:o.slice(1),callback:i=>this[n](i)}),t.removeAttributeNode(e)}async _render(){if(!this.render)throw new Error("Web components extending BaseElement must implement a `render` method.");this.beforeRender&&this.beforeRender();let e=await this.render();this.afterRender&&this.afterRender(e),this._configureEventListeners(e),this.bindListeners&&this.bindListeners(e),this.shadowRoot.appendChild(e),this.renderedCallback&&this.renderedCallback()}},a=l;function c(s){let e=document.createElement("template");return e.innerHTML=s,e.content.cloneNode(!0)}customElements.define("chat-create-ticket",class extends a{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}closeDialog(){this.dispatch("close-chat-widget")}back(){this.changeView("chat-list-tickets")}async submit(s){s.preventDefault();let e=new FormData(s.currentTarget),t=Object.fromEntries(e.entries()),o=JSON.stringify(t),n=await fetch("/padmission-tickets/api/tickets",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:o});if(n.ok){let i=await n.json();this.changeView("chat-view-ticket",{ticketId:i.id,subject:i.subject})}else console.error("Failed to create ticket:",n.statusText)}render(){return c(`
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
        `)}});customElements.define("chat-view-ticket",class extends a{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}closeDialog(){this.dispatch("close-chat-widget")}back(){this.changeView("chat-list-tickets")}render(){return c(`
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

                    <h2>${this.subject}</h2>

                    <button
                        class="button-icon"
                        data-close-dialog
                        @click="closeDialog"
                    >
                        <span class="sr-only">Close modal</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                     </button>
                </header>

                <chat-component
                    ticket-id="${this.ticketId}"
                    scroll-threshold="100"
                    polling-interval="10000"
                />
            </div>
        `)}});customElements.define("chat-list-tickets",class extends a{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}closeDialog(){this.dispatch("close-chat-widget")}async renderedCallback(){let s=await this.fetchTickets(),e=c(`
            <ul class="ticket-list">
                ${s.map(t=>`
                    <li>
                        <button
                            data-open-ticket="${t.id}"
                            class="ticket"
                        >
                            <span class="ticket__id">#${t.id}</span>

                            <span class="ticket__header">
                                <h4 class="ticket__title">${t.subject}</h4>
                                <date class="ticket__date">${t.updated_at}</date>
                            </span>
                            <span class="ticket__description">
                                ${t.latest_activity?t.latest_activity:"No messages yet"}
                            </span>
                        </button>
                    </li>
                `).join("")}
            </ul>
        `);e.querySelectorAll("[data-open-ticket]").forEach(t=>t.addEventListener("click",o=>{let n=o.currentTarget.dataset.openTicket;n&&this.openTicket(n)})),this.shadowRoot.querySelector("[data-ticket-list]").replaceChildren(e)}createTicket(){this.changeView("chat-create-ticket")}openTicket(s){let e=this.tickets.find(t=>t.id===Number.parseInt(s));this.changeView("chat-view-ticket",{ticketId:e.id,isClosed:e.is_closed,subject:e.subject})}async fetchTickets(){try{let s=await fetch("/padmission-tickets/api/tickets");if(!s.ok)throw new Error("Network response was not ok");let e=await s.json();return this.tickets=e.tickets||[],this.tickets}catch(s){return console.error("Failed to fetch tickets:",s),[]}}async render(){return c(`
            <div class="chat-list-tickets">
                <header>
                    <h2>
                        How can we help you?
                    </h2>

                    <button
                        class="button-icon"
                        data-close-dialog
                        @click="closeDialog"
                    >
                        <span class="sr-only">Close modal</span>
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                     </button>
                </header>

                <button
                    class="button"
                    @click="createTicket"
                >
                    <span>Open New Ticket</span>

                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                </button>

                <h3>Your tickets</h3>

                <div data-ticket-list>
                </div>
            </div>
        `)}});customElements.define("chat-widget",class extends a{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}renderedCallback(){this.shadowRoot.querySelector("button").addEventListener("click",s=>{this.shadowRoot.querySelector("dialog").showModal()}),window.addEventListener("close-chat-widget",s=>{let e=this.shadowRoot.querySelector("dialog");e&&e.close()}),window.addEventListener("change-view",s=>{let e=s.detail.viewName,t=s.detail.attributes||{};this.changeView(e,t)})}changeView(s,e){let t=document.createElement(s);for(let[o,n]of Object.entries(e)){let i=o.replace(/([a-z])([A-Z])/g,"$1-$2").toLowerCase();t.setAttribute(i,n)}this.shadowRoot.querySelector("[data-dialog-content]").replaceChildren(t)}render(){return c(`
            <style>
                :host {
                    display: none;
                }
            </style>

            <button
                class="button-icon"
                data-open-dialog
            >
                <span class="sr-only">Open support chat</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle-question-icon lucide-message-circle-question"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><path d="M12 17h.01"/></svg>
            </button>

            <dialog
                id="chat-widget-dialog"
                closedBy="closerequest"
            >
                <div data-dialog-content>
                    <chat-list-tickets></chat-list-tickets>
                </div>
            </dialog>
        `)}});})();
