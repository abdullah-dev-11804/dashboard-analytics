# Dashboard Analytics Moodle Block

This repository is the plugin root for `block_dashboardanalytics`.

Install location:

```text
moodle/blocks/dashboardanalytics
```

The first implementation slice includes:

- Moodle block metadata and settings
- Capability definitions
- Role routing by capability and by configured role shortname
- Dashboard shell for `/my/`
- Global filters from cohorts, departments, locations, positions, and courses
- KPI strip backed by Moodle users/courses/completions and an optional document table
- Compliance drilldown endpoint and table shell

The custom roles already created on the site can route immediately when their shortnames match the plugin settings:

- `companyowner`
- `companycoordinator`
- `trainingmanager`
- `clientadministrator`
- `manager`
- `systemadministrator`

After installing the plugin, add the block to the Dashboard page and configure the document table settings once the certificate/document source table is confirmed.
