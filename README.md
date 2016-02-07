# ![](https://avatars1.githubusercontent.com/u/8237355?v=2&s=50) Grav 

[![SensioLabsInsight](https://insight.sensiolabs.com/projects/cfd20465-d0f8-4a0a-8444-467f5b5f16ad/mini.png)](https://insight.sensiolabs.com/projects/cfd20465-d0f8-4a0a-8444-467f5b5f16ad) [![Gitter](https://badges.gitter.im/Join Chat.svg)](https://gitter.im/getgrav/grav?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge) [![Build Status](https://travis-ci.org/getgrav/grav.svg?branch=develop)](https://travis-ci.org/getgrav/grav)

Grav is a **Fast**, **Simple**, and **Flexible**, file-based Web-platform.  There is **Zero** installation required.  Just extract the ZIP archive, and you are already up and running.  It follows similar principles to other flat-file CMS platforms, but has a different design philosophy than most. Grav comes with a powerful **Package Management System** to allow for simple installation and upgrading of plugins and themes, as well as simple updating of Grav itself.

The underlying architecture of Grav is designed to use well-established and _best-in-class_ technologies to ensure that Grav is simple to use and easy to extend. Some of these key technologies include:

* [Twig Templating](http://twig.sensiolabs.org/): for powerful control of the user interface
* [Markdown](http://en.wikipedia.org/wiki/Markdown): for easy content creation
* [YAML](http://yaml.org): for simple configuration
* [Parsedown](http://parsedown.org/): for fast Markdown and Markdown Extra support
* [Doctrine Cache](http://docs.doctrine-project.org/en/2.0.x/reference/caching.html): layer for performance
* [Pimple Dependency Injection Container](http://pimple.sensiolabs.org/): for extensibility and maintainability
* [Symfony Event Dispatcher](http://symfony.com/doc/current/components/event_dispatcher/introduction.html): for plugin event handling
* [Symfony Console](http://symfony.com/doc/current/components/console/introduction.html): for CLI interface
* [Gregwar Image Library](https://github.com/Gregwar/Image): for dynamic image manipulation

# Requirements

- PHP 5.5.9 or higher. Check the [required modules list](http://learn.getgrav.org/basics/requirements#php-requirements)
- Check the [Apache](http://learn.getgrav.org/basics/requirements#apache-requirements) or [IIS](http://learn.getgrav.org/basics/requirements#iis-requirements) requirements

# QuickStart

These are the options to get Grav:

### Downloading a Grav Package

You can download a **ready-built** package from the [Downloads page on http://getgrav.org](http://getgrav.org/downloads)

### With composer

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

2. Install the **plugin** and **theme dependencies** by using the [Grav CLI application](http://learn.getgrav.org/advanced/grav-cli) `bin/grav`:
   ```
   $ cd ~/webroot/grav
   $ bin/grav install
   ```

Check out the [install procedures](http://learn.getgrav.org/basics/installation) for more information.

# Adding Functionality

You can download [plugins](http://getgrav.org/downloads/plugins) or [themes](http://getgrav.org/downloads/themes) manually from the appropriate tab on the [Downloads page on http://getgrav.org](http://getgrav.org/downloads), but the preferred solution is to use the [Grav Package Manager](http://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```
$ bin/gpm index
```

This will display all the available plugins and then you can install one or more with:

```
$ bin/gpm install <plugin/theme>
```

# Updating

To update Grav you should use the [Grav Package Manager](http://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```
$ bin/gpm selfupgrade
```

To update plugins and themes:

```
$ bin/gpm update
```


# Contributing
We appreciate any contribution to Grav, whether it is related to bugs, grammar, or simply a suggestion or improvement.
However, we ask that any contributions follow our simple guidelines in order to be properly received.

All our projects follow the [GitFlow branching model][gitflow-model], from development to release. If you are not familiar with it, there are several guides and tutorials to make you understand what it is about.

You will probably want to get started by installing [this very good collection of git extensions][gitflow-extensions].

What you mainly want to know is that:

- All the main activity happens in the `develop` branch. Any pull request should be addressed only to that branch. We will not consider pull requests made to the `master`.
- It's very well appreciated, and highly suggested, to start a new feature whenever you want to make changes or add functionalities. It will make it much easier for us to just checkout your feature branch and test it, before merging it into `develop`

# Getting Started

* [What is Grav?](http://learn.getgrav.org/basics/what-is-grav)
* [Install](http://learn.getgrav.org/basics/installation) Grav in few seconds
* Understand the [Configuration](http://learn.getgrav.org/basics/grav-configuration)
* Take a peek at our available free [Skeletons](http://getgrav.org/downloads/skeletons)
* If you have questions, jump on our [Gitter Room](https://gitter.im/getgrav/grav)!
* Have fun!

# Exploring More

* Have a look at our [Basic Tutorial](http://learn.getgrav.org/basics/basic-tutorial)
* Dive into more [advanced](http://learn.getgrav.org/advanced) functions

# License

See [LICENSE](LICENSE.txt)


[gitflow-model]: http://nvie.com/posts/a-successful-git-branching-model/
[gitflow-extensions]: https://github.com/nvie/gitflow

# Running Tests

First install the dev dependencies by running `composer update` from the Grav root.
Then `composer test` will run the Unit Tests, which should be always executed successfully on any site.

You can also run a single unit test file, e.g. `composer test tests/unit/Grav/Common/AssetsTest.php`
