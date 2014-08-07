About the `themes` and `plugins` folders
========================================

Grav packages come with the default [**antimatter**](antimatter) theme, [**error plugin**](error) and [**problems plugin**](problems).

Because they are separate projects (and git subtree didn't really work great for us), we are not providing them in the repository. If you plan to clone Grav and use it right away, make sure you grab a copy of the required dependencies.

Dependencies
============

Grav can work perfectly as is but it's not of much use if you don't have at least a theme. It's also a good idea to keep the `error` and `problems` plugins installed.

So if you decide to use a git copy of Grav, rather than a package, once you cloned it you also want to get a copy of [**antimatter theme**](antimatter), [**error plugin**](error) and [**problems plugin**](problems).

These are what you should do via command line in order to get up and running.

```
git clone http://github.com/getgrav/grav
cd grav/themes
git clone http://github.com/getgrav/grav-theme-antimatter antimatter
cd ../grav/plugins
git clone http://github.com/getgrav/grav-plugin-error error
git clone http://github.com/getgrav/grav-plugin-problems problems
```


[antimatter]: http://github.com/getgrav/grav-theme-antimatter
[error]: http://github.com/getgrav/grav-plugin-error
[problems]: http://github.com/getgrav/grav-plugin-problems
