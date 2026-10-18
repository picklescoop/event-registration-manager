Form, Cart and Registration Creator 1.2.3
================================
Install the ZIP via WordPress > Plugins > Add New > Upload Plugin.
Create a form under Event Forms, select a form type, configure its fields, publish and embed using [event_registration id="123"].

Types: Registration, Question/Comment, Appointment Request, Tickets/Products.
Fields: Default name, business, address, phone, email, products sold, plus customizable additions, including image/file upload.

Appointment requests: Set a Google appointment scheduling page URL. Visitors follow the link to view available times, then manually enter a requested date and time in the form. This does NOT query Google availability, reserve the time, or create a Google Calendar event. Do not represent these requests as confirmed bookings.

Shop: Enter each ticket/product on a separate line in the form editor: General Admission|25|https://example.org/photo.jpg. The visitor chooses quantities. The order and server-calculated total are saved to the entry and included in email. Payment methods remain offline or link/instruction-based and must be verified manually; this is NOT a PCI checkout or inventory management solution.

Email: Admin subject and message support {form_title}, {entry_id}, {fields}, {items}, {appointment}, {payment}, {total}. Uploaded files are attached to admin notifications and registrant receipts. Configure SMTP on WordPress for reliable delivery. Be aware of email attachment size and personal-data privacy.

Entries: Event Forms > Registrations, filter by form, change statuses, export CSV. Every entry records the form ID, so entries stay logically separated.

Design: Hero URL, accent, background, text color, font. No theme-specific dependencies.

Security/limitations: Uploaded files live in WordPress uploads and may be publicly accessible via URL. Do not request sensitive identity documents. No automatic payment verification, no real-time Google Calendar API, no stock tracking, no automated booking or payment capture. Test on staging before production.

Version 1.2.0: Admin submission detail/reply inbox, email reply history, and direct dashboard links in organizer notifications.

Clone forms: In Event Forms, hover over a form and click Clone Form. A new draft is created with the same fields, products, payment methods, emails and styling, but without prior submissions. Update the title, event details, payment instructions and embed shortcode before publishing.

Version 1.2.3: Renamed plugin and form-builder labels. Existing plugin folder, post types, metadata, shortcodes, and settings identifiers are intentionally unchanged for compatibility.


GETTING STARTED (v1.2.4)
==========================
1. In WordPress, open Forms > Getting Started & Help for the full step-by-step guide.
2. Open Forms > Add New. Name your form and choose a form type.
3. Customize the default fields and add any questions or picture-upload fields you need.
4. Add products/service levels only if you need tickets, quantities, or prices.
5. Set payment to Free, Optional, or Required and provide payment instructions.
6. Customize appearance, organizer email, email templates, and acknowledgment.
7. Publish and add [event_registration id="123"] to a WordPress page, replacing 123 with the form ID.
8. Submit a test entry. Review it under Forms > Registrations and verify email delivery.
9. Clone a previous form from the Forms list to reuse its settings without copying entries.

LIMITATIONS: External payment links are not automatically reconciled. Google appointment booking links do not reserve slots within the plugin. Do not publish private fields in public lists.


VERSION 1.2.5
- Colored, softened WordPress admin styling inspired by the provided design reference (not a pixel-identical redesign).
- Per-form optional QR ticket issuance: one unique ticket per product quantity, or one for a non-product registration.
- Ticket URLs in registrant receipts and entry details; administrator-only check-in and duplicate check-in prevention.
- QR image rendering relies on api.qrserver.com (external service). URLs include random ticket tokens but no registrant details.
- Payment status is NOT verified by ticket check-in. Ticket access is bearer-link based.
- Tickets are generated only for NEW submissions after the setting is enabled.

Version 1.2.6
- New step-by-step colorful Form Builder Studio interface in the WordPress form editor.
- Local browser QR generation (no third-party QR image service).
- QR Ticket Scanner under Forms; use HTTPS and compatible browser for camera scan, or manually enter ticket code.
- QR ticket validation and check-in still require logged-in administrator access.
- Note: local QR images render using JavaScript. Visitors with JavaScript disabled can use ticket codes.

Version 1.2.8: New enhanced dashboard with summary metrics, colorful navigation, quick actions, recent submissions, and built-in version changelog.

Version 1.2.9: Open a form editor to drag fields/products into order and see live form, email and confirmation previews. Click Update/Publish to save.

Version 1.3.0: Choose a form background image from Media Library or paste an image URL. Set image opacity 0–100. Use the color controls for title/field-label highlights, highlight opacity, heading and label font colors. See the changes immediately in Live Preview; save the form to publish.

Version 1.3.1: submission rate limiting, file validation, and check-in concurrency hardening.

Import/Export: From Forms > Import / Export, export a form as JSON, then import the file on another site. Imported forms are drafts and do not contain submissions. Check payment destinations and notification recipients before publishing.

Version 1.3.3: In the form editor, use the left toolbox to add fields, the middle panel to configure each section, and the right panel to preview. Save using WordPress Publish/Update.

Visual designer tip: Use the up/down arrow buttons on each field to reorder without dragging.
