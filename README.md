# Supply Chain Management Dashboard
> IE332 Assignment #3 — Purdue University

A full-stack web application providing interactive dashboards for supply chain management, built with PHP, MySQL, JavaScript (ApexCharts), HTML, and CSS.

🌐 **Live Site:** [https://web.ics.purdue.edu/~g1151934/Project/index.php](https://web.ics.purdue.edu/~g1151934/Project/index.php)

---

## Overview

This project delivers two role-based dashboards for monitoring and managing supply chain operations:

- **Supply Chain Manager Dashboard** — Company-level insights including KPIs, disruptions, transactions, and financial health.
- **Senior Manager Dashboard** — Cross-company analytics covering distributors, financials, and disruption trends.

---

## Features

### Supply Chain Manager Dashboard
- Company search with live dropdown filtering
- Company info display with edit/save functionality
- KPI summary cards (On-Time %, Average Delay, Std Dev, Disruption Count)
- Financial health line chart with historical trends
- On-Time Delivery radial/gauge chart
- Delay metrics with trend chart
- Disruption Events Distribution bar chart
- Average Recovery Time (ART) histogram
- Total Downtime (TD) histogram
- Regional Risk Concentration (RRC) heatmap
- High-Impact Disruption Rate (HDR) radial chart
- Disruption Severity Distribution (DSD) stacked horizontal bar chart
- Multi-select company and location filters
- Transaction tables (Shipping, Receiving, Adjustments) with time range and limit filters
- Shipment Volume bar chart
- On-Time Delivery trend line chart
- Status Mix bar chart
- Exposure by Lane bar chart
- Alerts sidebar with severity badges

### Senior Manager Dashboard
- Review all company data with upstream/downstream dependencies
- Criticality score ranking table
- Distributors tab: shipment volume, disruption counts, delay charts, supply chain resilience KPI
- Financials tab: financial dial, average health by company/type/region
- Disruptions tab: frequency over time, regional overview, event-specific company impact, company-specific disruption history
- Create new company with dynamic location dropdown
- Custom date range filters with cascading validation (impossible to select invalid ranges)
- Logout / session management

### Extra Features
- Downloadable bar graphs (Senior Manager Dashboard)
- Hover tooltips on all charts
- Zoom / click-and-drag on line graphs
- Reset filters to default
- Calendar date picker for filters
- Mobile-responsive layout
- Supply chain resilience score KPI

---

## Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, JavaScript, ApexCharts |
| Backend | PHP |
| Database | MySQL |
| Hosting | Purdue ICS Web Server |

---

## Project Structure

```
/Project
├── index.php                    # Login page
├── supplychainmanager.php       # Supply Chain Manager Dashboard
├── seniormanager.php            # Senior Manager Dashboard
├── supply chain manager queries.php   # SCM SQL query handlers
└── seniormanagerqueries.php     # Senior Manager SQL query handlers
```

---

## Login Credentials

| Dashboard | Username | Password |
|---|---|---|
| Supply Chain Manager | `John D` | `password` |
| Senior Manager | `Jane D` | `password` |

---

## Database

- **MySQL Username:** `g1151934`
- **Host:** Purdue ICS

> A full SQL export of the database schema and data is included as a separate file with the assignment submission.

---

## Key SQL Queries

### Supply Chain Manager
- `getCompanyInfo` — Fetches core company data
- `getKPIs` — On-time %, delay stats, disruption count
- `getOnTimeDelivery` — Radial chart data
- `getDelayMetrics` — Avg & std dev delay with trend
- `getDisruptionDistribution` — Event breakdown by type
- `getART` — Average Recovery Time by region/supplier
- `getTD` — Total Downtime by region/supplier
- `getRRC` — Regional Risk Concentration
- `getHDR` — High-Impact Disruption Rate
- `getDSD` — Disruption Severity Distribution
- `getTransactions` — Filtered transaction history
- `getShipmentVolume` — Monthly/Quarterly volume
- `getOnTimeChart` — On-time trend over time
- `getExposureByLane` — Shipping lane exposure scores

### Senior Manager
- Company search, dependencies, financial health, capacity routes
- Top distributors by volume, high-impact disruption counts, average shipment delay
- Financial scores by company, type, and region
- Disruption frequency, regional overview, event-specific and company-specific disruption data

---

## Testing

Over 60 documented test cases were executed across both dashboards covering:

- Authentication and URL tamper protection
- Filter functionality (date ranges, dropdowns, multi-select)
- Chart rendering and data accuracy
- Mobile and half-screen responsiveness
- Edit/save database operations
- Edge cases (empty data, invalid ranges, repeated clicks)

All test cases passed by the final submission date (December 10).

---

## Team

This project was built collaboratively by a team of 6 students, each contributing to different areas including frontend development, backend/SQL queries, chart integration, testing, and video production.

---

## Course

**ERP & Supply Chain Analytics Project** — West Lafayette, IN
IE 332 – Computing in Industrial Engineering
