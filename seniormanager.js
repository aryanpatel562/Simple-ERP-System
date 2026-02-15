// Utility function for AJAX requests
function ajaxRequest(url, successCallback, errorCallback) {
  const xhr = new XMLHttpRequest();
  xhr.open('GET', url, true);

  xhr.onload = function () {
    if (xhr.status >= 200 && xhr.status < 300) {
      try {
        const data = JSON.parse(xhr.responseText);
        successCallback(data);
      } catch (e) {
        if (errorCallback) errorCallback(e);
        console.error('Parse error:', e);
      }
    } else {
      if (errorCallback) errorCallback(xhr.statusText);
      console.error('Request failed:', xhr.statusText);
    }
  };

  xhr.onerror = function () {
    if (errorCallback) errorCallback('Network error');
    console.error('Network error');
  };

  xhr.send();
}


let avgDisChartInstance = null;

let regionalDisruptionsChart = null;

let severityDistributionChart = null;


/* logic for tab swicthing */
document.addEventListener('DOMContentLoaded', () => {

  const tabs = document.querySelectorAll('.tab');
  const views = {
    top: document.getElementById('view-top'),
    financials: document.getElementById('view-financials'),
    disruptions: document.getElementById('view-disruptions')
  };

  tabs.forEach(tab => {
    tab.addEventListener('click', e => {
      e.preventDefault();
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      const key = tab.dataset.tab;
      Object.values(views).forEach(v => v.classList.remove('active'));
      views[key].classList.add('active');

      if (key === "disruptions") {
        loadDisruptionTab();
      }
    });
  });

  /* DROPDOWN HANDLER - AI assisted in gathering handlers */
  const choice = document.getElementById('smViewChoice');
  if (choice) {
    choice.addEventListener('change', () => {
      // View choice changed
    });
  }

  /* CODE FOR ACTIONS TAB/MODAL LISTENERS FOR CREATE NEW COMP, REVIEW CRITCIAL SUPPLIERS, AND REVIEW ALL COMP DATA */
  const companyModal = document.getElementById('companyModal');
  const companyForm = document.getElementById('companyForm');
  const companyErrorBox = document.getElementById('companyFormError');

  function openCompanyModal() {
    companyModal.classList.add('open');
    companyErrorBox.textContent = '';
  }

  function closeCompanyModal() {
    companyModal.classList.remove('open');
    if (companyForm) {
      companyForm.reset();
      clearCompanyValidation();
    }
  }

  function clearCompanyValidation() {
    ['companyId', 'companyName', 'locationId', 'tierLevel', 'serviceType'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.classList.remove('invalid');
    });
  }

  const createCompanyBtn = document.querySelector('[data-action="create-company"]');
  if (createCompanyBtn) createCompanyBtn.addEventListener('click', openCompanyModal);

  const reviewCriticalBtn = document.querySelector('[data-action="review-critical"]');
  const investigateDisruptionsBtn = document.querySelector('[data-action="investigate-disruptions"]');
  if (reviewCriticalBtn) {
    reviewCriticalBtn.addEventListener('click', () => {
      // Review critical suppliers
    });
  }
  if (investigateDisruptionsBtn) {
    investigateDisruptionsBtn.addEventListener('click', () => {
      // Investigate disruptions
    });
  }

  document.querySelectorAll('[data-close="company-modal"]').forEach(btn => {
    btn.addEventListener('click', closeCompanyModal);
  });

  companyModal.addEventListener('click', (e) => {
    if (e.target === companyModal) closeCompanyModal();
  });

  if (companyForm) {
    companyForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const ids = ['companyId', 'companyName', 'locationId', 'tierLevel', 'serviceType'];
      let valid = true;
      clearCompanyValidation();
      ids.forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        const value = el.value.trim();
        if (!value) {
          valid = false;
          el.classList.add('invalid');
        }
      });
      if (!valid) {
        companyErrorBox.textContent = 'Please fill in all fields before saving.';
        return;
      }
      alert('Company saved (demo only).');
      closeCompanyModal();
    });
  }

 /* DISRUPTIONS TAB GRAPHS AND FILTERING  */

  /* Set date range listeners */
  const avgBtn = document.getElementById("avgDisLoadBtn");
  if (avgBtn) {
    avgBtn.addEventListener("click", () => {
      const start = document.getElementById("avgDisStart").value;
      const end = document.getElementById("avgDisEnd").value;
      if (!start || !end) return alert("Select both dates.");
      renderAvgDisChartByDate(start, end);
    });
  }

  /* Code for company dropdown selector */
  const companyDD = document.getElementById("companyDropdown");
  if (companyDD) {
    companyDD.addEventListener("change", () => {
      loadCompanyDisruptions(companyDD.value);
    });
  }

  /* Toggle buttons */
  document.addEventListener("click", e => {
    if (!e.target.classList.contains("dis-toggle")) return;

    document.querySelectorAll(".dis-toggle").forEach(b => b.classList.remove("active"));
    e.target.classList.add("active");

    window._regionalMode = e.target.dataset.mode === "high" ? "high" : "total";
    renderRegionalChart();
  });


  document.addEventListener("change", e => {
    if (e.target.id === "disruptionEventSelect") {
      const id = e.target.value;
      if (!id) {
        document.querySelector("#eventCompaniesTable tbody").innerHTML =
          '<tr><td colspan="2">Select an event.</td></tr>';
        return;
      }
      loadCompaniesByEvent(id);
    }
  });
});

/* call all disruption graphs */
function loadDisruptionTab() {
  loadAllEvents();
  populateCompanyDropdown();
  initDisruptionDateValidation();
}

function initDisruptionDateValidation() {
  const url = "seniormanagerqueries.php?q=get_disruption_date_limits";
  ajaxRequest(url, data => {
    if (!data || !data.length) return;

    const minDate = data[0].min_date;
    const maxDate = data[0].max_date || new Date().toISOString().split('T')[0];

    // Helper to setup validation for a pair of inputs
    const setupValidation = (startId, endId) => {
      const startInput = document.getElementById(startId);
      const endInput = document.getElementById(endId);

      if (startInput && endInput) {
        startInput.min = minDate;
        startInput.max = maxDate;
        endInput.min = minDate;
        endInput.max = maxDate;

        // Set default values if empty
        if (!startInput.value) startInput.value = minDate;
        if (!endInput.value) endInput.value = maxDate;

        startInput.addEventListener('change', () => {
          if (startInput.value < minDate) startInput.value = minDate;
          if (startInput.value > maxDate) startInput.value = maxDate;
          if (endInput.value !== "" && endInput.value < startInput.value) {
            endInput.value = startInput.value;
          }
          endInput.min = startInput.value;
        });

        endInput.addEventListener('change', () => {
          if (endInput.value > maxDate) endInput.value = maxDate;
          if (startInput.value !== "" && startInput.value > endInput.value) {
            startInput.value = endInput.value;
          }
        });
      }
      return { startInput, endInput };
    };

    // Setup for Average Disruption Chart
    setupValidation("avgDisStart", "avgDisEnd");

    // Setup for Regional Disruption Chart
    const regionalInputs = setupValidation("regionalStart", "regionalEnd");

    // Initial load for regional chart with default dates
    if (regionalInputs.startInput && regionalInputs.endInput) {
      loadRegionalDisruptions(regionalInputs.startInput.value, regionalInputs.endInput.value);
    }

    // Default load for Average Disruption Chart
    renderAvgDisChartByDate(minDate, maxDate);
  });

  // Listener for Regional Load Button
  const regLoadBtn = document.getElementById("regionalLoadBtn");
  if (regLoadBtn) {
    regLoadBtn.addEventListener("click", () => {
      const start = document.getElementById("regionalStart").value;
      const end = document.getElementById("regionalEnd").value;
      if (!start || !end) return alert("Select both dates.");
      loadRegionalDisruptions(start, end);
    });
  }
}

/* Regional disruptions ajax request */
window._regionalMode = "total";

function loadRegionalDisruptions(start, end) {
  if (!start || !end) return;

  const url = `seniormanagerqueries.php?q=get_regional_disruptions,${start},${end}`;

  ajaxRequest(url, data => {
    window._regionalDisData = data || [];
    renderRegionalChart();
  });
}
//APEX CHARTS stacked bar chart 
window._regionalMode = "total";

function renderRegionalChart() {
  const chartDiv = document.querySelector("#regionalDisruptionsChart");
  if (!chartDiv) return;

  // Clear placeholder text if we had any
  chartDiv.innerHTML = "";

  if (regionalDisruptionsChart) {
    regionalDisruptionsChart.destroy();
  }

  const data = window._regionalDisData || [];
  if (!data.length) {
    chartDiv.innerHTML = "No data for selected period.";
    return;
  }

  const categories = data.map(d => d.Region);
  const totals = data.map(d => Number(d.total_disruptions));
  const highs = data.map(d => Number(d.high_impact_disruptions));

  const series =
    window._regionalMode === "high"
      ? [{ name: "High Impact", data: highs }]
      : [
        { name: "Total", data: totals },
        { name: "High Impact", data: highs }
      ];

  regionalDisruptionsChart = new ApexCharts(chartDiv, {
    chart: {
      type: "bar",
      height: 350,
      stacked: true,   // Set stacked to true for stacked bars
    },
    plotOptions: {
      bar: {
        borderRadius: 4,
        horizontal: false,
      }
    },
    xaxis: {
      categories,
    },
    series,
    colors: ["#3b82f6", "#ef4444"],  // Blue for total, Red for high impact
    title: {
      text: "Regional Disruptions – Total vs High Impact",
      align: "center"
    },
    dataLabels: {
      enabled: true,
    },
  });

  regionalDisruptionsChart.render();
}


/* All companies affected by specific event table */
function loadAllEvents() {
  ajaxRequest("seniormanagerqueries.php?q=get_all_events", data => {
    const sel = document.getElementById("disruptionEventSelect");
    if (!sel) return;

    sel.innerHTML = '<option value="">Select an Event...</option>';
    data.forEach(ev => {
      const opt = document.createElement("option");
      opt.value = ev.EventID;
      opt.textContent = `${ev.EventDate} - ${ev.CategoryName}`;
      sel.appendChild(opt);
    });

    // Default: Select first event if available
    if (data.length > 0) {
      sel.selectedIndex = 1; // 0 is placeholder
      loadCompaniesByEvent(data[0].EventID);
    }
  });
}

function loadCompaniesByEvent(eventId) {
  const url = `seniormanagerqueries.php?q=get_companies_by_event,${eventId}`;
  ajaxRequest(url, data => {
    const tbody = document.querySelector("#eventCompaniesTable tbody");
    tbody.innerHTML = "";
    data.forEach(r => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td>${r.CompanyName}</td>
                <td>${r.ImpactLevel}</td>`;
      tbody.appendChild(tr);
    });
  });
}

/* All disruptions for a specific company via dropdown filter */
function populateCompanyDropdown() {
  ajaxRequest("seniormanagerqueries.php?q=get_all_companies", data => {
    const dd = document.getElementById("companyDropdown");
    if (!dd) return;

    dd.innerHTML = "";

    // Filter out blank company names
    const filteredData = data.filter(c => c.CompanyName && c.CompanyName.trim() !== "");

    filteredData.forEach(c => {
      const opt = document.createElement("option");
      opt.value = c.CompanyName;
      opt.textContent = c.CompanyName;
      dd.appendChild(opt);
    });

    if (filteredData.length > 0) loadCompanyDisruptions(filteredData[0].CompanyName);
  });
}

function loadCompanyDisruptions(companyName) {
  const url = `seniormanagerqueries.php?q=get_company_disruptions,${encodeURIComponent(companyName)}`;

  ajaxRequest(url, data => {
    const tbody = document.querySelector("#companyDisruptionsTable tbody");
    tbody.innerHTML = "";

    if (!data.length) {
      tbody.innerHTML = `<tr><td colspan="4">No disruptions for ${companyName}</td></tr>`;
      return;
    }

    data.forEach(r => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td>${r.CategoryName}</td>
                <td>${r.EventDate}</td>
                <td>${r.EventRecoveryDate || "Ongoing"}</td>
                <td>${r.ImpactLevel}</td>`;
      tbody.appendChild(tr);
    });
  });
}

/* Average Daily Disruptions line chart using apex charts library */
function renderAvgDisChartByDate(start, end) {
  const url = `seniormanagerqueries.php?q=get_disruption_frequency_range,${start},${end}`;

  ajaxRequest(url, data => {
    if (avgDisChartInstance) avgDisChartInstance.destroy();

    const categories = data.map(d => d.disruption_day);
    const counts = data.map(d => Number(d.daily_count));

    avgDisChartInstance = new ApexCharts(document.querySelector("#avgDisChart"), {
      chart: { type: "line", height: 200, toolbar: { show: false } },
      stroke: { curve: "smooth", width: 2 },
      colors: ["#2563eb"],
      series: [{ name: "Disruptions", data: counts }],
      xaxis: { categories, type: "datetime" },
      yaxis: { min: 0 }
    });

    avgDisChartInstance.render();
  });
}



// DISTRIBUTOR TAB ALL GRAPHS 
//DATA LOADING Distributor page all graphs filter to date range and default set to all time


function setDefaultDates() {

  const startInput = document.getElementById("startdate");
  const endInput   = document.getElementById("enddate");

  // Fetch min date from DB
  const q   = "get_datelimits";
  const url = "seniormanagerqueries.php?q=" + q;

  ajaxRequest(url, (data) => {

    // sanitize DB value
    const rawMin = data[0].mindate;
    const minDate = new Date(rawMin).toISOString().split("T")[0];
    
    const today = new Date().toISOString().split("T")[0];

    
    startInput.min = minDate;
    startInput.max = today;

    endInput.min = minDate;
    endInput.max = today;

  
    startInput.value = minDate;
    endInput.value = today;

    
    loadTopDistributors(minDate, today);
    loadTopDisDistrupt(minDate, today);
    loadDelayedDistributors(minDate, today);
    loadDistributorsResilience(minDate, today);

  });
}

document.addEventListener("DOMContentLoaded", () => {
  setDefaultDates();

  // Add validation listeners
  ["startdate","enddate"]
    .forEach(id => document.getElementById(id)
      .addEventListener("change", validateDateRange));
});

function validateDateRange() {
  const start = startdate.value;
  const end   = enddate.value;

  if (end < start) {
    enddate.value = start;
  }
}



//All content in top row distributor page will be filtered by date range at top
function getOverviewDateFiltering() {
  return  {
         start : document.getElementById("startdate").value,
         end  :  document.getElementById("enddate").value
  };

}

//refresh graphs by sending parameters
function onclickDatesOverview(){
  const { start, end } = getOverviewDateFiltering();

  loadTopDistributors(start, end);
  loadTopDisDistrupt(start, end);
  loadDelayedDistributors(start, end);
  loadDistributorsResilience(start, end);
  
}

//load the top distributers
function loadTopDistributors(start, end) {

  const q = ["get_top_distributors", start, end].join(",");
  const url = "seniormanagerqueries.php?q=" + q;

  ajaxRequest(url, (data) => {
    renderDistributorTable(data);
  },  (err) => {
    console.error("Error loading top distributors:", err);
  });
}

function renderDistributorTable(data) {
  const tbody = document.getElementById("distributorsvolume");
  tbody.innerHTML = "";

  let r = 1;
  data.forEach(row => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
            <td>${r++}</td>
            <td>${row.DistributorName}</td>
            <td>${row.TotalVolume}</td>
            <td>${row.OnTimePercent}%</td>
        `;

    tbody.appendChild(tr);
  });
}


let yearlyDisruptionsChart = null;

//load the top distributors and their disruption count using APEX CHARTS -horizontal bar chart
function loadTopDisDistrupt(start, end) {
  const q = ["get_disrupt_topdis", start, end].join(",");
  const url = "seniormanagerqueries.php?q=" + q;

  ajaxRequest(url, (data) => {

    const names = data.map(row => row.DistributorName)
    const disruptcount = data.map(row => Number(row.TotalEvents))

    if (yearlyDisruptionsChart) {
      yearlyDisruptionsChart.destroy();
    }
    yearlyDisruptionsChart = new ApexCharts(
      document.querySelector("#yearlyDisruptionsChart"),
      {
        chart: { type: "bar", height: names.length*35 },
        series: [
          {
            name: "Disruptions",
            data: disruptcount // example: sorted descending
          }
        ],
        colors: ['#10b981'],
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 4,
            barHeight: "25px",
            dataLabels: { position: "center" }
          }
        },
        dataLabels: {
          enabled: true,
          style: { colors: ["#fff"] }
        },
        xaxis: {
          categories: names
        }
      }
    );
    yearlyDisruptionsChart.render();


  });
}

let DelayedDistributorsChart = null;

//load the top distributors and their disruption count using APEX CHARTS -horizontal bar chart
function loadDelayedDistributors(start, end) {
  const q = ["get_disrupt_delay", start, end].join(",");
  const url = "seniormanagerqueries.php?q=" + q;

  ajaxRequest(url, (data) => {

    const names = data.map(row => row.DistributorName)
    const delaycount = data.map(row => Number(row.AvgDelayDays))

    if (DelayedDistributorsChart) {
      DelayedDistributorsChart.destroy();
    }

    DelayedDistributorsChart = new ApexCharts(
      document.querySelector("#DelayedDistributorsChart"),
      {
        chart: { type: "bar", height:  names.length*35},
        series: [
          {
            name: "Days",
            data: delaycount // example: sorted descending
          }
        ],
        colors: ["#3b82f6"],
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 4,
            barHeight: "25px",
            dataLabels: { position: "center" }
          }
        },
        dataLabels: {
          enabled: true,
          style: { colors: ["#fff"] }
        },
        xaxis: {
          categories: names
        }
      }
    );
    DelayedDistributorsChart.render();


  });
}

/*load the resilience score - this graph does not have it's own query
  it is a mathmatical equation that uses the data from 2 other graph's queries*/
function loadDistributorsResilience(start, end) {
  const q1 = ["get_disrupt_topdis", start, end].join(",");
  const q2 = ["get_disrupt_delay", start, end].join(",");

  const url1 = "seniormanagerqueries.php?q=" + q1;
  const url2 = "seniormanagerqueries.php?q=" + q2;
  //AI assited with uncommon case of using 2 php requests in one function needed to nest ajax requests

  // First request: disruption counts with a high impact rating
  ajaxRequest(url1, (freqData) => {

    // Second request: delay averages in days
    ajaxRequest(url2, (delayData) => {

      const merged = computeSCRS(freqData, delayData);
      merged.sort((a, b) => Number(a.Resilience) - Number(b.Resilience));
      renderResilience(merged);

    });

  });
}


function computeSCRS(freqData, delayData) {
  // Convert delay array → map for O(1) lookup
  const delayMap = new Map();
  delayData.forEach(row => {
    delayMap.set(row.DistributorName, Number(row.AvgDelayDays));
  });

  // Merge by distributor name + compute score
  return freqData.map(row => {
    const name = row.DistributorName;
    const totalEvents = Number(row.TotalEvents);
    const AvgDelayDays = delayMap.get(name) ?? 0;

    const Resilience = (1 / (1 + totalEvents * AvgDelayDays)) * 100;

    return {
      DistributorName: name,
      TotalEvents: totalEvents,
      AvgDelayDays: AvgDelayDays,
      Resilience: Resilience.toFixed(2)
    };
  });
}


function renderResilience(data) {
  const tbody = document.getElementById("distributorsresilience");
  tbody.innerHTML = "";

  data.forEach(row => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
            <td>${row.DistributorName}</td>
            <td>${row.Resilience}%</td>
        `;

    tbody.appendChild(tr);
  });

}



//GOES IN SIDEBAR -
function loadMostCritical() {

  const q = ["get_most_crit"].join(",");
  const url = "seniormanagerqueries.php?q=" + q;

  ajaxRequest(url, (data) => {
    renderCriticalTable(data);
  }, );
}

function renderCriticalTable(data) {
  const tbody = document.getElementById("mostcritcompanies");
  tbody.innerHTML = "";

  data.forEach(row => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
            <td>${row.CompanyName}</td>
            <td>${row.criticality}</td>
        `;

    tbody.appendChild(tr);
  });
}

//USED CHAT TO GET  SOME HELPER FUNCTIONS
document.addEventListener('DOMContentLoaded', () => {

  /*Helper for criticalmodal to assist with  on-clicking actions*/
  const criticalModal = document.getElementById('criticalModal');

  const reviewCriticalBtn = document.querySelector('[data-action="review-critical"]');
  if (reviewCriticalBtn) {
    reviewCriticalBtn.addEventListener('click', () => {
      criticalModal.classList.add('open');
      loadMostCritical();
    });
  }

  document.addEventListener('click', (e) => {
    if (e.target.matches('[data-close="critical-modal"]')) {
      criticalModal.classList.remove('open');
    }
  });

});

// FINANCIAL TAB ALL GRAPHS
//financial averages are filtered by quarter and year
// Quarter Helpers used Ai to assist in building this filtering system
function decodeQuarter(index) { //turns quarter, year into 1 int value
  const year = Math.floor(index / 4);
  const q = (index % 4) + 1;
  return { year, quarter: `Q${q}`, qNumber: q };
}

function getCurrentQuarterIndex() {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth() + 1;

  let q = 1;
  if (month <= 3) q = 1;
  else if (month <= 6) q = 2;
  else if (month <= 9) q = 3;
  else q = 4;

  return (year * 4) + (q - 1); //because Q4 = Q1+3 not =4
}

//set default to the current quarter on page load and on reset
function setDefaultQuarter() {

  const url = "seniormanagerqueries.php?q=get_quarterlimits";

  ajaxRequest(url, (data) => {

    // Correct extraction
    const minIndex = parseInt(data[0].min);

    const maxIndex = getCurrentQuarterIndex();

    populateDropdowns(minIndex, maxIndex);

    const { year, quarter } = decodeQuarter(maxIndex);

    document.getElementById("quarterSelectStart").value = quarter;
    document.getElementById("yearSelectStart").value = year;
    document.getElementById("quarterSelectEnd").value = quarter;
    document.getElementById("yearSelectEnd").value = year;

    loadFinancialScore(quarter, quarter, year, year);
    loadFinancialByCompType(quarter, quarter, year, year);
    loadFinancialRegionalPercents(quarter, quarter, year, year);
    loadFinancialByComp(quarter, quarter, year, year);
  });
}

document.addEventListener("DOMContentLoaded", () => {
  setDefaultQuarter();

  // Add validation listeners
  ["quarterSelectStart", "yearSelectStart", "quarterSelectEnd", "yearSelectEnd"]
    .forEach(id => document.getElementById(id)
      .addEventListener("change", validateQuarterRange));
});


// Attach validation listeners
["quarterSelectStart", "yearSelectStart", "quarterSelectEnd", "yearSelectEnd"]
  .forEach(id => document.getElementById(id)
    .addEventListener("change", validateQuarterRange));

//Dropdowns should only offer valid inputs 
function populateDropdowns(minIndex, maxIndex) {

  const qs = document.getElementById("quarterSelectStart");
  const ys = document.getElementById("yearSelectStart");
  const qe = document.getElementById("quarterSelectEnd");
  const ye = document.getElementById("yearSelectEnd");

  qs.innerHTML = "";
  ys.innerHTML = "";
  qe.innerHTML = "";
  ye.innerHTML = "";

  const years = new Set();

  for (let i = minIndex; i <= maxIndex; i++) {
    const { year } = decodeQuarter(i);

    if (!years.has(year)) {
      years.add(year);
      ys.add(new Option(year, year));
      ye.add(new Option(year, year));
    }
  }

  // Always show Q1-Q4 because this will never change
  ["Q1", "Q2", "Q3", "Q4"].forEach(q => {
    qs.add(new Option(q, q));
    qe.add(new Option(q, q));
  });
}


// Once the user clicks on date range that exists in data base it must be a logical situation

function validateQuarterRange() {

  const { startquarter, startyear, endquarter, endyear } = getFinancialRangeFiltering();

  const sq = parseInt(startquarter.replace("Q", ""));
  const eq = parseInt(endquarter.replace("Q", ""));

  const startIndex = startyear * 4 + sq;
  const endIndex = endyear * 4 + eq;

  // Force end date to always take place on or after start
  if (endIndex < startIndex) {
    document.getElementById("quarterSelectEnd").value = startquarter;
    document.getElementById("yearSelectEnd").value = startyear;
  }

  // Force end to max out at current real quarter
  const currentIndex = getCurrentQuarterIndex();
  if (endIndex > currentIndex) {
    const { year, quarter } = decodeQuarter(currentIndex);
    document.getElementById("quarterSelectEnd").value = quarter;
    document.getElementById("yearSelectEnd").value = year;
  }
}


function getFinancialRangeFiltering() {
  return {
    startquarter: document.getElementById("quarterSelectStart").value,
    startyear: parseInt(document.getElementById("yearSelectStart").value),
    endquarter: document.getElementById("quarterSelectEnd").value,
    endyear: parseInt(document.getElementById("yearSelectEnd").value)
  };
}


function onclickQuarterFinancial() {
  const { startquarter, startyear, endquarter, endyear } = getFinancialRangeFiltering();

  loadFinancialByComp(startquarter, endquarter, startyear, endyear);
  loadFinancialByCompType(startquarter, endquarter, startyear, endyear);
  loadFinancialRegionalPercents(startquarter, endquarter, startyear, endyear);
  loadFinancialScore(startquarter, endquarter, startyear, endyear);
}


let financialChart = null;
//Function to load in average score dial using Chart.js
function loadFinancialScore(startquarter, endquarter, startyear, endyear) {

  const q = ["get_financial_score", startquarter, endquarter, startyear, endyear].join(",");
  const url = "seniormanagerqueries.php?q=" + q;
    ajaxRequest(url, (data) => {
      const score = Number(data[0].AvgHealth) || 0;

      if (financialChart) {
        financialChart.destroy();
      }

      // Chart.js chart's configuration
      // We are using a Doughnut type chart to 
      // get a Gauge format chart 
      // This is approach is fine and actually flexible
      // to get beautiful Gauge charts out of it
      var config = {
        type: 'doughnut',
        data: {
          datasets: [{
            data: [score, 100 - score],
            backgroundColor: ['rgba(54, 162, 235, 0.8)', 'rgba(0, 0, 0, 0.1)'],
            borderWidth: 0
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutoutPercentage: '85%',
          rotation: -90,
          circumference: 180,
          plugins: {
            tooltip: { enabled: false },
            legend: { display: false }
          },
          animation: {
            animateRotate: true,
            animateScale: false
          },

        }
      };

      // Create the chart
      const chartCtx = document.getElementById('gaugeChart').getContext('2d');
      financialChart = new Chart(chartCtx, config);

      document.getElementById("financialscore").textContent = score.toFixed(2);

    });
}


let FinancialByCompChart = null;
let FinancialByCompTypeChart = null;
//all companies financial score using apex charts horizontal bar chart
function loadFinancialByComp(startquarter, endquarter, startyear, endyear) {
  const q = ["get_avg_financialhealth", startquarter, endquarter, startyear, endyear].join(",");
  const url ="seniormanagerqueries.php?q=" + q;
    ajaxRequest(url, (data) => {

      const names = data.map(row => row.CompanyName)
      const score = data.map(row => Number(row.avg_health))

      if (FinancialByCompChart) {
        FinancialByCompChart.destroy();
      }
      FinancialByCompChart = new ApexCharts(
        document.querySelector("#FinancialByCompChart"),
        {
          chart: { type: "bar", height: names.length*35 },
          series: [
            {
              name: "Score",
              data: score // example: sorted descending
            }
          ],
          colors: ['#10b981'],
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 4,
              barHeight: "25px",
              dataLabels: { position: "center" }
            }
          },
          dataLabels: {
            enabled: true,
            style: { colors: ["#fff"] }
          },
          xaxis: {
            categories: names
          }
        }
      );
      FinancialByCompChart.render();


    });
}

//Same chart as above but only the average for company type
function loadFinancialByCompType(startquarter, endquarter, startyear, endyear) {
  const q = ["get_avg_financialhealth2", startquarter, endquarter, startyear, endyear].join(",");
  const url = "seniormanagerqueries.php?q=" + q;
    ajaxRequest(url, (data) => {

      const type = data.map(row => row.company_type)
      const score = data.map(row => Number(row.avg_health))

      if (FinancialByCompTypeChart) {
        FinancialByCompTypeChart.destroy();
      }
      FinancialByCompTypeChart = new ApexCharts(
        document.querySelector("#FinancialByCompTypeChart"),
        {
          chart: { type: "bar", height: 260 },
          series: [
            {
              name: "Score",
              data: score // example: sorted descending
            }
          ],
          colors: ["#3b82f6"],
          plotOptions: {
            bar: {
              horizontal: true,
              borderRadius: 4,
              dataLabels: { position: "center" }
            }
          },
          dataLabels: {
            enabled: true,
            style: { colors: ["#fff"] }
          },
          xaxis: {
            categories: type
          }
        }
      );
      FinancialByCompTypeChart.render();


    });
}



//load the financial score by region 
function loadFinancialRegionalPercents(startquarter, endquarter, startyear, endyear) {

  const q = ["get_financial_regionscores", startquarter, endquarter, startyear, endyear].join(",");

  const url = "seniormanagerqueries.php?q=" + q;
    ajaxRequest(url, (data) => {
      renderFinancialRegionTable(data);
    });
}

function renderFinancialRegionTable(data) {
  const tbody = document.getElementById("regionfinancials");
  tbody.innerHTML = "";

  data.forEach((row) => {
    const tr = document.createElement("tr");

    tr.innerHTML = `
    
            <td>
            <button onclick="onclickRegionPlus('${row.ContinentName}')">+</button>
            ${row.ContinentName}</td>
            <td>${Number(row.HealthScore).toFixed(2)}</td>
        `;

    tbody.appendChild(tr);
  });
}


//when user clicks + sign the companies in that region should dropdown 
function onclickRegionPlus(region) {
  const startquarter = document.getElementById("quarterSelectStart").value;
  const endquarter = document.getElementById("quarterSelectEnd").value;
  const startyear = document.getElementById("yearSelectStart").value;
  const endyear = document.getElementById("yearSelectEnd").value;
  const q = ["get_financial_regioncomps", region, startquarter, endquarter, startyear, endyear].join(",");

  const url = "seniormanagerqueries.php?q=" + q;
    ajaxRequest(url, (data) => {
      renderFinancialRegionDropdown(data);
    });
}

function renderFinancialRegionDropdown(data) {

  // show the header once data exists
  document.getElementById("companyHeaderRow").style.display =
    data.length > 0 ? "table-header-group" : "none";

  const tbody = document.getElementById("regionfinancialsdropdown");
  tbody.innerHTML = "";

  data.forEach(row => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
            <td>${row.CompanyName}</td>
            <td>${Number(row.HealthScore).toFixed(2)}</td>
        `;
    tbody.appendChild(tr);
  });
}


//ADD NEW COMPANY FUNCTIONALITY
function onclickSaveComp() {
  const name = encodeURIComponent(document.getElementById("companyName").value);
  const location = encodeURIComponent(document.getElementById("locationId").value);
  const tier = encodeURIComponent(document.getElementById("tierLevel").value);
  const type = encodeURIComponent(document.getElementById("serviceType").value); //encodeURIComponent from Chat

  const q = `insert_newcomp,${name},${location},${tier},${type}`;
  const url = `seniormanagerqueries.php?q=${q}`;

  ajaxRequest(url, (data) => {

    if (data && Number(data.CompanyID) > 0) {
      // Company inserted
      const createModal = document.getElementById("companyModal");
      createModal.classList.remove("open");


      // Resets fields back to empty
      document.getElementById("companyName").value = "";
      document.getElementById("locationId").value = "";
      document.getElementById("tierLevel").value = "";
      document.getElementById("serviceType").value = "";

      displayNewCompID(data.CompanyID)
    }
    else {

      //When user doesn't put in correct info the ajax request returns a 0 for the companyID
      document.getElementById("companyFormError").textContent =
        "Error creating company. Please validate info and try again.";
    }
  });
};


function displayNewCompID(id) {
  document.getElementById("newCompanyID").textContent = id;
  document.getElementById("successModal").classList.add("open");
}


//From Chat - helped give me listeners
document.addEventListener('click', (e) => {
  if (e.target.matches('[data-close="company-modal"]')) {
    document.getElementById('companyModal').classList.remove('open');
  }
  if (e.target.matches('[data-close="success-modal"]')) {
    document.getElementById('successModal').classList.remove('open');
  }
});

function loadLocationDropdown() {
  const url = "seniormanagerqueries.php?q=get_locations";

  ajaxRequest(url, (data) => {
    const select = document.getElementById("locationId");

    // Destroy previous Select2 (if exists)
    if ($(select).hasClass("select2-hidden-accessible")) {
      $(select).select2("destroy");
    }

    // Reset & load options
    select.innerHTML = '<option value=""></option>'; // placeholder

    data.forEach(loc => {
      const city = loc.City ?? "(No City)";
      const opt = document.createElement("option");

      opt.value = loc.LocationID;
      opt.textContent = `${loc.LocationID}. ${city}, ${loc.CountryName}, ${loc.ContinentName}`;

      select.appendChild(opt);
    });

    // Initialize Select2
    $('#locationId').select2({
      placeholder: "Search by city, country...",
      allowClear: true,
      width: '100%',
      dropdownParent: $('#companyModal')  // IMPORTANT for modals
    });
  });
}


document
  .querySelector('[data-action="create-company"]')
  .addEventListener('click', () => {
    document.getElementById("companyModal").classList.add("open");
    loadLocationDropdown();
  });

// ==================== COMPANY INFO MODAL ====================

// Global variable to track current company in modal
let modalCurrentCompany = null;

document.addEventListener('DOMContentLoaded', () => {

  // Open modal and load Anderson Ltd by default
  const reviewCompaniesBtn = document.querySelector('[data-action="review-companies"]');
  if (reviewCompaniesBtn) {
    reviewCompaniesBtn.addEventListener('click', () => {
      document.getElementById('companyInfoModal').classList.add('open');
      // Load Anderson Ltd (CompanyID 26) by default
      modalCurrentCompany = 26;
      loadModalCompanyInfo(26);
      setupModalSearch();
      setupModalEditButtons();
    });
  }

  // Close modal when clicking outside
  const modal = document.getElementById('companyInfoModal');
  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target.id === 'companyInfoModal') {
        closeCompanyInfoModal();
      }
    });
  }
  
  // Setup transaction filters once when page loads
  setupTransactionFilters();
  
  // Setup transaction filters
  function setupTransactionFilters() {
    const daysFilter = document.getElementById('txDaysFilter');
    const limitFilter = document.getElementById('txLimitFilter');
    
    if (daysFilter) {
      daysFilter.addEventListener('change', () => {
        const days = parseInt(daysFilter.value);
        const limit = limitFilter ? parseInt(limitFilter.value) : 10;
        if (modalCurrentCompany) {
          loadModalRecentTransactions(modalCurrentCompany, days, limit);
        }
      });
    }
    
    if (limitFilter) {
      limitFilter.addEventListener('change', () => {
        const days = daysFilter ? parseInt(daysFilter.value) : 30;
        const limit = parseInt(limitFilter.value);
        if (modalCurrentCompany) {
          loadModalRecentTransactions(modalCurrentCompany, days, limit);
        }
      });
    }
  }
  
  // Setup Edit and Save button functionality
  function setupModalEditButtons() {
    const editBtn = document.getElementById('editModalCompanyBtn');
    const saveBtn = document.getElementById('saveModalCompanyBtn');
    
    if (editBtn) {
      editBtn.addEventListener('click', () => {
        // Enable editing on company name and tier level
        const companyName = document.getElementById('modalCompanyName');
        const tierLevel = document.getElementById('modalTierLevel');
        
        if (companyName) {
          companyName.contentEditable = "true";
          companyName.style.outline = "2px solid var(--brand)";
          companyName.style.padding = "4px 8px";
          companyName.style.borderRadius = "4px";
        }
        
        if (tierLevel) {
          tierLevel.contentEditable = "true";
          tierLevel.style.outline = "2px solid var(--brand)";
          tierLevel.style.padding = "4px 8px";
        }
        
        alert('Edit mode enabled. You can now edit Company Name and Tier Level.');
      });
    }
    
    if (saveBtn) {
      saveBtn.addEventListener('click', () => {
        const companyName = document.getElementById('modalCompanyName');
        const tierLevel = document.getElementById('modalTierLevel');
        
        // Disable editing
        if (companyName) {
          companyName.contentEditable = "false";
          companyName.style.outline = "none";
          companyName.style.padding = "0";
        }
        
        if (tierLevel) {
          tierLevel.contentEditable = "false";
          tierLevel.style.outline = "none";
          tierLevel.style.padding = "4px 12px";
        }
        
        alert('Changes saved! (Demo only - not persisted to database)');
      });
    }
  }

  // Setup search functionality
  function setupModalSearch() {
    const searchInput = document.getElementById('modalCompanySearch');
    const dropdown = document.getElementById('modalSearchDropdown');
    
    if (!searchInput || !dropdown) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
      clearTimeout(searchTimeout);
      const term = this.value.trim();
      
      if (term.length < 0) {
        dropdown.style.display = 'none';
        return;
      }
      
      searchTimeout = setTimeout(() => {
        performModalSearch(term);
      }, 300);
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  }

  // Perform search
  function performModalSearch(term) {
    const url = `seniormanagerqueries.php?action=searchCompanies&term=${encodeURIComponent(term)}`;
    
    ajaxRequest(url, (data) => {
      const dropdown = document.getElementById('modalSearchDropdown');
      
      if (data.success && data.companies && data.companies.length > 0) {
        dropdown.innerHTML = data.companies.map(c => `
          <div class="search-dropdown-item" data-company-id="${c.company_id}">
            <div class="search-dropdown-name">${c.company_name}</div>
            <div class="search-dropdown-meta">${c.company_type || ''} • ${c.address || ''}</div>
          </div>
        `).join('');
        
        // Add click handlers
        dropdown.querySelectorAll('.search-dropdown-item').forEach(item => {
          item.addEventListener('click', function() {
            const companyId = this.dataset.companyId;
            const companyName = this.querySelector('.search-dropdown-name').textContent;
            
            document.getElementById('modalCompanySearch').value = companyName;
            dropdown.style.display = 'none';
            
            modalCurrentCompany = companyId;
            loadModalCompanyInfo(companyId);
          });
        });
        
        dropdown.style.display = 'block';
      } else {
        dropdown.innerHTML = '<div style="padding: 10px; text-align: center; color: var(--muted);">No companies found</div>';
        dropdown.style.display = 'block';
      }
    }, (error) => {
      // Silent fail
    });
  }

  // Load company info into modal
  function loadModalCompanyInfo(companyId) {
    const url = `seniormanagerqueries.php?action=getCompanyInfo&company_id=${companyId}`;
    
    ajaxRequest(url, (data) => {
      if (data.success && data.company) {
        renderModalCompanyInfo(data.company);
        // Load recent transactions after company info is rendered
        loadModalRecentTransactions(companyId, 30, 10);
      } else {
        alert('Failed to load company information. Check console for details.');
      }
    }, (error) => {
      alert('Error loading company information: ' + error);
    });
  }

  // Render company info in modal
  function renderModalCompanyInfo(company) {
    // Company name
    document.getElementById('modalCompanyName').textContent = company.company_name || 'N/A';
    
    // Address
    document.getElementById('modalCompanyAddress').textContent = company.address || 'N/A';
    
    // Company type
    document.getElementById('modalCompanyType').textContent = company.company_type || 'N/A';
    
    // Tier level
    document.getElementById('modalTierLevel').textContent = company.tier_level || 'N/A';
    
    // Dependencies - Suppliers
    const dependsOnList = document.getElementById('modalDependsOn');
    if (company.suppliers && company.suppliers.length > 0) {
      dependsOnList.innerHTML = company.suppliers.map(s => 
        `<li style="margin-bottom: 4px;">${s.company_name} (${s.company_type})</li>`
      ).join('');
    } else {
      dependsOnList.innerHTML = '<li>No suppliers</li>';
    }
    
    // Dependencies - Customers
    const dependedOnByList = document.getElementById('modalDependedOnBy');
    if (company.customers && company.customers.length > 0) {
      dependedOnByList.innerHTML = company.customers.map(c => 
        `<li style="margin-bottom: 4px;">${c.company_name} (${c.company_type})</li>`
      ).join('');
    } else {
      dependedOnByList.innerHTML = '<li>No customers</li>';
    }
    
    // Financial status
    const financialDiv = document.getElementById('modalFinancialStatus');
    if (company.financial_health) {
      const fh = company.financial_health;
      const badge = fh.health_score >= 80 ? 'good' : fh.health_score >= 50 ? 'warn' : 'risk';
      const badgeColor = fh.health_score >= 80 ? 'var(--good)' : fh.health_score >= 50 ? 'var(--warn)' : 'var(--bad)';
      
      // Trend indicator
      const trend = parseFloat(fh.trend) || 0;
      const trendIcon = trend > 0 ? '↑' : trend < 0 ? '↓' : '→';
      const trendColor = trend > 0 ? '#10b981' : trend < 0 ? '#ef4444' : '#6b7280';
      const trendText = Math.abs(trend).toFixed(1);
      
      financialDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
          <span style="display: inline-block; background: ${badgeColor}; color: white; padding: 6px 16px; border-radius: 999px; font-weight: 600; font-size: 16px;">
            ${fh.health_score} / 100
          </span>
          <span style="display: inline-block; font-size: 13px; color: var(--muted);">
            As of: ${fh.assessment_date}
          </span>
          <span style="display: inline-block; font-size: 13px; color: ${trendColor}; font-weight: 600;">
            ${trendIcon} ${trendText} from last quarter
          </span>
        </div>
      `;
    } else {
      financialDiv.innerHTML = '<span style="color: var(--muted);">No financial data available</span>';
    }
    
    // Capacity - handle Manufacturer, Distributor, and Logistics Provider
    const capacityDiv = document.getElementById('modalCapacity');
    
    if (company.company_type === 'Manufacturer') {
      if (company.capacity) {
        capacityDiv.innerHTML = `<strong>${company.capacity}</strong> <span style="font-size: 14px; color: var(--muted);">units/month</span>`;
      } else {
        capacityDiv.innerHTML = '<span style="color: var(--muted); font-size: 14px;">N/A</span> <span style="font-size: 12px; color: var(--muted);">No capacity data</span>';
      }
    } else if (company.company_type === 'Distributor' || company.company_type === 'Logistics Provider') {
      const routes = company.routes_count || 0;
      capacityDiv.innerHTML = `<strong>${routes}</strong> <span style="font-size: 14px; color: var(--muted);">active distribution routes</span>`;
    } else {
      capacityDiv.innerHTML = '<span style="color: var(--muted); font-size: 14px;">N/A</span> <span style="font-size: 12px; color: var(--muted);">Not applicable</span>';
    }
    
    // Products
    const productsList = document.getElementById('productsList');
    const productDiversity = document.getElementById('productDiversity');
    
    if (productsList && productDiversity) {
      if (company.products && company.products.length > 0) {
        productsList.innerHTML = company.products.map(p => 
          `<span style="display: inline-block; background: #e0e7ff; color: #3730a3; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;">${p}</span>`
        ).join('');
        const diversity = company.products.length > 2 ? 'High' : company.products.length > 1 ? 'Medium' : 'Low';
        productDiversity.innerHTML = `<i class="fa-solid fa-chart-pie"></i> Diversity: ${diversity} (${company.products.length} categories)`;
      } else {
        productsList.innerHTML = '<span style="display: inline-block; background: #f3f4f6; color: #6b7280; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600;">No products</span>';
        productDiversity.innerHTML = '<i class="fa-solid fa-chart-pie"></i> No product data';
      }
    }
  }
});

// Load recent transactions for the modal
function loadModalRecentTransactions(companyId, days, limit) {
  days = days || 30;
  limit = limit || 10;
  
  const url = `seniormanagerqueries.php?action=getRecentTransactions&company_id=${companyId}&days=${days}&limit=${limit}`;
  
  ajaxRequest(url, (data) => {
    if (!data.success) {
      return;
    }
    
    const txByType = {
      shipping: data.transactions.filter(t => t.type === 'shipping'),
      receiving: data.transactions.filter(t => t.type === 'receiving'),
      adjustment: data.transactions.filter(t => t.type === 'adjustment')
    };
    
    const renderTxList = (id, items) => {
      const el = document.getElementById(id);
      if (!el) return;
      
      if (items.length === 0) {
        el.innerHTML = `<li style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; justify-items: center; color: var(--muted);">No recent ${id.replace('List', '')} transactions</li>`;
        return;
      }
      
      el.innerHTML = items.map(t => `
        <li style="display: grid; grid-template-columns: 80px 1fr 1fr 100px; gap: 8px; padding: 8px; border-bottom: 1px solid #f3f4f6;">
          <span style="text-align: left;">${t.transaction_id}</span>
          <span style="text-align: left;">${t.date}</span>
          <span style="text-align: left;">${t.company_name}</span>
          <span style="text-align: left;">${t.status}</span>
        </li>
      `).join('');
    };
    
    renderTxList('shippingList', txByType.shipping);
    renderTxList('receivingList', txByType.receiving);
    renderTxList('adjustmentsList', txByType.adjustment);
  }, (error) => {
    // Silent fail
  });
}

// Close modal function (global scope)
function closeCompanyInfoModal() {
  document.getElementById('companyInfoModal').classList.remove('open');
}