# Referers

<!-- prettier-ignore-start -->

## Referrers in the admin

The **Referrers** page is available to actors with `View:RefererPage` and is
restricted to their existing Capell site scope. It defaults to the last 30 UTC
days and offers today, yesterday, 7-day, 30-day, 90-day, month, previous-month,
all-time, and custom windows.

The report shows source count and share. **Other external** remains in the
denominator, so the displayed shares describe all measured referral sources.
Report results are cached for 60 seconds. Refresh uses a short per-site/window
lock and never polls or changes frontend delivery.

HTML cache hits are not measured: measurement needs an extension hook
before the cache short-circuits the request. This integration remains deferred.

---

For how to use Referers, see the [admin guide](admin-guide.md).
For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
