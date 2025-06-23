(()=>{var k=Object.defineProperty;var b=(s,t,e)=>t in s?k(s,t,{enumerable:!0,configurable:!0,writable:!0,value:e}):s[t]=e;var h=(s,t,e)=>(b(s,typeof t!="symbol"?t+"":t,e),e);var f=class extends HTMLElement{constructor(){super();h(this,"_useShadowDom",null);this.useShadowDom&&this.attachShadow({mode:"open"}),this._listeners=[]}get useShadowDom(){return this._useShadowDom!==null?this._useShadowDom:this.hasAttribute("use-shadow-dom")?(this._useShadowDom=this.getAttribute("use-shadow-dom")!=="false",this.removeAttribute("use-shadow-dom"),this._useShadowDom):!0}async connectedCallback(){this._initializeAttributes(),this._initializeStylesheet(),await this._render()}disconnectedCallback(){for(let{elem:e,event:i,callback:r}of this._listeners)e?.removeEventListener(i,r)}dispatch(e,i={}){let r=new CustomEvent(e,{bubbles:!0,composed:!0,detail:i});window.dispatchEvent(r)}changeView(e,i={}){this.dispatch("change-view",{viewName:e,attributes:i})}rootNode(){return this.useShadowDom?this.shadowRoot:this}_initializeStylesheet(){if(!this.stylesheet)return;let e=document.createElement("link");e.rel="stylesheet",e.href=this.stylesheet,this.rootNode().appendChild(e)}_initializeAttributes(){this.getAttributeNames().forEach(i=>{let r=i.replace(/-([a-z])/g,n=>n[1].toUpperCase()),c=this.getAttribute(i);c!==null&&(this[r]=c)})}_configureEventListeners(e){let i,r=[],c=document.createNodeIterator(e,NodeFilter.SHOW_ELEMENT,{acceptNode:n=>{if(!(n instanceof HTMLElement))return NodeFilter.FILTER_REJECT;if(n.tagName.includes("-")&&n.tagName!==this.tagName)return r.push(n),NodeFilter.FILTER_REJECT;for(let p of r)if(p.contains(n))return NodeFilter.FILTER_REJECT;return NodeFilter.FILTER_ACCEPT}});for(;i=c.nextNode();){if(!i)return;for(let n of i.attributes)n.name.startsWith("@")&&this._processEventHandler(n)}for(let{elem:n,event:p,callback:v}of this._listeners)n?.addEventListener(p,v)}_processEventHandler(e){let i=e.ownerElement,{name:r,value:c}=e;this._listeners.push({elem:i,event:r.slice(1),callback:n=>this[c](n)}),i.removeAttributeNode(e)}async _render(){if(!this.render)throw new Error("Web components extending BaseElement must implement a `render` method.");this.beforeRender&&this.beforeRender();let e=await this.render();this.afterRender&&this.afterRender(e),this._configureEventListeners(e),this.bindListeners&&this.bindListeners(e),this.useShadowDom?this.shadowRoot.appendChild(e):this.appendChild(e),this.renderedCallback&&this.renderedCallback()}},l=f;function a(s){let t=document.createElement("template");return t.innerHTML=s,t.content.cloneNode(!0)}var u=class u{constructor(){h(this,"panelId",null);h(this,"userId",null);h(this,"placeholder",null);h(this,"introMessage","");h(this,"lang",{});if(u.instance)throw new Error("Use ChatWidgetConfig.getInstance()");u.instance=this}static getInstance(){return u.instance||(u.instance=new u),u.instance}setConfig(t){Object.assign(this,t)}};h(u,"instance",null);var w=u,y=w.getInstance(),d=y;var _=(s,t={})=>{let e=d.lang;return(e[s]!==void 0?e[s]:s).replace(/:(\w+)/g,(r,c)=>t[c]!==void 0?t[c]:r)},o=_;customElements.define("chat-view-ticket",class extends l{get useShadowDom(){return!1}renderedCallback(){window.addEventListener("ticket-created",s=>{this.querySelector("[data-chat-subject]").innerHTML=s.detail.subject})}back(){this.changeView("chat-list-tickets")}render(){return a(`
                <div class="chat-view-ticket">
                    <header>
                        <button
                            data-back
                            class="button-icon"
                            @click="back"
                        >
                            <span class="sr-only">
                                ${o("view.back")}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                        </button>

                        <h2 data-chat-subject>
                            ${this.subject||o("view.new_chat")}
                        </h2>

                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                formmethod="dialog"
                            >
                                <span class="sr-only">${o("close_modal")}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                         </form>
                    </header>

                    <chat-component
                        ticket-id="${this.ticketId||""}"
                        default-message="${d.introMessage||""}"
                        scroll-threshold="100"
                        polling-interval="10000"
                    />

                    <style>
                        chat-component {
                            --color-primary: inherit;
                        }
                    </style>
                </div>
            `)}});var g=class{constructor(t){this.response=t}async error(t){try{return(await this.response.json()).error||o("errors.unknown")}catch{return o("errors.unknown")}}};async function m(s,t={},e="GET"){e==="GET"&&(s+="?"+new URLSearchParams(t).toString());let i=await fetch(s,{method:e,headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},credentials:"same-origin",body:e==="POST"?JSON.stringify(t):null});if(!i.ok)throw new g(i);return await i.json()}customElements.define("chat-list-tickets",class extends l{get useShadowDom(){return!1}async renderedCallback(){let s=await this.fetchTickets(),t=a(`
                <ul class="ticket-list">
                    ${s.map(e=>`
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
            `);t.querySelectorAll("[data-open-ticket]").forEach(e=>e.addEventListener("click",i=>{let r=i.currentTarget.dataset.openTicket;r&&this.openTicket(r)})),this.querySelector("[data-ticket-list]").replaceChildren(t)}createTicket(){this.changeView("chat-view-ticket")}openTicket(s){let t=this.tickets.find(e=>e.id===Number.parseInt(s));this.changeView("chat-view-ticket",{ticketId:t.id,isClosed:t.is_closed,subject:t.subject})}async fetchTickets(){try{let s=await m("/padmission-tickets/api/tickets");return this.tickets=s.tickets||[],this.tickets}catch(s){return console.error("Failed to fetch tickets:",s),[]}}async render(){return a(`
                <div class="chat-list-tickets">
                    <header>
                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                formmethod="dialog"
                            >
                                <span class="sr-only">${o("close_modal")}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>
                    </header>

                    <h2>
                        ${o("list.heading")}
                    </h2>

                    <button
                        class="button"
                        @click="createTicket"
                    >
                        <span>${o("list.create_ticket")}</span>

                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-right-icon lucide-chevron-right"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                    <h3>${o("list.tickets_heading")}</h3>

                    <div data-ticket-list>
                    </div>
                </div>
            `)}});customElements.define("chat-otp-request",class extends l{get useShadowDom(){return!1}async verifyUser(s){s.preventDefault();let t=this.querySelector("#email").value;try{let e=await m("/padmission-tickets/api/otp-request",{email:t},"POST");console.log("data",e),this.changeView("chat-otp-verify")}catch(e){let i=this.querySelector(".form-field");i.classList.add("has-error"),i.querySelector(".error").innerHTML=await e.error()}}async render(){return a(`
                <div class="chat-list-tickets">
                    <header>
                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                data-close-dialog
                                formmethod="dialog"
                            >
                                <span class="sr-only">${o("close_modal")}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>
                    </header>

                    <h2>
                        ${o("otp_request.heading")}
                    </h2>

                    <div class="form-container">
                        <p class="form-description">
                            ${o("otp_request.description")}
                        </p>

                        <form
                            @submit="verifyUser"
                            class="form"
                        >
                            <div class="form-field">
                                <label for="email" class="form-label">
                                    ${o("otp_request.email_label")}
                                </label>

                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autocomplete="email"
                                    class="form-input"
                                    required
                                >
                                <span class="error"></span>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button-primary">
                                    ${o("otp_request.submit_button")}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `)}});customElements.define("chat-otp-verify",class extends l{get useShadowDom(){return!1}back(){this.changeView("chat-otp-request")}async verifyUser(s){s.preventDefault();let t=this.querySelector("#otp").value;try{let e=await m("/padmission-tickets/api/otp-verify",{otp:t},"POST");this.changeView("chat-list-tickets"),d.userId=e.user_key}catch(e){let i=this.querySelector(".form-field");i.classList.add("has-error"),i.querySelector(".error").innerHTML=await e.error()}}async render(){return a(`
                <div class="chat-list-tickets">
                    <header>
                        <button
                            data-back
                            class="button-icon"
                            @click="back"
                        >
                            <span class="sr-only">
                                ${o("view.back")}
                            </span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-left-icon lucide-chevron-left"><path d="m15 18-6-6 6-6"/></svg>
                        </button>


                        <form data-close-dialog>
                            <button
                                class="button-icon"
                                data-close-dialog
                                formmethod="dialog"
                            >
                                <span class="sr-only">${o("close_modal")}</span>
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x-icon lucide-x"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                             </button>
                        </form>
                    </header>

                    <h2>
                        ${o("otp_verify.heading")}
                    </h2>

                    <div class="form-container">
                        <p class="form-description">
                            ${o("otp_verify.description")}
                        </p>

                        <form
                            @submit="verifyUser"
                            class="form"
                        >
                            <div class="form-field">
                                <label for="otp" class="form-label">
                                    ${o("otp_verify.label")}
                                </label>

                                <input
                                    id="otp"
                                    type="text"
                                    name="otp"
                                    autocomplete="one-time-code"
                                    class="form-input otp-input"
                                    maxlength="6"
                                    pattern="[0-9]*"
                                    inputmode="numeric"
                                    required
                                >
                                <div class="error"></div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="button button-primary">
                                    ${o("otp_verify.submit_button")}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            `)}});customElements.define("chat-widget",class extends l{get stylesheet(){return"/css/padmission-tickets/chat-widget.css"}beforeRender(){d.setConfig(JSON.parse(this.config))}renderedCallback(){this.shadowRoot.querySelector("button").addEventListener("click",s=>{d.userId?this.changeView("chat-list-tickets"):this.changeView("chat-otp-request"),this.shadowRoot.querySelector("dialog").show()}),window.addEventListener("close-chat-widget",s=>{let t=this.shadowRoot.querySelector("dialog");t&&t.close()}),window.addEventListener("change-view",s=>{let t=s.detail.viewName,e=s.detail.attributes||{};this.changeView(t,e)}),d.userId&&this.openTicketByHash()}openTicketByHash(){let s=window.location.hash;if(!s||!s.startsWith("#ticket-"))return;let t=s.substring(8);t&&(this.changeView("chat-view-ticket",{ticketId:t}),this.shadowRoot.querySelector("dialog").show())}changeView(s,t={}){let e=document.createElement(s);for(let[i,r]of Object.entries(t)){let c=i.replace(/([a-z])([A-Z])/g,"$1-$2").toLowerCase();e.setAttribute(c,r)}this.shadowRoot.querySelector("[data-dialog-content]").replaceChildren(e)}render(){return a(`
                <style>
                    :host {
                        display: none;
                    }
                </style>

                <button
                    class="button-icon"
                    data-open-dialog
                >
                    <span class="sr-only">${o("open_modal")}</span>
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
