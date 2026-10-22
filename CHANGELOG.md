## 1.3.56 — Appointment request privacy and calendar display settings
- Stop including an online meeting URL in the initial appointment request notification; joining details are reserved for approval/confirmation.
- Add an optional combined Google Calendar embed URL under Settings → Appointment Integrations, supporting the Google Calendar embed view of multiple calendars.
- Keep the existing permitted online meeting service checkboxes and calendar ID list in Appointment Integrations.
- The embed is for display only and is not a conflict-checking or calendar-write API. Further migration of legacy per-form credentials and full Google OAuth multi-calendar availability/approval testing remain outstanding.
- Live PHP/browser verification pending.

## 1.3.54 — Appointment Request scheduler administration page
- Fix root cause: builder navigation hid the Form Type section containing the appointment settings.
- Move the existing appointment settings DOM into a dedicated Appointment Scheduling administration page, preserving the original input elements and saved values.
- Display Scheduling navigation only for Appointment Request forms; keep it hidden for other form types.
- Keep form administration navigation at the first page when switching types instead of unexpectedly jumping ahead.
- PHP syntax unchanged apart from version identifiers; live browser verification pending.

## 1.3.53 — Emergency PHP syntax fix
- Fix unescaped single quotes introduced in the inline appointment form-type change handler in v1.3.52. These broke the surrounding single-quoted PHP echo string and caused a critical error.
- Preserve appointment-only scheduler visibility behavior.
- PHP lint and live-site verification still pending; do not use v1.3.52.

## 1.3.52 — Appointment scheduler visibility
- Show appointment location, meeting and scheduling settings immediately when Form Type changes to Appointment Request.
- Hide appointment-only settings for Registration, Question / Comment and Shopping Cart forms.
- Keep visibility synchronized on page load and changes, with a direct form-select handler and delegated admin event listener.
- No changes to stored appointment settings or frontend booking rules.
- PHP lint and browser verification pending.

## 1.3.51 — Payment security foundation (pre-integration)
- Require actual recorded paid status and full amount whenever a submission has an amount due, even when the selected method is `none`; prevent an unpaid amount from bypassing confirmation checks.
- Add administrator-only configuration readiness diagnostics for Stripe, PayPal and Square using server-side constants. No secret values are displayed or stored in payment URLs.
- Explicitly warn that provider credentials alone do not activate checkout, webhooks, payment verification or refunds.
- **Not a live payment release**: hosted checkout, signed webhook handlers and provider reconciliation are still outstanding and must be implemented and tested before enabling online payments.
- PHP lint and integration tests have not been run.

## 1.3.50 — Administrator submission deletion
- Add Delete Submission buttons to the submission inbox and individual submission detail.
- Require administrator permission, POST request, WordPress nonce and browser confirmation.
- Move submissions to WordPress Trash instead of permanently deleting them, and preserve uploaded Media Library attachments.
- Show a success message after moving a submission to Trash.
- PHP lint and live-site testing pending.

## 1.3.49 — Submission attachment gallery
- Display uploaded images as thumbnails in the administrator's individual submission details, grouped by their form field.
- Show uploaded PDFs and other stored attachments with View file and Download links.
- Use existing `_erm_files` attachment metadata, preserving previously uploaded files.
- Restrict the gallery to the existing administrator-only submission detail view.
- PHP lint and live WordPress testing pending.

## 1.3.48 — Emergency parse-error fix
- Remove duplicated, malformed PHP that was accidentally appended after `ERM_Plugin::init();` in the main plugin file. The stray code caused `unexpected token ".", expecting end of file` at line 927.
- Preserve the corrected submission detail rendering from v1.3.47.
- Update both the plugin header and runtime version constant.
- Site deployment and full PHP lint validation are still required.

## 1.3.47 — Critical PHP syntax hotfix
- Correct malformed PHP in submission detail output (previously line 868), which caused `unexpected token ">"` and a WordPress critical error.
- Restore rendering of registration field values and payment summary.
- Align the runtime version constant with the plugin header.
- Requires deployment and PHP lint/runtime verification on hosting before production use.

## 1.3.46 — In-plugin payment connection instructions
- Added expandable, provider-specific setup instructions directly to Settings > Payment Accounts for Stripe, PayPal (including eligible Venmo), Square, Zelle, Cash App, and cash/check.
- Included official provider dashboard links, sandbox/test setup guidance, webhook requirements, and security/verification checklists.
- Added a direct link to the guide from Getting Started & Help.
- Clearly identify which methods currently use manual payment links and which API integrations are not yet implemented; no API keys are requested or stored by this documentation-only update.
- Live WordPress testing pending.

## 1.3.45 — Payment and submission workflow safeguards
- Submission detail opens with registrant information and payment/registration status; optional QR tickets are moved below those details and malformed ticket metadata is skipped.
- Block manual confirmation of paid registrations/orders until full payment is recorded as verified.
- Require recording a verified amount through the payment management form before manually setting payment status to Paid.
- Send payment-pending instructions for cash/check and other selected methods, not an acceptance confirmation; ticket links for paid registrations are withheld until administrator confirmation.
- Send a separate registration confirmation email after administrator approval (appointment confirmations continue through their existing flow).
- Online processor checkout and server-verified payment webhooks are NOT implemented. External payment links cannot verify payment; do not use this version as an automatic online checkout.
- Production PHP/runtime validation and root cause of the reported WordPress critical error are still pending; inspect WP debug log before deployment.

## 1.3.43
- Allow registrants to select up to 10 photos or PDF files in each file-upload field instead of only one.
- Validate every uploaded file against the existing extension, MIME-type and per-file size restrictions.
- Show all uploaded attachments in the registration inbox and include them in organizer notification emails.
- Preserve compatibility with entries containing a single attachment ID.
- WordPress/PHP upload and request size limits still apply; live testing is pending.

## 1.3.42 — Security hardening
- Limit viewing and changing Zoom, Teams and Discord integration credentials to WordPress administrators; editors retain normal form editing without overwriting credentials.
- Preserve existing integration secrets when administrators leave password fields blank.
- Prevent appointment management bearer tokens from leaking through browser referrer headers and discourage search indexing.
- Rate-limit public appointment availability lookups and reject lookups for non-appointment forms.
- Require POST on the form settings save handler.
- Static source review only; live penetration testing, dependency scanning and deployment verification remain outstanding.

## 1.3.41
- Consolidated the Super Forms WordPress sidebar into six sections: All Forms, Add New, Registrations, Appointments, Settings, and Help & Updates.
- Removed duplicate All Forms entry and hidden redundant integration menu entries while keeping their underlying pages and existing URLs available.
- Added grouped navigation hubs for registrations/payment review/check-in, appointments/Google Calendar, settings/shipping, and help/changelog.
- The parent Super Forms menu continues to open the dashboard.

## 1.3.40
- Hide appointment location, online meeting integration, and appointment scheduling settings in the form builder unless Form Type is Appointment Request.
- Show appointment settings immediately when Appointment Request is selected; hide them again when another form type is chosen.
- Preserve existing appointment configuration when switching form types.

## 1.3.39
- Show each form's embed shortcode directly in Super Forms → All Forms.
- Add one-click Copy buttons to the form list, with clipboard fallback.

## 1.3.38
- Confirmation emails use the currently saved meeting link and do not fall back to a static link when automatic creation fails.
- Show administrator warnings on appointment records and send deduplicated meeting creation failure emails.
- Guard Zoom, Teams, and Discord creation with an entry-specific lock to reduce duplicate meetings.
- Provider rescheduling and cancellation synchronization remains pending.

## 1.3.37
- Added automatic Microsoft Teams meeting creation via Microsoft Graph application credentials and organizer access policy.
- Added automatic Discord voice-channel invitation creation via bot credentials.
- Existing Zoom automatic meeting creation remains available on appointment confirmation.
- Automatic creation occurs when an appointment is confirmed; joining links are stored and included in confirmation emails.
- Slack and FreeConferenceCall.com retain configured reusable meeting links; no unsupported auto-creation claims.
- Requires provider-specific permissions and live credential testing; meeting cancellation/rescheduling sync is not yet implemented.

## 1.3.36
- Appointment customers can choose studio, customer location, or online meeting.
- Travel appointments collect a required address and store it with the booking.
- Organizers can configure a reusable Zoom, Teams, Discord, Slack, FreeConferenceCall.com, or other meeting link.
- Optional Zoom Server-to-Server OAuth integration creates a unique meeting when an administrator confirms an appointment.
- Other platforms currently require a reusable meeting URL; no automatic meeting creation for them.
- Live integration and rescheduling behavior require further testing.

## 1.3.35
- Replaced the disabled Next section button at the final form-builder step with Submit Form.
- Submit Form triggers the existing WordPress Publish/Update action, preserving normal permissions, validation, and form-save hooks.
- The button remains Next section on earlier steps.

## 1.3.34
- Email preview refreshes as organizer/customer subject and template content changes, including inserted placeholders.
- Preview responds to field and product edits, form name changes, receipt toggle, and preview selection.
- Preview uses the same plain-text template substitution pattern as actual emails and indicates when customer receipts are disabled.
- This release does not change email delivery or saved message templates.

## 1.3.33
- Fixed dashboard permission error: keep the dashboard submenu registered with WordPress and hide only its visible menu link.
- Main Super Forms menu continues to redirect to the dashboard.

## 1.3.32
- Fixed the Super Forms dashboard permission error: the dashboard page is now registered with WordPress before being hidden from the submenu.
- Clicking Super Forms opens the dashboard; the redundant Dashboard submenu item stays hidden.
- Dashboard access requires WordPress administrator permissions.

## 1.3.31
- Removed the redundant Dashboard submenu entry under Super Forms.
- Clicking the main Super Forms menu continues to open the existing dashboard.
- All Forms remains a separate submenu entry.

## 1.3.30
- Rename the WordPress administrator menu to Super Forms.
- Clicking the Super Forms main menu opens the existing dashboard.
- All Forms and the dashboard Manage Forms links open the form list separately.

## 1.3.29
- Shippo settings, live carrier quotes at checkout and selected-rate server verification.
- Administrator-confirmed Shippo label purchasing and 4x6 PDF printing from Shipping Labels.
- Shipping Providers settings include direct USPS, UPS and FedEx credential storage, but direct API adapters are not yet implemented.
- Manual zone rates and pickup remain available. Live quotes require Shippo credentials and carrier support.

## 1.3.28
- Shopping cart forms support pickup, shipping, or both.
- Configure a default shipping rate and multiple country/postal-prefix shipping zones per shopping form.
- Buyers enter country, ZIP/postal code, and delivery address; see a live shipping estimate and order total.
- Server independently calculates and stores shipping cost, fulfillment method, address, and final amount.
- Fixed-rate shipping estimates only: no live USPS/UPS/FedEx/DHL rate APIs, label purchasing, or address validation.

## 1.3.27
- Shopping Cart / Storefront is now clearly labeled as a form type in the builder.
- Shopping forms show a live cart summary and subtotal as customers change product quantities.
- Shopping cart submissions must contain at least one product, checked both in the browser and on the server.
- Shopping carts no longer add the registration form's fixed fee to the cart total.
- Existing product catalog editor, descriptions, images, order emails, and manual payment methods remain supported.

## 1.3.26
- Registration forms can now define multiple named registration levels or areas, each with a different price.
- Registrants choose exactly one level; server-side validation uses the saved price, not a browser-submitted amount.
- Selected level and total are saved with each entry and included in notification emails.
- Existing single-amount registration forms continue to work unchanged when no levels are configured.

## 1.3.25
- Added five one-click appearance presets, a font family selector, and desktop/tablet/mobile appearance previews.
- Preview reflects current color and typography fields; save/update form to persist changes.
- No changes to form data, submissions, or payment records.

## 1.3.24
- Google Calendar connection page now shows recent per-booking synchronization outcomes and error messages.
- Failed appointment event writes are retried through WP-Cron with bounded backoff (up to five attempts); manual retry available to administrators.
- Connection failures are now recorded rather than silently ignored; successful retries clear outstanding retry schedules.
- WP-Cron requires site traffic or a configured server cron; external API behavior remains to be tested with a live Google account.

## 1.3.23
- New Payment Review dashboard with payment status filters and recorded amounts.
- Record verified amount, payment status, transaction reference, and notes per submission.
- Immutable-in-UI payment history records changes and administrator identity; legacy status dropdown changes are audited.
- Payment tracking is manual. Automatic PayPal/Venmo/Zelle/Cash App transaction verification and gateway processing are NOT implemented.

## 1.3.22
- Added a seven-day administrator appointment calendar with form filtering and booking links.
- Added visual controls for extra weekly time windows and blocked dates, preserving the underlying scheduling data.
- Appointment changes continue through availability-validated entry details; drag-and-drop rescheduling is not included.

## 1.3.21
- Separate editable customer confirmation subject and body, independent of organizer email.
- Preview selector switches between organizer and customer messages; customer template includes one-click placeholders.
- Existing forms retain original customer receipt when the new message field is blank; QR ticket and appointment-management links remain included.

## 1.3.20
- Centralized PayPal, Venmo, Zelle, Cash App, check payee, and mailing details in Payment Accounts administration.
- Forms now only choose accepted payment methods and payment requirements.
- Existing per-form payment account details remain usable until shared settings are saved; shared settings then override them across all forms.
- This is payment instruction management, not payment gateway transaction processing.

## 1.3.19
- Organized email customization into administrator notifications, customer confirmations, and live organizer-email preview.
- Added one-click insertion of supported template placeholders, with examples and contextual descriptions.
- Clarified that the customer receipt checkbox controls the built-in receipt; custom template edits apply to organizer notifications.

## 1.3.18
- Removed duplicate color preview/picker from appearance settings.
- Kept the single interactive color picker to the right of the hex input and eyedropper button.

## 1.3.17
- Removed the redundant Form Purpose navigation step.
- Form type remains selectable on the first builder screen and underlying data is preserved.
- Updated navigation counts and labels.

## 1.3.16
- Added a prominent form-name field as the first builder setting.
- The new field uses the existing WordPress form title; no duplicate metadata or migration required.
- Requires a nonempty name when saving through the classic post editor.

## 1.3.15
- Appearance customization now includes color maps, editable hex fields, live color swatches, and browser-supported eyedropper buttons.
- Supports accent, form background, text, label highlights, label text, and headings.
- Preserves existing stored hex values and improves accent-color validation.

## 1.3.14
- Google Calendar OAuth 2.0 account connection, refresh-token handling, and disconnection.
- Live Google FreeBusy availability checks (fail closed when connected Google is unavailable).
- Create, update, or delete Google Calendar events when appointments are confirmed, rescheduled while confirmed, or cancelled.
- Per-form calendar ID and admin connection page. Sync errors are saved to appointment metadata.
- Requires Google Cloud OAuth credentials and an enabled Calendar API; untested against a live Google account.

## 1.3.13
- Customer self-service cancellation and rescheduling via private email link.
- Read-only Google Calendar private iCal feed conflict checks (five-minute cache).
- Calendar feed failures cannot guarantee conflict prevention; Google OAuth and write-back are not included.

## 1.3.12
- Appointment confirmation and cancellation notification emails when admin changes registration status.
- Admin rescheduling form checks the new date/time against availability and conflicts.
- Stores appointment change history, including notification delivery result.
- Google Calendar sync and self-service rescheduling are not included.

## 1.3.11
- Scheduling buffers, minimum booking notice, blocked dates, and additional daily windows.
- Available slots are validated on submission against all configured restrictions.
- Google Calendar synchronization is not yet included.

## 1.3.10
- Context-aware form builder displays scheduling settings only for appointment forms.
- Product catalog only for sales forms; ticket settings for registrations and sales; payment settings hidden on contact forms.
- Existing visual designer emphasized; legacy fields table available under Advanced.
- All hidden settings remain in the form for backwards-compatible saving.

## 1.3.9
- Show appointment scheduling only for appointment forms; show products only for shop forms.
- Show QR ticket controls only for registration and shop forms; hide payment settings for contact forms.
- Add a form-purpose chooser and context-aware section navigation.
- Fix missing visual builder assets on WordPress post editing screens.
- Fix visual canvas field-ordering controls and make visual editing the primary interface.
- Retain existing data and shortcode compatibility.

## 1.3.8
- Configure start-time intervals independently from appointment duration.
- Remove overlapping appointment times from availability results.
- Save appointment duration with each new submission for future conflict calculations.
- Enforce availability again on form submission.

## 1.3.7
- Configure appointment availability by weekday and time window, duration, and booking horizon.
- Show visitors available appointment times and reject unavailable requests on the server.
- Prevent duplicate requests for the same time within a form. Google Calendar sync is not included.

## 1.3.6
- Responsive full/half/third-width form fields.
- Visual editor field-width selector and fixes for canvas controls.
- Existing forms default to full-width.

## 1.3.5
- Improved visual editor property editing without losing focus while typing.
- Added accessible up/down ordering controls and keyboard focus styling.

## 1.3.3
- Three-panel studio with field toolbox, editable sections, and live preview.
- Desktop/mobile preview toggle.
- Improved navigation and responsive admin styling.
- Preserves existing form settings and submission records.

## 1.3.2
- Import/export individual form configurations as JSON, with strict format validation and sanitized settings.
- Imported forms are new drafts; submissions and payment records are never imported.
- Updated dashboard navigation, workspace layout, and transfer screen.

## 1.3.1
- Per-form IP rate limiting for public submissions (8 attempts / 10 minutes).
- Verify uploaded file MIME/type with WordPress after upload; reject mismatches.
- Database advisory lock to reduce simultaneous double check-in attempts.
- Additional update and staging guidance.

## 1.3.0
- Background photo picker and opacity control per form.
- Customizable title/label highlights and font colors using color pickers.
- Live preview of image and readability styling.

## 1.2.9
- Drag-and-drop field and product ordering in the form builder.
- Live previews of the form, administrator email and acknowledgment page.
- Responsive side-by-side builder layout.
- Existing up/down controls remain available.

# Form, Cart and Registration Creator — Changelog

## 1.2.8
Enhanced admin dashboard, navigation, summary metrics, recent submissions and changelog.

## 1.2.7
Event check-in improvements.

## 1.2.6
Local QR generation and scanner.

## 1.2.5
Individual QR tickets.

## 1.2.4
Help and getting started.

## 1.2.3
Expanded product editor.

## 1.2.2
Plugin renamed.

## 1.2.1
Form cloning.

## 1.2.0
Inbox and replies.

## 1.1.0
Appointments and products.

## 1.0.1
Email file attachments.

## 1.0.0
Initial release.
