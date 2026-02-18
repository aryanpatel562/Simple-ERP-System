# Supply Chain Management Dashboard

Built for IE332 at Purdue. A web app with two dashboards for tracking supply chain data — one for supply chain managers and one for senior managers. Everything is connected to a live MySQL database and updates in real time.

 **Live Site:** [https://web.ics.purdue.edu/~g1151934/Project/index.php](https://web.ics.purdue.edu/~g1151934/Project/index.php)

---

## What it does

There are two dashboards depending on your role:

**Supply Chain Manager** — search and view individual company data, including KPIs, financial health, disruption metrics, and transaction history. You can edit company info directly from the dashboard and filter almost everything by date range, location, status, and more.

**Senior Manager** — broader view across all companies. Has three main tabs (Distributors, Financials, Disruptions) with charts, tables, and fully custom date filters. You can also create new companies from here and review any company's full data including upstream/downstream dependencies.

---

## Features

### Supply Chain Manager Dashboard
- Company search with live dropdown that filters as you type
- Edit and save company info directly to the database
- KPI cards — On-Time %, Average Delay, Std Dev, Disruption Count
- Financial health line chart with historical trend
- On-Time Delivery radial/gauge chart
- Delay metrics with trend chart
- Disruption Events Distribution bar chart
- Average Recovery Time (ART) histogram
- Total Downtime (TD) histogram
- Regional Risk Concentration (RRC) heatmap
- High-Impact Disruption Rate (HDR) radial chart
- Disruption Severity Distribution (DSD) horizontal stacked bar chart
- Multi-select filters for company and location
- Transaction tables (Shipping, Receiving, Adjustments) with time range and row limit filters
- Shipment Volume bar chart
- On-Time Delivery trend line chart
- Status Mix bar chart (Pending / On Time / Delayed)
- Exposure by Lane bar chart
- Alerts sidebar with color-coded severity badges

### Senior Manager Dashboard
- Full company info review with upstream/downstream dependencies
- Companies ranked by criticality score
- Distributors tab: top distributors by volume, high-impact disruption counts, average shipment delay, supply chain resilience KPI
- Financials tab: financial health dial, average health by company, by company type, and by region
- Disruptions tab: frequency over time, regional overview, event-specific company impact, company-specific disruption history
- Create new company form with dynamic location dropdown (only shows valid locations from the database)
- Logout button that properly ends the PHP session

### Extra stuff we added
- Bar graphs on the Senior Manager side are downloadable as images
- Hover tooltips on every chart
- Zoom and click-and-drag on line graphs
- Reset filters button on most tabs
- Calendar date picker for date filters
- Date range filters are cascading — you literally cannot select an invalid range, so charts never render empty
- Works on mobile

---

## Tech used

- PHP + MySQL for the backend
- HTML, CSS, JavaScript for the frontend
- ApexCharts for all graphs
- Hosted on the Purdue ICS web server

---

## Project structure

```
/Project
├── index.php                          # Login page
├── supplychainmanager.php             # Supply Chain Manager Dashboard
├── seniormanager.php                  # Senior Manager Dashboard
├── supply chain manager queries.php  # SCM SQL query handlers
└── seniormanagerqueries.php          # Senior Manager SQL query handlers
```

---

## Login

| Dashboard | Username | Password |
|---|---|---|
| Supply Chain Manager | `John_D` | `password` |
| Senior Manager | `Jane_D` | `password` |

---

## Database

- MySQL hosted on Purdue ICS (`g1151934`)
- Full SQL export included separately with the assignment submission

---

## Key SQL queries

### Supply Chain Manager
- `getCompanyInfo` — core company data
- `getKPIs` — on-time %, delay stats, disruption count
- `getOnTimeDelivery` — radial chart data
- `getDelayMetrics` — avg & std dev with trend over time
- `getDisruptionDistribution` — event breakdown by type
- `getART` — Average Recovery Time by region/supplier
- `getTD` — Total Downtime by region/supplier
- `getRRC` — Regional Risk Concentration
- `getHDR` — High-Impact Disruption Rate
- `getDSD` — Disruption Severity Distribution
- `getTransactions` — filtered transaction history
- `getShipmentVolume` — monthly/quarterly volume
- `getOnTimeChart` — on-time trend over time
- `getExposureByLane` — shipping lane exposure scores

### Senior Manager
- Company search, upstream/downstream dependencies, financial health, capacity routes
- Top distributors by volume, high-impact disruption counts, average shipment delay
- Financial scores by company, type, and region
- Disruption frequency over time, regional overview, event-specific and company-specific disruption history

---

## Testing

We wrote and ran 60+ test cases across both dashboards. Things we tested: authentication and URL tamper protection, all filter combos (date ranges, dropdowns, multi-select), chart data accuracy, mobile and half-screen layouts, edit/save flows, and edge cases like empty data ranges, invalid inputs, and rapid repeated clicks. Everything was passing by December 10.

---

## Team

Built collaboratively by a team of 6 students for IE332 – Computing in Industrial Engineering, Purdue University (West Lafayette, IN).

**ERP & Supply Chain Analytics Project**
