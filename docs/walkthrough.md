# Offline walkthrough

Run [setup](setup.md) and the disposable [database checks](database.md), then
start the preview profile. The preview is labeled synthetic; costs and receipts
are offline fixtures, not provider execution or accuracy evidence.

1. Sign in as the owner. The invitation list shows lifecycle, remaining lifetime
   questions, known estimated USD cost and UTC expiry with pagination.
2. Choose **Create invitation**. Enter a recipient label, totals/rate/threshold
   and an expiry interval. The defaults match pinned QueryLens.
3. Copy the code/link from the creation response. Do not screenshot or publish
   it. Continue to details; the secret is never offered again. A replay gives a
   receipt, not another invitation or a recovered secret.
4. Run `python3 tools/check-contract.py` for actual PHP-issued Python login and
   visitor-session verification against the isolated ledger. Example preview
   URLs are offline destinations; no hosted login or paid request is claimed.
5. Open an invitation's usage details. Known cost and unknown count are separate;
   pending/unknown/legacy/not-admitted receipts are never displayed as free usage.
   Measured model/token fields are allowlisted; questions/results remain absent.
6. Edit the lifetime total below past usage: remaining questions become zero,
   while historical usage is preserved. Blank expiry preserves the current date;
   a new interval starts from database current time. Stale edit forms are rejected.
7. Revoke an invitation permanently. Its metadata/history remain, further edits
   are rejected, and Python rejects both its code and linked sessions. Already
   admitted work may finish and record its cost once.
8. Sign out. Remove the preview stack with the documented command when finished.

Chrome owner login, list and form were inspected locally with synthetic data.
Capture portfolio screenshots only from the labeled preview list/detail screens;
exclude credentials, creation responses, environment values and browser password
UI. Publishing screenshots/code and hosted acceptance remain owner release steps.
