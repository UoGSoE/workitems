# Workitems

A Laravel app that lets a small IT team keep track of what each person is working on, what's coming up, and who could use a hand.

## What it does

Each person has their own plate of work items, split into what they're doing now ("my plate"), what's on the horizon, what's parked and what's done. A work item can have several people on it, rough effort and elapsed-time estimates, a start date and a due date, notes, and a history of every change.

Every work item has a visibility setting. It can be seen by just the people on it (plus whoever they report to), by the team, or by everyone through the "Classifieds" page, which is for work someone would like help with or that someone else might learn from. There is also a secret setting, which works like "just the people on it" but can't be changed once set.

Team leads get a "My team" page with a timeline of their people's work. From there they can draft new work, hand it to someone, and move work between people. People with leads reporting to them get an overview that rolls up everyone beneath them. People given the "manage people" permission add people, set up teams and leads, and mark leavers. Developers (admins) can do all of that and also impersonate other users to see the app as they do. People are emailed when they are added to a work item, taken off one, or have work moved to them.

Sign-in is through Keycloak SSO, with a local username and password login available when SSO is turned off.

## Prerequisites

- [Lando](https://lando.dev/) for local development (it provides PHP 8.5, MySQL, Redis, Node and Mailhog)
- A [Flux UI Pro](https://fluxui.dev/) licence, as the app uses `livewire/flux-pro`

## Getting started

```bash
git clone https://github.com/UoGSoE/workitems.git
cd workitems
cp .env.example .env
lando start
lando composer install
lando artisan key:generate
lando npm run build
lando mfs
```

`lando mfs` drops the database, runs the migrations and seeds it with `TestDataSeeder`. That creates a department of about thirty people, five of whom you can sign in as, all with the password `secret`:

- `member2x` does the work
- `line2x` leads a small team
- `lead2x` has leads reporting to them
- `manager2x` is at the top
- `admin2x` is a developer of the app (an admin)

Other Lando commands:

```bash
lando artisan ...    # run artisan commands
lando composer ...   # run composer
lando npm ...        # run npm
lando npmd           # run the Vite dev server
lando horizon        # run the queue worker
```

Emails are caught by Mailhog; `lando info` shows its address.

## Configuration

Beyond the usual Laravel settings, `.env` has:

- `SSO_ENABLED` and the `KEYCLOAK_*` values for Keycloak sign-in. With `SSO_ENABLED=false` you sign in with a username and password.
- `LLM_MODEL_NAME`, `AZURE_OPENAI_API_KEY` and `AZURE_OPENAI_ENDPOINT` (and optionally `LLM_PROVIDER`, which defaults to `azure`), for the experimental "brain dump" agent (see below).

## Setting up a real install

On a fresh database with nobody in it, create the first account and then sign in to add everyone else from the People page:

```bash
php artisan workitems:bootstrap-user {username} {email} {surname} {forenames}
```

To make an existing user an admin:

```bash
php artisan workitems:make-admin {email}
```

## Brain dump (experimental)

`app/Ai/Agents/BrainDump.php` turns a free-text description of everything on someone's plate into draft work items. It isn't wired into the UI yet, but you can try it from the command line with a text file:

```bash
php artisan app:try-brain-dump notes.txt --provider=ollama --model=gemma4:e4b
```

## Running tests

The test suite uses Pest and an in-memory SQLite database, so there's nothing to migrate first.

```bash
lando test
```

Or without Lando:

```bash
php artisan test
```

## Contributing

Follow the getting started steps above and run the tests before opening a pull request.

## Licence

MIT. See [LICENSE](LICENSE).
