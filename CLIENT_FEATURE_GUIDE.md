# Dashboard Analytics Plugin Feature Guide

Version date: July 23, 2026

## 1. Product Overview

`Dashboard Analytics` is a Moodle analytics and compliance reporting plugin designed for multi-company training operations. It provides role-based dashboards for platform admins, client/company managers, and employees, with a strong focus on:

- training compliance visibility
- certificate and document tracking
- expiring and expired document control
- workforce and turnover analytics
- course quality insights
- company-scoped operational reporting
- AVR / Act of Completed Works reporting

The plugin is built to work in a multi-tenant Moodle/IOMAD environment, where different companies must only see their own data, while the platform owner can view broader analytics across the whole system.

This guide focuses on the product features and business capabilities rather than internal development details.

---

## 2. Core Business Purpose

The plugin helps training providers and enterprise clients answer questions such as:

- Which employees are compliant today?
- Which certificates are expiring soon?
- Which companies, sites, or personnel categories are most at risk?
- Which new hires are still missing required documentation?
- Which courses are underperforming in pass rate or learner feedback?
- What work was completed this month for billing and AVR reporting?

In short, it turns Moodle training activity and document records into an operational management layer.

---

## 3. Role-Based Dashboards

The plugin currently supports three major dashboard experiences.

### 3.1 Company Dashboard

Primary audience:

- SENTAL Superadmin
- company owner / company-level management roles

Main purpose:

- company-wide and cross-company analytics
- executive compliance visibility
- risk tracking
- training quality monitoring
- workforce movement
- server and reporting controls for superadmin users

Current tab structure:

- KPI
- Overview
- Compliance
- Staff Turnover
- Training Quality
- Proctoring
- Forecast
- Server (superadmin only)
- Reports / AVR (superadmin only)
- Analytics Courses (superadmin only)

### 3.2 Client Dashboard

Primary audience:

- training manager
- HR / client coordination roles

Main purpose:

- company-scoped operational compliance management
- employee and document follow-up
- turnover and risk monitoring

Current tab structure:

- KPI
- Compliance
- Staff Turnover

### 3.3 Employee Dashboard

Primary audience:

- individual learners / employees

Main purpose:

- personal compliance view
- current certificates and course status
- progress and personal actions

Current tab structure:

- Overview
- Certificates
- Courses

There is also a companion profile analytics experience being prepared so employee-level analytics can appear directly from a user profile context.

---

## 4. Key Product Strengths

### 4.1 Multi-source compliance intelligence

The plugin does not rely on a single certificate source. It already combines:

- NCASign / EDS-driven completion documents (`local_ncasign`)
- legacy uploaded course completion documents from the document upload system (`sentaldocupload`)

This means compliance can be calculated even when some courses use the newer EDS/certificate generation path and others still rely on manually uploaded legacy documents.

### 4.2 Company-safe tenant scoping

The plugin is designed for IOMAD / multi-company Moodle usage. Depending on role:

- superadmin users can view broader platform/company analytics
- company/client roles are locked to their permitted company scope
- employee roles only see their own data

This is one of the plugin’s most important commercial strengths for enterprise clients.

### 4.3 Action-oriented compliance management

The plugin is not only reporting-oriented. It is designed to help teams act on:

- expired documents
- expiring documents
- employees missing certificates
- company hot spots
- course-level non-compliance
- new-hire onboarding risk

### 4.4 Configurable course inclusion

Admins can manually decide which courses should be included in analytics. This allows:

- exclusion of test/legacy courses
- bulk control over reporting relevance
- cleaner client-facing dashboards

This works alongside Moodle course visibility rules.

### 4.5 Localised enterprise UI

The plugin is prepared for multilingual usage, with Russian already treated as a primary working language and English strings also available.

---

## 5. Compliance Model

Compliance is the heart of the plugin.

### 5.1 Employee Compliance

Employee compliance is treated as a percentage:

`valid enrolled courses with a valid document / total enrolled courses x 100`

This gives a more realistic view than a simple yes/no rule.

### 5.2 Company Compliance

Company compliance is calculated as:

`the arithmetic mean of employee compliance percentages`

This provides a fairer company-level score, especially where workforces have mixed course loads.

### 5.3 Document status logic

Document records are classified into:

- Active
- Expiring
- Expired
- In Progress

The plugin uses document issue and expiry dates from the available document source and combines them with course validity logic where applicable.

### 5.4 Exclusion rules

Courses can be excluded from analytics if:

- the course is hidden, or
- the admin disables it from the analytics course control

This prevents unwanted test or legacy courses from polluting compliance metrics.

---

## 6. Data Sources and Integrations

The plugin brings together multiple Moodle and external data sources.

### 6.1 Moodle core data

Used for:

- users
- enrolments
- course completions
- activity progress
- course visibility
- login and activity-based indicators

### 6.2 IOMAD company data

Used for:

- company scoping
- tenant-safe filtering
- company-level aggregation

### 6.3 NCASign / EDS integration

Used for:

- generated course completion documents
- EDS queue visibility
- signed/completed document status

### 6.4 Legacy document upload integration

Used for:

- uploaded completion documents for legacy courses
- support for operations where not all certificates come from the NCASign flow

### 6.5 Course rating integration

The plugin already connects with the course rating system for:

- course ratings
- learner feedback
- quality review signals

### 6.6 Proctoring / trust data

A proctoring/trust area exists and parts of the groundwork are present, but this area is currently in transition because a more complete in-house proctoring system is being developed.

### 6.7 Server and uptime monitoring

The server section is intended to show:

- capacity and infrastructure health
- disk forecasting
- error summaries
- system settings
- uptime-based indicators

This makes the plugin useful both as an operational dashboard and as a service delivery management tool.

---

## 7. Dashboard Features by Area

## 7.1 KPI Tab

The KPI layer is designed to show the most important numbers first.

Current KPI capabilities include:

- total active users / staff
- overall compliance
- expiring within 30 days
- expired now
- EDS queue
- server disk health for superadmin users

Purpose:

- quick executive snapshot
- one-click drilldowns to action lists
- consistent top-level status view

---

## 7.2 Overview Tab

The Overview tab acts as a management summary page.

Current and intended feature set includes:

- total user volume
- platform/company activity indicators
- average compliance
- active company visibility
- platform registration growth
- activity snapshot metrics
- company health summary
- priority action cards

Business value:

- immediate health summary
- trend visibility
- management-friendly reporting layer

---

## 7.3 Compliance Tab

This is the most important operational tab in the product.

Current and intended feature set includes:

- compliance trend by company
- compliance ranking snapshot
- document status distribution
- heatmap views by business dimensions
- expired vs expiring by company
- non-compliance by course
- EDS queue visibility
- employee/document drilldown tables

This tab supports the main compliance workflows:

- who is missing a document
- where the biggest company risks sit
- which courses are driving non-compliance
- which departments/sites/personnel groups need intervention

---

## 7.4 Staff Turnover Tab

This tab connects workforce movement with compliance risk.

Current and intended feature set includes:

- new employees vs deactivated employees trend
- turnover percentage by company
- new hires without certificates risk

Business value:

- identifies onboarding gaps
- shows where training demand is growing
- highlights companies with risky workforce churn

---

## 7.5 Training Quality Tab

This tab focuses on learning effectiveness rather than only compliance.

Current and intended feature set includes:

- first-attempt pass rate by course
- engagement ratio / learning activity quality
- course ratings and learner feedback
- NPS-style or quality-review-oriented feedback direction

Business value:

- identifies weak courses
- highlights content that may need revision
- helps connect compliance performance with learning quality

---

## 7.6 Forecast Tab

The Forecast area is intended to help planning teams prepare ahead of time.

Current and intended feature set includes:

- 30 / 60 / 90 day expiry windows
- company-level upcoming risk
- weekly or rolling expiry forecasting

Business value:

- supports resource planning
- helps prevent last-minute certificate crises
- useful for training coordination teams

---

## 7.7 Proctoring Tab

This area remains strategically important, but is currently only partially active.

Originally intended feature set includes:

- trust score distribution
- average trust score by company
- proctoring coverage
- suspicious behaviour indicators
- trust vs completion relationships

Current reality:

- some groundwork and placeholder/partial reporting exist
- full commercial proctoring reporting is being held back while a dedicated in-house proctoring system is being developed

This should be positioned as an upcoming expansion area rather than a finished feature set.

---

## 7.8 Server Tab

Visible to platform-level admin users only.

Current and intended feature set includes:

- disk, RAM, CPU, database, and concurrent user gauges
- disk capacity forecast
- error log summaries
- read-only system settings view
- uptime and infrastructure health visibility

Business value:

- helps SENTAL operate the platform as a managed service
- adds internal operational value beyond learner analytics

---

## 7.9 Reports / AVR Tab

This is one of the plugin’s strongest commercial differentiators.

Purpose:

- generate Act of Completed Works / AVR documents from LMS data

Current and intended feature set includes:

- client company selection
- month/year selection
- act number and contract number inputs
- service provider details
- load-from-LMS services table
- editable act quantity
- LMS total vs act total comparison
- difference warnings
- reset to LMS
- clear all
- Excel export flow

Business value:

- bridges training delivery and billing
- reduces manual monthly reporting effort
- makes the plugin more than a dashboard; it becomes part of the delivery and finance workflow

---

## 7.10 Analytics Course Control

Admin-only feature.

Purpose:

- decide which courses should participate in analytics

Capabilities:

- search courses
- view current visibility and analytics state
- toggle inclusion/exclusion

Business value:

- clean dashboards
- practical control over noisy or legacy courses
- useful during rollout and long-term maintenance

---

## 8. Filtering and Drilldown Experience

The plugin is built around interactive filtering rather than static reports.

Supported or intended filters include:

- company
- employee
- course
- department
- location
- position
- personnel category
- site / facility
- education
- period

Key behaviour:

- filters apply across KPIs and visuals
- drilldowns open detailed tables
- tables support sorting and pagination
- users can search and narrow data quickly

This is a major usability strength when selling the product.

---

## 9. Employee and Profile-Oriented Experience

The plugin family is not limited to management dashboards.

It also supports a personal view for employees, including:

- personal course list
- certificate and protocol visibility
- personal compliance situation
- upcoming expiries
- self-service awareness of training status

This is important commercially because it shows the solution is useful for both management and end users.

---

## 10. What Is Already Implemented vs What Is Strategic / Ongoing

The plugin already includes a substantial working foundation, especially in:

- role-based dashboards
- company/client scoping
- KPI reporting
- compliance analytics
- document status reporting
- turnover analytics
- training quality analytics
- server/admin views
- AVR reporting workflow
- course inclusion controls
- legacy + EDS certificate source support

Areas that should be described as active roadmap / in progress rather than fully complete:

- full proctoring analytics
- deeper employee dashboard expansion
- richer forecast and planning layers
- finalised Excel/AVR document formatting polish where needed
- broader external workflow integrations where still evolving

This distinction matters commercially: the plugin is already saleable, but it also clearly has an upgrade path.

---

## 11. Commercial Positioning

From a product perspective, this plugin can be positioned as:

### A. Compliance and certificate control platform

Best for clients who care about:

- expiring certificates
- training validity
- readiness audits
- employee training oversight

### B. Multi-tenant training operations dashboard

Best for:

- training providers
- corporate LMS operators
- enterprise HSE training teams

### C. Training delivery and billing support tool

Because of AVR / Act reporting, it is not only a dashboard. It also supports the commercial reporting side of training delivery.

### D. Expandable analytics product

Because it already includes operational analytics, quality analytics, compliance logic, and admin controls, it can grow into a broader workforce learning intelligence platform.

---

## 12. Recommended Sales Talking Points

- Role-based dashboards for admins, client managers, and employees
- Multi-company / IOMAD-safe access model
- Real compliance percentages instead of simplistic pass/fail visibility
- Unified certificate analytics across EDS-generated and legacy uploaded documents
- Strong expiring / expired / missing document tracking
- Workforce turnover and onboarding risk analytics
- Course quality and learner feedback visibility
- AVR / Act reporting directly from LMS activity
- Admin control over which courses count in analytics
- Built for real enterprise training operations, not only generic Moodle reporting

---

## 13. Short Summary

`Dashboard Analytics` is a role-aware enterprise Moodle analytics plugin that combines:

- compliance management
- document intelligence
- workforce risk visibility
- training quality reporting
- company-safe multi-tenant access
- operational and administrative dashboards
- AVR reporting for commercial training delivery

It already delivers meaningful product value today, and it also has a clear roadmap for future expansion in areas such as proctoring, employee analytics, and deeper operational automation.
