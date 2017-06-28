# ![](https://avatars1.githubusercontent.com/u/8237355?v=2&s=50) Grav

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/cfd20465-d0f8-4a0a-8444-467f5b5f16ad/mini.png)](https://insight.sensiolabs.com/projects/cfd20465-d0f8-4a0a-8444-467f5b5f16ad) [![Slack](https://grav-chat.now.sh/badge.svg)](https://chat.getgrav.org) [![Build Status](https://travis-ci.org/getgrav/grav.svg?branch=develop)](https://travis-ci.org/getgrav/grav) [![OpenCollective](https://opencollective.com/grav/backers/badge.svg)](#backers) [![OpenCollective](https://opencollective.com/grav/sponsors/badge.svg)](#sponsors)

Grav is a **Fast**, **Simple**, and **Flexible**, file-based Web-platform.  There is **Zero** installation required.  Just extract the ZIP archive, and you are already up and running.  It follows similar principles to other flat-file CMS platforms, but has a different design philosophy than most. Grav comes with a powerful **Package Management System** to allow for simple installation and upgrading of plugins and themes, as well as simple updating of Grav itself.

The underlying architecture of Grav is designed to use well-established and _best-in-class_ technologies to ensure that Grav is simple to use and easy to extend. Some of these key technologies include:

* [Twig Templating](http://twig.sensiolabs.org/): for powerful control of the user interface
* [Markdown](http://en.wikipedia.org/wiki/Markdown): for easy content creation
* [YAML](http://yaml.org): for simple configuration
* [Parsedown](http://parsedown.org/): for fast Markdown and Markdown Extra support
* [Doctrine Cache](http://doctrine-orm.readthedocs.io/projects/doctrine-orm/en/latest/reference/caching.html): layer for performance
* [Pimple Dependency Injection Container](http://pimple.sensiolabs.org/): for extensibility and maintainability
* [Symfony Event Dispatcher](http://symfony.com/doc/current/components/event_dispatcher/introduction.html): for plugin event handling
* [Symfony Console](http://symfony.com/doc/current/components/console/introduction.html): for CLI interface
* [Gregwar Image Library](https://github.com/Gregwar/Image): for dynamic image manipulation

# Requirements

- PHP 5.5.9 or higher. Check the [required modules list](https://learn.getgrav.org/basics/requirements#php-requirements)
- Check the [Apache](https://learn.getgrav.org/basics/requirements#apache-requirements) or [IIS](https://learn.getgrav.org/basics/requirements#iis-requirements) requirements

# QuickStart

These are the options to get Grav:

### Downloading a Grav Package

You can download a **ready-built** package from the [Downloads page on https://getgrav.org](https://getgrav.org/downloads)

### With Composer

You can create a new project with the latest **stable** Grav release with the following command:

```
$ composer create-project getgrav/grav ~/webroot/grav
```

### From GitHub

1. Clone the Grav repository from [https://github.com/getgrav/grav]() to a folder in the webroot of your server, e.g. `~/webroot/grav`. Launch a **terminal** or **console** and navigate to the webroot folder:
   ```
   $ cd ~/webroot
   $ git clone https://github.com/getgrav/grav.git
   ```

2. Install the **plugin** and **theme dependencies** by using the [Grav CLI application](https://learn.getgrav.org/advanced/grav-cli) `bin/grav`:
   ```
   $ cd ~/webroot/grav
   $ bin/grav install
   ```

Check out the [install procedures](https://learn.getgrav.org/basics/installation) for more information.

# Adding Functionality

You can download [plugins](https://getgrav.org/downloads/plugins) or [themes](https://getgrav.org/downloads/themes) manually from the appropriate tab on the [Downloads page on https://getgrav.org](https://getgrav.org/downloads), but the preferred solution is to use the [Grav Package Manager](https://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```
$ bin/gpm index
```

This will display all the available plugins and then you can install one or more with:

```
$ bin/gpm install <plugin/theme>
```

# Updating

To update Grav you should use the [Grav Package Manager](https://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```
$ bin/gpm selfupgrade
```

To update plugins and themes:

```
$ bin/gpm update
```


# Contributing
We appreciate any contribution to Grav, whether it is related to bugs, grammar, or simply a suggestion or improvement! Please refer to the [Contributing guide](CONTRIBUTING.md) for more guidance on this topic.

## Security issues
If you discover a possible security issue related to Grav or one of its plugins, please send an email to the core team at contact@getgrav.org and we'll address it as soon as possible.

# Getting Started

* [What is Grav?](https://learn.getgrav.org/basics/what-is-grav)
* [Install](https://learn.getgrav.org/basics/installation) Grav in few seconds
* Understand the [Configuration](https://learn.getgrav.org/basics/grav-configuration)
* Take a peek at our available free [Skeletons](https://getgrav.org/downloads/skeletons)
* If you have questions, jump on our [Slack Room](https://getgrav.org/slack)!
* Have fun!

# Exploring More

* Have a look at our [Basic Tutorial](https://learn.getgrav.org/basics/basic-tutorial)
* Dive into more [advanced](https://learn.getgrav.org/advanced) functions

# Backers
Support us with a monthly donation and help us continue our activities. [[Become a backer](https://opencollective.com/grav#backer)]

<a href="https://opencollective.com/grav/backer/0/website" target="_blank"><img src="https://opencollective.com/grav/backer/0/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/1/website" target="_blank"><img src="https://opencollective.com/grav/backer/1/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/2/website" target="_blank"><img src="https://opencollective.com/grav/backer/2/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/3/website" target="_blank"><img src="https://opencollective.com/grav/backer/3/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/4/website" target="_blank"><img src="https://opencollective.com/grav/backer/4/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/5/website" target="_blank"><img src="https://opencollective.com/grav/backer/5/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/6/website" target="_blank"><img src="https://opencollective.com/grav/backer/6/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/7/website" target="_blank"><img src="https://opencollective.com/grav/backer/7/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/8/website" target="_blank"><img src="https://opencollective.com/grav/backer/8/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/9/website" target="_blank"><img src="https://opencollective.com/grav/backer/9/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/10/website" target="_blank"><img src="https://opencollective.com/grav/backer/10/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/11/website" target="_blank"><img src="https://opencollective.com/grav/backer/11/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/12/website" target="_blank"><img src="https://opencollective.com/grav/backer/12/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/13/website" target="_blank"><img src="https://opencollective.com/grav/backer/13/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/14/website" target="_blank"><img src="https://opencollective.com/grav/backer/14/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/15/website" target="_blank"><img src="https://opencollective.com/grav/backer/15/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/16/website" target="_blank"><img src="https://opencollective.com/grav/backer/16/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/17/website" target="_blank"><img src="https://opencollective.com/grav/backer/17/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/18/website" target="_blank"><img src="https://opencollective.com/grav/backer/18/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/19/website" target="_blank"><img src="https://opencollective.com/grav/backer/19/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/20/website" target="_blank"><img src="https://opencollective.com/grav/backer/20/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/21/website" target="_blank"><img src="https://opencollective.com/grav/backer/21/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/22/website" target="_blank"><img src="https://opencollective.com/grav/backer/22/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/23/website" target="_blank"><img src="https://opencollective.com/grav/backer/23/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/24/website" target="_blank"><img src="https://opencollective.com/grav/backer/24/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/25/website" target="_blank"><img src="https://opencollective.com/grav/backer/25/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/26/website" target="_blank"><img src="https://opencollective.com/grav/backer/26/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/27/website" target="_blank"><img src="https://opencollective.com/grav/backer/27/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/28/website" target="_blank"><img src="https://opencollective.com/grav/backer/28/avatar.svg"></a>
<a href="https://opencollective.com/grav/backer/29/website" target="_blank"><img src="https://opencollective.com/grav/backer/29/avatar.svg"></a>


# Sponsors
Become a sponsor and get your logo on our README on Github with a link to your site. [[Become a sponsor](https://opencollective.com/grav#sponsor)]

<a href="https://opencollective.com/grav/sponsor/0/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/0/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/1/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/1/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/2/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/2/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/3/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/3/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/4/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/4/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/5/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/5/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/6/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/6/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/7/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/7/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/8/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/8/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/9/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/9/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/10/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/10/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/11/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/11/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/12/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/12/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/13/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/13/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/14/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/14/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/15/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/15/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/16/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/16/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/17/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/17/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/18/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/18/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/19/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/19/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/20/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/20/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/21/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/21/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/22/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/22/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/23/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/23/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/24/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/24/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/25/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/25/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/26/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/26/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/27/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/27/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/28/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/28/avatar.svg"></a>
<a href="https://opencollective.com/grav/sponsor/29/website" target="_blank"><img src="https://opencollective.com/grav/sponsor/29/avatar.svg"></a>

# License

See [LICENSE](LICENSE.txt)


[gitflow-model]: http://nvie.com/posts/a-successful-git-branching-model/
[gitflow-extensions]: https://github.com/nvie/gitflow

# Running Tests

First install the dev dependencies by running `composer update` from the Grav root.
Then `composer test` will run the Unit Tests, which should be always executed successfully on any site.

You can also run a single unit test file, e.g. `composer test tests/unit/Grav/Common/AssetsTest.php`
