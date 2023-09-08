# MarkupComponents

This module provides a set of functions allowing you to segment your code into components/snippets.

## About

MarkupComponents is inspired by [Kirby](https://getkirby.com)’s [`snippet`](https://getkirby.com/docs/reference/templates/helpers/snippet) helper function. Its aim is to help keeping the code cleaner and more manageable by having smaller and reusable blocks of code organized in folders.

This is an opinionated interpretation but “components” are understood as individual content elements (e.g. a slideshow) whereas “snippets” are seen as bigger chunks of code (e.g. for a part of a page).

## Usage

The basic usage of this module, without any of the options described later, is as follow:

First, add a reference to the module at the beginning of your code (usually _init.php if using [Markup Regions](https://processwire.com/docs/front-end/output/markup-regions/)):
```php
/** @var MarkupComponents $mc */
$mc = $modules->get("MarkupComponents");
```

Then, in your template, echo your component along with any custom variable:
```php
echo $mc->component("slideshow", ["images" => $page->images]);
```

Assuming your folder is like this:
```
/templates
↳ /components
  ↳ /slideshow
    ↳ slideshow.css
    ↳ slideshow.js
    ↳ slideshow.php
```

The `slideshow.php` will be rendered and its associated `.css` and `.js` files added to the module’s internal assets arrays.

For these to be output in your HTML, you will need to use:

```php
<html>
	<head>
		<!-- Your code -->
		<?php
			echo $mc->printStyles();
			echo $mc->printScripts(true); // outputs head scripts
		?>
	</head>
	<body>
		<!-- Your code -->
		<?= $mc->printScripts() ?>
	</body>
</html>
```

### Notes

- You can use the module’s `script("path/to/js")` or `style("path/to/css")` functions to add your own files. Note you can also specify HTML attributes with an associative array `["attribute’s name" => "value"]` as a second argument
- Behind the scene, calling `component()` (or `snippet()`) generates the component’s markup using `wireRenderFile`, meaning you have access to all PW API variables and why you can specify your own variables by passing an array as the second argument
- Shorter functions are available:
  
  `script()` → `js()`

  `style()` → `css()`

  `printScripts()` → `scripts()`

  `printStyles()` → `styles()`
- Snippets are basically components, just in another folder

## Options

There’s a few options you can toggle to accomodate your coding style:

- You can automatically instanciate the module with a custom variable name
- With the Functions API you can use the module’s functions directly, e.g.: `component()` instead of `$mc->component()`. Note this is does not relate to [`$config->useFunctionsAPI`](https://processwire.com/api/ref/functions/#pwapi-methods-Functions-API)
- You can automatically add `.css` and `.js` files on page render
- If for a reason or another you would like to have the `.css` and `.js` added to `$config->styles` and `$config->scripts`, you can do so but you’d lose the HTML attributes part
