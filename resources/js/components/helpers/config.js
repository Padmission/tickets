class ChatWidgetConfig {
	/** @type {ChatWidgetConfig|null} */
	static instance = null;

	panelId = null;
	userId = null;
	placeholder = null;
	introMessage = "";
	allowScreenshots = false;
	allowFileUploads = false;
	maxUploadFileSize = 10 * 1024 * 1024;
	lang = {};

	constructor() {
		if (ChatWidgetConfig.instance) {
			throw new Error("Use ChatWidgetConfig.getInstance()");
		}

		ChatWidgetConfig.instance = this;
	}

	static getInstance() {
		if (!ChatWidgetConfig.instance) {
			ChatWidgetConfig.instance = new ChatWidgetConfig();
		}

		return ChatWidgetConfig.instance;
	}

	/**
	 * @param {Partial<ChatWidgetConfig>} config
	 */
	setConfig(config) {
		Object.assign(this, config);
	}
}

const config = ChatWidgetConfig.getInstance();
export default config;
