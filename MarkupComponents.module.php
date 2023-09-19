<?php namespace ProcessWire;

/**
 * Components/snippets system inspired by Kirby’s `snippet()` helper function
 * 
 * Copyright (c) 2023 EPRC
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

	public function __construct() {
		parent::__construct();
		$this->set("autoAddAssets", 0);
		$this->set("autoFuel", 0);
		$this->set("fuelName", "mc");
		$this->set("functionsApi", 0);
		$this->set("useConfig", 0);
	}

	public function init() {
		$this->components = new WireArray();
		$this->scriptsHead = new WireArray();
		$this->scripts = new WireArray();
		$this->styles = new WireArray();
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
		if($this->functionsApi && $this->page->template != "admin") {
			include_once __DIR__ . "/MarkupComponentsFunctions.php";
		}
	}

	protected function addAssets(HookEvent $event) {
		$parentEvent = $event->arguments(0);
		$html = $parentEvent->return;
		// todo: handle ajax loading
		// $components = "<script>const components = [{$this->listComponents()}]</script>";
		$headScripts = $this->printScripts(true);
		$scripts = $this->printScripts();
		$styles = $this->printStyles();
		$html = str_replace("</head>", "{$styles}{$headScripts}</head>", $html);
		$html = str_replace("</body>", "{$scripts}</body>", $html);
		$parentEvent->return = $html;
	}

	public function getComponents() {
		return $this->components;
	}

	/**
	 * Returns the components’ name as a string, using a separator and quotes
	 * 
	 * @return string
	 * 
	 */
	public function listComponents($options = []) {
		$defaultOptions = [
			"separator" => ",",
			"quote" => "\"",
			"closingQuote" => "",
			"prepend" => "",
			"append" => ""
		];
		$options = array_merge($defaultOptions, $options);
		if(!$options["closingQuote"]) $options["closingQuote"] = $options["quote"];
		$separator = $options["closingQuote"] . $options["separator"] . $options["quote"];
		return $this->components->implode($separator, "", [
			"prepend" => $options["prepend"] . $options["quote"],
			"append" => $options["closingQuote"] . $options["append"]
		]);
	}

	/**
	 * Adds a `<script>` inside either `<head>` or `<body>` tags
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
			$fullPath = $filename;
		} else {
			if(strpos($filename, ".js") === false) {
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
		if($addToHead) {
			if(!$this->scriptsHead->has("src=$fullPath")) {
				$this->scriptsHead->add(WireData([
					"src" => $fullPath,
					"attr" => $this->attrToString($attr)
				]));
			}
		} else {
			if(!$this->scripts->has("src=$fullPath")) {
				$this->scripts->add(WireData([
					"src" => $fullPath,
					"attr" => $this->attrToString($attr)
				]));
			}
		}
		if($this->useConfig) {
			$this->config->scripts->add($fullPath);
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
	 * Prints the `<script>` tags
	 * 
	 * @var bool $head Print the head scripts?
	 * @return string
	 * 
	 */
	public function printScripts($head = false) {
		$str = "";
		foreach($this->getScripts($head) as $script) {
			$str .= "<script src=\"$script->src\" $script->attr></script>";
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
	 * Adds a `<style>` inside the `<head>` tag
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
			$fullPath = $filename;
		} else {
			if(strpos($filename, ".css") === false) {
				$filename .= ".css";
			} 
			[$path, $url] = $this->getPathAndUrl($filename);
			if(!file_exists($path)) return;
			$fullPath = "$url?v=" . filemtime($path);
		}
		if(!$this->styles->has("src=$fullPath")) {
			$this->styles->add(WireData([
				"src" => $fullPath,
				"attr" => $this->attrToString($attr)
			]));
		}
		if($this->useConfig) {
			$this->config->styles->add($fullPath);
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
	 * @return WireArray
	 * 
	 */
	public function getStyles() {
		return $this->styles;
	}

	/**
	 * Prints the `<style>` tags
	 * 
	 * @return string
	 * 
	 */
	public function printStyles() {
		$str = "";
		foreach($this->styles as $style) {
			$str .= "<link rel=\"stylesheet\" type=\"text/css\" href=\"$style->src\" $style->attr>";
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
		$tplPath = $this->config->paths->templates;
		$tplUrl = $this->config->urls->templates;
		if(strpos($filename, "site") === 0) $filename = "/$filename";
		if(strpos($filename, $tplPath) !== false) {
			$path = $filename;
			$url = str_replace($tplPath, $tplUrl, $filename);
		} elseif(strpos($filename, $tplUrl) !== false) {
			$path = str_replace($tplUrl, $tplPath, $filename);
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
		if(!$this->components->has("$path/$name")) {
			if(file_exists("{$tpl}$path/$name.js")) {
				$this->script("$path/$name.js", $vars["attrScript"] ?? []);
			}
			if(file_exists("{$tpl}$path/$name.css")) {
				$this->style("$path/$name.css", $vars["attrStyle"] ?? []);
			}
			$this->components->add("$path/$name");
		}
		return wireRenderFile("{$tpl}$path/$name.php", $vars);
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
			$this->_("This allows you to use the module’s functions without having instanciating it, e.g.: `component()` instead of `%s->component()`"),
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
		$f->attr("name", "useConfig");
		$f->label = $this->_("Add .css and .js to \$config?"); 
		$f->label2 = $this->_("Yes");
		$f->description = $this->_("When calling `component()` or `snippet()`, associated .css and .js files are added to an internal WireArray that you can output using `printStyles()` and `printScripts()`. This option allows you to have these added to `\$config->styles` and `\$config->scripts` as well.");
		$f->checked = !!$this->useConfig;
		$inputfields->add($f);
	}
}
