# Contributing to Grav

:+1::tada: First, thanks for getting involved with Grav! :tada::+1:

Please take a moment to review this document in order to make the contribution
process easy and effective for everyone involved.

Following these guidelines helps to communicate that you respect the time of
the developers managing and developing this open source project. In return,
they should reciprocate that respect in addressing your issue or assessing
patches and features.

## Grav, Plugins, Themes and Skeletons

Grav is a large open source project — it's made up of over 100 repositories. When you initially consider contributing to Grav, you might be unsure about which of those 200 repositories implements the functionality you want to change or report a bug for.

[https://github.com/getgrav/grav](https://github.com/getgrav/grav) is the main Grav repository. The core of Grav is provided by this repo.

[https://github.com/getgrav/grav-plugin-admin](https://github.com/getgrav/grav-plugin-admin) is the Admin Plugin repository.

Every Plugin and Theme has its own repository. If you have a problem you think is specific to a Theme or Plugin, please report it in its corresponding repository. Please read the Plugin or Theme documentation to ensure the problem is not addressed there already.

Every Skeleton also has its own repository, so if an issue is not specific to a theme or plugin but rather to its usage in the skeleton, report it in the skeleton repository.

## Using the issue tracker

The issue tracker is the preferred channel for [bug reports](#bugs),
[features requests](#features) and [submitting pull
requests](#pull-requests), but please respect the following restrictions:

* Please **do not** use the issue tracker for support requests. Use
  [the Forum](http://getgrav.org/forum) or [the Gitter chat](https://gitter.im/getgrav/grav).


<a name="bugs"></a>
## Bug reports

A bug is a _demonstrable problem_ that is caused by the code in the repository.
Good bug reports are extremely helpful - thank you!

Guidelines for bug reports:

1. **Check you satisfy the Grav requirements** &mdash; [http://learn.getgrav.org/basics/requirements](http://learn.getgrav.org/basics/requirements)

2. **Check this happens on a clean Grav install** &mdash; check if the issue happens on any Grav site, or just with a specific configuration of plugins / theme

3. **Use the GitHub issue search** &mdash; check if the issue has already been
   reported.

4. **Check if the issue is already being solved in a PR** &mdash; check the open Pull Requests to see if one already solves the problem you're having

5. **Check if the issue has been fixed** &mdash; try to reproduce it using the
   latest `develop` branch in the repository.

6. **Isolate the problem** &mdash; create a [reduced test
   case](http://css-tricks.com/reduced-test-cases/) and provide a step-by-step instruction set on how to recreate the problem. Include code samples, page snippets or yaml configurations if needed.

7. **Check the problem on Grav 1.1** &mdash; if you're using Grav 1.0, latest stable release, please also check if you can replicate the issue on Grav 1.1 RC as many bugs are already solved in the next Grav release.

A good bug report shouldn't leave others needing to chase you up for more
information. Please try to be as detailed as possible in your report.

- What is your environment? Is it localhost, OSX, Linux, on a remote server? Same happening locally and or the server, or just locally or just on Linux?

- What steps will reproduce the issue? What browser(s) and OS experience the problem?

- What would you expect to be the outcome?

- Did the problem start happening recently (e.g. after updating to a new version of Grav) or was this always a problem?

- If the problem started happening recently, can you reproduce the problem in an older version of Grav? What's the most recent version in which the problem doesn't happen? You can download older versions of Grav from the releases page on Github.

- Can you reliably reproduce the issue? If not, provide details about how often the problem happens and under which conditions it normally happens.


All these details will help contributors to fix any potential bugs.

Important: [include Code Samples in triple backticks](https://help.github.com/articles/github-flavored-markdown/#fenced-code-blocks) so that Github will provide a proper indentation. [Add the language name after the backticks](https://help.github.com/articles/github-flavored-markdown/#syntax-highlighting) to add syntax highlighting to the code snippets.

Example:

> Short and descriptive example bug report title
>
> A summary of the issue and the browser/OS environment in which it occurs. If
> suitable, include the steps required to reproduce the bug.
>
> 1. This is the first step
> 2. This is the second step
> 3. Further steps, etc.
>>
> Any other information you want to share that is relevant to the issue being
> reported. This might include the lines of code that you have identified as
> causing the bug, and potential solutions (and your opinions on their
> merits).


<a name="features"></a>
## Feature requests

Feature requests are welcome. But take a moment to find out whether your idea
fits with the scope and aims of the project. It's up to *you* to make a strong
case to convince the project's developers of the merits of this feature. Please
provide as much detail and context as possible.


<a name="pull-requests"></a>
## Pull requests

Good pull requests - patches, improvements, new features - are a fantastic
help. They should remain focused in scope and avoid containing unrelated
commits.

**Please ask first** in [Slack](https://getgrav.org/slack) or in the Forum before embarking on any significant pull request (e.g.
implementing features, refactoring code..),
otherwise you risk spending a lot of time working on something that the
project's developers might not want to merge into the project.

Please adhere to the coding conventions used throughout the project (indentation,
accurate comments, etc.) and any other requirements.

See [Using Pull Request](https://help.github.com/articles/using-pull-requests/) and [Fork a Repo](https://help.github.com/articles/fork-a-repo/) if you're not familiar with Pull Requests.

Any pull request should be based on the `develop` branch. We will not consider pull requests made to master.

**IMPORTANT**: By submitting a patch, you agree to allow the project owner to
license your work under the same license as that used by the project.

<a name="translations"></a>
### Translations
Translations for Grav core and the Admin plugin are managed through Crowdin:

- Admin: https://crowdin.com/project/grav-admin
- Core: https://crowdin.com/project/grav-core

Please do not post translations PRs for core or admin translations on GitHub, with the exception of fixes for the english language.

All other plugins and themes translations are handled directly in their GitHub repository, and the string are usually found in the `languages.yaml` file at the root of each project.
