# Certificate and pay slip PDFs — design

Date: 18 November 2026. Status: owner-approved design (chat). Task id: D02.

## Goal

Student certificates and teacher pay slips can be printed and downloaded as real PDF files. The pay slip carries the institute logo from Settings.

## Decisions (owner)

- Server-side PDF with `barryvdh/laravel-dompdf` (pure PHP; no Node/Chrome on the server).
- Pay slip layout: letterhead — logo left, institute name + contact beside it, A5 portrait.

## Certificate PDF

- Route `GET /certificates/{certificate}/pdf` → `CertificateController@pdf`, name `certificates.pdf`, middleware `role:admin,student`; same check as `print` (a student only for own certificate, else 403).
- Without query: `inline` response (opens in browser, print from viewer). With `?download=1`: `attachment`, filename `certificate-{certificate_number}.pdf`.
- New view `certificates/pdf.blade.php`: A4 landscape, zero margin. `public/template.png` embedded as base64 `<img>` positioned absolutely full-page (dompdf does not support `background-size: cover`); text block keeps current positions/fonts/German text. Revoked certificates show the REVOKED stamp and reason without flexbox. Missing template → plain white page (existing log warning kept).
- Existing HTML `certificates/print.blade.php` toolbar: replace the single button with **Download PDF** (`?download=1`) and **Print PDF** (inline, new tab). Existing links to `certificates.print` remain valid.

## Pay slip PDF

- Route `GET /payroll/payouts/{payout}/pdf` → `PayrollController@slipPdf`, name `payroll.slip.pdf`, middleware `role:admin,teacher`; same `authorizeDue` check as `slip`.
- Inline by default; `?download=1` → attachment `payslip-{voucher_number}.pdf`.
- New view `payroll/slip-pdf.blade.php`, A5 portrait:
  - Letterhead: logo (left) from `setting('institute_logo')` on the `public` disk, embedded as base64 data URI; omitted when unset or file missing. Beside it: institute name (from payout `slip_details.institute_name`, fallback current `institute_name()`), then `institute_address`, `institute_phone`, `institute_email` from current Settings (each omitted when empty).
  - Title "Salary Payment Slip", voucher number, paid date/time, amount box (currency from `slip_details.currency_code`).
  - Table: Paid to (teacher name + reference), For, Due amount, Method, Reference — same values as the HTML slip; empty rows omitted.
  - Reversed payout: "REVERSED" stamp + reversal reason.
  - Footer signature lines: "Received by", "Authorised by".
- HTML `payroll/slip.blade.php`: add **Download PDF** and **Print PDF** buttons beside the existing Print button.
- `payroll/show.blade.php`: add a PDF download icon next to each payout's slip icon.

## Shared

- Small helper `App\Support\PdfImage::dataUri(?string $absolutePath): ?string` returning a base64 data URI for an existing readable image (png/jpg/jpeg/webp→ mime from extension), else null. Used for logo and certificate template.
- dompdf options: `isRemoteEnabled = false` (no network), default paper set per view.

## Unchanged

Receipts, HTML slip/certificate visual design, data, migrations. No new permissions.

## Tests (`tests/Feature/PdfDocumentsTest.php`, isolated SQLite)

- Certificate PDF: admin gets 200, `Content-Type: application/pdf`, body starts with `%PDF`, `Content-Disposition` inline; `?download=1` → attachment with `certificate-{number}.pdf`.
- Owning student 200; another student 403; teacher 403 (middleware).
- Revoked certificate view contains "REVOKED" (assert on rendered view HTML).
- Pay slip PDF: admin 200 PDF; owning teacher 200; other teacher 403; download filename `payslip-{voucher}.pdf`.
- Logo: with a fake `public` disk image set in `institute_logo`, the rendered slip view HTML contains `data:image/png;base64,`; without logo no `<img` in the letterhead and PDF still 200.
- Reversed payout view contains "REVERSED".
