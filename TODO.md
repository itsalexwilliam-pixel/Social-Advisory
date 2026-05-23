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
