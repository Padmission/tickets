class ChatWidgetConfig {
	/** @type {ChatWidgetConfig|null} */
	static instance = null;

	panelId = null;
	userId = null;
	placeholder = null;
	introMessage = "";
	lang = {};

	constructor() {
		if (window.ChatWidgetConfig) {
			return window.ChatWidgetConfig;
		}

		window.ChatWidgetConfig = this;
	}

	/**
	 * @param {Partial<ChatWidgetConfig>} config
	 */
	setConfig(config) {
		Object.assign(this, config);
	}
}

const config = new ChatWidgetConfig();
export default config;
