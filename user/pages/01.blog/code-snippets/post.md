---
title: Code Snippets
date: 09:57 08-05-2015
headline: Including code in your posts is simplicity itself
taxonomy:
    category: blog
    tag: [grav, tips]
---


You can add code blocks to your content quickly and easily. These code blocks can be inline with your text, highlighted so they stand out against the rest of the content, or set apart in its own block to preserve formatting and enable syntax highlighting. This guide will help you get started.

===

## Inline Code

Inline code is handy when you want to insert a string of code, or a specific file or function name without breaking your sentence. To do this, simply wrap your snippets with `` ` ``. Here is an example:

```text
In this example, `<section></section>` should be wrapped as **code**.
```

Renders as:

In this example, `<section></section>` should be wrapped as **code**.

> Inline code will not include syntax highlighting. It is only intended for quick snippets.

## Indented Code

Intending your code is a quick and easy way to create a code block. By intending your code with at least four spaces, Grav will render it in a code block.

<pre>
  // Some comments
  line 1 of code
  line 2 of code
  line 3 of code
</pre>

Renders to:

    // Some comments
    line 1 of code
    line 2 of code
    line 3 of code

## Block Code "Fences"

This is easily the most powerful method of creating a code block as it gives you optimal control over the output. You place any lines you would like to have appear in the code block within fences, consisting of three `` ` `` characters, with the first instance being followed directly by the type of code being presented in the code block. For example:

<pre>
``` markup
Sample text here...
```
</pre>

Renders as:

```
Sample text here...
```

HTML example:

``` html
<HTML>
   <HEAD>
      <TITLE>
         A Small Hello
      </TITLE>
   </HEAD>
<BODY>
   <H1>Hi</H1>
   <P>This is very minimal "hello world" HTML document.</P>
</BODY>
</HTML>
```

You can find a more complete guide for using Markdown in your site's content in the [official Grav documentation](http://learn.getgrav.org/content/markdown).
