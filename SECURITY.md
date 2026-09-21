# Security Policy

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Use GitHub's private vulnerability reporting:

**[Report a vulnerability](https://github.com/crivion/laranode/security/advisories/new)**

That thread is private between you and the maintainers, and it lets us credit you
and publish an advisory from the same place once a fix is out.

 If you get no acknowledgement within a few days, please open a public issue saying only
that you are waiting on a security response — no details — so we know to look.

## What helps

Whatever you have is worth sending, but these make a report much faster to act on:

- what an attacker ends up able to do, not just what looks wrong
- the affected file or endpoint, and the version or commit you looked at
- whether it needs an authenticated account, and whether that account needs to be
  an administrator
- anything you were unsure about, or did not verify

You do not need a working exploit. A clear description of the mechanism is enough,
and saying plainly what you did and did not confirm is more useful than a guess
presented as fact.

## What to expect

- an acknowledgement that a human has read it
- confirmation of whether we reproduced it
- a fix, and a release
- an advisory published after that release, so installs have a window to upgrade
  before the details are public

You will be credited by name unless you would rather not be. Ask and we will word
it however you prefer, or leave you out entirely.

## Scope

Laranode is a hosting control panel: it manages Linux users, Apache vhosts,
PHP-FPM pools, MySQL and the firewall on the machine it runs on. The things we
care most about are:

- one hosting account reaching another account's files, databases or sites
- an unprivileged panel user gaining administrator access
- anything that reaches root from the web process
- authentication or session handling flaws

Since the panel administers the host by design, "the panel can change system
configuration" is not by itself a vulnerability. An account that should not be
able to trigger it, or a path that escapes the account it belongs to, is.

## Supported versions

Fixes land on the latest release. Older versions are not patched separately, so
please upgrade before reporting an issue against an old version.

When you upgrade, use the one-liner rather than the copy of the upgrade script on
your server — the local copy comes from the release you are upgrading *from*, so
it cannot contain fixes to the upgrade process itself:

```bash
curl -sSL https://raw.githubusercontent.com/crivion/laranode/refs/heads/main/laranode-scripts/bin/laranode-upgrade.sh | bash
```
