const MarkupComponents = (function() {
	const ajaxListeners = [];
	const headers = new Headers({
		"Content-Type": "application/json",
		"X-Requested-With": "XMLHttpRequest"
	});

	window.addEventListener("popstate", (e) => {
		if(e.state.history) {
			location.reload();
		}
	});

	/**
	 * Load a page using `fetch` and then replace the target’s content. It will
	 * also automatically handle inline scripts and components’ associated
	 * scripts/styles to avoid duplicate imports
	 * 
	 * @param {String} href - The page to load
	 * @param {HTMLElement | String} target - The element target in which to put
	 * the page’s content. Can be a CSS selector. Defaults to `<body>`
	 * @param {Object} options
	 * @param {Number} options.delay - Minimum amount of milliseconds to wait
	 * before replacing the target’s content
	 * @param {Boolean} options.history - Update history with new url
	 * @param {String} options.historyIgnoreSegment - Trim segment from url
	 * before adding new history state
	 */
	function load(href, target = document.body, options = {}) {
		if(!href || !target) return;
		if(typeof target === "string") {
			target = document.querySelector(target);
			if(!target) return;
		}
		options = Object.assign({
			body: {},
			delay: 0,
			history: false,
			historyIgnoreSegment: ""
		}, options);
		const time = Date.now();
		return new Promise((resolve, reject) => {
			fetch(href, {
				method: "POST",
				body: JSON.stringify(options.body),
				headers
			})
				.then((res) => res.json())
				.then((json) => {
					if(options.history) {
						if(
							options.historyIgnoreSegment &&
							typeof options.historyIgnoreSegment === "string"
						) {
							const index = href.lastIndexOf(options.historyIgnoreSegment);
							href = href.slice(0, index);
						}
						history.pushState({ history: true }, "", href);
					}
					options.delay -= Date.now() - time;
					insertHtml(json, target, options.delay)
						.then(resolve)
						.catch((error) => {
							console.log(error);
							reject();
						})
				})
				.catch((error) => {
					console.error(error);
					reject();
				});
		});
	}

	function generateSelector(element) {
		if(!(element instanceof HTMLElement)) return "";
		if(element.tagName.toLowerCase() == "body") {
			return "body";
		}
		let selector = element.tagName.toLowerCase();
		selector += (element.id != "") ? `#${element.id}` : "";
		if(element.className) {
			const classes = element.className.split(/\s/);
			for(let i = 0; i < classes.length; i++) {
				if(element.parentElement.querySelectorAll(selector).length === 1) break;
				selector += `.${classes[i]}`;
			}
		}
		if(element.parentElement.querySelectorAll(selector).length > 1) {
			selector += `:nth-child(${Array.from(element.parentElement.children).indexOf(element)})`;
		}
		return generateSelector(element.parentElement) + " > " + selector;
	}

	function insertHtml(json, target, delay = 0) {
		return new Promise((resolve, reject) => {
			if(!json || !target) reject();
			if(typeof target === "string") {
				target = document.querySelector(target);
				if(!target) reject();
			}
			const { html, scripts } = extractScripts(json.html);
			for(const type of ["styles", "scripts"]) {
				if(!json[type]) continue;
				for(const file of json[type]) {
					const isJs = type === "scripts";
					const href = isJs ? "src" : "href";
					// skip already imported files
					if(document.querySelector(`[${href}="${file.src}"]`)) continue;
					const tag = document.createElement(isJs ? "script" : "link");
					tag[href] = file.src;
					if(!isJs) {
						tag.rel = "stylesheet";
						tag.type = "text/css";
					} else {
						// load synchronously, in case of js dependencies
						tag.async = false;
					}
					for(const name in file.attr) {
						const value = file.attr[name];
						if(isNaN(parseInt(name))) {
							tag.setAttribute(name, value);
						} else {
							tag.setAttribute(value, "");
						}
					}
					document.head.appendChild(tag);
				}
			}
			setTimeout(() => {
				target.innerHTML = "";
				target.insertAdjacentHTML("beforeend", html);
				scripts.forEach((script) => {
					target.appendChild(script);
				});
				requestAnimationFrame(() => {
					trigger("ajax");
					resolve();
				});
			}, Math.max(0, delay));
		});
	}

	function extractScripts(html) {
		const regex = /<script(?<attributes>.*)>(?<content>(?:.|\n)*?)<\/script>/gm;
		const matches = html.matchAll(regex);
		const scripts = [];
		for(const match of matches) {
			if(!match.groups.content) continue;
			const script = document.createElement("script");
			script.insertAdjacentHTML("beforeend", match.groups.content);
			if(match.groups.attributes) {
				const regex = / (?<name>[^=]*)(?:=(?:"|')(?<value>.*?)(?:"|'))?/gm;
				const attributes = match.groups.attributes.matchAll(regex);
				for(const attribute of attributes) {
					if(!attribute.groups.name) continue;
					script.setAttribute(attribute.groups.name, attribute.groups.value);
				};
			}
			scripts.push(script);
			html = html.replace(match[0], "");
		};
		return { html, scripts };
	}

	/**
	 * Add a listener to the specified event. Only "load" and "ajax" are supported
	 * 
	 * @param {string} event - The event to listen for
	 * @param {string} listener - The callback to call when the event is triggered
	 * @param {boolean} triggerAfterAjax - Call the listener after each ajax request
	 */
	function on(event, listener, triggerAfterAjax = false) {
		if(event === "load") {
			if(document.readyState === "complete") {
				listener();
			} else {
				window.addEventListener("load", listener);
			}
			if(triggerAfterAjax) {
				on("ajax", listener);
			}
		} else if(event === "ajax") {
			ajaxListeners.push(listener);
		}
	}

	function off(event, listener) {
		if(event === "load") {
			window.removeEventListener(event, listener);
		} else if(event === "ajax") {
			ajaxListeners = ajaxListeners.filter((c) => c !== listener);
		}
	}

	function trigger(event) {
		if(event === "ajax") {
			ajaxListeners.forEach(listener => listener());
		}
	}

	return {
		load,
		on,
		off
	}
})();
