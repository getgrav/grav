---
title: Getting Started with Grav
date: 09:55 07-07-2015
headline: Grav is super easy to install, just follow along...
taxonomy:
    category: blog
    tag: [grav]
---

Grav is very easy to install. Because it does not require a database, installation can be as simple as unzipping the Grav core (or a skeleton) in the server directory you wish to have your Grav install appear.

Pretty much the only real requirement of Grav is that your server is running PHP 5.4 or higher. You can use Grav with Apache, Nginx, LiteSpeed, IIS, etc.

---

## Option 1: Install with ZIP package

The easiest way to install Grav is to use the ZIP package and install it:

1. Download the latest-and-greatest Grav Base package from the [Downloads](http://getgrav.org/downloads) page.
2. Extract the ZIP file in your webroot of your web server, e.g. `~/webroot/grav`.

If you downloaded the ZIP file and then plan to move it to your webroot, please move the **entire folder** because it contains several hidden files (such as `.htaccess`) that will not be selected by default. The omission of these hidden files can cause problems when running Grav.

---

## Option 2: Install from GitHub

The alternative method is to install Grav from the GitHub repository and then run a simple dependency installation script:

Clone the Grav repository from GitHub to a folder in the webroot of your server, e.g. `~/webroot/grav`. Launch a `Terminal` or `Command Line` and navigate to the webroot folder:

```text
$ cd ~/webroot
$ git clone https://github.com/getgrav/grav.git
```

Install the **plugin** and theme **dependencies** by using the [Grav CLI application](http://learn.getgrav.org/advanced/grav-cli) `bin/grav`:

```text
$ cd ~/webroot/grav
$ bin/grav install
This will automatically clone the required dependencies from GitHub directly into this Grav installation.
```

You can find more information on installing and updating Grav by visiting Grav's [official documentation](http://learn.getgrav.org/basics/installation).
