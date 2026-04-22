<?php namespace ProcessWire;

/**
 * Components/snippets system inspired by Kirby’s `snippet()` helper function
 * 
 * Copyright (c) 2025 EPRC
 * Licensed under MIT License, see LICENSE
 *
 * https://eprc.studio
 *
 * For ProcessWire 3.x
 * Copyright (c) 2021 by Ryan Cramer
 * Licensed under GNU/GPL v2
 *
 * https://www.processwire.com
 *
 */

class MarkupComponents extends WireData implements Module, ConfigurableModule {

	private WireArray $components;
	private WireArray $scriptsHead;
	private WireArray $scripts;
	private WireArray $styles;
	private WireArray $stylesNoscript;

	public function __construct() {
		parent::__construct();
		$this->set("allowUnsafeInline", 0);
		$this->set("autoAddAssets", 0);
		$this->set("autoFuel", 0);
		$this->set("fuelName", "mc");
		$this->set("functionsApi", 0);
		$this->set("importHelperJs", 0);
		$this->set("overwriteAjax", 0);
		$this->set("updateCSP", 0);
	}

	public function init() {
		$this->components = new WireArray();
		$this->scriptsHead = new WireArray();
		$this->scripts = new WireArray();
		$this->styles = new WireArray();
		$this->stylesNoscript = new WireArray();
		if($this->autoAddAssets) {
			$this->addHookAfter("PageRender::renderPage", $this, "addAssets");
		}
		if($this->autoFuel) {
			if($this->wire($this->fuelName)) {
				$this->set("fuelError", $this->fuelName);
				$this->set("fuelName", "mc");
				$this->modules->saveConfig($this, "fuelName", "mc");
			} else {
				$this->wire($this->fuelName ?: "mc", $this);
			}
		}
		if($this->functionsApi) {
			include_once __DIR__ . "/MarkupComponentsFunctions.php";
		}
		if($this->overwriteAjax) {
			if($this->config->ajax) {
				$this->addHookAfter("PageRender::renderPage", $this, "convertToJson");
			} elseif($this->importHelperJs) {
				$this->script(__DIR__ . "/MarkupComponentsHelper.js", true);
			}
		}
	}

	protected function addAssets(HookEvent $event) {
		$parentEvent = $event->arguments(0);
		if($parentEvent->object !== $event->page) return;
		$scriptsHead = $this->printScripts(true);
		$scripts = $this->printScripts();
		$styles = $this->printStyles();
		$html = $parentEvent->return;
		$html = str_replace("</head>", "{$styles}{$scriptsHead}</head>", $html);
		$html = str_replace("</body>", "{$scripts}</body>", $html);
		$parentEvent->return = $html;
	}

	protected function convertToJson(HookEvent $event) {
		$parentEvent = $event->arguments(0);
		if(
			$parentEvent->object !== $event->page
			|| !empty(preg_grep("/application\/json/", headers_list()))
			|| !empty(preg_grep("/text\/plain/", headers_list()))
		) return;
		header("Content-Type: application/json");
		// Note: <noscript> styles are not sent as they are irrelevant in a json
		// most likely fetched using... javascript
		$json = array_merge($this->getDefaultJson($parentEvent->object), [
			"html" => $parentEvent->return,
			"styles" => [...$this->styles->each(["src", "attr"])],
			"scripts" => [
				...$this->scriptsHead->each(["src", "attr"]),
				...$this->scripts->each(["src", "attr"])
			]
		]);
		$parentEvent->return = json_encode($json);
	}

	/**
	 * Allow to add additional data to the json returned in an ajax request
	 * 
	 * @var Page $page Current page being rendered
	 * @return array Associative array defaulting with the page’s title
	 * 
	 */
	public function ___getDefaultJson(Page $page) {
		return [ "title" => $page->title ];
	}

	public function getComponents() {
		return $this->components;
	}

	/**
	 * Return the components’ name as a string, using a separator and quotes
	 * 
	 * @return string
	 * 
	 */
	public function listComponents($options = []) {
		$options = array_merge([
			"separator" => ",",
			"quote" => "\"",
			"closingQuote" => "",
			"prepend" => "",
			"append" => ""
		], $options);
		if(!$options["closingQuote"]) $options["closingQuote"] = $options["quote"];
		$separator = $options["closingQuote"] . $options["separator"] . $options["quote"];
		return $this->components->implode($separator, "", [
			"prepend" => $options["prepend"] . $options["quote"],
			"append" => $options["closingQuote"] . $options["append"]
		]);
	}

	/**
	 * Add a `<script>` inside either `<head>` or `<body>` tags
	 * 
	 * You can also specify attributes, e.g. `type="module"`, with an array:
	 * `["type" => "module"]`
	 * 
	 * If an array is set as the second argument, it will be used as `$attr`
	 * and `$addToHead` will be set to `false`
	 * 
	 * @param string $filename Filename or URL pointing to the script file
	 * @param bool|array $addToHead Add to `<head>`?
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	public function script($filename, $addToHead = false, $attr = []) {
		if(!$filename) return;
		if(strpos($filename, "http") !== false) {
			$external = true;
			$fullPath = $filename;
		} else {
			if(stripos($filename, ".js") === false) {
				$filename .= ".js";
			}
			[$path, $url] = $this->getPathAndUrl($filename);
			if(!file_exists($path)) return;
			$fullPath = "$url?v=" . filemtime($path);
		}
		if(is_array($addToHead)) {
			$attr = $addToHead;
			$addToHead = false;
		}
		$inline = array_key_exists("inline", $attr);
		unset($attr["inline"]);
		$script = WireData([ "src" => $fullPath, "attr" => $attr ]);
		if(empty($external) && $inline && $this->allowUnsafeInline) {
			$script->content = file_get_contents($path);
			$this->appendHashToCSP($script->content, "script");
		}
		if($addToHead) {
			if(!$this->scriptsHead->has("src=$fullPath")) {
				$this->scriptsHead->add($script);
			}
		} elseif(!$this->scripts->has("src=$fullPath")) {
			$this->scripts->add($script);
		}
	}

	/**
	 * Shorter function call for `script`
	 * 
	 * @param string $filename Filename or URL pointing to the script file
	 * @param bool|array $addToHead Add to `<head>`?
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	public function js($filename, $addToHead = false, $attr = []) {
		$this->script($filename, $addToHead, $attr);
	}

	/**
	 * @return WireArray
	 * 
	 */
	public function getScripts($head = false) {
		return $head ? $this->scriptsHead : $this->scripts;
	}
	
	/**
	 * Print the `<script>` tags
	 * 
	 * @var bool $head Print the head scripts?
	 * @return string
	 * 
	 */
	public function printScripts($head = false) {
		$str = "";
		foreach($this->getScripts($head) as $script) {
			$str .= "<script {$this->attrToString($script->attr)}";
			if(!empty($script->content)) {
				$str .= ">$script->content";
			} else {
				$str .= " src=\"$script->src\">";
			}
			$str .= "</script>";
		}
		return $str;
	}

	/**
	 * Shorter function call for `printScripts`
	 * 
	 * @var bool $head Print the head scripts?
	 * @return string
	 * 
	 */
	public function scripts($head = false) {
		return $this->printScripts($head);
	}
	
	/**
	 * Add a `<style>` inside the `<head>` tag
	 * 
	 * You can also specify attributes, e.g. `media="print"`, with an array:
	 * `["media" => "print"]`
	 * 
	 * @param string $filename Filename or URL pointing to the style file
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	public function style($filename, $attr = []) {
		if(!$filename) return;
		if(strpos($filename, "http") !== false) {
			$external = true;
			$fullPath = $filename;
		} else {
			if(strpos($filename, ".css") === false) {
				$filename .= ".css";
			} 
			[$path, $url] = $this->getPathAndUrl($filename);
			if(!file_exists($path)) return;
			$fullPath = "$url?v=" . filemtime($path);
		}
		$inline = array_key_exists("inline", $attr);
		unset($attr["inline"]);
		$noscript = array_key_exists("noscript", $attr);
		unset($attr["noscript"]);
		$style = WireData([ "src" => $fullPath, "attr" => $attr ]);
		if(empty($external) && $inline && $this->allowUnsafeInline) {
			$style->content = file_get_contents($path);
			$this->appendHashToCSP($style->content, "style");
		}
		if($noscript) {
			if(!$this->stylesNoscript->has("src=$fullPath")) {
				$this->stylesNoscript->add($style);
			}
		} elseif(!$this->styles->has("src=$fullPath")) {
			$this->styles->add($style);
		}
	}

	/**
	 * Shorter function call for `style`
	 * 
	 * @param string $filename Filename or URL pointing to the style file
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	public function css($filename, $attr = []) {
		$this->style($filename, $attr);
	}

	/**
	 * Add the css file’s content in a `<noscript>` tag
	 * 
	 * @param string $filename Filename or URL pointing to the style file
	 * 
	 */
	public function noscript($filename, $attr = []) {
		$this->style($filename, array_merge($attr, ["noscript" => true]));
	}

	/**
	 * @return WireArray
	 * 
	 */
	public function getStyles() {
		return $this->styles;
	}

	/**
	 * Print the `<style>` tags
	 * 
	 * @return string
	 * 
	 */
	public function printStyles() {
		$str = "";
		foreach($this->styles as $style) {
			if(!empty($style->content)) {
				$str .= "<style {$this->attrToString($style->attr)}>$style->content</style>";
			} else {
				$str .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"$style->src\" {$this->attrToString($style->attr)}>";
			}
		}
		if($this->stylesNoscript->count()) {
			$str .= "<noscript>";
			foreach($this->stylesNoscript as $style) {
				if(!empty($style->content)) {
					$str .= "<style {$this->attrToString($style->attr)}>$style->content</style>";
				} else {
					$str .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"$style->src\" {$this->attrToString($style->attr)}>";
				}
			}
			$str .= "</noscript>";
		}
		return $str;
	}

	/**
	 * Shorter function call for `printStyles`
	 * 
	 * @return string
	 * 
	 */
	public function styles() {
		return $this->printStyles();
	}

	/**
	 * Append inline script/style hash to the Content-Security-Policy header
	 * 
	 * @param string $data The file’s content
	 * @param string $type Can be either `"script"` or `"style"`
	 * 
	 */
	private function appendHashToCSP($data, $type) {
		if(
			!$this->updateCSP
			|| !$data
			|| !in_array($type, ["script", "style"])
		) return;
		$hash = hash("sha256", $data);
		$csp = array_filter(headers_list(), function($header) {
			return strpos($header, "Content-Security-Policy") !== false;
		});
		if(count($csp)) {
			$csp = reset($csp);
		}
		if(empty($csp)) {
			$csp = "Content-Security-Policy: $type-src 'self' 'sha256-$hash'";
		} elseif(strpos($csp, "$type-src") !== false) {
			$csp = str_replace("$type-src", "$type-src 'sha256-$hash'", $csp);
		} else {
			$csp .= "$type-src 'self' 'sha256-$hash';";
		}
		header($csp);
	}

	private function attrToString($attr = []) {
		if(empty($attr)) return "";
		if(is_string($attr)) $attr = [$attr => ""];
		$str = "";
		foreach($attr as $key => $value) {
			if(is_int($key)) {
				$str .= "$value ";
			} else {
				$str .= "$key=\"$value\" ";
			}
		}
		return trim($str);
	}
	
	private function getPathAndUrl($filename = "") {
		$sitePath = $this->config->paths->site;
		$siteUrl = $this->config->urls->site;
		$tplPath = $this->config->paths->templates;
		$tplUrl = $this->config->urls->templates;
		if(strpos($filename, "site") === 0) $filename = "/$filename";
		if(strpos($filename, $tplPath) !== false) {
			$path = $filename;
			$url = str_replace($tplPath, $tplUrl, $filename);
		} elseif(strpos($filename, $tplUrl) !== false) {
			$path = str_replace($tplUrl, $tplPath, $filename);
			$url = $filename;
		} elseif(strpos($filename, $sitePath) !== false) {
			$path = $filename;
			$url = str_replace($sitePath, $siteUrl, $filename);
		} elseif(strpos($filename, $siteUrl) !== false) {
			$path = str_replace($siteUrl, $sitePath, $filename);
			$url = $filename;
		} else {
			$path = "{$tplPath}$filename";
			$url = "{$tplUrl}$filename";
		}
		return [$path, $url];
	}
	
	/**
	 * Render a component from /site/templates/components
	 * 
	 * It will automatically add the relevant css/js files if they share the
	 * same name. You can specify subfolders as well, e.g. "folder/component"
	 * 
	 * @param string $name
	 * @param array $vars Associative array of variables sent to the component
	 * file. The keys "attrScript" / "attrStyle" will be used as attributes for
	 * `<script>` and `<link>` tags
	 * @param bool $isSnippet
	 * @return string Rendered component file
	 * @throws WireException Thrown if the component file doesn’t exists
	 * 
	 */
	public function component($name, $vars = [], $isSnippet = false) {
		if(!$name) return "";
		$path = $isSnippet ? "snippets" : "components";
		$tpl = $this->config->paths->templates;
		$folders = explode("/", $name);
		$name = $folders[count($folders) - 1];
		for($i = 0; $i < count($folders) - 1; $i++) {
			$path .= "/$folders[$i]";
		}
		if(file_exists("{$tpl}$path/$name/$name.php")) {
			$path .= "/$name";
		}
		if(file_exists("{$tpl}$path/$name.view.php")) {
			$temp = wireFiles()->tempDir($isSnippet ? "snippets" : "components");
			$tempPath = $temp->get();
			$contents = file_get_contents("{$tpl}$path/$name.php");
			$tokens = token_get_all($contents);
			$appendCloseTag = false;
			foreach ($tokens as $token) {
				if (is_array($token)) {
					if (token_name($token[0]) === 'T_CLOSE_TAG')
					$appendCloseTag = false;
					elseif (token_name($token[0]) === 'T_OPEN_TAG')
					$appendCloseTag = true;
				}
			}
			if($appendCloseTag) {
				$contents .= "?>";
			}
			$contents .= file_get_contents("{$tpl}$path/$name.view.php");
			file_put_contents("{$tempPath}$name.php", $contents);
			$out = wireRenderFile("{$tempPath}$name.php", $vars);
		} else {
			$out = wireRenderFile("{$tpl}$path/$name.php", $vars);
		}
		if(!$this->components->has("$path/$name")) {
			if(file_exists("{$tpl}$path/$name.js")) {
				$this->script("$path/$name.js", $vars["attrScript"] ?? []);
			}
			if(file_exists("{$tpl}$path/$name.css")) {
				$this->style("$path/$name.css", $vars["attrStyle"] ?? []);
			}
			if(file_exists("{$tpl}$path/$name.noscript.css")) {
				$this->noscript("$path/$name.noscript.css");
			}
			$this->components->add("$path/$name");
		}
		return $out;
	}

	/**
	 * Render a snippet from /site/templates/snippets
	 * 
	 * It will automatically add the relevant css/js files if they share the
	 * same name. You can specify subfolders as well, e.g. "folder/snippet"
	 * 
	 * @param string $name
	 * @param array $vars Associative array of variables sent to the component
	 * file. The keys "attrScript" / "attrStyle" will be used as attributes for
	 * `<script>` and `<link>` tags
	 * @return string Rendered snippet file
	 * @throws WireException Thrown if the snippet file doesn’t exists
	 * 
	 */
	public function snippet($name = "", $vars = []) {
		if(!$name) return "";
		return $this->component($name, $vars, true);
	}

	public function getModuleConfigInputfields(InputfieldWrapper $inputfields) {
		$modules = $this->modules;
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "autoFuel");
		$f->columnWidth = 50;
		$f->label = $this->_("Automatically instanciate this module?");
		$f->label2 = $this->_("Yes");
		$f->description = $this->_("The module will be instanciated and made available in your template code through the `\$$this->fuelName` variable");
		$f->checked = !!$this->autoFuel;
		$inputfields->add($f);
	
		/** @var InputfieldText $f */
		$f = $modules->get("InputfieldText");
		$f->attr("name", "fuelName");
		$f->columnWidth = 50;
		$f->label = $this->_("Customize variable name");
		$f->description = $this->_("Default: `\$mc`");
		$f->showIf = "autoFuel=1";
		if($this->fuelError) {
			$f->error($this->_("Please choose a variable name that is not already used by Processwire or another module"));
			$f->attr("value", $this->fuelError);
		} else {
			$f->attr("value", $this->fuelName);
		}
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "functionsApi");
		$f->label = $this->_("Use Functions API?");
		$f->label2 = $this->_("Yes");
		$f->description = sprintf(
			$this->_("This allows you to use the module’s functions without having to instanciate it, e.g.: `component()` instead of `%s->component()`"),
			$this->autoFuel ? "\$$this->fuelName" : "\$modules->get('MarkupComponents')"
		);
		$f->checked = !!$this->functionsApi;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "autoAddAssets");
		$f->label = $this->_("Automatically add .css and .js files on page render?");
		$f->label2 = $this->_("Yes");
		$f->checked = !!$this->autoAddAssets;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "allowUnsafeInline");
		$f->columnWidth = 50;
		$f->description = $this->_("When specifying [\"inline\" => true] in the `attr` argument of the `script()`, `style()` or `noscript()` methods, you can output the file’s content directly within `<script>` or `<style>` tags. Please note this won’t have any effect when using the ajax overwriting option");
		$f->label = $this->_("Allow unsafe inline css/js");
		$f->label2 = $this->_("Yes");
		$f->checked = !!$this->allowUnsafeInline;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "updateCSP");
		$f->columnWidth = 50;
		$f->description = $this->_("To help prevent XSS, hashes of the inline scripts/styles can be automatically added to the CSP header. Note that if you don’t already have a CSP in place it may result in external and/or inline scripts/styles to not load, such as TracyDebugger’s");
		$f->label = $this->_("Append file hashes to Content-Security-Policy?");
		$f->label2 = $this->_("Yes");
		$f->showIf = "allowUnsafeInline=1";
		$f->checked = !!$this->updateCSP;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "overwriteAjax");
		$f->columnWidth = 50;
		$f->label = $this->_("Overwrite ajax calls?");
		$f->label2 = $this->_("Yes");
		$f->description = $this->_("If you make an ajax call (using `$.ajax()` or `fetch()`), you may use this option to overwrite the page render process and wrap its output in a json, along with the `<script>` and `<style>` added by MarkupComponents.");
		$f->checked = !!$this->overwriteAjax;
		$inputfields->add($f);
	
		/** @var InputfieldCheckbox $f */
		$f = $modules->get("InputfieldCheckbox");
		$f->attr("name", "importHelperJs");
		$f->columnWidth = 50;
		$f->label = $this->_("Import helper js?");
		$f->label2 = $this->_("Yes");
		$f->description = $this->_("This js file exposes a `MarkupComponents` variable allowing you to simply handle ajax calls and styles/scripts imports.");
		$f->showIf = "overwriteAjax=1";
		$f->checked = !!$this->importHelperJs;
		$inputfields->add($f);
	}
}
