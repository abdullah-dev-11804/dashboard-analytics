# Moodle Dashboard Analytics Block Plugin Structure

## Source Deck Sections

The presentation is split into these role/dashboard sections:

- Company Owner: slides 1-19
- Company Coordinator/Training Manager: slides 20-33
- Client Administrator/Manager: slides 34-41
- System Administrator: slides 42-49
- User profile dashboard: slide 50

This should be built as one Moodle block plugin with role-aware dashboards, not separate plugins.

## Recommended Plugin Identity

- Plugin type: `block`
- Plugin directory: `blocks/dashboardanalytics`
- Component name: `block_dashboardanalytics`
- Main purpose: render a dashboard shell on Moodle dashboard pages, then load role-specific analytics through Moodle AJAX/external APIs.

## Core Build Principle

Keep the plugin in four clear layers:

1. Block shell: decides whether the current user can see the block and renders the dashboard container.
2. Output/templates: Mustache templates for filters, KPI cards, chart cards, drill panels, and tables.
3. Services/repositories: PHP classes that calculate KPIs, chart datasets, drilldown rows, and action links.
4. JavaScript UI: Chart.js rendering, global filters, tab switching, card expansion, drilldown loading, and optional `sendPrompt()` actions.

This avoids putting SQL or business rules inside JavaScript.

## Proposed File Tree

```text
blocks/dashboardanalytics/
  version.php
  block_dashboardanalytics.php
  settings.php
  lib.php

  db/
    access.php
    services.php
    install.xml
    upgrade.php
    tasks.php
    caches.php

  lang/en/
    block_dashboardanalytics.php

  classes/
    permissions.php
    filters.php
    context_resolver.php

    external/
      get_bootstrap.php
      get_filter_options.php
      get_kpis.php
      get_chart.php
      get_drilldown.php
      get_employee_profile.php
      get_server_metric.php

    service/
      dashboard_service.php
      kpi_service.php
      overview_service.php
      compliance_service.php
      turnover_service.php
      training_quality_service.php
      proctoring_service.php
      forecast_service.php
      server_service.php
      user_profile_service.php

    repository/
      dimension_repository.php
      employee_repository.php
      document_repository.php
      completion_repository.php
      quiz_repository.php
      proctoring_repository.php
      forecast_repository.php
      server_metric_repository.php

    output/
      renderer.php
      dashboard.php
      chart_card.php
      kpi_card.php
      drilldown_table.php

    task/
      collect_server_metrics.php
      refresh_analytics_cache.php

    privacy/
      provider.php

  templates/
    dashboard.mustache
    filter_bar.mustache
    kpi_strip.mustache
    kpi_card.mustache
    role_tabs.mustache
    chart_card.mustache
    drilldown_table.mustache
    server_gauge.mustache
    empty_state.mustache
    error_state.mustache

  amd/src/
    dashboard.js
    filters.js
    kpis.js
    charts.js
    drilldowns.js
    tables.js
    server.js
    prompt_actions.js

  amd/build/
    generated AMD files

  styles.css
  thirdpartylibs.xml
  README.md
```

## Moodle Capabilities

Define the role visibility in `db/access.php`. The exact Moodle roles can be assigned these capabilities after installation.

```text
block/dashboardanalytics:addinstance
block/dashboardanalytics:myaddinstance
block/dashboardanalytics:view
block/dashboardanalytics:viewowner
block/dashboardanalytics:viewcoordinator
block/dashboardanalytics:viewclientmanager
block/dashboardanalytics:viewsystem
block/dashboardanalytics:viewprofile
block/dashboardanalytics:viewemployeeidentity
block/dashboardanalytics:viewproctoring
block/dashboardanalytics:managesettings
```

Important privacy rule: named employee rows should only be returned when the user has `block/dashboardanalytics:viewemployeeidentity` in the correct tenant/company context.

## Role Dashboards

### Company Owner

Tabs from the deck:

- KPI Strip
- Overview
- Compliance
- Staff Turnover
- Training Quality
- Proctoring
- Forecast
- Server

This is the broadest executive dashboard. It can include cross-company comparison, named employee drilldowns where permitted, proctoring summaries, forecasted training demand, and high-level server health.

### Company Coordinator/Training Manager

Tabs from the deck:

- KPI Strip
- Overview
- Compliance
- Proctoring
- Forecast

This role should focus on operational action: compliance queues, expiring/expired documents, proctoring review lists, and upcoming scheduling load.

### Client Administrator/Manager

Tabs from the deck:

- Overview
- Compliance
- Forecast
- 30/60/90 days
- New staff

This dashboard should be filtered to the manager's own client/company scope. The deck emphasizes department, location, position, and course dimension switchers.

### System Administrator

Tabs from the deck:

- KPI Strip
- Capacity
- Performance
- Forecast
- Error Log
- System Settings

This view should be isolated from normal business dashboards. It needs server metrics, cron/task health, database growth, error diagnostics, and read-only recommended setting checks.

### User Profile Dashboard

This should be a lightweight personal dashboard for the logged-in user:

- Personal compliance status
- Current certificates/documents
- Expiring soon
- Required courses
- Attempt/proctoring records visible to the user
- Links to courses, certificates, and support actions

## Dashboard Request Flow

1. `block_dashboardanalytics.php` renders the block shell.
2. `classes/output/dashboard.php` decides the initial role dashboard using capabilities.
3. `templates/dashboard.mustache` renders the global shell with empty containers.
4. `amd/src/dashboard.js` initializes tabs, filters, cards, and chart containers.
5. JS calls `core/ajax` external functions:
   - `get_bootstrap`
   - `get_filter_options`
   - `get_kpis`
   - `get_chart`
   - `get_drilldown`
6. PHP services validate permissions and call repositories.
7. Repositories query Moodle tables and return normalized arrays.
8. JS renders Chart.js charts and Mustache table fragments.

## Global Filters

Every role except System Administrator should use the same filter object:

```text
companyids[]
departmentids[]
locationids[]
positionids[]
courseids[]
status
datefrom
dateto
search
```

The filter service should translate these into safe SQL parameters. Do not build SQL fragments directly in JavaScript.

Recommended setting fields:

- Company source: cohort, tenant table, custom profile field, or configured mapping table
- Department profile field shortname
- Location profile field shortname
- Position profile field shortname
- Document/certificate table source
- Quilgo/proctoring table source
- Forecast threshold default
- Server metric source table

## Chart/Data Modules

### KPI Strip

Service: `kpi_service`

Return cards with:

```text
key
label
value
unit
status
trend
drilldownkey
explainable_sql_key
```

Each KPI card should be clickable and open a drilldown/explanation panel.

### Overview

Service: `overview_service`

Charts:

- Compliance trend by company
- Compliance comparison by company
- Document status donut
- Department/location compliance heatmap for client managers

### Compliance

Service: `compliance_service`

Charts/tables:

- Expired vs expiring by company/dimension
- Non-compliance by course
- Compliance action table
- EDS/document queue if required by the implementation

### Staff Turnover

Service: `turnover_service`

Charts:

- New vs deactivated staff by month
- Turnover rate by company
- New staff risk list

### Training Quality

Service: `training_quality_service`

Charts:

- First-attempt pass rate by course
- Active time vs session time
- Monthly completion rate by company

### Proctoring

Service: `proctoring_service`

Charts:

- Trust score distribution
- Average trust score by company
- Completion vs trust scatter plot

This module should be capability-gated because proctoring records are sensitive.

### Forecast

Service: `forecast_service`

Charts/cards:

- 30/60/90-day expiry cards
- 13-week scheduling pressure chart
- Revenue projection placeholder if source data exists

### Server

Service: `server_service`

Charts/gauges:

- Disk/RAM/CPU/DB/concurrent users gauges
- 7-day metric sparklines
- Disk usage 13-week forecast
- Error log summary
- Cron/scheduled task status
- Read-only system settings recommendations

## Database Tables

Use Moodle core tables where possible. Add plugin tables only for data the plugin owns.

Likely plugin-owned tables:

```text
block_dashboardanalytics_metrics
block_dashboardanalytics_metric_events
block_dashboardanalytics_tenant_settings
block_dashboardanalytics_prompt_log
```

Use Moodle cache definitions for expensive aggregates:

```text
dashboard_bootstrap
kpi_results
chart_results
filter_options
```

Avoid storing employee drilldown data in plugin tables unless there is a specific audit/caching requirement.

## External API Shape

Use Moodle external functions from `db/services.php`.

```text
block_dashboardanalytics_get_bootstrap
block_dashboardanalytics_get_filter_options
block_dashboardanalytics_get_kpis
block_dashboardanalytics_get_chart
block_dashboardanalytics_get_drilldown
block_dashboardanalytics_get_employee_profile
block_dashboardanalytics_get_server_metric
```

Every endpoint should:

1. Require login.
2. Validate context.
3. Check dashboard capability.
4. Validate and clean parameters.
5. Enforce tenant/company scope server-side.
6. Return aggregate data if identity access is not allowed.

## Frontend Structure

Use Moodle AMD modules:

- `dashboard.js`: bootstraps the dashboard.
- `filters.js`: owns global filters and chips.
- `kpis.js`: loads and refreshes KPI cards.
- `charts.js`: wraps Chart.js setup and chart reuse.
- `drilldowns.js`: handles chart click events and detail panels.
- `tables.js`: sorting, pagination, search.
- `server.js`: live server gauges and threshold sliders.
- `prompt_actions.js`: optional `sendPrompt()` integration from the deck.

Cards should load collapsed by default except the KPI strip, matching the deck.

## Suggested Build Phases

### Phase 1: Plugin Skeleton

- Create `block_dashboardanalytics`
- Add version, language strings, capabilities, settings, and empty dashboard shell
- Add Mustache templates for role tabs, filters, KPI cards, and empty chart cards

### Phase 2: Role Routing and Filters

- Map Moodle roles/capabilities to the five dashboard sections
- Build the global filter bar
- Build `dimension_repository` for company, department, location, position, and course

### Phase 3: KPI and Compliance Foundation

- Implement KPI strip
- Implement document status logic: active, expiring, expired, no document
- Implement compliance action table and drilldowns

### Phase 4: Role-Specific Charts

- Company Owner: overview, compliance, turnover, quality, proctoring, forecast
- Coordinator: overview, compliance, proctoring, forecast
- Client Manager: department/location/position views and heatmap

### Phase 5: Server Dashboard

- Add metric collection task
- Add capacity/performance gauges
- Add disk forecast and error log views
- Add admin-only settings recommendations

### Phase 6: User Profile Dashboard

- Add personal compliance/document/course view
- Keep it small and fast

### Phase 7: Hardening

- Add unit tests for services/repositories
- Add Behat coverage for role visibility and filter behavior
- Add performance checks for large user/course/document sets
- Review privacy provider and named employee access rules

## First Implementation Target

Start with this narrow vertical slice:

1. Plugin installs successfully.
2. Dashboard block appears on `/my/`. 
3. Current user sees the correct role dashboard tabs.
4. Global filters load.
5. KPI strip loads real data.
6. Compliance action table supports drilldown.

Once this slice works, the remaining charts can be added one service at a time without changing the architecture.
