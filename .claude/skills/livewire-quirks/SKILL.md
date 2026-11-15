---
name: livewire-quirks
description: >
  Hard-won Livewire and Flux failure modes, each one hit and verified while
  building this app: modals that open with keyboard focus left on the page
  body, a command palette whose highlighted entry is decided by a resting
  mouse pointer, lists that will not reset when swapped, and markdown emails
  that turn typed text into live links. Load it alongside the
  livewire-development and fluxui-development skills whenever you add or
  change a flyout, modal, command palette, select or mailable, and before you
  call a keyboard flow "working" on the strength of feature tests alone.
version: "0.1.0"
---

# Livewire and Flux quirks

Field notes to sit beside the vendor Livewire and Flux skills, which are
generated and may be overwritten. Every rake below was hit in a real session
and is stamped with the versions and date it was verified on. Each says what
was checked and what is only a guess. If you are on much newer versions,
re-verify before relying on a rake, and delete any that upstream has fixed.

Unless a rake says otherwise: Livewire 4.4.8, Flux and Flux Pro 2.21.0,
Laravel 13, headless Chrome driven by playwright-cli, November 2026.

Feature tests and an axe scan catch none of the focus and pointer rakes
below. They only show up when you drive the page in a browser, by keyboard.

## Focus

### A modal can open with focus still on the page body

Symptom: `document.querySelector('dialog[open]')` exists but
`document.activeElement` is `BODY`. A sighted keyboard user barely notices
because the next Tab goes into the dialog. A screen reader gets no
announcement that anything opened.

Seen in two cases (2026-11-13 and 2026-11-14):

- a flyout shown from the server with `Flux::modal('name')->show()` in a
  Livewire action
- a `variant="bare"` modal holding a `flux:command`, opened by a
  `flux:modal.trigger` with a `shortcut`

Fix: put `autofocus` on the first field. `<flux:input autofocus />`,
`<flux:textarea autofocus />` and `<flux:command.input autofocus />` all
worked.

Not checked: a plain (not bare) modal opened by a trigger with ordinary form
fields inside. Guess: the row menu closing and the dialog opening race, and
the menu's focus return wins.

Probe to reuse, after opening the modal:
`(d => d.contains(document.activeElement))(document.querySelector('dialog[open]'))`

### `autofocus` on a `flux:select` lands on an element that cannot take focus

`autofocus` on `<flux:select variant="listbox">` or `variant="combobox"` is
rendered on the `<ui-select>` wrapper, so nothing is focused. Use the
combobox variant with its `input` slot (2026-11-13):

```blade
<flux:select wire:model="personId" variant="combobox" label="Who?">
    <x-slot name="input">
        <flux:select.input placeholder="Type a name" autofocus />
    </x-slot>
    ...options...
</flux:select>
```

The slot is in
`vendor/livewire/flux-pro/stubs/resources/views/flux/select/variants/combobox.blade.php`.

### `autofocus` does nothing on an element Livewire swaps in

`autofocus` is honoured when a dialog opens, not when Livewire morphs a new
input into a dialog that is already open. Add
`x-init="$nextTick(() => $el.focus())"` to the input as well. On first page
load the dialog is closed, so the `x-init` focus does nothing and does not
steal focus from the page (checked 2026-11-14).

### `flux:main` is not a main landmark

`<flux:main>` renders `<div data-flux-main>`, so a page on the Flux sidebar
layout has no main landmark (axe: `landmark-one-main`, `region`). Attributes
pass through, so `<flux:main role="main">` fixes it. This app's layout has it.
Checked 2026-11-13 with
`document.querySelectorAll('[role=main]').length === 1`.

### Focusing a `flux:radio` selects it

Symptom: a `flux:radio.group` with nothing chosen, in a flyout. Tab onto the
group, or put `autofocus` on the first radio, and the first radio becomes
checked (`aria-checked="true"`) without a click or a key press on it. Enter
then submits the form with that choice. A native radio group only takes
focus; this one also selects.

Seen 2026-11-15 on the "Who owns it?" flyout of the team page, by keyboard in
headless Chrome. Where "nothing chosen" has to mean something, use a native
`<flux:select placeholder="...">` instead: it opens focused (`autofocus`
works on the default variant) with an empty value, and the server's
`required` rule refuses an empty submit. Not checked: whether a mouse click
elsewhere in the group has the same effect, or other Flux versions.

### A select bound to an unset array key shows its first name as if chosen

Symptom: `<flux:select wire:model="moveAllTo.{{ $id }}" placeholder="Choose someone">`
with `public $moveAllTo = [];`. The browser shows the first real option
("Me"), not the placeholder, while the server still holds nothing. Submitting
gives a `required` error for a field that looks filled in. With the property
set to `''` for that key the placeholder shows.

Seen 2026-11-15 in the team page's "Move it all" drawer, in headless Chrome.
Fix: fill every key with `''` in `mount()` and again after the action
(`MyTeam::nobodyIsChosenToMoveEverythingTo()`). In a test, `assertSet(...,
'')` passes on an unset key because it compares loosely; use
`assertSetStrict`.

## Command palette (`flux:command`)

The docs show one palette in a bare modal and say nothing about a palette
that leads to a second list. This app does that with one palette whose list
changes. The working version is at the bottom of
`resources/views/livewire/my-plate.blade.php`. What it needed (all checked
2026-11-14):

### Swapping the list: change the `wire:key` on `flux:command`

After picking an entry, the typed filter text has to go, or it filters the
new list too. Give `flux:command` a `wire:key` that changes with the state
(`wire:key="palette-{{ $picked?->id ?? 'all' }}"`). Livewire then replaces
the whole palette: empty search box, fresh filter, first entry highlighted.
Pair it with the `x-init` focus above, because the new search box is not
focused on its own.

### A resting mouse pointer decides what Enter does

Symptom: open the palette by hotkey with the mouse pointer sitting where the
list appears. The entry under the pointer is highlighted, not the first one,
and Enter runs it. After a list swap the same thing happens, which is how a
stray Enter could park or finish the wrong work item.

Cause, read from `vendor/livewire/flux-pro/dist/flux.min.js`: each option
highlights itself on `mouseenter`, and the browser fires `mouseenter` on
whatever appears under a still pointer.

Fix: ignore `mouseenter` until the mouse really moves. Three details matter:

- Stop the event in the capture phase on an ancestor
  (`x-on:mouseenter.capture="mouseHasMoved || $event.stopPropagation()"`).
  `mouseenter` does not bubble, but capture listeners on ancestors still run.
- Reset the flag every time the palette opens
  (`x-on:modal-show.document="mouseHasMoved = false"`) and when the list
  swaps (`x-init` on the keyed `flux:command`). Resetting only on close is
  not enough: movement from before the palette opened still counts.
- On a move, the browser sends `mouseenter` before `mousemove`. So at the
  first real `mousemove`, set the flag and re-send `mouseenter` to the entry
  under the pointer, or that entry never highlights:
  `event.target.closest('[data-flux-command-item]')?.dispatchEvent(new MouseEvent('mouseenter'))`.
  Check `event.movementX || event.movementY` so a move event with no
  movement does not count.

When probing this with playwright-cli, park the pointer over the list with
`mousemove` before pressing the hotkey, or you will not reproduce it. When
probing anything else in the palette, move the pointer well away first, or
this effect will confuse the result.

### One hotkey per trigger, and `cmd` is the Mac key only

`<flux:modal.trigger shortcut="cmd.k">` binds one shortcut (see
`vendor/livewire/flux/stubs/resources/views/flux/modal/trigger.blade.php`).
For Ctrl+K as well, add this to the same trigger:

```blade
x-on:keydown.ctrl.k.document="$event.preventDefault(); $dispatch('modal-show', { name: 'palette' })"
```

Checked: both keys open the palette in headless Chrome on macOS. Not checked:
a real Windows or Linux browser.

### Closing the palette and focusing something else works

A palette entry with
`x-on:click="$flux.modal('palette').close(); $nextTick(() => document.getElementById('whats-on').focus())"`
closes the palette and leaves focus in that input. The worry that the closing
dialog would pull focus back did not happen.

### Not checked on a real phone

On a touch screen the action list's search box is given `inputmode="none"`
so the on-screen keyboard should stay away. With touch emulation the
attribute is set and tapping works. Whether a real phone keeps its keyboard
down has not been seen.

## Modals from the server

- `Flux::modal('name')->show()` dispatches a browser event `modal-show` with
  the modal's name (`vendor/livewire/flux/src/Concerns/InteractsWithComponents.php`).
  In a Livewire test: `->assertDispatched('modal-show', name: 'palette')`.
  In Alpine: `x-on:modal-show.document="..."`.
- `<flux:modal @close="unpick">` becomes `wire:close="unpick"` on the dialog,
  which is how the palette goes back to its first list when it closes
  (`vendor/livewire/flux/stubs/resources/views/flux/modal/index.blade.php`).
- A flyout opened from the server needs the id of the record it is about.
  Mark that property `#[Locked]`, set it only in the action that opens the
  flyout, and fetch the record again through the signed-in person in every
  action. Test the lock with
  `expect(fn () => $page->set('editingId', $id))->toThrow(CannotUpdateLockedPropertyException::class)`.
  A review of this app on 2026-11-13 found that an unlocked id would let the
  browser point the "Add someone" flyout at a work item the person was not
  on and read who was on it. That was found by reading the code, not
  reproduced in a browser.

## Not Livewire, but met while building Livewire pages

### Markdown emails run markdown over escaped variables

In a `--markdown` mailable, `{{ $title }}` is HTML-escaped and then the whole
template goes through the markdown converter. A title or message containing
`[text](https://...)` or `![](https://...)` comes out as a live link or a
remote image in an email the app sends on someone's behalf. Raw HTML typed by
a person stays escaped; markdown syntax does not. Checked 2026-11-14 by
rendering a mailable.

Fix: put anything a person typed inside an HTML paragraph, which markdown
leaves alone, and keep blank lines out of it. A blank line inside the typed
text also ends the paragraph, so `squish()` single-line values such as a title:

```blade
<p>{{ $who->full_name }} {{ $whatHappened }} <a href="{{ route('home') }}">{{ str($workItem->title)->squish() }}</a>.</p>

@if ($theirMessage)
<p>Message: {!! nl2br(e($theirMessage)) !!}</p>
@endif
```

The mail theme's styles are still applied to those tags (checked: the
rendered `<p>` and `<a>` carry inline styles).
`tests/Feature/TakingPeopleOffTest.php` has a test for it.

`$message` is taken in mail views: the mailer sets it to the message object
(`$data['message'] = ...` in
`vendor/laravel/framework/src/Illuminate/Mail/Mailer.php`). That is why the
mailable's property is called `$theirMessage`.

### `assertSeeInHtml` on a mailable fails at random on names with apostrophes

Seen once, 2026-11-14: "Did not see expected text [Ashley O&#039;Conner ...]".
The rendered email has a plain apostrophe, and `assertSeeInHtml` escapes the
text it looks for. Faker only sometimes produces such a name, so the test
fails now and then. Pin the name in the test and pass `escape: false`.

### `Model::find(null)` still runs a query

`WorkItem::find($this->editingId)` with no id set runs
`select * from work_items where id is null` on every render. If the records
are already loaded, find among them: `$workItems->find($this->editingId)`
returns null without a query, and it also keeps the lookup to records the
person is allowed to see.

### Relations on a pivot model cannot be eager loaded with `with()`

`$workItem->pivot->addedBy` in a loop is one query per row. This loads them
in one (checked 2026-11-14 with `expectsDatabaseQueryCount`: 8 and 12 queries
became 4):

```php
Collection::make($workItems->pluck('pivot'))->load('addedBy');
```

`Collection` there is `Illuminate\Database\Eloquent\Collection`. See
`User::workItemsWithEveryoneInvolved()`.

### The local queue needs a worker before history or email appear

In this project's lando setup the queue is redis and nothing runs it until
`lando horizon` is started. A queued listener or mailable that "does not
work" in the browser is usually waiting there. Tests use the sync queue and
will not show it.
