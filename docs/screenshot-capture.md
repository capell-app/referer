# Referral report capture

The required `referer-admin-report` capture renders the real Filament report at
`/admin/referer`. Its marketplace asset is
`docs/screenshots/referer-admin-report.png`; declaration does not mean the image
has been captured. Keep the package disabled until the manager captures and
reviews this image and the package review checks pass.

Use a disposable installed App with at least one site and an administrator with
`View:RefererPage` and access to that site. Set `CAPELL_SCREENSHOT_FIXTURE=record-state`
and `CAPELL_SCREENSHOT_APP_PATH` to that App's actual base path. The runner invokes
`capell:referer:screenshot-fixture --force` through the manifest fixture contract.
The command refuses production, missing fixture flags, a different App path and
missing sites. It does not grant permissions.

The fixture seeds current UTC daily and lifetime aggregate counters for Google,
Bing, LinkedIn and Other external in each existing disposable site. Repeating it
on the same day leaves four rows per site and 224 measured requests. It clears
only the seeded sites' default report cache so an earlier empty report does not
hide the new rows. Use a fresh disposable dataset for each capture session.
The capture waits for the actual Google row with 128 requests; an empty or
unavailable report cannot satisfy that selector. The real-page regression test
also checks Google's 57.1% share and site selection.

The cache-hit collection limitation documented in `screenshots.json` is separate
from this admin report capture. This fixture does not claim to resolve it.
