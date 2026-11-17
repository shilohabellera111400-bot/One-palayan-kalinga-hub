# PWD & Senior Citizen Services - Preservation & Restoration Guide

## Overview
As part of the system scope optimization, the **Senior Citizen (OSCA)** and **Persons with Disability (PDAO)** services have been temporarily removed from active navigation and user interfaces to keep the project focused on:
- **Crisis Assistance (AICS)**
- **Socialized & Resettlement Housing**
- **Appointments & Window Scheduling**
- **Document Pre-Verification**
- **Resident Verification & Portal Management**

All original source code, UI designs, AJAX handlers, database records, and SQL schemas have been **100% preserved and backed up**.

---

## What is Preserved in this Archive?
1. `pwd.php` - Resident PWD Services & ID Application Portal
2. `pwd-services.php` - Public PWD Citizen's Charter details
3. `senior.php` - Resident Senior Citizen Services & OSCA ID Application Portal
4. `senior-services.php` - Public Senior Citizen Citizen's Charter details
5. `submit-pwd-application-ajax.php` - AJAX application processing for PWD ID & Booklets
6. `submit-senior-application-ajax.php` - AJAX application processing for OSCA ID & Pension
7. `staff/manage-pwd.php` - Staff Desk PWD application review, approval & ID releasing
8. `staff/manage-senior.php` - Staff Desk OSCA application review, approval & Pension allocation
9. `backup_pwd_senior_db.sql` - Full SQL DDL & DML database backup for `pwd_applications` and `senior_applications`

---

## How to Restore / Re-Integrate Instantly

### Option 1: One-Click Toggle (Recommended)
Open `config/features.php` and set:
```php
define('FEATURE_ENABLE_PWD_SENIOR', true);
```
Setting this to `true` instantly re-activates:
- Senior Citizen & PWD sidebar links in `includes/portal-sidebar.php`
- Senior Citizen & PWD service options in `appointments.php`
- Senior Citizen & PWD public service cards in `services.php`
- Senior Citizen & PWD forms in `print-forms.php`
- Senior Citizen & PWD staff desk queues in `staff/staff-dashboard.php`
- Chatbot topics & quick links in `includes/chatbot-widget.php`

### Option 2: Running the Toggle Script via CLI
From the project root:
```bash
php modules_archive/pwd_senior_services/toggle_scope.php on
```
To turn it back off:
```bash
php modules_archive/pwd_senior_services/toggle_scope.php off
```

---

## Database Integrity Guarantee
- No tables were dropped or modified.
- Tables `pwd_applications`, `senior_applications`, and columns `residents.senior_citizen` and `residents.pwd` remain fully functional and intact in the active database.
