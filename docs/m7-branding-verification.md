# Branding and document review — 2026-10-02

The generated NestCrafters wordmark is integrated in the shared guest/auth and workspace brand component. Expanded navigation uses the wordmark, collapsed navigation uses the house/nest mark, and the shared head uses the PNG favicon. The legacy mascot remains an unused source asset.

## Local evidence

Review used synthetic accounts on port 8013 and only `nestcrafters_m2_test`. MariaDB is available again; the earlier connection blocker is resolved. Production, the legacy application, the SQL export and the imported main database were not modified.

| Check | Result |
| --- | --- |
| Document layout | No horizontal overflow at measured CSS widths 358, 374, 389, 429, 768 and 1280. No broken visible images. Narrow and desktop layouts visually inspected. |
| Logo | Guest sign-in wordmark, expanded sidebar wordmark and compact house/nest mark inspected. |
| Admin shell | Synthetic admin login redirected to `/admin`; role menu and new wordmark rendered correctly. Desktop dashboard visually inspected with no horizontal overflow at measured width 1280. [Screenshot](m7-brand-admin-desktop.png). |
| Keyboard | Skip-to-content activation and subsequent form tab order checked; visible focus on file/name/date controls. This is partial keyboard coverage, not a screen-reader audit. |
| Image upload | File chooser selected the public synthetic favicon; image preview and ready state appeared. Submission returned “Document uploaded to private storage” and a new stored-file row. |
| PDF download | Authenticated Download action saved `approved-w9.pdf`, 614 bytes, SHA256 `d436a3242225039346170d5fbbdd325acd1cd478717b1f2ae67f3921443c549f`. Poppler found one Letter page and rendered the expected synthetic text correctly. |
| PDF fixture repair | Replaced the invalid header-only PDF fixture with a complete page tree, text stream and cross-reference table. Subsequent fixture resets reproduce a usable document. |
| Browser PDF viewer | Still unverified: the in-app viewer showed a blank dark surface. Successful download and independent rendering do not count as browser preview acceptance. |

Screenshot: [document screen with the new branding](m7-brand-documents-desktop.png).

Automated verification: **31 tests / 407 assertions passed** across FoundationTest, M4ContractorWorkflowTest, M5JobWorkflowTest, OwnershipPolicyTest and MediaUploadRetryTest. Both upload JavaScript checks passed. Pint passed for the repaired fixture, and Blade templates compiled successfully. This is a focused regression run, not a new full-suite result. The SQL SHA256 still matches `3ebef325d80b514f900f462673ba40398365fbbcdb4162c20b00771469495fc5`. The fixture was restored after tests; the review tab is left on the synthetic admin dashboard and viewport overrides were reset.

## Remaining M7 checks

- Native iOS/Android camera selection, orientation, file picker, PDF preview and saving downloads require an actual device or supported emulator.
- Current browser zoom/viewport behavior did not permit an exact 320/375/390/430 matrix. Record measured widths above rather than treating requested dimensions as actual widths. A later viewport override also stopped affecting the application tab; overrides were reset.
- Complete keyboard review of mobile navigation and all admin/contractor workflows, plus screen-reader and Safari checks, remains open.
- Client decisions D2–D10 and release acceptance remain separate.

M7 remains **in progress**. Branding integration and this local document review are complete within the evidence above.
