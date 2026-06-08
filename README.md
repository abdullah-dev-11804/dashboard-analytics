# Dashboard Analytics Moodle Block

This repository is the plugin root for `block_dashboardanalytics`.

Install location:

```text
moodle/blocks/dashboardanalytics
```

The current implementation slice includes:

- Moodle block metadata and settings
- Capability definitions for the three active dashboards
- Role routing by capability and by configured role shortname
- Dashboard shell for `/my/`
- Global filters from cohorts, departments, locations, positions, and courses
- KPI strip backed by Moodle users/courses/completions and an optional document table
- Compliance drilldown endpoint and table shell

The active dashboard routing is:

- Company Dashboard: site admins and `companyowner`
- Client Dashboard: `trainingmanager`
- Employee Dashboard: ordinary logged-in users

After installing the plugin, add the block to the Dashboard page and configure the document table settings once the certificate/document source table is confirmed.
