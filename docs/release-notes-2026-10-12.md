# What's New — October 12, 2026

The superadmin **Incentives** page (the monthly branch-manager incentive) is now hidden for the time being. Nothing has been deleted.

---

## Superadmin

### Incentives page disabled

- The **Incentives** link no longer appears in the sidebar.
- `/incentives` and `POST /incentives/pay` return a 404 while the feature is off.
- All incentive data is intact; no records were changed or removed.
- The feature sits behind the `features.incentives` flag and can be re-enabled by setting `INCENTIVES_ENABLED=true` in the environment.
- The **per-day attendance incentive** entered on attendance sheets and shown on payslips is a separate feature and is unaffected.

---

## Notes for administrators

No action is needed. To bring the Incentives page back, set `INCENTIVES_ENABLED=true` and clear the config cache.
