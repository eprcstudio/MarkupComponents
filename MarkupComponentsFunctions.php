<?php namespace ProcessWire;

function callMarkupComponentsFunction($name, ...$arguments) {
	$markupComponentsInstance = wire()->modules->get("MarkupComponents");
	if(method_exists("ProcessWire\MarkupComponents", $name)) {
		return $markupComponentsInstance->{$name}(...$arguments);
	}
}

if(!function_exists("script")) {
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
	function script($filename, $addToHead = false, $attr = []) {
		callMarkupComponentsFunction("script", $filename, $addToHead, $attr);
	}
}

if(!function_exists("js")) {
	/**
	 * Shorter function call for `script`
	 * 
	 * @param string $filename Filename or URL pointing to the script file
	 * @param bool|array $addToHead Add to `<head>`?
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	function js($filename, $addToHead = false, $attr = []) {
		callMarkupComponentsFunction("js", $filename, $addToHead, $attr);
	}
}

if(!function_exists("printScripts")) {
	/**
	 * Prints the `<script>` tags
	 * 
	 * @var bool $head Print the head scripts?
	 * @return string
	 * 
	 */
	function printScripts($head = false) {
		return callMarkupComponentsFunction("printScripts", $head);
	}
}

if(!function_exists("scripts")) {
	/**
	 * Shorter function call for `printScripts`
	 * 
	 * @var bool $head Print the head scripts?
	 * @return string
	 * 
	 */
	function scripts($head = false) {
		return callMarkupComponentsFunction("scripts", $head);
	}
}

if(!function_exists("style")) {
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
	function style($filename, $attr = []) {
		callMarkupComponentsFunction("style", $filename, $attr);
	}
}

if(!function_exists("css")) {
	/**
	 * Shorter function call for `style`
	 * 
	 * @param string $filename Filename or URL pointing to the style file
	 * @param array $attr Associative array converted into tag’s attributes
	 * 
	 */
	function css($filename, $attr = []) {
		callMarkupComponentsFunction("css", $filename, $attr);
	}
}

if(!function_exists("printStyles")) {
	/**
	 * Prints the `<style>` tags
	 * 
	 * @return string
	 * 
	 */
	function printStyles() {
		return callMarkupComponentsFunction("printStyles");
	}
}

if(!function_exists("styles")) {
	/**
	 * Shorter function call for `printStyles`
	 * 
	 * @return string
	 * 
	 */
	function styles() {
		return callMarkupComponentsFunction("styles");
	}
}

if(!function_exists("component")) {
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
	function component($name, $vars = [], $isSnippet = false) {
		return callMarkupComponentsFunction("component", $name, $vars, $isSnippet);
	}
}

if(!function_exists("snippet")) {
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
	function snippet($name, $vars = []) {
		return callMarkupComponentsFunction("snippet", $name, $vars);
	}
}
