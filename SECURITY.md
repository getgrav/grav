# Security Policy

## Supported Versions

Active development of Grav happens on the **2.0** branch, which is currently shipping as **release candidates**. 2.0 RC replaces the older **1.8 beta** line outright. All security work lands on 2.0 first, and 2.0 is the recommended target for any new install.

| Version | Status                           | Notes                                                                                                                                  |
| ------- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 2.0.x   | :white_check_mark: Active        | Current development line, shipping as RC. All security fixes land here.                                                                |
| 1.7.x   | :warning: Limited maintenance    | Only critical issues exploitable **without** admin or publisher access get backported. See [What gets backported to 1.7](#what-gets-backported-to-17) below. |
| 1.8.x   | :x: Not supported                | 1.8 was only ever a beta line. It has been replaced wholesale by 2.0 RC. No further releases or backports.                             |
| < 1.7   | :x: Not supported                |                                                                                                                                        |

### What gets backported to 1.7

Grav 1.7 is in stable maintenance. We backport security fixes to 1.7 only when **all** of the following apply:

* The issue can be exploited **without** any authenticated Grav account, or with an account that does **not** have publisher-level (page edit) or admin permissions.
* The issue has real-world impact: data exposure, privilege escalation, RCE, persistent XSS reachable by anonymous visitors, and similar.
* A working PoC is available so we can confirm both the vulnerability and the fix.

Anything that requires a publisher or admin account to exploit is **out of scope for 1.7 backports**, even when the rendered effect of the exploit reaches anonymous visitors. The fix will land on **2.0** instead, and operators on 1.7 should plan their upgrade to 2.0.

### About 1.8

The 1.8 line never reached a stable release. It was an interim beta and has been replaced wholesale by **Grav 2.0 RC**. We do not ship security fixes to 1.8, and you should not run a 1.8 build in production. Move to 2.0 RC instead.

## :pushpin: Note on Security Severity

The Grav project rates security issues by **whether the issue crosses a trust boundary**, not by which account level can trigger it. A publisher running Twig in pages they author is doing exactly what publishers are entrusted to do. An admin running CLI tools or editing config is doing exactly what admins are entrusted to do. Those capabilities are not vulnerabilities, they are the role.

A vulnerability is when an actor can **escape the trust scope of their role**: a publisher whose stored content compromises an admin session, an unauthenticated visitor who reaches a privileged sink, an account at any tier that gains capabilities it was not granted.

Please use the following guidelines when selecting a **Severity** in a GitHub Security Advisory. Reports submitted at **High** or **Critical** that do not meet these guidelines will be re-classified or closed.

| Severity     | When to use                                                                                                                                                                                                                                                                                          |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **CRITICAL** | An **unauthenticated** attacker can achieve RCE, exfiltrate site data, or gain admin-equivalent control. No Grav account required.                                                                                                                                                                   |
| **HIGH**     | A **cross-trust-boundary** issue. A lower-privilege actor (or anonymous visitor against a stored payload) ends up running code, exfiltrating data, or taking actions inside a higher-privilege session. Examples: stored XSS that fires in a super-admin session, publisher-to-admin privilege escalation, CSRF that elevates privileges. |
| **MODERATE** | An authenticated user can do something **outside the documented scope of their role**, but the impact stays within their own session or affects only same-tier users.                                                                                                                                |
| **LOW**      | An admin or super-admin can do something nefarious **within their already-granted capabilities**. In practice these are usually **wontfix / by design**, because giving someone admin keys means trusting them with admin keys.                                                                      |

The CVSS score that the GitHub advisory form computes does **not** override these guidelines. CVSS rewards impact regardless of trust scope, which inflates ratings for issues that are actually in-scope behaviour for the role that triggers them. We will downgrade or close on the criteria above.

## :warning: Manual installs

Older releases that are no longer reachable through the in-app updater can still be installed using the [`direct-install` command](https://learn.getgrav.org/17/admin-panel/tools), or by downloading the package from our [Releases directory](https://github.com/getgrav/grav/releases) if your server does not meet the minimum PHP requirements of the latest stable.

## :pencil: Reporting a Vulnerability

Please contact **security@getgrav.org** with a detailed explanation of the security issue. If it appears to be a legitimate issue, please submit an **advisory via GitHub Security**: https://github.com/getgrav/grav/security/advisories

A good report includes:

* The exact Grav version tested (commit hash if possible).
* A minimal, self-contained PoC that we can run to confirm both the issue and the fix.
* The threat model: what level of account or access the attacker needs, and what they gain. Be explicit about whether the issue stays inside the actor's trust scope or crosses a trust boundary.
* Your suggested fix or mitigation, if you have one.

> NOTE: Please do not use 3rd party security issue reporting services. We like to keep everything in the GitHub ecosystem for easier manageability.

## :bug: Bug Bounties

We greatly appreciate your efforts to improve Grav, but as a small open source project we **do not have the resources to offer bounties** for security issues found. Reporters are credited on the published advisory.
