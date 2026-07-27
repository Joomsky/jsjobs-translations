# JS Jobs translations

Language files for the JS Jobs Joomla component, delivered to sites through a
signed manifest.

## Layout

```
manifest.json          generated - index of languages + SHA-256 of each file
manifest.json.sig      generated - base64 RSA-SHA256 signature of manifest.json
fr-FR/fr-FR.com_jsjobs.ini
de-DE/de-DE.com_jsjobs.ini
...
```

Folder names must be a valid Joomla language code (`xx-XX`), and the file inside
must be named `<code>.com_jsjobs.ini`. Anything else is skipped by the builder.

## Publishing a change

```bash
php build-manifest.php . /secure/path/jsjobs_update_private.pem 1.4.0
git add -A && git commit -m "Update translations" && git push
```

The third argument is the minimum JS Jobs version a translation applies to.

`build-manifest.php` refuses to publish any `.ini` that does not parse — a
single malformed key would blank out that entire language on every site that
downloads it.

## Signing key

Signatures are verified by sites against `jsjobs_update_pubkey.pem`, which ships
inside the component. Sign with the **matching private key** — the same one used
for JS Jobs updates.

**The private key must never be committed here or placed on a web server.**
`.gitignore` excludes `*.pem` and `*.key` as a safety net, but keep the key
outside this folder entirely.

## How sites consume it

Served free over jsDelivr:

```
https://cdn.jsdelivr.net/gh/<org>/jsjobs-translations@main/manifest.json
```

A site fetches the manifest, verifies the signature, then downloads only the
language the admin asks for and checks it against the SHA-256 in the manifest.
Sites cache the manifest for 12 hours.

> jsDelivr caches aggressively. For an urgent fix, publish under a new git tag
> and point `translations_manifest_url` at that tag, or purge via
> `https://purge.jsdelivr.net/gh/<org>/jsjobs-translations@main/manifest.json`.

## Contributing a language

1. Copy `en-GB.com_jsjobs.ini` from the component as your starting point.
2. Translate the **values**, never the keys.
3. Keep `%s` placeholders — a message that loses one is rejected on install.
4. Do not use double quotes inside values.
5. Use job-board vocabulary. In French, for example, *Resume* is **CV**, never
   *reprise*; *Job* is **offre d'emploi**; *Cover Letter* is **lettre de
   motivation**.
