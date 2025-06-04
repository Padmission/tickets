(()=>{var w=Object.defineProperty;var p=(t,s,e)=>s in t?w(t,s,{enumerable:!0,configurable:!0,writable:!0,value:e}):t[s]=e;var u=(t,s,e)=>(p(t,typeof s!="symbol"?s+"":s,e),e);var d=class extends HTMLElement{constructor(){super();u(this,"_useShadowDom",null);this.useShadowDom&&this.attachShadow({mode:"open"}),this._listeners=[]}get useShadowDom(){return this._useShadowDom!==null?this._useShadowDom:this.hasAttribute("use-shadow-dom")?(this._useShadowDom=this.getAttribute("use-shadow-dom")!=="false",this.removeAttribute("use-shadow-dom"),this._useShadowDom):!0}async connectedCallback(){this._initializeAttributes(),this._initializeStylesheet(),await this._render()}disconnectedCallback(){for(let{elem:e,event:i,callback:o}of this._listeners)e?.removeEventListener(i,o)}dispatch(e,i={}){let o=new CustomEvent(e,{bubbles:!0,composed:!0,detail:i});window.dispatchEvent(o)}changeView(e,i={}){this.dispatch("change-view",{viewName:e,attributes:i})}rootNode(){return this.useShadowDom?this.shadowRoot:this}_initializeStylesheet(){if(!this.stylesheet)return;let e=document.createElement("link");e.rel="stylesheet",e.href=this.stylesheet,this.rootNode().appendChild(e)}_initializeAttributes(){this.getAttributeNames().forEach(i=>{let o=i.replace(/-([a-z])/g,n=>n[1].toUpperCase()),a=this.getAttribute(i);a!==null&&(this[o]=a)})}_configureEventListeners(e){let i,o=[],a=document.createNodeIterator(e,NodeFilter.SHOW_ELEMENT,{acceptNode:n=>{if(!(n instanceof HTMLElement))return NodeFilter.FILTER_REJECT;if(n.tagName.includes("-")&&n.tagName!==this.tagName)return o.push(n),NodeFilter.FILTER_REJECT;for(let l of o)if(l.contains(n))return NodeFilter.FILTER_REJECT;return NodeFilter.FILTER_ACCEPT}});for(;i=a.nextNode();){if(!i)return;for(let n of i.attributes)n.name.startsWith("@")&&this._processEventHandler(n)}for(let{elem:n,event:l,callback:m}of this._listeners)n?.addEventListener(l,m)}_processEventHandler(e){let i=e.ownerElement,{name:o,value:a}=e;this._listeners.push({elem:i,event:o.slice(1),callback:n=>this[a](n)}),i.removeAttributeNode(e)}async _render(){if(!this.render)throw new Error("Web components extending BaseElement must implement a `render` method.");this.beforeRender&&this.beforeRender();let e=await this.render();this.afterRender&&this.afterRender(e),this._configureEventListeners(e),this.bindListeners&&this.bindListeners(e),this.useShadowDom?this.shadowRoot.appendChild(e):this.appendChild(e),this.renderedCallback&&this.renderedCallback()}},c=d;function r(t){let s=document.createElement("template");return s.innerHTML=t,s.content.cloneNode(!0)}customElements.define("chat-view-ticket",class extends c{get useShadowDom(){return!1}async connectedCallback(){let t=this.getRootNode().host;this.defaultMessage=t.defaultMessage,console.log("chat-view-ticket connectedCallback",this.defaultMessage),super.connectedCallback()}renderedCallback(){window.addEventListener("ticket-created",t=>{this.querySelector("[data-chat-subject]").innerHTML=t.detail.subject})}back(){this.changeView("chat-list-tickets")}render(){return r(`
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
                            ${this.subject||"New Chat"}
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
                        ticket-id="${this.ticketId||""}"
                        default-message="${this.defaultMessage||""}"
                        scroll-threshold="100"
                        polling-interval="10000"
                    />

                    <style>
                        chat-component {
                            --color-primary: inherit;
                        }
                    </style>
                </div>
            `)}});async function h(t,s={},e="GET"){e==="GET"&&(t+="?"+new URLSearchParams(s).toString());let i=await fetch(t,{method:e,headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},credentials:"same-origin",body:e==="POST"?JSON.stringify(s):null});if(!i.ok){let o=await i.text();throw console.error("HTTP error response",{status:i.status,text:o}),new Error("HTTP Error")}return await i.json()}customElements.define("chat-list-tickets",class extends c{get useShadowDom(){return!1}async renderedCallback(){let t=await this.fetchTickets(),s=r(`
                <ul class="ticket-list">
                    ${t.map(e=>`
                        <li>
                            <button
                                data-open-ticket="${e.id}"
                                class="ticket"
                            >
                                <span class="ticket__id">#${e.id}</span>

                                <span class="ticket__header">
                                    <h4 class="ticket__title">${e.subject}</h4>
                                    <date class="ticket__date">${e.updated_at}</date>
                                </span>
                                <span class="ticket__description">
                                    ${e.latest_message?e.latest_message:"No messages yet"}
                                </span>
                            </button>
                        </li>
                    `).join("")}
                </ul>
            `);s.querySelectorAll("[data-open-ticket]").forEach(e=>e.addEventListener("click",i=>{let o=i.currentTarget.dataset.openTicket;o&&this.openTicket(o)})),this.querySelector("[data-ticket-list]").replaceChildren(s)}createTicket(){this.changeView("chat-view-ticket")}openTicket(t){let s=this.tickets.find(e=>e.id===Number.parseInt(t));this.changeView("chat-view-ticket",{ticketId:s.id,isClosed:s.is_closed,subject:s.subject})}async fetchTickets(){try{let t=await h("/padmission-tickets/api/tickets");return this.tickets=t.tickets||[],this.tickets}catch(t){return console.error("Failed to fetch tickets:",t),[]}}async render(){return r(`
                <div class="chat-list-tickets">
                    <header>
                        <h2>
                            How can we help you?
                        </h2>

                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                data-close-dialog
                                formmethod="dialog"
                            >
                                <span class="sr-only">Close modal</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>

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
            `)}});customElements.define("chat-widget",class extends c{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}renderedCallback(){this.shadowRoot.querySelector("button").addEventListener("click",t=>{this.changeView("chat-list-tickets"),this.shadowRoot.querySelector("dialog").show()}),window.addEventListener("close-chat-widget",t=>{let s=this.shadowRoot.querySelector("dialog");s&&s.close()}),window.addEventListener("change-view",t=>{let s=t.detail.viewName,e=t.detail.attributes||{};this.changeView(s,e)})}changeView(t,s={}){let e=document.createElement(t);for(let[i,o]of Object.entries(s)){let a=i.replace(/([a-z])([A-Z])/g,"$1-$2").toLowerCase();e.setAttribute(a,o)}this.shadowRoot.querySelector("[data-dialog-content]").replaceChildren(e)}render(){return r(`
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

                    </div>
                </dialog>
            `)}});})();
