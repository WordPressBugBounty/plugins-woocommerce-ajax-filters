# License-aware updater — framework 3.1.1

This is client preparation. It does not enable restrictions on the API, migrate
data, change license keys, or publish a customer plugin release. Existing v1
responses and first-time observe responses retain the old updater behavior.
The base is upstream **8b781bd** (3.1.0, 2026-10-05), fetched and fast-forwarded
on 2026-10-06 with the local 3.1.1 updater changes preserved. This includes the
condition array-key stored XSS fix: numeric reindexing on save and render,
including previously stored meta, plus attribute escaping and type validation.
The previous local 3.0.5.3 overlay is superseded.

## Activation and contract

The existing `BeRocket_updater` in `includes/updater.php` handles all decisions.
`check_plugin_activation()` uses one request and the existing activation cache;
`update_check_set()` builds the permitted update directly; `plugin_api_data()`
and `update_message()` use that same decision. The read hook reuses the same
builder for already cached update rows. There is no `license-updates.php`,
`BeRocket_License_Updates`, callback replacement bridge or parallel HTTP client.
Both version declarations and `framework_version.php` are **3.1.1**.

Eligible installed plugins have `version_capability >= 10` and an explicit
40-character product key. The existing universal account-key fallback remains
lifetime/legacy. The effective product keys come from the framework's existing
registry, including its network key overrides.

The client batches product keys through existing **`POST /v1/check_plugin_key/`**.
It adds `license_protocol=berocket-license/1`, `url`, and per-key
`installed_version` / `installed_licenses`. A legacy server ignores these extra
fields. TLS verification is enabled on the shared activation request; redirects
are disabled. Unsaved keys tested through the account UI cannot change enrollment
or the shared activation cache.

The companion API package adds an **`updates` object** to each enrolled result row. Existing
`id`, `status`, `license=paid|business` keep their meaning; an expired but valid
key must still have `status=true`. Example for a product key:

```json
{
  "id": 1,
  "status": true,
  "license": "paid",
  "updates": {
    "protocol": "berocket-license/1",
    "mode": "enforce",
    "product_id": 1,
    "valid_key": true,
    "license_kind": "subscription",
    "updates_expired": true,
    "updates_until_utc": "2026-12-10T12:00:00Z",
    "latest_version": "3.8.1",
    "allowed_version": "3.8",
    "package": "https://api.berocket.com/v1/update_release/1/<key>/paid/3.8"
  }
}
```

`product_id` is a JSON integer; flags are booleans. Versions are plain version
strings, without a `(paid)` suffix. No order IDs, revisions, release UUIDs, or
new tables are needed in this client contract. `license_kind` is separate from
the existing paid/business selector. Lifetime has a null cutoff and cannot be
expired. An invalid key has no allowed version or package; `unknown` duration
is accepted in enforce only for an invalid key, never guessed into expiration.

`allowed_version=null` and/or `package=""` represents no downloadable allowed
file. The client does not manufacture a package when it is empty. A nonempty
package must exactly match the fixed API origin and route:

```text
/v1/update_release/<product-id>/<key>/<paid-or-business>/<allowed-version>
```

This distinct route is implemented by the separate API package, **not by this
framework**. Deploy and verify it before sending `mode=enforce`. It cannot
be an alias that ignores the version. For every download the server must verify
key, product, domain, current rights, selector, file version and availability.
Existing download routes must also enforce the intended server policy; client
UI controls are not an authorization boundary. The primary ZIP remains on the
API; Spaces is outside the client download path.

## WordPress behavior

| Installed / allowed / latest | Result |
| --- | --- |
| 3.8 / 3.8 / 3.8.1 | Standard update row shows 3.8.1, empty package removes the update link; renewal text is appended |
| 3.7 / 3.8 / 3.8.1 | The standard update button installs **3.8**; the message separately names 3.8.1 and renewal |
| 3.8.2 / 3.8 / 3.8.1 | No downgrade |
| Lifetime with available latest | Latest remains installable, no expiry notice |
| First legacy/observe reply | Original response unchanged |
| Previously enforced key, missing/malformed reply or network failure | Keep its last decision, clear package, show a verification message, never silently restore latest |

When the installed selector is known, paid → business can use the same version.
Unknown installed selectors do not trigger repeated same-version reinstallation.
The framework continues to show a valid expired key as a valid key: it does not
turn expiry into an activation failure or disable the plugin's functionality.

The updater covers update transients on both read and write, plugin-information
results (including cached legacy links), automatic-update eligibility and the
pre-download guard used by single/bulk updates. A download forces fresh rights
confirmation. One bulk upgrader reuses its fresh batch within that PHP request;
another upgrader or changed request identity confirms again.
A stale/generic/wrong-version package fails with a request to
check again; it is not silently changed into another ZIP. After unpacking, a
header/version check runs before the installation replacement. A different
version or missing expected main file is rejected.

Public latest comes from **`https://apicdn.berocket.com/plugins_data`**;
API rights select the permitted version and exact ZIP. API `latest_version` is
validated as part of its decision but is not used as the public announcement.
A permitted package ahead of the CDN version is held until the CDN catches up.
During a CDN outage a previously observed public version can be reused; without
one the client does not invent a public version from the API.

The existing plugin-information method reads the publisher's HTML changelog at
**`https://apicdn.berocket.com/update/<public-slug>`**. There is no automatic
request or fallback to the shop's `?preview=in_plugin_info` endpoint. The public
slug is validated before building the URL on the fixed CDN origin. Script/style
blocks and the old interactive load-more payload are removed; WordPress KSES
allows only basic text/list/link markup, with no images, frames or event handlers.
An explicit link opens the product's complete release history when clicked.

Both CDN requests use WordPress HTTP, TLS verification, no redirects and a
15-second timeout. Catalog/changelog bodies are capped at 512/256 KiB;
invalid HTTP/JSON, duplicate IDs or malformed versions reject the catalog.
The catalog retains its existing one-day success / one-hour failure cache.
Changelog cache entries identify source, public version and URL; success lasts
12 hours, failure one hour. Legacy cache objects are replaced. Public metadata
stores no personalized package or license notice; those are overlaid from current
rights on each read. If notes are unavailable, the screen shows a product link
and any license message. No shop HTTP request is needed to display that link.

It preserves the administrator's automatic-update preference; eligibility does
not opt the user into automatic updates. Manual ZIP uploads remain a separate
administrator action. Server authorization is still required.

## Cache, outages and multisite

One `br_plugin_activation` transient holds both activation rows and normalized
update decisions. A separate `refresh_at` controls reuse; the transient itself
lives seven days to retain retry history across a refresh deadline. The shortest
applicable interval in a batch wins:

| Confirmation | Automatic refresh |
| --- | --- |
| Valid legacy response | Seven days, preserving upstream activation frequency |
| Enforced active annual / lifetime | 12 hours plus 0–60 minutes of stable per-site jitter |
| Enforced expired annual | One hour plus 0–10 minutes of jitter |
| Failed transport, malformed/missing or unavailable confirmation | 15 minutes, then exponential backoff up to six hours; small bounded jitter |
| Future paid cutoff | Refresh no later than that exact UTC boundary |

The longer successful cache avoids a new API query on each admin page while
download-time confirmation still protects the ZIP. Expired rights are checked
more often so renewal is discovered; **Check again** makes it immediate.
An observed newer CDN version refreshes enrolled rights once, without shortening
legacy activation caching. A highest-observed version prevents alternating CDN
edges from causing loops. CDN changes cannot repeatedly bypass failure backoff.
These are per-site cache rules, not a cross-request distributed lock or a load
test of thousands of clients.

Request keys, installed versions, selectors, blog, URL and protocol identify the
batch, invalidating old-format activation caches. No `br_lu_*` transient is created.
Transport failures preserve confirmed activation; enrolled update packages are
held. Unknown duration cannot turn a confirmed valid key into an invalid key.
An explicit fresh invalid-key response overrides remembered activation and is
not undone by a subsequent transport outage.

The last enrolled decision is kept in `br_lu_known_<identity>`, a non-autoloaded option, so an
expired transient cannot silently remove its restrictions. Neither this option
nor the shared activation cache stores the raw key or package URL. WordPress's
normal update transient still contains its package, as it does today.

**Dashboard → Updates → Check again** clears activation and public catalog caches for an
authorized administrator. A key change uses a separate identity. Returning an
already enrolled key to observe is not an implicit grant of latest; a supported
fresh enforce decision is needed to restore availability after renewal.

The loader considers network-active plugins when this loader runs first.
An older loader may already have declared the classes before reaching 3.1.1,
notably when it misses network-only copies. That case keeps its existing updater;
3.1.1 does not install a parallel override. Before a multisite pilot, verify the
actually loaded framework version and deliver 3.1.1 with the selected plugin.
Full live multisite testing remains required.

## Delivery and rollback

This checkout is the `plugin-framework` submodule currently located in
`plugin-info/berocket`. Product changes in this package are confined to framework;
a separate local plugin-info commit pins the exact submodule reference without
changing its application code. Neither commit publishes a customer release.
After review, ship that exact framework reference with one
chosen customer plugin in a separate release. Do not update every plugin's
submodule at once.

Keep real customer groups unenrolled during client rollout. Server-side
enforcement requires separate review of immutable ZIP paths, private download
authorization and the version-specific endpoint. This client package cannot
prove those production conditions. A widespread PHP/loader issue requires a
newer framework hotfix; there is no instant remote rollback of installed code.

Tests load the actual updater class: **73 license checks, 50 CDN/cache checks,
11 recorded API-response contract checks and five loader cases** pass on PHP
7.4.33 and 8.1.8. The isolated WordPress 7.0.1 / PHP 8.1.8 integration run passes
**55 checks**, including real installation, wrong-ZIP/bulk rejection, real KSES,
CDN failures, request reuse and unchanged shop keys/orders/download permissions.

```text
php tests/license-updates.php
php tests/update-delivery.php
php tests/framework-loader.php old-first
php tests/framework-loader.php new-first
php tests/framework-loader.php network-only
php tests/framework-loader.php mixed-network
php tests/framework-loader.php old-already-loaded
```

Run the tests on each supported PHP runtime before shipping. Isolated WordPress
integration tests also exercise actual update rows, information filters, bulk
rejection and installation from a synthetic ZIP. These use a fixture HTTP
responses for CDN/API and synthetic ZIPs. The separate recorded API-response
contract check uses responses from the isolated API tests. Neither verifies a
production deployment or replaces a live multisite pilot/load test.
