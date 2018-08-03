# Using Referers

1. Open **Referrers** in the admin Marketing and Monitoring area.
2. Select a site from the sites available to your current actor.
3. Choose a reporting window. Use **Custom dates** only for dates within the
   400-day daily retention period.
4. Select **Apply filters** to load the report, or **Refresh report** to clear
   the current cached result under a short refresh lock.

An unavailable report indicates that the counter tables could not be queried;
it does not expose the underlying database error. Empty results mean that no
successful referral-bearing HTML page requests were measured in the selected
window.

The dashboard's **Top referral sources** widget uses the same site scope and
last-30-day window. Its **View all** link opens the full report.

## Troubleshooting

| What you see                  | What it means                                                                          | What to do                                                                                                         |
| ----------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| The report is unavailable     | The counter tables could not be queried                                                | Check database connectivity, package migrations, and the Referers health check                                     |
| The report is empty           | No successful referral-bearing HTML page requests were observed in the selected window | Check the window and remember that missing headers, cache/CDN bypasses, and static exports are outside this report |
| The Referrers page is missing | The package is not installed or the actor lacks the view permission                    | Confirm installation and grant `View:RefererPage` within the existing site scope                                   |
