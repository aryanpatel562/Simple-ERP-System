<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Not logged in - redirect to login page
    header("Location: index.php");
    exit();
}

// Check if user has correct role
if ($_SESSION['role'] !== 'SupplyChainManager') {
    // Wrong role - redirect to login page
    $_SESSION['login_error'] = 'Access denied. Supply Chain Manager access required.';
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Supply Chain Manager Dashboard</title>

  <!-- Icons + fonts -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">

  <!-- App CSS -->
  <link rel="stylesheet" href="dashboard.css">

  <!-- ApexCharts -->
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body>

  <!-- TOP BAR -->
  <header class="topbar" role="banner">
    <div class="topbar-inner">
      <div class="app-title">Supply chain manager dashboard</div>

      <nav class="tabs" aria-label="Primary">
        <a class="tab active" href="#company" data-tab="company">Company info</a>
        <a class="tab" href="#events" data-tab="events">Disruption events</a>
        <a class="tab" href="#transactions" data-tab="transactions">Transactions</a>
      </nav>

      <div class="spacer"></div>
      <div class="text-start">
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

      <div class="search-wrap" id="companySearchWrap">
        <div class="search-container">
          <div class="search" aria-label="Company Search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input id="companySearch" type="search" placeholder="Company search…" autocomplete="off" />
          </div>
          <button id="searchBtn" class="search-btn">
            <i class="fa-solid fa-search"></i>
          </button>
          <div id="searchDropdown" class="search-dropdown"></div>
        </div>
      </div>
    </div>
  </header>

  <!-- MAIN GRID -->
  <div class="wrap">

    <!-- ALERTS SIDEBAR -->
    <aside class="alerts" aria-label="Alerts">
      <h3>Alerts</h3>
      <div id="alertsContainer">
        <!-- Will be populated dynamically -->
      </div>
    </aside>

    <!-- COMPANY VIEW -->
    <section id="view-company" class="view active">
      <section class="content">

        <!-- COMPANY INFO CARD -->
        <article class="card company-card">

          <div class="company-header">
            <h4>Company information</h4>
            <button class="btn-pill" id="editCompanyBtn">
              <i class="fa-solid fa-pen"></i>
              Edit
            </button>
          </div>

          <div class="company-name" id="companyName">Loading...</div>

          <!-- Address -->
          <div class="company-section">
            <div class="section-label">Address</div>
            <div class="company-value" id="companyAddress">Loading...</div>
          </div>

          <!-- Type & Tier -->
          <div class="company-row">
            <div class="company-section half">
              <div class="section-label">Company type</div>
              <div class="badge-info" id="companyType">Loading...</div>
            </div>
            <div class="company-section half">
              <div class="section-label">Tier level</div>
              <div class="badge-info tier" id="tierLevel">Loading...</div>
            </div>
          </div>

          <!-- Dependencies -->
          <div class="company-section">
            <div class="section-label">Dependencies</div>

            <div class="dependencies">
              <div>
                <small class="dep-label">Depends on (suppliers)</small>
                <ul id="dependsOn">
                  <li>Loading...</li>
                </ul>
              </div>

              <div>
                <small class="dep-label">Depended on by (customers)</small>
                <ul id="dependedOnBy">
                  <li>Loading...</li>
                </ul>
              </div>
            </div>
          </div>

          <!-- Financial Status -->
          <div class="company-section">
            <div class="section-label">Most recent financial status</div>

            <div class="financial-row" id="financialStatus">
              <span class="financial-badge good">Loading...</span>
            </div>
          </div>

          <!-- Capacity / Routes -->
          <div class="company-section">
            <div class="section-label" id="capacityLabel">Unique routes operated</div>
            <div id="capacityValue" class="capacity-row">
              <span class="capacity-num">-</span>
              <span class="capacity-desc">Loading...</span>
            </div>
          </div>

          <!-- Products -->
          <div class="company-section">
            <div class="section-label">Products supplied</div>

            <div class="product-tags" id="productsList">
              <!-- Will be populated dynamically -->
            </div>

            <div class="product-diversity" id="productDiversity">
              <i class="fa-solid fa-chart-pie"></i>
              Loading...
            </div>
          </div>

          <!-- Transactions summary -->
          <div class="company-section">
            <div class="section-label" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
              <span>Recent transactions</span>
              <div style="display: flex; gap: 8px; align-items: center;">
                <select id="txDaysFilter" class="chip" style="font-size: 11px; padding: 4px 8px;">
                  <option value="7">Last 7 days</option>
                  <option value="30" selected>Last 30 days</option>
                  <option value="90">Last 90 days</option>
                  <option value="180">Last 180 days</option>
                  <option value="365">Last year</option>
                  <option value="3650">All time</option>
                </select>
                <select id="txLimitFilter" class="chip" style="font-size: 11px; padding: 4px 8px;">
                  <option value="5">Show 5</option>
                  <option value="10" selected>Show 10</option>
                  <option value="20">Show 20</option>
                  <option value="50">Show 50</option>
                  <option value="999999">Show All</option>
                </select>
              </div>
            </div>

            <div class="tx-block">
              <div class="tx-header"><i class="fa-solid fa-truck"></i> Shipping</div>
              <div class="tx-table-header">
                <span>ID</span>
                <span>Date</span>
                <span>To</span>
                <span>Status</span>
              </div>
              <ul class="tx-list" id="shippingList">
                <li>Loading...</li>
              </ul>
            </div>

            <div class="tx-block">
              <div class="tx-header"><i class="fa-solid fa-box"></i> Receiving</div>
              <div class="tx-table-header">
                <span>ID</span>
                <span>Date</span>
                <span>From</span>
                <span>Status</span>
              </div>
              <ul class="tx-list" id="receivingList">
                <li>Loading...</li>
              </ul>
            </div>

            <div class="tx-block">
              <div class="tx-header"><i class="fa-solid fa-wrench"></i> Adjustments</div>
              <div class="tx-table-header">
                <span>ID</span>
                <span>Date</span>
                <span>Reason</span>
                <span>Status</span>
              </div>
              <ul class="tx-list" id="adjustmentsList">
                <li>Loading...</li>
              </ul>
            </div>
          </div>

          <div class="company-actions">
            <button class="btn-pill primary" id="saveCompanyBtn">
              <i class="fa-solid fa-save"></i>
              Save changes
            </button>
          </div>

        </article>

        <!-- FINANCIAL HEALTH (PAST YEAR) UNDER COMPANY INFO -->
        <article class="card financial-health-card">
          <div class="card-header-row">
            <h4>Financial health (past year)</h4>
            <select id="finRangeSelect" class="chip">
              <option value="3650" selected>All time</option>
              <option value="365">Past year</option>
              <option value="730">Past 2 years</option>
            </select>
          </div>
          <div id="financialChart"></div>
        </article>

        <!-- KPIs WITHIN DATE RANGE – WIDE CARD -->
        <article class="card kpi-card">
          <div class="card-header-row">
            <h4>KPIs within date range</h4>
            <select id="kpiRangeSelect" class="chip">
              <option value="3650" selected>All time</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="365">Past year</option>
            </select>
          </div>

          <div class="kpi-summary">
            <div class="kpi-item">
              <div class="kpi-label">On-time %</div>
              <div class="kpi-value" id="kpiOnTime">-</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">Avg delay</div>
              <div class="kpi-value" id="kpiAvgDelay">-</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">Std dev</div>
              <div class="kpi-value" id="kpiStdDelay">-</div>
            </div>
            <div class="kpi-item">
              <div class="kpi-label">Disruptions</div>
              <div class="kpi-value" id="kpiDisruptions">-</div>
            </div>
          </div>
        </article>

        <!-- ON-TIME DELIVERY RATE (RADIAL) -->
        <article class="card ontime-card">
          <div class="card-header-row">
            <h4>On-time delivery rate</h4>
            <select id="onTimeRangeSelect" class="chip">
              <option value="3650" selected>All time</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="365">Past year</option>
            </select>
          </div>
          <div id="onTimeChart"></div>
        </article>

        <!-- AVG & STD DEV DELAY (KPI + SMALL TREND) -->
        <article class="card delay-card">
          <div class="card-header-row">
            <h4>Average &amp; std dev delay</h4>
            <select id="delayTimeSelect" class="chip">
              <option value="3650" selected>All time</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
              <option value="180">Last 180 days</option>
              <option value="365">Past year</option>
            </select>
          </div>

          <div class="kpi-delay">
            <div class="metric">
              <small>Average delay</small>
              <b id="avgDelayKPI">-</b>
            </div>
            <div class="metric">
              <small>Std deviation</small>
              <b id="stdDelayKPI">-</b>
            </div>
          </div>

          <div id="delayTrendChart" class="small-chart"></div>
        </article>

        <!-- DISTRIBUTION OF DISRUPTION EVENTS (PAST YEAR) -->
        <article class="card disruption-card">
          <div class="card-header-row">
            <h4>Disruption events (past year)</h4>
            <select id="disruptionRange" class="chip">
              <option value="3650" selected>All time</option>
              <option value="90">Last 90 days</option>
              <option value="180">Last 180 days</option>
              <option value="365">Last 365 days</option>
            </select>
          </div>
          <div id="disruptionEventsChart" class="small-chart"></div>
        </article>

      </section>
    </section>

    <!--DISRUPTION EVENTS VIEW -->
    <section id="view-events" class="view">

  <div class="filters">
    <span class="label">Filter selection:</span>

    <!-- Multi-select companies chip -->
    <div class="chip multiselect" id="companyChip">
      <span id="companyChipLabel">All Companies</span>
      <i class="fa-solid fa-caret-down"></i>

      <!-- actual checkbox list lives inside here -->
      <div class="multiselect-menu" id="f-company">
        <button id="deselectAllBtn" class="deselect-btn">Deselect All</button>
        <!-- checkboxes get inserted here dynamically -->
      </div>
    </div>

    <select class="chip" id="f-region">
      <option value="">All Regions</option>
    </select>
    <select class="chip" id="f-tier">
      <option value="">All Tiers</option>
      <option value="1">Tier 1</option>
      <option value="2">Tier 2</option>
      <option value="3">Tier 3</option>
    </select>
    <select class="chip" id="f-period">
      <option value="3650" selected>All time</option>
      <option value="30">Last 30d</option>
      <option value="90">Last 90d</option>
      <option value="365">Past Year</option>
    </select>
  </div>

  <div class="grid-4">

    <!-- DF -->
    <article class="card">
      <h4>Disruption Frequency (DF)</h4>
      <div id="dfChart"></div>
      <div class="kpi-note">Number of disruption events per period.</div>
    </article>

    <!-- ART -->
    <article class="card">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
        <h4 style="margin: 0;">Avg Recovery Time (ART)</h4>
        <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
          <select id="artGroupBy" class="chip">
            <option value="supplier">By Supplier</option>
            <option value="region">By Region</option>
          </select>
          <select id="artSelect" class="chip">
            <option value="all">All Types</option>
            <option value="Natural">Natural Disaster</option>
            <option value="Supply">Supply Shortage</option>
            <option value="Transport">Transportation</option>
            <option value="Political">Political</option>
            <option value="Cyber">Cyber Attack</option>
          </select>
        </div>
      </div>
      <div id="artChart"></div>
      <div class="kpi-note">Average time to fully recover from events.</div>
    </article>

    <!-- TD -->
    <article class="card">
      <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
        <h4 style="margin: 0;">Total Downtime (TD)</h4>
        <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-end;">
          <select id="tdGroupBy" class="chip">
            <option value="supplier">By Supplier</option>
            <option value="region">By Region</option>
          </select>
          <select id="tdSelect" class="chip">
            <option value="all">All Types</option>
            <option value="Natural">Natural Disaster</option>
            <option value="Supply">Supply Shortage</option>
            <option value="Transport">Transportation</option>
            <option value="Political">Political</option>
            <option value="Cyber">Cyber Attack</option>
          </select>
        </div>
      </div>
      <div id="tdChart"></div>
      <div class="kpi-note">Aggregates downtime across all disruptions for selected view.</div>
    </article>

    <!-- RRC -->
    <article class="card">
      <div class="card-header-row">
        <h4>Regional Risk Concentration (RRC)</h4>
      </div>
      <div id="rrcChart"></div>
      <div class="kpi-note">% of disruptions affecting each region. Note: Totals exceed 100% because disruptions often affect multiple regions.</div>
    </article>


  <!-- HDR -->
  <div class="hdr-chart">
  <article class="card">
    <h4>High-Impact Disruption Rate (HDR)</h4>
    <div id="hdrChart"></div>
    <div class="kpi-note">Percentage of high impact disruptions out of all disruptions.</div>
  </article>
  </div>
  <!-- DSD -->
  <div class="dsd-chart">
  <article class="card">
    <h4>Disruption Severity Distribution (DSD)</h4>
    <div id="dsdChart"></div>
    <div class="kpi-note">Counts of disruptions and their severities.</div>
  </article>
  </div>
</section>

    <!-- TRANSACTIONS VIEW -->
<section id="view-transactions" class="view">

  <!-- FILTERS -->
  <div class="filters">
    <span class="label">Filter selection:</span>

    <select id="txTimeRange" class="chip">
      <option value="3650" selected>All time</option>
      <option value="30">Last 30 days</option>
      <option value="90">Last 90 days</option>
      <option value="180">Last 180 days</option>
      <option value="365">Past Year</option>
    </select>

    <!-- Multi-select locations chip -->
    <div class="chip multiselect" id="locationChip">
      <span id="locationChipLabel">All Locations</span>
      <i class="fa-solid fa-caret-down"></i>

      <!-- actual checkbox list lives inside here -->
      <div class="multiselect-menu" id="txLocation">
        <button id="deselectAllLocationsBtn" class="deselect-btn">Deselect All</button>
        <!-- checkboxes get inserted here dynamically -->
      </div>
    </div>

    <!-- Multi-select shipping companies chip -->
    <div class="chip multiselect" id="shippingCompanyChip">
      <span id="shippingCompanyChipLabel">All Shipping Companies</span>
      <i class="fa-solid fa-caret-down"></i>

      <!-- actual checkbox list lives inside here -->
      <div class="multiselect-menu" id="txShippingCompany">
        <button id="deselectAllShippingBtn" class="deselect-btn">Deselect All</button>
        <!-- checkboxes get inserted here dynamically -->
      </div>
    </div>

    <!-- Multi-select receiving companies chip -->
    <div class="chip multiselect" id="receivingCompanyChip">
      <span id="receivingCompanyChipLabel">All Receiving Companies</span>
      <i class="fa-solid fa-caret-down"></i>

      <!-- actual checkbox list lives inside here -->
      <div class="multiselect-menu" id="txReceivingCompany">
        <button id="deselectAllReceivingBtn" class="deselect-btn">Deselect All</button>
        <!-- checkboxes get inserted here dynamically -->
      </div>
    </div>

    <select id="txStatus" class="chip">
      <option value="ALL">All statuses</option>
      <option value="Pending">Pending</option>
      <option value="OnTime">On Time</option>
      <option value="Delayed">Delayed</option>
    </select>

    <!-- Apply and Reset Buttons -->
    <button id="applyTransactionFilters" class="btn-pill" style="margin-left: auto; background-color:green">
      <i class="fa-solid fa-check"></i>
      Apply Filters
    </button>
    <button id="resetTransactionFilters" class="btn-pill" style="margin-left: auto;">
      <i class="fa-solid fa-rotate-right"></i>
      Reset Filters
    </button>
  </div>

  <!-- TRANSACTION TABLE -->
<div class="table-container-vertical">
  <div class="table-wrap">
    <!-- Fixed Header -->
    <table class="table-header">
      <thead>
        <tr>
          <th>Transaction</th>
          <th>Date</th>
          <th>Location</th>
          <th>Shipping Company</th>
          <th>Receiving Company</th>
          <th>Status</th>
          <th>Exposure</th>
          <th>Actions</th>
        </tr>
      </thead>
    </table>
    
    <!-- Scrollable Body -->
    <div class="table-body-scroll">
      <table class="table-body">
        <tbody id="txBody">
          <tr><td colspan="8" style="text-align:center;">Loading...</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

  <!-- TRANSACTION CHARTS - 2x2 Grid -->
  <div class="grid-2x2">

    <!-- Shipment Volume -->
    <article class="card" style= "height:290px">
      <h4>Shipment Volume</h4>
      <div id="txVolumeChart"></div>
    </article>

    <!-- On-Time Delivery Rate -->
    <article class="card" style= "height:290px">
      <h4>On-Time Delivery</h4>
      <div id="txOnTimeChart"></div>
    </article>

    <!-- Status Mix -->
    <article class="card" style= "height:270px" id="statusMixCard">
      <h4>Shipment Status Mix</h4>
      <div id="txStatusChart"></div>
    </article>
    <!-- Exposure vid -->
    <article class="card" style= "height:270px">
      <h4>Disruption Exposure by Lane</h4>
    <div id="txExposureChart"></div>
    </article>  

  </div>
</section>

  </div>

  <!-- External JavaScript -->
  <script src="scmanager.js"></script>
</body>
</html>