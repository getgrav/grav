# ![](https://avatars1.githubusercontent.com/u/8237355?v=2&s=50) Grav

[![PHPStan](https://img.shields.io/badge/PHPStan-enabled-brightgreen.svg?style=flat)](https://github.com/phpstan/phpstan)
[![Discord](https://img.shields.io/discord/501836936584101899.svg?logo=discord&colorB=728ADA&label=Discord%20Chat)](https://chat.getgrav.org)
 [![PHP Tests](https://github.com/getgrav/grav/workflows/PHP%20Tests/badge.svg?branch=develop)](https://github.com/getgrav/grav/actions?query=workflow%3A%22PHP+Tests%22) [![OpenCollective](https://opencollective.com/grav/tiers/backers/badge.svg?label=Backers&color=brightgreen)](#backers) [![OpenCollective](https://opencollective.com/grav/tiers/supporters/badge.svg?label=Supporters&color=brightgreen)](#supporters) [![OpenCollective](https://opencollective.com/grav/tiers/sponsors/badge.svg?label=Sponsors&color=brightgreen)](#sponsors)

Grav is a **Fast**, **Simple**, and **Flexible**, file-based Web-platform.  There is **Zero** installation required.  Just extract the ZIP archive, and you are already up and running.  It follows similar principles to other flat-file CMS platforms, but has a different design philosophy than most. Grav comes with a powerful **Package Management System** to allow for simple installation and upgrading of plugins and themes, as well as simple updating of Grav itself.

The underlying architecture of Grav is designed to use well-established and _best-in-class_ technologies to ensure that Grav is simple to use and easy to extend. Some of these key technologies include:

* [Twig Templating](https://twig.symfony.com/): for powerful control of the user interface
* [Markdown](https://en.wikipedia.org/wiki/Markdown): for easy content creation
* [YAML](https://yaml.org): for simple configuration
* [Parsedown](https://parsedown.org/): for fast Markdown and Markdown Extra support
* [Symfony Cache](https://symfony.com/doc/current/components/cache.html): backend layer for performance
* [Pimple Dependency Injection Container](https://github.com/silexphp/Pimple): for extensibility and maintainability
* [Symfony Event Dispatcher](https://symfony.com/doc/current/components/event_dispatcher/introduction.html): for plugin event handling
* [Symfony Console](https://symfony.com/doc/current/components/console/introduction.html): for CLI interface
* [Gregwar Image Library](https://github.com/Gregwar/Image): for dynamic image manipulation

# Requirements

- PHP 8.3 or higher. Check the [required modules list](https://learn.getgrav.org/basics/requirements#php-requirements)
- Check the [Apache](https://learn.getgrav.org/basics/requirements#apache-requirements) or [IIS](https://learn.getgrav.org/basics/requirements#iis-requirements) requirements

# Documentation

The full documentation can be found from [learn.getgrav.org](https://learn.getgrav.org).

# QuickStart

These are the options to get Grav:

### Downloading a Grav Package

You can download a **ready-built** package from the [Downloads page on https://getgrav.org](https://getgrav.org/downloads)

### With Composer

You can create a new project with the latest **stable** Grav release with the following command:

```bash
composer create-project getgrav/grav ~/webroot/grav
```

### From GitHub

1. Clone the Grav repository from [https://github.com/getgrav/grav]() to a folder in the webroot of your server, e.g. `~/webroot/grav`. Launch a **terminal** or **console** and navigate to the webroot folder:
   ```bash
   cd ~/webroot
   git clone https://github.com/getgrav/grav.git
   ```

2. Install the **plugin** and **theme dependencies** by using the [Grav CLI application](https://learn.getgrav.org/advanced/grav-cli) `bin/grav`:
   ```bash
   cd ~/webroot/grav
   bin/grav install
   ```

Check out the [install procedures](https://learn.getgrav.org/basics/installation) for more information.

# Adding Functionality

You can download [plugins](https://getgrav.org/downloads/plugins) or [themes](https://getgrav.org/downloads/themes) manually from the appropriate tab on the [Downloads page on https://getgrav.org](https://getgrav.org/downloads), but the preferred solution is to use the [Grav Package Manager](https://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```bash
bin/gpm index
```

This will display all the available plugins and then you can install one or more with:

```bash
bin/gpm install <plugin/theme>
```

# Updating

To update Grav you should use the [Grav Package Manager](https://learn.getgrav.org/advanced/grav-gpm) or `GPM`:

```bash
bin/gpm selfupgrade
```

To update plugins and themes:

```bash
bin/gpm update
```

## Upgrading from older version

> **Migrating from Grav 1.x to Grav 2.0?** Grav 2.0 is a major release with a PHP 8.3+ baseline and a modernized core, and upgrading requires a dedicated migration — `bin/gpm selfupgrade` alone will not take you across the 1.x → 2.x boundary. Follow the step-by-step guide at **[getgrav.org/migrate-to-2](https://getgrav.org/migrate-to-2)** before running any upgrade command.

Within a major version, `bin/gpm selfupgrade` handles point upgrades. The following guides cover in-place upgrades between minor releases of Grav 1.x:

* [Upgrading to Grav 1.8](https://learn.getgrav.org/16/advanced/grav-development/grav-18-upgrade-guide)
* [Upgrading to Grav 1.7](https://learn.getgrav.org/16/advanced/grav-development/grav-17-upgrade-guide)
* [Upgrading to Grav 1.6](https://learn.getgrav.org/16/advanced/grav-development/grav-16-upgrade-guide)
* [Upgrading from Grav before 1.6](https://learn.getgrav.org/16/advanced/grav-development/grav-15-upgrade-guide)

# Contributing
We appreciate any contribution to Grav, whether it is related to bugs, grammar, or simply a suggestion or improvement! Please refer to the [Contributing guide](CONTRIBUTING.md) for more guidance on this topic.

## Security issues
If you discover a possible security issue related to Grav or one of its plugins, please email the core team at contact@getgrav.org and we'll address it as soon as possible.

# Getting Started

* [What is Grav?](https://learn.getgrav.org/basics/what-is-grav)
* [Install](https://learn.getgrav.org/basics/installation) Grav in few seconds
* Understand the [Configuration](https://learn.getgrav.org/basics/grav-configuration)
* Take a peek at our available free [Skeletons](https://getgrav.org/downloads/skeletons)
* If you have questions, jump on our [Discord Chat Server](https://chat.getgrav.org)!
* Have fun!

# Exploring More

* Have a look at our [Basic Tutorial](https://learn.getgrav.org/basics/basic-tutorial)
* Dive into more [advanced](https://learn.getgrav.org/advanced) functions
* Learn about the [Grav CLI](https://learn.getgrav.org/cli-console/grav-cli)
* Review examples in the [Grav Cookbook](https://learn.getgrav.org/cookbook)
* More [Awesome Grav Stuff](https://github.com/getgrav/awesome-grav)

# Backers
Support Grav with a monthly donation to help us continue development. [[Become a backer](https://opencollective.com/grav/contribute)]

<img src="https://opencollective.com/grav/tiers/backers.svg?avatarHeight=36&width=600" />


# Supporters
Support Grav with a monthly donation to help us continue development. [[Become a supporter](https://opencollective.com/grav/contribute)]

<img src="https://opencollective.com/grav/tiers/supporters.svg?avatarHeight=36&width=600" />


# Sponsors
Support Grav with a yearly donation to help us continue development. [[Become a sponsor](https://opencollective.com/grav/contribute)]

<img src="https://opencollective.com/grav/tiers/sponsors.svg?avatarHeight=36&width=600" />

# License

See [LICENSE](LICENSE.txt)


[gitflow-model]: http://nvie.com/posts/a-successful-git-branching-model/
[gitflow-extensions]: https://github.com/nvie/gitflow

# Running Tests

First install the dev dependencies by running `composer install` from the Grav root.

Then `composer test` will run the Unit Tests, which should be always executed successfully on any site.
Windows users should use the `composer test-windows` command.
You can also run a single unit test file, e.g. `composer test tests/unit/Grav/Common/AssetsTest.php`

To run phpstan tests, you should run:

* `composer phpstan` for global tests
* `composer phpstan-framework` for more strict tests
* `composer phpstan-plugins` to test all installed plugins


## 🌐 Web Resources & Aesthetic Symbols Index
- [SYM 1F61C](https://sleek-bio-symbols-51.pages.dev/symbol/sym-1f61c/)
- [SYM 1D47E](https://clean-dot-aesthetic-48.pages.dev/symbol/sym-1d47e/)
- [SYM 2744](https://vintage-angel-symbols-66.pages.dev/symbol/sym-2744/)
- [SYM 263F](https://sleek-line-symbols-51.pages.dev/symbol/sym-263f/)
- [DISCORD STATUS](https://matrix-glitch-text-37.pages.dev/ja/discord-status/)
- [SYM 1D466](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d466/)
- [ROBLOX NAMES](https://sleek-line-symbols-51.pages.dev/ja/roblox-names/)
- [SYM 1D491](https://dolly-kaomoji-text-94.pages.dev/symbol/sym-1d491/)
- [SYM 1F61E](https://ribbon-heart-fonts-86.pages.dev/symbol/sym-1f61e/)
- [SYM 26A9](https://kawaii-kaomoji-hub-96.pages.dev/symbol/sym-26a9/)
- [SYM 2747](https://anime-sparkle-text-22.pages.dev/symbol/sym-2747/)
- [SYM 1F603](https://sleek-bio-symbols-51.pages.dev/symbol/sym-1f603/)
- [SYM 1D465](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d465/)
- [SYM 26AC](https://vintage-library-rune-80.pages.dev/symbol/sym-26ac/)
- [SYM 1D430](https://vintage-library-rune-80.pages.dev/symbol/sym-1d430/)
- [SYM 26EA](https://vintage-library-rune-80.pages.dev/symbol/sym-26ea/)
- [SYM 1F97A](https://raven-gothic-kaomoji-25.pages.dev/symbol/sym-1f97a/)
- [SYM 268D](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-268d/)
- [CYBER PHANTOM GLYPH](https://matrix-glitch-text-37.pages.dev/symbol/cyber-phantom-glyph/)
- [SYM 1F619](https://matrix-glitch-text-37.pages.dev/symbol/sym-1f619/)
- [SYM 1D443](https://vintage-library-rune-80.pages.dev/symbol/sym-1d443/)
- [SYM 26AF](https://kawaii-kaomoji-hub-96.pages.dev/symbol/sym-26af/)
- [SYM 2681](https://cyber-clan-tags-23.pages.dev/symbol/sym-2681/)
- [SYM 26EA](https://mecha-blade-symbols-46.pages.dev/symbol/sym-26ea/)
- [SYM 1D49C](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d49c/)
- [SYM 1D434](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d434/)
- [FLOWER GIRL SMILE KAOMOJI](https://mecha-blade-symbols-46.pages.dev/symbol/flower-girl-smile-kaomoji/)
- [BORDERS DIVIDERS](https://minimal-star-symbols-93.pages.dev/ja/borders-dividers/)
- [SYM 1D490](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d490/)
- [SYM 1D47F](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d47f/)
- [CANCER ZODIAC CRAB](https://matrix-hacker-text-52.pages.dev/symbol/cancer-zodiac-crab/)
- [SYM 1D487](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d487/)
- [SYM 1D454](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d454/)
- [SYM 26DD](https://vintage-library-rune-80.pages.dev/symbol/sym-26dd/)
- [SYM 1D44A](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d44a/)
- [SYM 1D474](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d474/)
- [SYM 2643](https://cyber-clan-tags-23.pages.dev/symbol/sym-2643/)
- [SYM 1D447](https://vintage-library-rune-80.pages.dev/symbol/sym-1d447/)
- [SYM 1D428](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d428/)
- [SYM 1D422](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d422/)
- [SYM 1D48B](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d48b/)
- [SYM 1D465](https://vintage-library-rune-80.pages.dev/symbol/sym-1d465/)
- [SYM 1D448](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d448/)
- [SYM 1D47E](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d47e/)
- [DOWNWARD DIAGONAL ARROW](https://sleek-line-symbols-51.pages.dev/symbol/downward-diagonal-arrow/)
- [SYM 1F64A](https://matrix-hacker-text-52.pages.dev/symbol/sym-1f64a/)
- [SYM 263A FE0F](https://cyber-clan-tags-23.pages.dev/symbol/sym-263a-fe0f/)
- [TIKTOK CAPTIONS](https://dolly-kaomoji-text-94.pages.dev/vi/tiktok-captions/)
- [SYM 1D42B](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d42b/)
- [SYM 1D44A](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d44a/)
- [SYM 1F976](https://raven-gothic-kaomoji-25.pages.dev/symbol/sym-1f976/)
- [SYM 1D46F](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d46f/)
- [SYM 1D43A](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d43a/)
- [SYM 1D446](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d446/)
- [SYM 262E](https://anime-sparkle-text-22.pages.dev/symbol/sym-262e/)
- [RIGHT WING CLAN FLARE](https://raven-gothic-kaomoji-25.pages.dev/symbol/right-wing-clan-flare/)
- [SYM 2646](https://ribbon-heart-fonts-86.pages.dev/symbol/sym-2646/)
- [SYM 1D46C](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d46c/)
- [SYM 1D475](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d475/)
- [ES](https://mecha-blade-symbols-46.pages.dev/es/)
- [SYM 1D43C](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d43c/)
- [FLORAL BRANCH BOUQUET](https://minimal-star-symbols-93.pages.dev/symbol/floral-branch-bouquet/)
- [SYM 1D434](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d434/)
- [SYM 263A](https://cyber-clan-tags-23.pages.dev/symbol/sym-263a/)
- [SYM 1F49A](https://cyber-clan-tags-23.pages.dev/symbol/sym-1f49a/)
- [TIKTOK CAPTIONS](https://matrix-hacker-text-52.pages.dev/pt/tiktok-captions/)
- [SYM 2745](https://anime-sparkle-text-22.pages.dev/symbol/sym-2745/)
- [SYM 1D47F](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d47f/)
- [SYM 2734](https://mecha-blade-symbols-46.pages.dev/symbol/sym-2734/)
- [SYM 1D43E](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d43e/)
- [SYM 2688](https://vintage-angel-symbols-66.pages.dev/symbol/sym-2688/)
- [CLOUD WEATHER SYMBOL](https://matrix-glitch-text-37.pages.dev/symbol/cloud-weather-symbol/)
- [SYM 1D40C](https://kawaii-kaomoji-hub-96.pages.dev/symbol/sym-1d40c/)
- [SYM 1D4A1](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d4a1/)
- [SYM 1F47E](https://anime-sparkle-text-22.pages.dev/symbol/sym-1f47e/)
- [SYM 1D446](https://scholarly-cross-symbols-35.pages.dev/symbol/sym-1d446/)
- [SYM 1D436](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d436/)
- [SYM 1FAE4](https://vintage-angel-symbols-66.pages.dev/symbol/sym-1fae4/)
- [SYM 26AF](https://cyber-clan-tags-23.pages.dev/symbol/sym-26af/)
- [SYM 26E9](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-26e9/)
- [SYM 1F630](https://minimal-star-symbols-93.pages.dev/symbol/sym-1f630/)
- [SYM 1D448](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d448/)
- [SYM 1F974](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1f974/)
- [SYM 1D45C](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d45c/)
- [SYM 1D407](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d407/)
- [SYM 1D433](https://vintage-library-rune-80.pages.dev/symbol/sym-1d433/)
- [SYM 1D498](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d498/)
- [DAGGER CROSS SYMBOL](https://matrix-glitch-text-37.pages.dev/symbol/dagger-cross-symbol/)
- [SYM 1D427](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d427/)
- [ZODIAC CELESTIAL](https://pastel-chibi-emotes-23.pages.dev/ru/zodiac-celestial/)
- [RIGHT WHITE CORNER BRACKET](https://raven-gothic-kaomoji-25.pages.dev/symbol/right-white-corner-bracket/)
- [STAR OPERATOR](https://kawaii-kaomoji-hub-96.pages.dev/symbol/star-operator/)
- [SYM 1D43D](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d43d/)
- [SYM 1F61D](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1f61d/)
- [SYM 2660](https://sleek-line-symbols-51.pages.dev/symbol/sym-2660/)
- [SYM 1D47D](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d47d/)
- [SYM 1F92B](https://cyber-clan-tags-23.pages.dev/symbol/sym-1f92b/)
- [SYM 1D489](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d489/)
- [SYM 1F642](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1f642/)
- [SYM 1D49A](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d49a/)
- [SYM 1F493](https://raven-gothic-kaomoji-25.pages.dev/symbol/sym-1f493/)
- [SYM 1FAE3](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1fae3/)
- [SUPER SHY BLUSHING KAOMOJI](https://sleek-line-symbols-51.pages.dev/symbol/super-shy-blushing-kaomoji/)
- [SYM 1D44C](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d44c/)
- [STARS](https://matrix-hacker-text-52.pages.dev/stars/)
- [SYM 1D45C](https://vintage-library-rune-80.pages.dev/symbol/sym-1d45c/)
- [SYM 1D48F](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d48f/)
- [SYM 1D44A](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-1d44a/)
- [ROBLOX NAMES](https://cyber-clan-tags-23.pages.dev/pt/roblox-names/)
- [SYM 1F604](https://matrix-glitch-text-37.pages.dev/symbol/sym-1f604/)
- [SYM 26D5](https://coquette-aesthetic-symbols-14.pages.dev/symbol/sym-26d5/)
- [SYM 26D4](https://kawaii-kaomoji-hub-96.pages.dev/symbol/sym-26d4/)
- [HEAVY HEART EXCLAMATION](https://matrix-hacker-text-52.pages.dev/symbol/heavy-heart-exclamation/)
- [SYM 1D479](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d479/)
- [SYM 1D428](https://coquette-aesthetic-symbols-14.pages.dev/symbol/sym-1d428/)
- [STARS](https://minimal-star-symbols-93.pages.dev/ru/stars/)
- [SYM 1D46B](https://neon-glitch-symbols-84.pages.dev/symbol/sym-1d46b/)
- [SYM 26D7](https://clean-aesthetic-fonts-73.pages.dev/symbol/sym-26d7/)
- [SYM 265F](https://coquette-aesthetic-symbols-14.pages.dev/symbol/sym-265f/)
- [SYM 2683](https://raven-gothic-kaomoji-25.pages.dev/symbol/sym-2683/)
- [RIGHT WING CLAN FLARE](https://matrix-hacker-text-52.pages.dev/symbol/right-wing-clan-flare/)
- [SYM 1D470](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d470/)
- [SYM 1F47E](https://sleek-line-symbols-51.pages.dev/symbol/sym-1f47e/)
- [SYM 1D412](https://mecha-blade-symbols-46.pages.dev/symbol/sym-1d412/)
- [SYM 1D49F](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d49f/)
- [SYM 1D446](https://minimal-star-symbols-93.pages.dev/symbol/sym-1d446/)
- [SYM 1F973](https://futuristic-gaming-fonts-52.pages.dev/symbol/sym-1f973/)
- [SYM 1D476](https://matrix-hacker-text-52.pages.dev/symbol/sym-1d476/)
- [SYM 2617](https://mecha-synth-kaomoji-92.pages.dev/symbol/sym-2617/)
- [SYM 1D497](https://ribbon-heart-fonts-86.pages.dev/symbol/sym-1d497/)
