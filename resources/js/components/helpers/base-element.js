class BaseElement extends HTMLElement {
	_useShadowDom = null;
	get useShadowDom() {
		if (this._useShadowDom !== null) {
			return this._useShadowDom;
		}

		if (this.hasAttribute("use-shadow-dom")) {
			this._useShadowDom = this.getAttribute("use-shadow-dom") !== "false";
			this.removeAttribute("use-shadow-dom");
			return this._useShadowDom;
		}

		return true;
	}
	constructor() {
		super();

		if (this.useShadowDom) {
			this.attachShadow({ mode: "open" });
		}

		this._listeners = [];
	}

	async connectedCallback() {
		this._initializeAttributes();
		this._initializeStylesheet();
		await this._render();
	}

	disconnectedCallback() {
		for (const { elem, event, callback } of this._listeners) {
			elem?.removeEventListener(event, callback);
		}
	}

	dispatch(eventName, detail = {}) {
		const event = new CustomEvent(eventName, {
			bubbles: true,
			composed: true,
			detail: detail,
		});

		window.dispatchEvent(event);
	}

	changeView(viewName, attributes = {}) {
		this.dispatch("change-view", {
			viewName: viewName,
			attributes: attributes,
		});
	}

	rootNode() {
		if (this.useShadowDom) {
			return this.shadowRoot;
		}

		return this;
	}

	_initializeStylesheet() {
		if (!this.stylesheet) {
			return;
		}

		const link = document.createElement("link");
		link.rel = "stylesheet";
		link.href = this.stylesheet;

		this.rootNode().appendChild(link);
	}

	_initializeAttributes() {
		const attributes = this.getAttributeNames();

		attributes.forEach((attr) => {
			const camelCaseAttr = attr.replace(/-([a-z])/g, (g) =>
				g[1].toUpperCase(),
			);
			const value = this.getAttribute(attr);

			if (value !== null) {
				this[camelCaseAttr] = value;
			}
		});
	}

	_configureEventListeners(rootNode) {
		let node;
		const nestedCustomElements = [];

		// Create a node iterator to iterate through all elements in the shadow root
		const iterator = document.createNodeIterator(
			rootNode,
			NodeFilter.SHOW_ELEMENT,
			{
				acceptNode: (node) => {
					// Reject any node that is not an HTML element
					if (!(node instanceof HTMLElement)) {
						return NodeFilter.FILTER_REJECT;
					}

					// Check if node is a nested custom element
					if (node.tagName.includes("-") && node.tagName !== this.tagName) {
						nestedCustomElements.push(node);
						return NodeFilter.FILTER_REJECT;
					}

					// Check if node is a child of a nested custom element
					for (const nested of nestedCustomElements) {
						if (nested.contains(node)) {
							return NodeFilter.FILTER_REJECT;
						}
					}
					return NodeFilter.FILTER_ACCEPT;
				},
			},
		);

		while ((node = iterator.nextNode())) {
			if (!node) return;

            let attributes = Array.from(node.attributes)

			for (const attr of attributes) {
				if (attr.name.startsWith("@")) {
					this._processEventHandler(attr);
				}
			}
		}

		for (const { elem, event, callback } of this._listeners) {
			elem?.addEventListener(event, callback);
		}
	}

	_processEventHandler(attr) {
		const elem = attr.ownerElement;
		// Extract the name and value of the attribute
		// Example: `@click="handleClick"` -> `click` event and `handleClick` method
		const { name: event, value: method } = attr;

		this._listeners.push({
			elem: elem,
			event: event.slice(1),
			callback: (e) => this[method](e),
		});

        elem.removeAttributeNode(attr);
	}

	async _render() {
		if (!this.render) {
			throw new Error(
				"Web components extending BaseElement must implement a `render` method.",
			);
		}

		if (this.beforeRender) {
			this.beforeRender();
		}

		const node = await this.render();

		if (this.afterRender) {
			this.afterRender(node);
		}

		this._configureEventListeners(node);

		if (this.bindListeners) {
			this.bindListeners(node);
		}

		if (this.useShadowDom) {
			this.shadowRoot.appendChild(node);
		} else {
			this.appendChild(node);
		}

		if (this.renderedCallback) {
			this.renderedCallback();
		}
	}
}

export default BaseElement;
