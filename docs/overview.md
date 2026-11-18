# Referers

<!-- prettier-ignore-start -->

Arrivals from search and social sites are counted by name, with a report in the admin and a card on your dashboard. Google, Facebook and six other well-known sites are named, and every other website that sends people to yours is grouped as Other external. A developer must add any other website you want named.

Referers stores only counts, one for each site, each day and each label, plus a running total for each site and label. No IP address, cookie, page address or record of an individual visit is kept. Visits from your own site or with no referring site are ignored, and percentages are shares of the arrivals counted.

Each count is a page Capell builds for an arrival, so a reload can count again and one person can be counted more than once. Crawlers, link-preview fetchers, requests with no browser identity and browser prefetch or prerender requests are not counted. Pages served from a saved copy, such as one held by HTML Cache or a CDN, are not counted. Daily counts are deleted after 400 days and all-time totals are kept. That clean-up needs background jobs, which your host or developer must have running.

The admin report offers preset periods from today to all time, or a custom range, for each site you can see.

## Referrers in the admin

The **Referrers** page is available to actors with `View:RefererPage` and is
restricted to their existing Capell site scope. It defaults to the last 30 UTC
days and offers today, yesterday, 7-day, 30-day, 90-day, month, previous-month,
all-time, and custom windows.

The report shows source count and share. **Other external** remains in the
denominator, so the displayed shares describe all measured referral sources.
Report results are cached for 60 seconds. Refresh uses a short per-site/window
lock and never polls or changes frontend delivery.

---

For how to use Referers, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
