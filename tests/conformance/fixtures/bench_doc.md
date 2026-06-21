
# Core Markdown Typography

> [!NOTE]
> Details on the full capabilities of Spectre.css can be found in the [Official Spectre Documentation](https://picturepan2.github.io/spectre/elements.html)

### Headings

# H1 Heading `40px`

## H2 Heading `32px`

### H3 Heading `28px`

#### H4 Heading `24px`

##### H5 Heading `20px`

###### H6 Heading `16px`

```html
# H1 Heading
# H1 Heading `40px`</small>`

<span class="h1">H1 Heading</span>
```

## PHP 7.4 issues

black _&_ white

a **&** b

### Paragraphs

Lorem ipsum dolor sit amet, consectetur [adipiscing elit. Praesent risus leo, dictum in vehicula sit amet](#), feugiat tempus tellus. Duis quis sodales risus. Etiam euismod ornare consequat.

Climb leg rub face on everything give attitude nap all day for under the bed. Chase mice attack feet but rub face on everything hopped up on goofballs.

### Markdown Semantic Text Elements

**Bold** `**Bold**`

_Italic_ `_Italic_`

~~Deleted~~ `~~Deleted~~`

`Inline Code` `` `Inline Code` ``

### HTML Semantic Text Elements

I18N `<abbr>`

Citation `<cite>`

Ctrl + S `<kbd>`

TextSuperscripted `<sup>`

TextSubscripted `<sub>`

Underlined `<u>`

Highlighted `<mark>`

20:14 `<time>`

x = y + 2 `<var>`

### Blockquote

> The advance of technology is based on making it fit in so that you don't really even notice it,
> so it's part of everyday life.
> 
> *   Bill Gates

```plaintext
> The advance of technology is based on making it fit in so that you don't really even notice it,
> so it's part of everyday life.
>
> <cite>- Bill Gates</cite>
```

### Unordered List

*   list item 1
*   list item 2
    *   list item 2.1
    *   list item 2.2
    *   list item 2.3
*   list item 3

```plaintext
* list item 1
* list item 2
    * list item 2.1
    * list item 2.2
    * list item 2.3
* list item 3
```

### Ordered List

1.  list item 1
2.  list item 2
    1.  list item 2.1
    2.  list item 2.2
    3.  list item 2.3
3.  list item 3

```plaintext
1. list item 1
1. list item 2
    1. list item 2.1
    1. list item 2.2
    1. list item 2.3
1. list item 3
```

### Table

[div class="table"]

| Name | Genre | Release date |
| --- | :-: | --: |
| The Shawshank Redemption | Crime, Drama | 14 October 1994 |
| The Godfather | Crime, Drama | 24 March 1972 |
| Schindler's List | Biography, Drama, History | 4 February 1994 |
| Se7en2222 | Crime, Drama, Mystery | 22 September 1995 |

[/div]

```plaintext
| Name                        | Genre                         | Release date         |
| :-------------------------- | :---------------------------: | -------------------: |
| The Shawshank Redemption    | Crime, Drama                  | 14 October 1994      |
| The Godfather               | Crime, Drama                  | 24 March 1972        |
| Schindler's List            | Biography, Drama, History     | 4 February 1994      |
| Se7en                       | Crime, Drama, Mystery         | 22 September 1995    |
```

### Enhanced Tables

Three opt-in, non-GFM table extensions (enabled for this page via the `markdown.tables` front matter; off by default so standard tables are untouched).

**Colspan** — leave a cell blank and it merges into the cell on its left:

| Item | Jan | Feb | Mar |
| :-- | --: | --: | --: |
| Widgets | 10 | 12 | 9 |
| Gadgets | 7 | 8 | 11 |
| Total for the quarter |   |   | 57 |

```plaintext
| Item | Jan | Feb | Mar |
| :-- | --: | --: | --: |
| Widgets | 10 | 12 | 9 |
| Total for the quarter |   |   | 57 |
```

**Header-less** — start straight at the divider row for a table with no header:

| --- | --- |
| Mercury | Closest to the Sun |
| Neptune | Furthest from the Sun |

```plaintext
| --- | --- |
| Mercury | Closest to the Sun |
| Neptune | Furthest from the Sun |
```

**Captions** — a `[Caption]` line immediately after a table becomes a `<caption>`:

| Symbol | Element |
| --- | --- |
| H | Hydrogen |
| He | Helium |
[The first two elements of the periodic table]

```plaintext
| Symbol | Element |
| --- | --- |
| H | Hydrogen |
| He | Helium |
[The first two elements of the periodic table]
```

**Attributes** — a `{.class #id}` line right after a table sets the class and id on the `<table>` itself, handy for CSS frameworks that style `.striped`, `.responsive`, `.minimal` and the like (the kramdown-style `{:.class}` form is accepted too):

| Name | Role |
| --- | --- |
| Ada | Engineer |
| Grace | Architect |
{.striped .responsive #team-table}

```plaintext
| Name | Role |
| --- | --- |
| Ada | Engineer |
| Grace | Architect |
{.striped .responsive #team-table}
```

**Multi-line cells** — end a row with a backslash and the next line continues it, joining each column with a line break:

| Feature | Description |
| --- | --- |
| Tables | Alignment, escaped pipes, \
| | and inline cell content |
| Lists | Ordered, unordered and task lists |

```plaintext
| Feature | Description |
| --- | --- |
| Tables | Alignment, escaped pipes, \
| | and inline cell content |
| Lists | Ordered, unordered and task lists |
```

### Task Lists

GitHub-style task lists render as checkboxes (new in Grav 2.0):

- [x] Ship the markdown engine
- [x] Add task lists
- [ ] Take over the world

```plaintext
- [x] Ship the markdown engine
- [x] Add task lists
- [ ] Take over the world
```

### Highlight, Subscript & Superscript

New inline syntax (Grav 2.0): you can ==highlight text==, write H~2~O, and note that E = mc^2^.

```plaintext
==highlight text==, H~2~O, E = mc^2^
```

### Autolinks

Bare `www.` URLs and email addresses now link automatically (new in Grav 2.0): visit www.getgrav.org or email hello@getgrav.org.

```plaintext
visit www.getgrav.org or email hello@getgrav.org
```

### Alerts

GitHub-style alerts are provided by the `github-markdown-alerts` plugin, which brings the five standard alert types (and their styling):

> [!NOTE]
> Useful information that users should know, even when skimming content.

> [!TIP]
> Helpful advice for doing things better or more easily.

> [!IMPORTANT]
> Key information users need to know to achieve their goal.

> [!WARNING]
> Urgent info that needs immediate user attention to avoid problems.

> [!CAUTION]
> Advises about risks or negative outcomes of certain actions.

```plaintext
> [!NOTE]
> Useful information that users should know, even when skimming content.

> [!TIP]
> Helpful advice for doing things better or more easily.
```