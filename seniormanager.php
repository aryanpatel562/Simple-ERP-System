<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['role'])) {
    // Not logged in - redirect to login page
    header("Location: index.php");
    exit();
}

// Check if user has correct role
if ($_SESSION['role'] !== 'SeniorManager') {
    // Wrong role - redirect to login page
    $_SESSION['login_error'] = 'Access denied. Senior Manager access required.';
    header("Location: index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Senior Manager Dashboard - Test</title>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
  
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
  
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

  <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
  <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />

  <link rel="stylesheet" href="seniormanager.css">
  <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</head>
<body class="sm-dashboard">

  <header class="topbar" role="banner">
    <div class="topbar-inner">
      <div class="app-title">Senior manager dashboard</div>

      <nav class="tabs" aria-label="Primary">
        <a class="tab active" href="#top" data-tab="top">Distributors</a>
        <a class="tab" href="#financials" data-tab="financials">Financials</a>
        <a class="tab" href="#disruptions" data-tab="disruptions">Disruptions</a>
      </nav>

      <div class="spacer"></div>
      <div class="text-start">
        <a href="logout.php" class="btn btn-danger">Logout</a>
    </div>

    </div>
  </header>

  <div class="wrap">

    <!-- Actions Column -->
    <aside class="alerts" aria-label="Actions">
      <h4>Actions</h4>

      <button class="alert" type="button" data-action="create-company">
        <span class="badge good"></span>
        <div>
          <h3>Create New Company</h3>

        </div>
      </button>
      
      <button class="alert" type="button" data-action="review-companies">
        <span class="badge warn"></span>
        <div>
          <h3>Review all Company Data</h3>
          </div>
      </button>

      <button class="alert" type="button" data-action="review-critical">
        <span class="badge bad"></span>
        <div>
          <h3>Review critical suppliers</h3>
        </div>
      </button>

    </aside>
<!-- Distributors view tab -->
 <section id="view-top" class="view active">
  <section class="content distributors-grid">
    <!-- Date filters -->
    <div class="filter-bar">
      <label>Start Date:</label>
      <input type="date" id="startdate">
      <label>End Date:</label>
      <input type="date" id="enddate">
      <button onclick="onclickDatesOverview()">Load</button>
      <button onclick="setDefaultDates()">Reset: ALL TIME</button>
    </div>
<!-- ROW 1: tables -->
    
    <div class="overview-row-1">
      <!-- Left: Top distributors -->
      <article class="card table-card">
        <h4>Top Distributors by Volume</h4>
        <div class="table-scroll">
          <table id="top10-table">
            <thead>
              <tr class="bg-primary bg-gradient text-white" style= "font-size: 11px; text-transform: uppercase;">
                <th>#</th>
                <th>Company</th>
                <th>Volume</th>
                <th>On-time%</th>
              </tr>
            </thead>
            <tbody id="distributorsvolume"></tbody>
          </table>
        </div>
      </article>

      <!-- Right: Resilience Score  -->
      <article class="card table-card">
        <h4>Distributors Supply Chain Resilience Score</h4>
        <div class="section-label">
          Supply Chain Resilience Score is 1/(1 + (Average Delay Length of Shipments)*(Count of High Impact Frequencies)) and shows a companies ability to recover quickly
        </div>
        <div class="table-scroll table-scroll-tall">
          <table>
            <thead>
              <tr style= "font-size: 11px; text-transform: uppercase;">
                <th>Distributor</th>
                <th>Supply Chain Resilience Score</th>
              </tr>
            </thead>
            <tbody id="distributorsresilience"></tbody>
          </table>
        </div>
      </article>
    </div>

    <!-- Row 2: charts -->
    <div class="overview-row-2">
      <article class="card chart-card">
        <h4>High Impact disruption count across top distributors by volume</h4>
        <div class="chart-container">
          <div class="chart-scroll">
          <div id="yearlyDisruptionsChart"></div>
        </div>
        </div>
      </article>

      <article class="card chart-card">
        <h4>Average shipment delay of all distributors (Days)</h4>
        <div class="chart-container">
          <div class="chart-scroll">
          <div id="DelayedDistributorsChart"></div>
        </div>
        </div>
      </article>
    </div>

  </section>
</section>

<!-- ===== Finanacials Views Tab ===== -->
    <section id="view-financials" class="view">
      <section class="content financials-grid">
       <div class="filter-bar">
        <label>Start Date:</label>
        <select id="quarterSelectStart">
          </select>
        <select id="yearSelectStart">
        </select>
        <label>End Date:</label>
        <select id="quarterSelectEnd">
          </select>
        <select id="yearSelectEnd">
        </select>
        <button onclick="onclickQuarterFinancial()">Load</button> 
        <button onclick="setDefaultQuarter()">Reset: CURRENT QUARTER</button> 
      </div>
             

<article class="card table-card financials-table">
  <h4>Average Financial Score by Region</h4>
  <div class="financials-table-wrapper">
    <table>
      <thead>
        <tr class="bg-primary bg-gradient text-white" style= "text-transform: uppercase; font-size: 11px">
          <th>Region</th>
          <th>Score</th>
        </tr>
      </thead>
      <tbody id="regionfinancials"></tbody>

    <thead id="companyHeaderRow">
    <tr class="bg-primary bg-gradient text-white" style= "text-transform: uppercase; font-size: 11px">
      <th>Company</th>
      <th>Score</th>
    </tr>
  </thead>
  <tbody id="regionfinancialsdropdown"></tbody>


    </table>
  </div>
</article>

<article class="card table-card financials-score">
  <h4>Financial Score Across All Companies</h4>

  <div class="gauge-container">
    <canvas id="gaugeChart"></canvas>
    <div class="financial-dial-text" id="financialscore"></div>
  </div>
</article>



<article class="card chart-card financials-chart-left">
  <h4>Average Financial Health by Company</h4>

  <div class="chart-container">
    <div class="chart-scroll">
      <div id="FinancialByCompChart"></div>
    </div>
  </div>
</article>


<article class="card chart-card financials-chart-right">
  <h4>Average Financial Health by Company Type</h4>

  <div class="chart-container">
    
      <div id="FinancialByCompTypeChart"></div>
  </div>  
</article>
 </section>
</section>



    <!-- ========== Disruptions View Tab ========== -->
    <section id="view-disruptions" class="view">
      <section class="content disruptions-grid">

        <!-- Regionals -->
        <article class="card dis-regions-card">
          <h4>Regional Disruptions</h4>

          <div class="dis-toggle-row">
            <button class="dis-toggle active" type="button" data-mode="total">Total</button>
            <button class="dis-toggle" type="button" data-mode="high">High Impact</button>

            <div class="regional-date-container" style="margin-left:auto;">
              <label>Start Date:</label>
              <input type="date" id="regionalStart" />
              <label>End Date:</label>
              <input type="date" id="regionalEnd" />
              <button id="regionalLoadBtn">Load</button>
            </div>
          </div>

          <div id="regionalDisruptionsChart"></div>
        </article>

        <!-- EVENT → AFFECTED COMPANIES -->
        <article class="card dis-events-card">
          <div class="dis-events-header-row">
            <h4>Companies affected by disruption event:</h4>
          </div>
          <div class="dis-events-header-row">
            <select id="disruptionEventSelect" style="max-width: 300px;">
              <option value="">Select an Event...</option>
            </select>
          </div>

          <div class="dis-events-body" style="overflow-y: auto; max-height: 400px;">
            <div class="table-scroll">
            <table id="eventCompaniesTable">
              <thead>
                <tr class="bg-primary bg-gradient text-white" style= "font-size: 11px; text-transform: uppercase;">
                  <th>Company</th>
                  <th>Impact Level</th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="2">Select an event to view affected companies.</td></tr>
              </tbody>
            </table>
            </div>
          </div>
        </article>

        <!-- AVERAGE DISRUPTIONS (UPDATED — DATE RANGE) -->
        <article class="card dis-bottom-wide">
          <div class="dis-bottom-header-row">
            <h4>Daily disruptions</h4>
            <div class="avg-date-container">
              <label>Start Date:</label>
              <input type="date" id="avgDisStart" />
              <label>End Date:</label>
              <input type="date" id="avgDisEnd" />
              <button id="avgDisLoadBtn">Load</button>
            </div>
          </div>

          <div id="avgDisChart"></div>
        </article>

        <!-- COMPANY DISRUPTIONS (UPDATED — DROPDOWN) -->
        <article class="card dis-company-card">
          <div class="dis-company-header-row">
            <h4>All disruptions for a specific company:</h4>
          </div>

          <div class="dis-company-header-row">
             <select id="companyDropdown" style="max-width: 300px;"></select>
          </div>

          <div class="dis-company-table-wrapper">
            <table id="companyDisruptionsTable">
              <thead>
                <tr class="bg-primary bg-gradient text-white" style= "font-size: 11px; text-transform: uppercase;">
                  <th>Category</th>
                  <th>Start</th>
                  <th>Recovery</th>
                  <th>Impact</th>
                </tr>
              </thead>
              <tbody>
                <tr style="text-transform: uppercase; font-size: 11px;">
                  <td colspan="4">Select a company to view disruptions.</td>
                </tr>
              </tbody>
            </table>
          </div>
        </article>

      </section>
    </section>

  </div>

  <!-- Allows user to input their own company  -->
  <div class="modal-backdrop" id="companyModal">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="companyModalTitle">
      
      <div class="modal-header">
        <div class="modal-title" id="companyModalTitle">Create / edit company</div>
        <button class="btn btn-secondary" type="button" data-close="company-modal">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

          <div class="form-field">
            <label for="companyName">Company name</label>
            <input type="text" id="companyName" name="companyName" autocomplete="off">
          </div>

          <div class="form-field">
            <label for="locationId">Location ID</label>
            <select id="locationId" name="locationId">
            <option value=""></option>
            </select>
          </div>

          <div class="form-field">
            <label for="tierLevel">Tier level</label>
            <select id="tierLevel" name="tierLevel">
              <option value="">Select tier</option>
              <option value="1">Tier 1</option>
              <option value="2">Tier 2</option>
              <option value="3">Tier 3</option>
            </select>
          </div>

          <div class="form-field">
            <label for="serviceType">Service</label>
            <select id="serviceType" name="serviceType">
              <option value="">Select service</option>
              <option value="Manufacturer">Manufacturer</option>
              <option value="Distributor">Distributor</option>
              <option value="Retailer">Retailer</option>
            </select>
          

          <div class="error-text" id="companyFormError"></div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-secondary" type="button" data-close="company-modal">Cancel</button>
          <button class="btn btn-primary" type="button" onclick="onclickSaveComp()">Save</button>
        </div>
        </div>

  </div>
<!-- After the user creates their company this will pop-up and tell them their new company ID -->
<div class="modal-backdrop" id="successModal">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <div class="modal-title">Company Created</div>
      <button class="btn btn-secondary" type="button" data-close="success-modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>

    <div class="modal-body">
      <p>Your new Company ID is:</p>
      <h2 id="newCompanyID" style="margin-top:8px;"></h2>
    </div>

    <div class="modal-footer">
      <button class="btn btn-primary" type="button" data-close="success-modal">OK</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="criticalModal">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <div class="modal-title">Review Critical Companies</div>
        <div class="modal-subtitle">Most to least critical</div>
    </div>
        <div class="modal-body">
        <article class="card">
        <div class="table-scroll table-scroll-tall">
          <table>
            <thead>
              <tr class="bg-primary bg-gradient text-white" style= "font-size:12px; text-transform: uppercase;" >
                <th>Company</th>
                <th>Criticality</th>
              </tr>
            </thead>
            <tbody id="mostcritcompanies"></tbody>
          </table>
        </div>
      </article>
        </div>

    <div class="modal-footer">
      <button class="btn btn-primary" type="button" data-close="critical-modal">OK</button>
    </div>
  </div>
</div>

<div class="modal-backdrop" id="companiesModal">
  <div class="modal" role="dialog" aria-modal="true">
    <div class="modal-header">
      <div class="modal-title">Review Critical Companies</div>
      <button class="btn btn-secondary" type="button" data-close="companies-modal">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
        <div class="modal-body">
        
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

    <!-- COMPANY VIEW -->
    <section id="view-company" class="view active">
      <section class="content">

      
        
        </div>

    <div class="modal-footer">
      <button class="btn btn-primary" type="button" data-close="companies-modal">OK</button>
    </div>
  </div>
</div>

</div>
</div>

<!-- Company Info Modal -->
<div class="modal-backdrop" id="companyInfoModal">
  <div class="modal" style="max-width: 900px;">
    <div class="modal-header">
      <div class="modal-title">Company Information</div>
      <button class="btn btn-secondary" onclick="closeCompanyInfoModal()">×</button>
    </div>
    
    <div class="modal-body">
      <!-- Search Bar -->
      <div style="margin-bottom: 16px; position: relative;">
        <input 
          type="text" 
          id="modalCompanySearch" 
          placeholder="Search companies..."
          style="width: 100%; padding: 10px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px;"
        />
        <div id="modalSearchDropdown" class="search-dropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid var(--line); border-radius: 8px; margin-top: 4px; max-height: 200px; overflow-y: auto; z-index: 1001; box-shadow: 0 4px 6px rgba(0,0,0,0.1);"></div>
      </div>

      <!-- Company Information Display -->
      <div class="card" style="margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
          <h2 id="modalCompanyName" style="margin: 0; font-size: 24px; font-weight: 700;">Loading...</h2>
          
        </div>

        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 4px;">ADDRESS</div>
          <div id="modalCompanyAddress" style="font-size: 14px;">Loading...</div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
          <div>
            <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 4px;">COMPANY TYPE</div>
            <div id="modalCompanyType" style="font-size: 14px; color: var(--brand);">Loading...</div>
          </div>
          <div>
            <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 4px;">TIER LEVEL</div>
            <div id="modalTierLevel" style="display: inline-block; background: #fef3c7; color: #92400e; padding: 4px 12px; border-radius: 999px; font-weight: 600; font-size: 14px;">Loading...</div>
          </div>
        </div>

        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">DEPENDENCIES</div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
            <div>
              <div style="font-weight: 600; margin-bottom: 4px; font-size: 13px;">Depends on (suppliers)</div>
              <ul id="modalDependsOn" style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
                <li>Loading...</li>
              </ul>
            </div>
            <div>
              <div style="font-weight: 600; margin-bottom: 4px; font-size: 13px;">Depended on by (customers)</div>
              <ul id="modalDependedOnBy" style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
                <li>Loading...</li>
              </ul>
            </div>
          </div>
        </div>

        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">MOST RECENT FINANCIAL STATUS</div>
          <div id="modalFinancialStatus">Loading...</div>
        </div>

        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">CAPACITY</div>
          <div id="modalCapacity" style="font-size: 18px; font-weight: 700;">Loading...</div>
        </div>

        <!-- Products -->
        <div style="margin-bottom: 16px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 8px;">PRODUCTS SUPPLIED</div>
          
          <div id="productsList" style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 8px;">
            <!-- Will be populated dynamically -->
          </div>

          <div id="productDiversity" style="font-size: 13px; color: var(--muted);">
            <i class="fa-solid fa-chart-pie"></i>
            Loading...
          </div>
        </div>

        <!-- Recent Transactions -->
        <div>
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <div style="font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase;">RECENT TRANSACTIONS</div>
            <div style="display: flex; gap: 8px; align-items: center;">
              <select id="txDaysFilter" style="font-size: 11px; padding: 4px 8px; border: 1px solid var(--line); border-radius: 4px;">
                <option value="7">Last 7 days</option>
                <option value="30" selected>Last 30 days</option>
                <option value="90">Last 90 days</option>
                <option value="180">Last 180 days</option>
                <option value="365">Last year</option>
                <option value="3650">All time</option>
              </select>
              <select id="txLimitFilter" style="font-size: 11px; padding: 4px 8px; border: 1px solid var(--line); border-radius: 4px;">
                <option value="5">Show 5</option>
                <option value="10" selected>Show 10</option>
                <option value="20">Show 20</option>
                <option value="50">Show 50</option>
                <option value="999999">Show All</option>
              </select>
            </div>
          </div>

          <!-- Shipping -->
          <div style="margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f3f4f6; border-radius: 6px 6px 0 0; font-weight: 600; font-size: 13px;">
              <i class="fa-solid fa-truck"></i> Shipping
            </div>
            <div style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; font-size: 12px; font-weight: 600; color: var(--muted); border-bottom: 1px solid var(--line);">
              <span>ID</span>
              <span>Date</span>
              <span>To</span>
              <span>Status</span>
            </div>
            <ul id="shippingList" style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
              <li style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; text-align: center; color: var(--muted);">Loading...</li>
            </ul>
          </div>

          <!-- Receiving -->
          <div style="margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f3f4f6; border-radius: 6px 6px 0 0; font-weight: 600; font-size: 13px;">
              <i class="fa-solid fa-box"></i> Receiving
            </div>
            <div style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; font-size: 12px; font-weight: 600; color: var(--muted); border-bottom: 1px solid var(--line);">
              <span>ID</span>
              <span>Date</span>
              <span>From</span>
              <span>Status</span>
            </div>
            <ul id="receivingList" style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
              <li style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; text-align: center; color: var(--muted);">Loading...</li>
            </ul>
          </div>

          <!-- Adjustments -->
          <div style="margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; padding: 8px; background: #f3f4f6; border-radius: 6px 6px 0 0; font-weight: 600; font-size: 13px;">
              <i class="fa-solid fa-wrench"></i> Adjustments
            </div>
            <div style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; font-size: 12px; font-weight: 600; color: var(--muted); border-bottom: 1px solid var(--line);">
              <span>ID</span>
              <span>Date</span>
              <span>Reason</span>
              <span>Status</span>
            </div>
            <ul id="adjustmentsList" style="list-style: none; padding: 0; margin: 0; font-size: 13px;">
              <li style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; text-align: center; color: var(--muted);">Loading...</li>
            </ul>
          </div>
        </div>

        
      </div>
    </div>
  </div>
</div>

  <script src="seniormanager.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
</body>
</html>