# dev-toolkit-bundle

> Reusable Symfony local dev-environment commands and DX scaffolding.

A small `--dev` Symfony bundle that provides `dev:up` / `dev:down` / `dev:status` / `dev:logs` to
orchestrate the local environment (Docker + Symfony CLI server + assets), plus a
`dev-toolkit:install` command that scaffolds a project's quality tooling (PHP-CS-Fixer, PHPStan),
`Makefile`, a pre-commit hook, and a CI workflow.

## Requirements

- PHP 8.2+, Symfony 7.1+ (or 8.x)
- [Symfony CLI](https://symfony.com/download) and Docker (Compose v2) for the `dev:*` commands
- Unix-like environment (Linux / macOS / WSL2)

## Install (via GitHub, pre-Packagist)

Until this is published on Packagist, pull it in as a VCS repository. In the **consuming project's**
`composer.json`:

```json
{
    "repositories": [
        { "type": "vcs", "url": "https://github.com/dktaylor/dev-toolkit-bundle" }
    ]
}
```

Then:

```bash
composer require --dev "dktaylor/dev-toolkit-bundle:dev-main"
```

Register the bundle for the `dev`/`test` environments in `config/bundles.php` (this bundle ships no
Flex recipe, so this one line is manual):

```php
Dktaylor\DevToolkit\DevToolkitBundle::class => ['dev' => true, 'test' => true],
```

Scaffold the config and wire up the composer scripts:

```bash
bin/console dev-toolkit:install      # add --force to overwrite existing files
composer install                     # installs the isolated tools (extra.dev-tools)
make hooks                           # optional: enable the pre-commit hook
bin/console dev:up
```

## Commands

| Command | What it does |
|---|---|
| `dev:up` | Starts Docker services (if a compose file exists), the Symfony web server, the `.wip` proxy (if `.symfony.local.yaml` declares one), and importmap assets (if AssetMapper is used). Reports the app URL and any missing one-time setup (secret keys, browser drivers, local HTTPS CA). |
| `dev:down` (`dev:stop`) | Stops the Symfony server and Docker services (containers preserved). |
| `dev:status` | Shows `symfony server:status` + `docker compose ps`. |
| `dev:logs` | Tails the Symfony web server logs. |
| `dev-toolkit:install` | Scaffolds quality config + patches `composer.json` (see below). |

## What `dev-toolkit:install` scaffolds

- Files (skipped if present unless `--force`): `.php-cs-fixer.dist.php`, `phpstan.dist.neon`,
  `Makefile`, `.github/workflows/ci.yaml`, `.githooks/pre-commit`,
  `tools/phpstan/composer.json`, `tools/php-cs-fixer/composer.json`.
- `composer.json` (additive, never overwrites existing keys):
  - scripts: `phpstan`, `cs-fix`, `cs-check`, `security-check`, `test`, `quality`, `install-tools`
  - appends `@install-tools` to `post-install-cmd` / `post-update-cmd`
  - `extra.dev-tools`: `["tools/phpstan", "tools/php-cs-fixer"]`

The isolated tools live in their own `tools/*` composer projects (kept separate so their dependencies
don't clash with the app) and are installed automatically on `composer install` for dev — skipped on
`--no-dev` so production deploys never pull dev tooling.

## Design notes

- The `dev:*` commands run through the [Symfony CLI](https://symfony.com/doc/current/setup/symfony_server.html);
  the web-server start redirects the daemon's stdio to `/dev/null` so it can't hang callers that read
  its output (POSIX-only).
- Preflight nudges (secrets / drivers / CA) are gated on evidence the project uses each feature, so
  they stay quiet for projects that don't.

## Roadmap

Once stable — and if other developers want it — publish on Packagist and (optionally) ship a Flex
recipe so `composer require --dev` scaffolds everything automatically, replacing the manual
`bundles.php` line and the `dev-toolkit:install` step.