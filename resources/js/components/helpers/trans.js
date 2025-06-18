import config from "./config.js"
/**
 * Translate a string using Laravel's translation format
 */
const __ = (key, replacements = {}) => {
    const lang = config.lang;
    const value = lang[key] !== undefined ? lang[key] : key;

    return value.replace(/:(\w+)/g, (match, key) => {
        return replacements[key] !== undefined ? replacements[key] : match;
    });
};

export default __;
