# Security Policy

Grav is maintained by a very small team. We read every report, and we genuinely appreciate the time researchers put into them. To keep that sustainable, this page states up front what we consider a vulnerability, what we fix quietly, and what we close. **Please read the first three sections before filing.** Reports that ignore them are closed with a link back here.

## :triangular_ruler: How we decide what is a vulnerability

Grav rates security issues by **whether the issue crosses a trust boundary**, not by what a given account level is technically able to do.

A publisher running Twig in pages they author is doing exactly what publishers are entrusted to do. An admin editing config, installing a plugin, or running CLI tools is doing exactly what admins are entrusted to do. Those capabilities are not vulnerabilities, they are the role. Handing someone admin keys means trusting them with admin keys.

A vulnerability is when an actor can **escape the trust scope of their role**:

* An unauthenticated visitor reaches a privileged sink.
* A publisher's stored content executes inside an admin session.
* An account at any tier gains a capability it was not granted.
* A scoped credential (such as a scoped API key) acts outside its scope.

If your report does not describe one of those, it is very likely not a vulnerability under this policy, however severe the CVSS calculator makes it look.

## :no_entry: What we do not publish an advisory for

Every published advisory is a signal to operators that they need to act today. We spend that signal carefully. These categories are **closed without an advisory**. Many are still real code quality issues and we may still fix them, but they do not get a GHSA.

**Not a vulnerability. Closed, usually without a code change:**

* **Anything an admin or super-admin can do within their granted capabilities.** Editing config, running CLI or scheduler tasks, installing plugins and themes, writing Twig into templates, using the file manager, reaching the filesystem through admin-only tooling.
* **Publisher-authored content executing in the publisher's own scope.** Twig or markdown that a page editor writes and that runs as that page editor is the feature.
* **`Security::detectXss()` bypasses.** `detectXss()` is a heuristic denylist used to flag suspicious content for humans. It is explicitly **not** a security boundary, it never was complete, and it cannot be. Grav's actual XSS defense is escaping at output. A new string that slips past the pattern list is not a vulnerability and we will not issue advisories for it. If you have found content that renders unescaped at output, that *is* in scope, so report that instead and show the rendered sink.
* **Account or email enumeration** through registration, password reset, or login messaging. This is documented, intentional behavior in the default configuration.
* **Self-XSS**, or any issue requiring the victim to paste a payload into their own browser or admin form.
* **Reports with no working proof of concept.** Static analyzer output, LLM-generated code readings, and "this pattern looks unsafe" reports without a demonstrated exploit path are closed. We do not have the capacity to build the PoC for you.
* **Dependency CVEs with no reachable path in Grav.** Show the call path from Grav code to the vulnerable function.
* **Missing hardening or defense-in-depth suggestions** with no demonstrated exploit: absent security headers, missing rate limits, permissive defaults that are documented as such.
* **Issues that only occur under a non-default configuration** that our own docs already flag as dangerous.
* **Anything against an unsupported version.** See [Supported Versions](#supported-versions). 1.8 and anything below 1.7 are out of scope entirely.

**Real, but fixed quietly. We take the patch, you get credited in the CHANGELOG, no advisory:**

* Non-constant-time comparisons without a demonstrated practical timing oracle.
* Scoping and permission tightening where the actor could already reach equivalent data through their granted role.
* Path traversal reachable only by an account that already has admin file-manager rights.
* Authenticated resource-consumption issues with no amplification.
* Input validation improvements at boundaries that are already behind an authorization check.

This bucket is not a demotion. It is how most good security work lands in most projects. Your patch ships, your name is in the release notes, operators do not get paged for something they cannot act on.

**Published as an advisory:** issues that cross a trust boundary as described above, where operators running a supported version need to know and need to upgrade.

## :pushpin: Severity

Use these guidelines when selecting a **Severity** in a GitHub Security Advisory. The CVSS score the advisory form computes does **not** override them. CVSS rewards impact regardless of trust scope, which systematically inflates ratings for behavior that is in scope for the role that triggers it. Reports filed at High or Critical that do not meet the bar below are re-classified or closed.

| Severity     | When to use                                                                                                                                                                                                                                                                                          |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CRITICAL** | An **unauthenticated** attacker can achieve RCE, exfiltrate site data, or gain admin-equivalent control. No Grav account required.                                                                                                                                                                   |
| **HIGH**     | A **cross-trust-boundary** issue. A lower-privilege actor (or an anonymous visitor against a stored payload) ends up running code, exfiltrating data, or taking actions inside a higher-privilege session. Examples: stored XSS that fires in a super-admin session, publisher-to-admin privilege escalation, CSRF that elevates privileges. |
| **MODERATE** | An authenticated user can do something **outside the documented scope of their role**, but the impact stays within their own session or affects only same-tier users.                                                                                                                                |
| **LOW**      | An admin or super-admin can do something nefarious **within their already-granted capabilities**. Per the section above, these are closed as by design or fixed quietly. They do not receive advisories.                                                                                             |

## :pencil: Reporting a vulnerability

Submit an **advisory via GitHub Security**: https://github.com/getgrav/grav/security/advisories

For anything you would rather not put in the GitHub form, email **security@getgrav.org** first.

A report we can act on **must** include all of the following. Reports missing them are closed, and we will ask you to refile once you have them:

1. **The exact version tested**, with a commit hash. A large share of what we receive is already fixed on `develop`. Please check before filing.
2. **A minimal, self-contained proof of concept** that we can run to confirm both the issue and the fix. Not a description of one.
3. **The threat model**, stated explicitly: what account or access level the attacker starts with, what they end up with, and **which trust boundary is crossed**. If you cannot name the boundary, re-read the first section.
4. **The component that owns the bug.** Grav is a core plus many plugins in separate repositories. Advisories filed on `getgrav/grav` for bugs that live in a plugin cost us real time to re-home. If the code you are reporting lives under `user/plugins/<name>/`, the bug belongs to that plugin's repository.

Optional and very welcome: your suggested fix or mitigation.

> NOTE: Please do not use third-party security issue reporting services. We keep everything in the GitHub ecosystem so it stays manageable.

## :calendar: How we process reports

We triage in **one batch per week**, and we publish advisories on a **single release day each month**. This is deliberate. Batching is what makes it possible for a small team to give each report real attention instead of a rushed reply.

What that means for you:

* Expect an initial response within **one week**, not the same day.
* A fix may land on `develop` well before the advisory publishes, if one publishes at all.
* Please do not ping for status inside the first week, and please do not refile the same issue on multiple repositories to get attention faster. Both slow the queue down for everyone.

We do not request CVEs, and we do not offer bug bounties. As a small open source project we do not have the resources for either. Reporters are credited on the published advisory, or in the CHANGELOG for issues we fix quietly.

## Supported Versions

Active development of Grav happens on the **develop** branch. All security work lands on 2.0 first, and 2.0 is the recommended target for any new install.

| Version | Status                           | Notes                                                                                                                                  |
| ------- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 2.0.x   | :white_check_mark: Active        | Current development line, shipping as stable. All security fixes land here.                                                                |
| 1.7.x   | :warning: Limited maintenance    | Only critical issues exploitable **without** admin or publisher access get backported. See [What gets backported to 1.7](#what-gets-backported-to-17) below. |
| 1.8.x   | :x: Not supported                | 1.8 was only ever a beta line. It has been replaced wholesale by 2.0. No further releases or backports.                             |
| < 1.7   | :x: Not supported                |                                                                                                                                        |

### What gets backported to 1.7

Grav 1.7 is in stable maintenance. We backport security fixes to 1.7 only when **all** of the following apply:

* The issue can be exploited **without** any authenticated Grav account, or with an account that does **not** have publisher-level (page edit) or admin permissions.
* The issue has real-world impact: data exposure, privilege escalation, RCE, persistent XSS reachable by anonymous visitors, and similar.
* A working PoC is available so we can confirm both the vulnerability and the fix.

Anything that requires a publisher or admin account to exploit is **out of scope for 1.7 backports**, even when the rendered effect of the exploit reaches anonymous visitors. The fix lands on **2.0** instead, and operators on 1.7 should plan their upgrade to 2.0.

### About 1.8

The 1.8 line never reached a stable release. It was an interim beta and has been replaced wholesale by **Grav 2.0**. We do not ship security fixes to 1.8, and you should not run a 1.8 build in production. Move to 2.0 instead.

## :warning: Manual installs

Older releases that are no longer reachable through the in-app updater can still be installed using the [`direct-install` command](https://learn.getgrav.org/17/admin-panel/tools), or by downloading the package from our [Releases directory](https://github.com/getgrav/grav/releases) if your server does not meet the minimum PHP requirements of the latest stable.
