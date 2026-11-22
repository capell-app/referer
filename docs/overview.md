# Referers

<!-- prettier-ignore-start -->

Arrivals from search and social sites are counted by name, with a report in the admin and a card on your dashboard. Google, Facebook and six other well-known sites are named, and every other website is grouped as Other external. A developer must add any other site you want named.

Referers stores only counts, one for each site, each day and each label, plus a running total for each site and label. No IP address, cookie, page address or individual visit is kept. Visits from your own site or with no referring site are ignored, and percentages are shares of the arrivals counted.

Each count is a page Capell builds for an arrival, so a reload can count again and one person can be counted more than once. Crawlers, link-preview fetchers, requests with no browser identity and browser prefetch requests are not counted, nor are pages served from a saved copy such as HTML Cache or a CDN. Daily counts are deleted after 400 days. A source's all-time total is kept while it still has a daily count in that period and is deleted with its last one. That clean-up needs background jobs running.

The admin report offers preset periods up to all time, or a custom range, for each site you can see.

## Referrers in the admin

The **Referrers** page is available to actors with `View:RefererPage` and is
restricted to their existing Capell site scope. It defaults to the last 30 UTC
days and offers today, yesterday, 7-day, 30-day, 90-day, month, previous-month,
all-time, and custom windows.

The report shows source count and share. **Other external** remains in the
denominator, so the displayed shares describe all measured referral sources;
visits with no referring site are not measured and are not in the denominator.
The report states this beside the table. If a counter write fails, the report
keeps showing stored counts with a note that recent figures may be low.
Report results are cached for 60 seconds. Refresh uses a short per-site/window
lock and never polls or changes frontend delivery.

---

For how to use Referers, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
