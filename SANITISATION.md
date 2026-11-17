# queue-rta sanitisation record

This is modern editorial material for the 2026 realTasia archive.

## Publication model

The public repository preserves the historical commit sequence, messages, dates and authors, with detected historical credential values redacted from file contents. The untouched private `pipewrk/queue-rta` repository remains the archival master. The snapshot changes are described separately below.

## Historical credential redactions

Detected AWS access IDs and associated secret values, credential and token values in configuration, source, and examples were replaced with explicit redaction markers throughout the public history. The surrounding files, commit sequence and commit metadata remain. This includes values that GitHub push protection identified; fixture keys are marked as such here without treating them as evidence of active credentials.

The following commits introduced or changed detected values. The first identifier refers to the private source; the link opens the rewritten public commit. Later commit IDs may also differ because their ancestry changed.

| Original commit | Public commit | Original message |
|---|---|---|
| `410ca03` | [`abfb39c`](https://github.com/realtasia/queue-rta/commit/abfb39cc4bfa89ef408dabffb004f98a817e529e) | First commit |
| `4c9a875` | [`55bc79c`](https://github.com/realtasia/queue-rta/commit/55bc79c56a43ecd833e9bb240a12b909ca261f82) | Converted to and created IronWorker functionality |

Affected paths:

- `application/classes/amazonsdk.php`
- `application/classes/config.ini`
- `iron.json`

## Snapshot changes

| Category | Treatment | Reason | Behavioural effect |
|---|---|---|---|
| Worker, email and ticker logs plus captured payloads | Omitted | Operational records may contain private messages, recipients and system data | Historical diagnostic data is unavailable |
| IronWorker configuration | Omitted and replaced by a clearly synthetic editorial example | The original contained private worker and datastore configuration | Worker deployment requires new configuration |
| MongoDB, worker and AWS operating values | Exact literals replaced with type-compatible synthetic values | Credentials and private infrastructure must not be public | Integrations require new configuration |
| Payment instructions and support contact examples | Bank-account and WhatsApp values replaced with explicit archive placeholders | Historical financial and contact details are not part of the public artefact | Email-template structure remains; payment instructions are non-operational |

`COMMIT_COMMENTARY.md` is contemporary analysis. Its links point to the corresponding public historical commits.

## Rights

No open-source licence is granted for this repository as a whole. Historical third-party notices continue to govern the files they cover. All other rights are reserved.
