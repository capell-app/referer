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
