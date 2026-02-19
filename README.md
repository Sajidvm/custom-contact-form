# Custom Contact Form

A streamlined, AJAX-powered inquiry system for WordPress. This plugin provides a professional interface for lead capture and includes a backend management system with CSV export capabilities.

## Features

- **Professional UI:** Modern grid-based layout using Plus Jakarta Sans for improved user engagement.
- **Dual-Form Support:** Separate tables for different inquiry types via `id="1"` and `id="2"`.
- **AJAX Submissions:** Background processing for a seamless user experience without page refreshes.
- **Leads Dashboard:** Centralized admin area to view and manage all patient submissions.
- **CSV Export:** One-click data export for external lead management and CRM integration.

## Installation

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress 'Plugins' menu.
3. Database tables are automatically created upon activation.

## Usage

Embed the forms using the following shortcodes:

- Form 1: `[contact_form id="1"]`
- Form 2: `[contact_form id="2"]`

To manage leads, navigate to the Leads menu in the WordPress sidebar. You can switch between form datasets and click Export CSV to download the records.

## Technical Details

- **Tables:** `wp_contact_form` and `wp_contact_form_2`
- **Security:** Uses WordPress nonces for request validation and thorough server-side sanitization
- **Requirements:** Compatible with WordPress 5.0+ and utilizes the built-in jQuery library
