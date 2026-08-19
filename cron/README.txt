============================================================
 Daily traffic rollup — one-time cPanel setup
============================================================

This makes the Dashboard and "today's views" column update
automatically every day, with no manual work after this.

WHAT YOU NEED TO DO (once, takes ~2 minutes):

1. Log in to cPanel.
2. Find "Cron Jobs" (search box at the top, or under Advanced).
3. Under "Add New Cron Job":
   - Common Settings: choose "Once Per Day (0 0 * * *)"
     (or set it manually: Minute=0, Hour=0, rest = *)
   - Command: paste this, but change the path to match
     where you actually uploaded the site files:

     php /home/YOUR_CPANEL_USERNAME/public_html/cron/rollup-traffic.php

     Tip: if you're not sure of the exact path, cPanel's
     File Manager shows it — click this file and check its
     "Full Path" in the file details panel.

4. Click "Add New Cron Job".

That's it. From tomorrow onward it runs by itself every night,
no dashboard clicking, no manual step — cPanel handles the
scheduling and Dashboard/All Videos will just start showing
real "today" numbers once a full day has passed.

------------------------------------------------------------
HOW TO CHECK IT'S WORKING
------------------------------------------------------------
- Most cPanel setups email the account owner the output of
  every cron run. Check that email a day after setting this
  up — you should see a line like:
      [rollup-traffic] Done. 3 video/country rows written for 2026-08-09.
- Or wait a day, then check Admin → Dashboard & Traffic —
  the "Traffic today" numbers should no longer be stuck at 0.

------------------------------------------------------------
IF SOMETHING GOES WRONG
------------------------------------------------------------
- "No such file" error in the cron log → the path in the
  Command field doesn't match where the files actually are.
  Double-check with File Manager.
- No email / no output at all → some hosts route cron output
  to a log file instead of email; check cPanel's cron log,
  or add ">> /home/YOUR_USERNAME/cron.log 2>&1" to the end
  of the Command field to capture it yourself.
- This script refuses to run from a browser on purpose
  (it will show "This script can only be run from the
  command line / cron." if you visit it directly) — that's
  expected and not an error.
