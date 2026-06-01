# TODO - Branding Migration to Social Security Administration

- [x] Replace all legacy branding in application code and Blade templates
- [x] Replace defaults/fallbacks in controllers, support classes, and migrations
- [x] Update operational/config references (e.g., supervisor identifiers/log files, sample env/db names)
- [x] Update documentation files (README.md, FEATURES.md) to remove legacy branding references
- [x] Run repository-wide verification search and confirm zero legacy-brand matches in code/docs

# TODO - Production Login/Account Context Stabilization

- [x] Identify exact 403 source ("Account context is missing")
- [x] Patch base controller account resolution with safe fallback + persistence
- [ ] Clear caches and re-test dashboard access
- [ ] Run post-fix thorough UI/API verification

# TODO - Production Issues: Send Speed + Click Tracking

- [x] Locate send-speed implementation path (`SendController` + `ProcessCampaignQueueJob` + `WorkMailsQueueCommand`)
- [x] Locate click-tracking path (`TracksEmailContent` + `TrackingController@click`)
- [x] Fix send-speed behavior for low queue counts (e.g., 5 emails) and ensure rate limiting is honored
- [x] Fix click tracking flow and ensure click rows are recorded + redirect works
- [ ] Validate both fixes with focused production-safe checks
- [ ] Verify campaign with `emails_per_minute=5` continues processing after first send (no stall) on production worker

# TODO - Bulk SMTP CSV Header Fix

- [x] Inspect SMTP bulk upload parsing and identify why `label` is required
- [x] Update parser to accept either `label` or `name` as SMTP name header
- [x] Improve missing-header validation message for name header requirement
- [x] Run quick syntax validation for updated controller

# TODO - SMTP Strict Round Robin Rotation

- [x] Inspect current SMTP selection flow in queue worker
- [x] Implement strict rotation pointer so sends go smtp1 -> smtp2 -> ... -> smtp1
- [x] Ensure fallback on SMTP failure still works without breaking rotation
- [x] Run syntax checks after update

# TODO - SMTP Upload Validation + Auto-Inactive Dead SMTP

- [ ] Inspect SMTP index counters and fix account-wide stat calculation (not paginated subset)
- [ ] Implement bulk-upload SMTP connection test before saving each row
- [ ] Implement auto-inactive on SMTP failure (test/send/bulk validation flows)
- [ ] Add optional batch recheck command to deactivate dead active SMTPs
- [ ] Update/add feature tests for new SMTP behavior
- [ ] Run critical-path test execution and report results
