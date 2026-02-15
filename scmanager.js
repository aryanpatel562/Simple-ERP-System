// Global state
let currentCompany = null;
let charts = {};

document.addEventListener("DOMContentLoaded", function () {
  initializeDashboard();
  setupNavigation();
  setupCompanySearch();
  setupCompanyEdit();
  setupCompanyInfoSelectors();
  setupDateRange();
});

// General Utility Functions (Ajax)

function ajaxRequest(url, method, params, callback) {
  const xhr = new XMLHttpRequest();

  if (method === 'GET' && params) {
    url += '?' + new URLSearchParams(params).toString();
  }

  xhr.open(method, url, true);
  if (method === 'POST') {
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
  }

  xhr.onreadystatechange = function() {
    if (xhr.readyState === 4) {
      if (xhr.status === 200) {
        try {
          callback(null, JSON.parse(xhr.responseText));
        } catch (e) {
          callback(e, null);
        }
      } else {
        callback(new Error('Request failed with status ' + xhr.status), null);
      }
    }
  };

  xhr.send(method === 'POST' && params ? new URLSearchParams(params).toString() : null);
}

function debounce(func, wait) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func(...args), wait);
  };
}

function setupEventListeners(ids, handler) {
  ids.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', handler);
  });
}

function getCurrentCompanyId() {
  return window.currentCompanyId || currentCompany;
}

function getDateRangeDays() {
  return window.customDateRange ? window.customDateRange.days : 365;
}

function loadCompanyData(companyId) {
  window.currentCompanyId = companyId;
  currentCompany = companyId;
  const days = getDateRangeDays();

  loadCompanyInfo(companyId);
  loadAlerts(companyId);
  loadKPIs(companyId, days);
  loadOnTimeDeliveryChart(companyId, days);
  loadDelayMetrics(companyId, days);
  loadDisruptionDistribution(companyId, days);
  loadFinancialHealthChart(companyId, days);
  loadRecentTransactions(companyId, 30, 10); // Default: 30 days, 10 items
}

// Generic chart render functions

function renderBarChart(key, selector, data, config = {}) {
  const el = document.querySelector(selector);
  if (!el) return;

  const categories = data.map(d => d.period || d.lane || d.month);
  const values = data.map(d => parseFloat(
    d.count || d.value || d.avg_recovery_time || d.total_downtime ||
    d.volume || d.exposure_score || d.health_score || d.avg_delay ||
    d.on_time_pct || 0
  ));

  const chartConfig = {
    chart: {
      type: config.type || "bar",
      height: config.height || 260,
      toolbar: { show: false },
      ...(config.chart || {})
    },
    series: [{ name: config.name || "Value", data: values }],
    xaxis: {
      categories,
      ...(config.xaxis || {}),
      labels: {
        ...(config.xaxis?.labels || {})
      }
    },
    colors: [config.color || "#3b82f6"],
    plotOptions: config.plotOptions || { bar: { columnWidth: "50%", borderRadius: 6 } },
    dataLabels: { enabled: false },
    grid: {
      borderColor: '#f1f1f1',
      ...(config.grid || {})
    },
    ...(config.stroke && { stroke: config.stroke }),
    ...(config.yaxis && { yaxis: config.yaxis })
  };

  if (charts[key]) {
    charts[key].updateOptions({
      xaxis: {
        categories,
        ...(config.xaxis || {}),
        labels: {
          ...(config.xaxis?.labels || {})
        }
      }
    });
    charts[key].updateSeries([{ name: config.name || "Value", data: values }]);
  } else {
    charts[key] = new ApexCharts(el, chartConfig);
    charts[key].render();
  }
}

function renderLineChart(key, selector, data, config = {}) {
  const el = document.querySelector(selector);
  if (!el) return;

  const categories = data.map(d => d.period || d.lane || d.month);
  const values = data.map(d => parseFloat(
    d.count || d.value || d.avg_recovery_time || d.total_downtime ||
    d.volume || d.exposure_score || d.health_score || d.avg_delay ||
    d.on_time_pct || 0
  ));

  const chartConfig = {
    chart: {
      type: "line",
      height: config.height || 260,
      toolbar: { show: false },
      ...(config.chart || {})
    },
    series: [{ name: config.name || "Value", data: values }],
    xaxis: {
      categories,
      ...(config.xaxis || {}),
      labels: {
        rotate: -25,
        rotateAlways: categories.length > 10,
        ...(config.xaxis?.labels || {})
      }
    },
    stroke: config.stroke || {
      show: true,
      curve: 'smooth',
      width: 3,
      colors: ['#000000']
    },
    markers: {
      size: 0,
      colors: [config.color || "#3b82f6"],
      strokeColors: '#fff',
      strokeWidth: 2,
      hover: {
        size: 0
      }
    },
    fill: config.fill || {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.1,
        stops: [0, 90, 100]
      }
    },
    dataLabels: { enabled: false },
    grid: {
      borderColor: '#f1f1f1',
      ...(config.grid || {})
    },
    tooltip: {
      x: {
        show: true
      },
      y: {
        formatter: function(value) {
          return config.formatter ? config.formatter(value) : value;
        }
      }
    },
    colors: ['#000000'],  // Chart line color
    ...(config.yaxis && { yaxis: config.yaxis })
  };

  if (charts[key]) {
    charts[key].destroy();
    charts[key] = new ApexCharts(el, chartConfig);
    charts[key].render();
  } else {
    charts[key] = new ApexCharts(el, chartConfig);
    charts[key].render();
  }
}

function renderTDHistogram(data) {
  const el = document.querySelector('#tdChart');
  if (!el) return;

  // Extract downtime values
  const downtimeValues = data.map(d => d.total_downtime);

  if (downtimeValues.length === 0) {
    el.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No data available</div>';
    return;
  }

  // Get the current grouping selection
  const groupBy = document.getElementById('tdGroupBy')?.value || 'supplier';
  const entityType = groupBy === 'region' ? 'Region' : 'Supplier';

  // Calculate bins for histogram
  const max = Math.max(...downtimeValues);
  const min = Math.min(...downtimeValues);
  const binCount = Math.min(10, Math.ceil(Math.sqrt(downtimeValues.length)));
  const binWidth = (max - min) / binCount || 1;

  // Create bins
  const bins = [];
  for (let i = 0; i < binCount; i++) {
    const binStart = min + (i * binWidth);
    const binEnd = binStart + binWidth;
    bins.push({
      range: `${Math.round(binStart)}-${Math.round(binEnd)}`,
      start: binStart,
      end: binEnd,
      count: 0,
      entities: []
    });
  }

  // Fill bins with data
  data.forEach(item => {
    for (let bin of bins) {
      if (item.total_downtime >= bin.start && item.total_downtime < bin.end) {
        bin.count++;
        bin.entities.push(item.label);
        break;
      }
      // Handle the max value (inclusive for last bin)
      if (item.total_downtime === max && bin.end >= max) {
        bin.count++;
        bin.entities.push(item.label);
        break;
      }
    }
  });

  const categories = bins.map(b => b.range);
  const values = bins.map(b => b.count);

  const chartConfig = {
    chart: {
      type: "bar",
      height: 240,
      toolbar: { show: false }
    },
    series: [{
      name: `Number of ${entityType}s`,
      data: values
    }],
    xaxis: {
      categories,
      title: {
        text: 'Total Downtime (days)',
        style: { fontSize: '11px', fontWeight: 600 }
      },
      labels: {
        style: {
          fontSize: '9px'
        },
        rotate: -45,
        rotateAlways: true
      }
    },
    yaxis: {
      title: {
        text: `Number of ${entityType}s`,
        style: { fontSize: '11px', fontWeight: 600 }
      },
      labels: {
        style: {
          fontSize: '10px'
        },
        formatter: function(val) {
          return Math.floor(val);
        }
      }
    },
    colors: ['#f59e0b'],
    plotOptions: {
      bar: {
        columnWidth: "85%",
        borderRadius: 2,
        distributed: false
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function(val) {
        return val > 0 ? val : '';
      },
      style: {
        fontSize: '10px',
        colors: ['#fff']
      }
    },
    grid: {
      borderColor: '#f1f1f1',
      padding: {
        left: 10,
        right: 10,
        bottom: 5
      }
    },
    tooltip: {
      custom: function({series, seriesIndex, dataPointIndex, w}) {
        const bin = bins[dataPointIndex];
        const entities = bin.entities.slice(0, 5).join(', ');
        const more = bin.count > 5 ? ` and ${bin.count - 5} more` : '';
        return `<div style="padding: 10px;">
          <strong>${bin.range} days</strong><br/>
          <strong>Count:</strong> ${bin.count} ${entityType.toLowerCase()}(s)<br/>
          ${bin.count > 0 ? `<strong>Examples:</strong> ${entities}${more}` : ''}
        </div>`;
      }
    }
  };

  if (charts['td']) {
    charts['td'].destroy();
  }

  charts['td'] = new ApexCharts(el, chartConfig);
  charts['td'].render();
}

function renderARTHistogram(data) {

  const el = document.querySelector('#artChart');
  if (!el) {
    return;
  }

  // Check if data exists and is an array
  if (!data || !Array.isArray(data)) {
    el.innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444; background-color: #fee2e2; border-radius: 4px;">Error: Invalid data format. Expected array, got ' + typeof data + '</div>';
    return;
  }

  if (data.length === 0) {
    el.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No data available for the selected filters</div>';
    return;
  }


  // Extract ART values with null check
  const artValues = data
    .map(d => {
      if (!d) {
        return null;
      }
      if (typeof d.avg_recovery_time === 'undefined') {
        return null;
      }
      return d.avg_recovery_time;
    })
    .filter(v => v !== null && !isNaN(v) && v >= 0);


  if (artValues.length === 0) {
    el.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No valid recovery time data available</div>';
    return;
  }

  // Get the current grouping selection
  const groupBy = document.getElementById('artGroupBy')?.value || 'supplier';
  const entityType = groupBy === 'region' ? 'Region' : 'Supplier';

  // Calculate bins for histogram
  const max = Math.max(...artValues);
  const min = Math.min(...artValues);

  // Handle edge case: all values are the same
  if (max === min) {
    const singleBin = {
      range: `${Math.round(min)}`,
      count: artValues.length,
      entities: data.map(d => d.label)
    };

    const chartConfig = {
      chart: { type: "bar", height: 240, toolbar: { show: false } },
      series: [{ name: `Number of ${groupBy === 'region' ? 'Region' : 'Supplier'}s`, data: [singleBin.count] }],
      xaxis: { categories: [singleBin.range], title: { text: 'Avg Recovery Time (days)', style: { fontSize: '11px', fontWeight: 600 } } },
      yaxis: { title: { text: `Number of ${groupBy === 'region' ? 'Region' : 'Supplier'}s`, style: { fontSize: '11px', fontWeight: 600 } } },
      colors: ['#10b981'],
      plotOptions: { bar: { columnWidth: "50%", borderRadius: 2 } },
      dataLabels: { enabled: true, style: { fontSize: '10px', colors: ['#fff'] } }
    };

    if (charts['art']) charts['art'].destroy();
    charts['art'] = new ApexCharts(el, chartConfig);
    charts['art'].render();
    return;
  }

  const binCount = Math.min(10, Math.ceil(Math.sqrt(artValues.length)));
  const binWidth = Math.max(1, (max - min) / binCount); 

  // Create bins
  const bins = [];
  for (let i = 0; i < binCount; i++) {
    const binStart = min + (i * binWidth);
    const binEnd = (i === binCount - 1) ? max + 0.1 : binStart + binWidth; 

    // Format range label more precisely
    const startLabel = binStart % 1 === 0 ? Math.round(binStart) : binStart.toFixed(1);
    const endLabel = binEnd % 1 === 0 ? Math.round(binEnd) : binEnd.toFixed(1);

    bins.push({
      range: `${startLabel}-${endLabel}`,
      start: binStart,
      end: binEnd,
      count: 0,
      entities: []
    });
  }

  // Fill bins with data
  data.forEach(item => {
    for (let bin of bins) {
      if (item.avg_recovery_time >= bin.start && item.avg_recovery_time < bin.end) {
        bin.count++;
        bin.entities.push(item.label);
        break;
      }
      // Handle the max value (inclusive for last bin)
      if (item.avg_recovery_time === max && bin.end >= max) {
        bin.count++;
        bin.entities.push(item.label);
        break;
      }
    }
  });

  const categories = bins.map(b => b.range);
  const values = bins.map(b => b.count);


  // Safety check for bins
  if (!bins || bins.length === 0 || !categories || categories.length === 0 || !values || values.length === 0) {
    el.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">Unable to create histogram - insufficient data</div>';
    return;
  }

  const chartConfig = {
    chart: {
      type: "bar",
      height: 240,
      toolbar: { show: false }
    },
    series: [{
      name: `Number of ${entityType}s`,
      data: values
    }],
    xaxis: {
      categories,
      title: {
        text: 'Avg Recovery Time (days)',
        style: { fontSize: '11px', fontWeight: 600 }
      },
      labels: {
        style: {
          fontSize: '9px'
        },
        rotate: -45,
        rotateAlways: true
      }
    },
    yaxis: {
      title: {
        text: `Number of ${entityType}s`,
        style: { fontSize: '11px', fontWeight: 600 }
      },
      labels: {
        style: {
          fontSize: '10px'
        },
        formatter: function(val) {
          return Math.floor(val);
        }
      }
    },
    colors: ['#10b981'],
    plotOptions: {
      bar: {
        columnWidth: "85%",
        borderRadius: 2,
        distributed: false
      }
    },
    dataLabels: {
      enabled: true,
      formatter: function(val) {
        return val > 0 ? val : '';
      },
      style: {
        fontSize: '10px',
        colors: ['#fff']
      }
    },
    grid: {
      borderColor: '#f1f1f1',
      padding: {
        left: 10,
        right: 10,
        bottom: 5
      }
    },
    tooltip: {
      custom: function({series, seriesIndex, dataPointIndex, w}) {
        const bin = bins[dataPointIndex];
        const entities = bin.entities.slice(0, 5).join(', ');
        const more = bin.count > 5 ? ` and ${bin.count - 5} more` : '';
        return `<div style="padding: 10px;">
          <strong>${bin.range} days</strong><br/>
          <strong>Count:</strong> ${bin.count} ${entityType.toLowerCase()}(s)<br/>
          ${bin.count > 0 ? `<strong>Examples:</strong> ${entities}${more}` : ''}
        </div>`;
      }
    }
  };

  if (charts['art']) {
    charts['art'].destroy();
  }

  charts['art'] = new ApexCharts(el, chartConfig);
  charts['art'].render();
}

function renderRadialChart(key, selector, value, config = {}) {
  const el = document.querySelector(selector);
  if (!el) return;

  const val = parseFloat(value) || 0;

  if (charts[key]) {
    charts[key].updateOptions({ series: [val] });
  } else {
    charts[key] = new ApexCharts(el, {
      chart: { type: "radialBar", height: config.height || 250 },
      series: [val],
      labels: [config.label || "Percentage"],
      colors: [config.color || "#10b981"],
      plotOptions: {
        radialBar: {
          hollow: { size: "65%" },
          dataLabels: {
            name: { fontSize: "14px", offsetY: 20 },
            value: {
              fontSize: "26px",
              offsetY: -10,
              formatter: (val) => `${val.toFixed(1)}%`
            }
          }
        }
      }
    });
    charts[key].render();
  }
}

function loadChart(action, params, successCallback) {

  ajaxRequest('supply_chain_manager_queries.php', 'GET',
    Object.assign({}, params, { action }),
    function(error, data) {
      if (error) {
        return;
      }


      if (!data.success) {
        return;
      }

      successCallback(data);
    }
  );
}

// Dashboard Initializing

function initializeDashboard() {
  currentCompany = 26;
  loadCompanyInfo(26);
  loadAlerts(26);
}

function setupNavigation() {
  const tabs = document.querySelectorAll(".tab");
  const views = {
    company: document.getElementById("view-company"),
    events: document.getElementById("view-events"),
    transactions: document.getElementById("view-transactions")
  };
  const searchWrap = document.getElementById("companySearchWrap");

  tabs.forEach(tab => {
    tab.addEventListener("click", (e) => {
      e.preventDefault();
      tabs.forEach(t => t.classList.remove("active"));
      tab.classList.add("active");

      const key = tab.dataset.tab;
      Object.values(views).forEach(v => v.classList.remove("active"));
      views[key].classList.add("active");

      // Show/hide search bar - only visible on company tab
      if (searchWrap) {
        if (key === 'company') {
          searchWrap.style.display = 'flex';
        } else {
          searchWrap.style.display = 'none';
        }
      }

      if (key === 'events') loadDisruptionEventsView();
      else if (key === 'transactions') loadTransactionsView();
    });
  });
}

function setupCompanySearch() {
  const companySearch = document.getElementById("companySearch");
  const searchBtn = document.getElementById("searchBtn");
  const searchDropdown = document.getElementById("searchDropdown");

  if (!companySearch) return;

  const performSearch = () => {
    const term = companySearch.value.trim();
    if (term.length > 0) showSearchResults(term);
    else hideSearchDropdown();
  };

  companySearch.addEventListener("input", debounce(performSearch, 300));
  searchBtn?.addEventListener("click", performSearch);
  companySearch.addEventListener("keypress", (e) => {
    if (e.key === 'Enter') performSearch();
  });

  document.addEventListener("click", (e) => {
    if (searchDropdown && !searchDropdown.contains(e.target) &&
        e.target !== companySearch && e.target !== searchBtn) {
      hideSearchDropdown();
    }
  });
}

function setupCompanyEdit() {
  const editBtn = document.getElementById("editCompanyBtn");
  const saveBtn = document.getElementById("saveCompanyBtn");

  if (editBtn) editBtn.addEventListener("click", enableEditMode);
  if (saveBtn) saveBtn.addEventListener("click", saveCompanyChanges);
}

function setupCompanyInfoSelectors() {
  const selectors = {
    kpiRangeSelect: (val) => loadKPIs(currentCompany, val),
    onTimeRangeSelect: (val) => loadOnTimeDeliveryChart(currentCompany, val),
    delayTimeSelect: (val) => loadDelayMetrics(currentCompany, val),
    disruptionRange: (val) => loadDisruptionDistribution(currentCompany, val),
    finRangeSelect: (val) => loadFinancialHealthChart(currentCompany, val)
  };

  Object.entries(selectors).forEach(([id, handler]) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('change', function() {
      if (currentCompany) handler(this.value);
    });
  });

  // Transaction filters
  const txDaysFilter = document.getElementById('txDaysFilter');
  const txLimitFilter = document.getElementById('txLimitFilter');

  if (txDaysFilter) {
    txDaysFilter.addEventListener('change', function() {
      if (currentCompany) {
        const days = this.value;
        const limit = txLimitFilter?.value || 10;
        loadRecentTransactions(currentCompany, days, limit);
      }
    });
  }

  if (txLimitFilter) {
    txLimitFilter.addEventListener('change', function() {
      if (currentCompany) {
        const days = txDaysFilter?.value || 30;
        const limit = this.value;
        loadRecentTransactions(currentCompany, days, limit);
      }
    });
  }
}

function setupDateRange() {
  const dateFrom = document.getElementById('dateFrom');
  const dateTo = document.getElementById('dateTo');
  const applyBtn = document.getElementById('applyDateRange');

  if (dateFrom && dateTo) {
    const today = new Date();
    const oneYearAgo = new Date();
    oneYearAgo.setFullYear(today.getFullYear() - 1);

    dateFrom.value = oneYearAgo.toISOString().split('T')[0];
    dateTo.value = today.toISOString().split('T')[0];
  }

  if (applyBtn) {
    applyBtn.addEventListener('click', function() {
      const from = new Date(dateFrom.value);
      const to = new Date(dateTo.value);

      if (!dateFrom.value || !dateTo.value) {
        alert('Please select both dates');
        return;
      }

      if (from > to) {
        alert('Start date must be before end date');
        return;
      }

      window.customDateRange = {
        from: dateFrom.value,
        to: dateTo.value,
        days: Math.ceil((to - from) / (1000 * 60 * 60 * 24))
      };

      const companyId = getCurrentCompanyId();
      if (companyId) loadCompanyData(companyId);
      else alert('Please select a company first');
    });
  }
}

// Company Info

function loadAlerts(companyId) {
  loadChart('getAlerts', { company_id: companyId }, (data) => {
    renderAlerts(data.alerts);
  });
}

function renderAlerts(alerts) {
  const container = document.getElementById('alertsContainer');
  if (!container) return;

  if (!alerts || alerts.length === 0) {
    container.innerHTML = '<div class="alert"><div class="alert-header"><span class="badge good"></span><div class="alert-title">All Systems Normal</div></div><small>No active disruptions</small></div>';
    return;
  }

  container.innerHTML = alerts.map(alert => `
    <div class="alert">
      <div class="alert-header">
        <span class="badge ${alert.severity}"></span>
        <div class="alert-title">${alert.title}</div>
      </div>
      <small>${alert.description}</small>
    </div>
  `).join('');
}

function loadCompanyInfo(companyId) {
  loadChart('getCompanyInfo', { company_id: companyId }, (data) => {
    renderCompanyInfo(data.company);
    const days = 3650;
    loadKPIs(companyId, days);
    loadOnTimeDeliveryChart(companyId, days);
    loadDelayMetrics(companyId, days);
    loadDisruptionDistribution(companyId, days);
    loadFinancialHealthChart(companyId, days);
    loadRecentTransactions(companyId, 30, 10); 
  });
}

function renderCompanyInfo(company) {
  document.getElementById('companyName').textContent = company.company_name || 'N/A';
  document.getElementById('companyAddress').textContent = company.address || 'N/A';
  document.getElementById('companyType').textContent = company.company_type || 'N/A';
  document.getElementById('tierLevel').textContent = company.tier_level || 'N/A';

  // Dependencies
  const renderList = (id, items, key) => {
    const el = document.getElementById(id);
    el.innerHTML = items && items.length > 0
      ? items.map(i => `<li>${i.company_name} (${i.company_type})</li>`).join('')
      : `<li>No ${key}</li>`;
  };

  renderList('dependsOn', company.suppliers, 'suppliers');
  renderList('dependedOnBy', company.customers, 'customers');

  // Financial status
  if (company.financial_health) {
    const fh = company.financial_health;
    const badge = fh.health_score >= 80 ? 'good' : fh.health_score >= 50 ? 'warn' : 'risk';
    const trendIcon = fh.trend > 0 ? 'up' : 'down';
    const trendColor = fh.trend > 0 ? '#10b981' : '#ef4444';

    document.getElementById('financialStatus').innerHTML = `
      <span class="financial-badge ${badge}">${fh.health_score} / 100</span>
      <span class="financial-small">As of: ${fh.assessment_date}</span>
      <span class="financial-trend" style="color: ${trendColor}">
        <i class="fa-solid fa-arrow-${trendIcon}"></i>
        ${Math.abs(fh.trend)} from last quarter
      </span>
    `;
  }

  // Capacity
  const capacityLabel = document.getElementById('capacityLabel');
  const capacityValue = document.getElementById('capacityValue');

  const capacityConfig = {
    'Distributor': ['Unique routes operated', company.routes_count || 0, 'active distribution routes'],
    'Logistics Provider': ['Unique routes operated', company.routes_count || 0, 'active distribution routes'],
    'Manufacturer': ['Production capacity', company.capacity || 0, 'units per month']
  };

  const config = capacityConfig[company.company_type] || ['Capacity', 'N/A', 'Not applicable'];
  capacityLabel.textContent = config[0];
  capacityValue.innerHTML = `
    <span class="capacity-num">${config[1]}</span>
    <span class="capacity-desc">${config[2]}</span>
  `;

  // Products
  const productsList = document.getElementById('productsList');
  const productDiversity = document.getElementById('productDiversity');

  if (company.products && company.products.length > 0) {
    productsList.innerHTML = company.products.map(p => `<span class="tag">${p}</span>`).join('');
    const diversity = company.products.length > 2 ? 'High' : company.products.length > 1 ? 'Medium' : 'Low';
    productDiversity.innerHTML = `<i class="fa-solid fa-chart-pie"></i> Diversity: ${diversity} (${company.products.length} categories)`;
  } else {
    productsList.innerHTML = '<span class="tag">No products</span>';
    productDiversity.innerHTML = '<i class="fa-solid fa-chart-pie"></i> No product data';
  }
}

function loadKPIs(companyId, days) {
  loadChart('getKPIs', { company_id: companyId, days }, (data) => {
    const k = data.kpis;
    document.getElementById('kpiOnTime').textContent = k.on_time_pct != null ? `${k.on_time_pct}%` : 'N/A';
    document.getElementById('kpiAvgDelay').textContent = k.avg_delay != null ? `${k.avg_delay} days` : 'N/A';
    document.getElementById('kpiStdDelay').textContent = k.std_delay != null ? `${k.std_delay} days` : 'N/A';
    document.getElementById('kpiDisruptions').textContent = k.disruption_count || '0';
  });
}

function loadOnTimeDeliveryChart(companyId, days) {
  loadChart('getOnTimeDelivery', { company_id: companyId, days }, (data) => {
    renderRadialChart('onTime', '#onTimeChart', data.on_time_pct, { label: 'On-time %', color: '#10b981' });
  });
}

function loadDelayMetrics(companyId, days) {
  loadChart('getDelayMetrics', { company_id: companyId, days }, (data) => {
    // KPIs
    document.getElementById('avgDelayKPI').textContent =
      data.avg_delay != null ? `${data.avg_delay} days` : 'N/A';
    document.getElementById('stdDelayKPI').textContent =
      data.std_delay != null ? `${data.std_delay} days` : 'N/A';

    const el = document.getElementById('delayTrendChart');
    if (!el) return;

    const delayVal = data.avg_delay != null ? parseFloat(data.avg_delay) : NaN;
    if (isNaN(delayVal)) {
      el.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">No delay data</div>';
      if (charts.delayTrend) {
        charts.delayTrend.destroy();
        charts.delayTrend = null;
      }
      return;
    }

    // St.dev / mean graph - make it a flat line
    const flatSeries = [
      { period: 'A', avg_delay: delayVal },
      { period: 'B', avg_delay: delayVal }
    ];

    renderBarChart('delayTrend', '#delayTrendChart', flatSeries, {
      type: 'line',
      height: 180,
      name: 'Avg delay (days)',
      color: '#2563eb',
      stroke: { curve: 'smooth', width: 3 },
      xaxis: {
        labels: { show: false },
        axisBorder: { show: false },
        axisTicks: { show: false }
      },
      yaxis: {
        title: { text: 'Delay (days)' }
      },
      grid: {
        padding: { top: 10, right: 0, bottom: 0, left: 0 }
      }
    });
  });
}

function loadDisruptionDistribution(companyId, days) {
  loadChart('getDisruptionDistribution', { company_id: companyId, days }, (data) => {
    const distribution = Object.entries(data.distribution).map(([k, v]) => ({ period: k, count: v }));
    renderBarChart('disruption', '#disruptionEventsChart', distribution, {
      height: 250,
      name: 'Events',
      color: '#f97316'
    });
  });
}

function loadFinancialHealthChart(companyId, days) {
  loadChart('getFinancialHealth', { company_id: companyId, days }, (data) => {
    renderBarChart('financial', '#financialChart', data.history, {
      type: 'line',
      height: 260,
      name: 'Health score',
      color: '#2563eb',
      stroke: { curve: 'smooth', width: 3 },
      yaxis: { min: 0, max: 100, title: { text: 'Score' } }
    });
  });
}

function loadRecentTransactions(companyId, days, limit) {
  days = days || 30;
  limit = limit || 10;

  loadChart('getRecentTransactions', { company_id: companyId, days: days, limit: limit }, (data) => {
    const txByType = {
      shipping: data.transactions.filter(t => t.type === 'shipping'),
      receiving: data.transactions.filter(t => t.type === 'receiving'),
      adjustment: data.transactions.filter(t => t.type === 'adjustment')
    };

    const renderTxList = (id, items) => {
      const el = document.getElementById(id);
      if (!el) return;

      if (items.length === 0) {
        el.innerHTML = `<li style="justify-content:center;color:var(--muted);">No recent ${id.replace('List', '')} transactions</li>`;
        return;
      }

      el.innerHTML = items.map(t => `
        <li>
          <span>${t.transaction_id}</span>
          <span>${t.date}</span>
          <span>${t.company_name}</span>
          <span>${t.status}</span>
        </li>
      `).join('');
    };

    renderTxList('shippingList', txByType.shipping);
    renderTxList('receivingList', txByType.receiving);
    renderTxList('adjustmentsList', txByType.adjustment);
  });
}

// Company Search
function showSearchResults(searchTerm) {
  loadChart('searchCompanies', { search: searchTerm }, (data) => {
    const dropdown = document.getElementById('searchDropdown');
    if (!dropdown) return;

    if (!data.companies || data.companies.length === 0) {
      dropdown.innerHTML = '<div class="search-dropdown-empty">No companies found</div>';
    } else {
      dropdown.innerHTML = data.companies.map(c => `
        <div class="search-dropdown-item" data-company-id="${c.company_id}">
          <div class="search-dropdown-name">${c.company_name}</div>
          <div class="search-dropdown-meta">ID: ${c.company_id}</div>
        </div>
      `).join('');

      dropdown.querySelectorAll('.search-dropdown-item').forEach(item => {
        item.addEventListener('click', function() {
          currentCompany = this.dataset.companyId;
          document.getElementById('companySearch').value = this.querySelector('.search-dropdown-name').textContent;
          hideSearchDropdown();
          loadCompanyInfo(currentCompany);
          loadAlerts(currentCompany);
        });
      });
    }
    dropdown.classList.add('show');
  });
}

function hideSearchDropdown() {
  const dropdown = document.getElementById('searchDropdown');
  if (dropdown) dropdown.classList.remove('show');
}

function enableEditMode() {
  ['companyName', 'tierLevel'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.contentEditable = "true";
      el.style.outline = "2px solid #2563eb";
      el.style.padding = "4px";
      el.style.borderRadius = "4px";
    }
  });
  alert("Edit mode enabled.");
}

function saveCompanyChanges() {
  if (!currentCompany) {
    alert('No company selected');
    return;
  }

  ajaxRequest('supply_chain_manager_queries.php', 'POST', {
    action: 'updateCompany',
    company_id: currentCompany,
    company_name: document.getElementById('companyName').textContent,
    tier_level: document.getElementById('tierLevel').textContent
  }, function(error, data) {
    if (error || !data.success) {
      alert('Error updating company: ' + (data?.message || error));
      return;
    }

    alert('Company updated successfully!');
    ['companyName', 'tierLevel'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
        el.contentEditable = "false";
        el.style.outline = "none";
      }
    });
    loadCompanyInfo(currentCompany);
  });
}

// disruption events

function loadDisruptionEventsView() {
  loadDisruptionFilters();
  setupDisruptionEventListeners();
  setupDisruptionDateRange();
}

function loadDisruptionFilters() {
  loadChart('getAllCompanies', {}, (data) => {
    const container = document.getElementById('f-company');
    if (!container) return;

    // Deselect All button
    const deselectBtn = container.querySelector('#deselectAllBtn');

    // clear everything else, but then put the button back
    container.innerHTML = '';
    if (deselectBtn) {
      container.appendChild(deselectBtn);
    }

    // All Companies first
    const allLabel = document.createElement('label');
    const cbAll    = document.createElement('input');
    cbAll.type = 'checkbox';
    cbAll.value = 'ALL';
    cbAll.checked = true;
    allLabel.appendChild(cbAll);
    allLabel.appendChild(document.createTextNode(' All Companies'));
    container.appendChild(allLabel);

    // Each individual company
    data.companies.forEach(c => {
      const label = document.createElement('label');
      const cb    = document.createElement('input');
      cb.type  = 'checkbox';
      cb.value = c.company_id;
      cb.checked = true;

      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + c.company_name));
      container.appendChild(label);
    });

    // Initial label text
    updateCompanyChipLabel();

    // initial load with everything selected
    loadDisruptionCharts();
  });

  // Regions
  loadChart('getRegions', {}, (data) => {
    const regionSelect = document.getElementById('f-region');
    if (!regionSelect) return;

    regionSelect.innerHTML =
      '<option value="">All Regions</option>' +
      data.regions.map(r => `<option value="${r}">${r}</option>`).join('');
  });
}
function setupDisruptionDateRange() {
  const startEl  = document.getElementById('disStart');
  const endEl    = document.getElementById('disEnd');
  const applyBtn = document.getElementById('disLoadBtn');
  const clearBtn = document.getElementById('disClearBtn');

  if (!startEl || !endEl) return;

  // Optional defaults
  const today = new Date();
  const oneYearAgo = new Date();
  oneYearAgo.setFullYear(today.getFullYear() - 1);

  if (!startEl.value) startEl.value = oneYearAgo.toISOString().split('T')[0];
  if (!endEl.value)   endEl.value   = today.toISOString().split('T')[0];

  // APPLY = turn on custom date range
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      const start = startEl.value;
      const end   = endEl.value;

      if (!start || !end) {
        alert('Select both dates.');
        return;
      }

      const startDate = new Date(start);
      const endDate   = new Date(end);

      if (startDate > endDate) {
        alert('Start date must be before end date.');
        return;
      }

      const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24)) || 1;

      // Store custom disruption date range globally
      window.disruptionDateRange = {
        from: start,
        to: end,
        days
      };

      loadDisruptionCharts();
    });
  }

  // CLEAR = turn off custom date range and go back to dropdown-based periods
  if (clearBtn) {
    clearBtn.addEventListener('click', () => {
      startEl.value = '';
      endEl.value   = '';
      window.disruptionDateRange = null;

      loadDisruptionCharts();
    });
  }
}


function setupDisruptionEventListeners() {
  // dropdowns
  setupEventListeners(['f-region', 'f-tier', 'f-period'], loadDisruptionCharts);

  // chart specific selects
  setupEventListeners(
    ['artSelect', 'artGroupBy', 'tdSelect', 'tdGroupBy', 'rrcSelect'],
    loadDisruptionCharts
  );

  // company multi-select
  const companyChip = document.getElementById('companyChip');
  const companyMenu = document.getElementById('f-company');

  if (companyChip) {
    companyChip.addEventListener('click', (e) => {
      if (e.target.closest('.multiselect-menu')) return; 
      companyChip.classList.toggle('open');
    });

    // event listener for multi-select
    document.addEventListener('click', (e) => {
      if (!companyChip.contains(e.target)) {
        companyChip.classList.remove('open');
      }
    });
  }

  if (companyMenu) {
    companyMenu.addEventListener('click', (e) => e.stopPropagation());

    companyMenu.addEventListener('change', (e) => {
      onCompanyCheckboxChange(e);
      loadDisruptionCharts();
    });
  }

  // deselect 
  const deselectBtn = document.getElementById('deselectAllBtn');
  if (deselectBtn) {
    deselectBtn.addEventListener('click', (e) => {
      e.stopPropagation(); // keeps dropdown open

      const allCb = getAllCompaniesCheckbox();
      const cbs = getCompanyCheckboxes();

      // Turn off all checkboxes
      if (allCb) allCb.checked = false;
      cbs.forEach(cb => cb.checked = false);

      updateCompanyChipLabel();
      showNoCompanyMessage();

      // prevent menu from closing
      return false;
    });
  }
}


function getDisruptionFilters() {
  const ids = getSelectedCompanyIds();

  // Start with the dropdown values as defaults
  let period    = document.getElementById('f-period')?.value || '3650';
  let dfPeriod  = period;
  let hdrPeriod = period;

  // Override with custom date range if set
  if (window.disruptionDateRange && window.disruptionDateRange.days) {
    const days = String(window.disruptionDateRange.days);
    period    = days;
    dfPeriod  = days;
    hdrPeriod = days;
  }

  const filters = {
    region: document.getElementById('f-region')?.value || '',
    tier: document.getElementById('f-tier')?.value || '',

    // Time periods for different chart types
    period: period,          // used by getART, getTD, getRRC, getDSD
    df_period: dfPeriod,     // used by getDF
    hdr_period: hdrPeriod,   // used by getHDR

    art_type: document.getElementById('artSelect')?.value || 'all',
    art_group_by: document.getElementById('artGroupBy')?.value || 'supplier',
    td_type: document.getElementById('tdSelect')?.value || 'all',
    td_group_by: document.getElementById('tdGroupBy')?.value || 'supplier',
    rrc_metric: document.getElementById('rrcSelect')?.value || 'count',
    dsd_type: document.getElementById('dsdSelect')?.value || 'all'
  };

  // Add date range filters if custom range is set
  if (
    window.disruptionDateRange &&
    window.disruptionDateRange.from &&
    window.disruptionDateRange.to
  ) {
    filters.date_from = window.disruptionDateRange.from;
    filters.date_to   = window.disruptionDateRange.to;
  }

  if (ids) {
    filters.company_ids = ids;
  }

  return filters;
}



function getAllCompaniesCheckbox() {
  return document.querySelector('#f-company input[value="ALL"]');
}

function getCompanyCheckboxes() {
  return Array.from(
    document.querySelectorAll('#f-company input[type="checkbox"]')
  ).filter(cb => cb.value !== 'ALL');
}

function getSelectedCompanyIds() {
  const allCb = getAllCompaniesCheckbox();
  const cbs   = getCompanyCheckboxes();

  // If "All Companies" is checked, treat as "no explicit filter"
  if (allCb && allCb.checked) {
    return '';              // <- important: DO NOT send 'ALL'
  }

  const selected = cbs.filter(cb => cb.checked);
  if (selected.length === 0) {
    return '';              // no selection; charts guard will catch this
  }

  // Comma-separated list of selected company IDs
  return selected.map(cb => cb.value).join(',');
}

function updateCompanyChipLabel() {
  const labelSpan = document.getElementById('companyChipLabel');
  if (!labelSpan) return;

  const allCb = getAllCompaniesCheckbox();
  const companyCbs = getCompanyCheckboxes();
  const selected = companyCbs.filter(cb => cb.checked);

  if (allCb && allCb.checked) {
    labelSpan.textContent = 'All Companies';
    return;
  }

  if (selected.length === 0 && (!allCb || !allCb.checked)) {
    labelSpan.textContent = 'No company selected';
    return;
  }

  if (selected.length === 1) {
    const name = selected[0].parentElement.textContent.trim();
    labelSpan.textContent = name;
  } else {
    labelSpan.textContent = `${selected.length} companies`;
  }
}

function onCompanyCheckboxChange(e) {
  const target    = e.target;
  if (!target.matches('input[type="checkbox"]')) return;

  const allCb      = getAllCompaniesCheckbox();
  const companyCbs = getCompanyCheckboxes();

  if (target === allCb) {
    if (allCb.checked) {
      // select everything
      companyCbs.forEach(cb => cb.checked = true);
    }
  } else {
    if (allCb && allCb.checked) {
      allCb.checked = false;
    }
  }

  updateCompanyChipLabel();
}

function showNoCompanyMessage() {
  const message = `<div style="padding:20px;text-align:center;color:#6b7280;">
    No company selected
  </div>`;

  document.querySelector('#dfChart').innerHTML = message;
  document.querySelector('#artChart').innerHTML = message;
  document.querySelector('#tdChart').innerHTML = message;
  document.querySelector('#rrcChart').innerHTML = message;
  document.querySelector('#hdrChart').innerHTML = message;
  document.querySelector('#dsdChart').innerHTML = message;
}

function loadDisruptionCharts() {
  const filters = getDisruptionFilters();

  const allCb = getAllCompaniesCheckbox();
  const selectedCbs = getCompanyCheckboxes().filter(cb => cb.checked);

  if ((!allCb || !allCb.checked) && selectedCbs.length === 0) {
    showNoCompanyMessage();
    return;
  }

  loadChart('getDF', filters, (data) => renderBarChart('df', '#dfChart', data.data, {
  name: 'Disruptions per Day',
  color: '#3b82f6',
  yaxis: {
    title: {
      text: 'Disruptions per Day'
    }
  }
}));

  // ART chart - render as histogram
  loadChart('getART', filters, (data) => {

    // Handle undefined or missing data
    if (!data || !data.data) {
      const el = document.querySelector('#artChart');
      if (el) {
        el.innerHTML = '<div style="padding: 20px; text-align: center; color: #721c24; background-color: #f8d7da; border-radius: 4px; border: 1px solid #f5c6cb;">Error loading ART data. Check console for details.</div>';
      }
      return;
    }

    renderARTHistogram(data.data);
  });

  // TD chart - render as histogram
  loadChart('getTD', filters, (data) => {
    renderTDHistogram(data.data);
  });

  // In loadDisruptionCharts()
loadChart('getRRC', filters, (data) => {
  renderRRCChart(data);
});

loadChart('getHDR', filters, (data) => renderHDRChart(data.hdr_pct));
loadChart('getDSD', filters, renderDSDChart);
}

// RRC always uses risk score 
function renderRRCChart(data) {
  const el = document.querySelector("#rrcChart");
  if (!el || !data.data || data.data.length === 0) {
    if (el) {
      el.innerHTML = '<div style="padding:5px;text-align:center;color:#6b7280;">No regional data</div>';
    }
    return;
  }

  if (charts.rrc) {
    charts.rrc.destroy();
    charts.rrc = null;
  }

  // Only risk score
  const series = data.data.map(d => ({
    name: d.region,
    data: [{
      x: 'Risk Score',
      y: parseFloat(d.value) || 0   
    }]
  }));

  charts.rrc = new ApexCharts(el, {
    chart: { type: "heatmap", height: 300, toolbar: { show: false } },
    series,
    plotOptions: {
      heatmap: {
        colorScale: {
          ranges: [
            { from: 0,  to: 85,  color: '#10b981', name: 'Low' },
            { from: 85, to: 95,  color: '#f59e0b', name: 'Medium' },
            { from: 95, to: 100, color: '#ef4444', name: 'High' }
          ]
        }
      }
    },
    dataLabels: {
      enabled: true,
      formatter: (val) => (val || val === 0) ? val.toFixed(1) + '%' : '',
      style: { fontSize: '12px', fontWeight: 'bold', colors: ['#fff'] }
    },
    xaxis: { labels: { show: false } },
    tooltip: {
      custom: ({ series, seriesIndex, dataPointIndex, w }) => {
        const region     = w.config.series[seriesIndex].name;
        const value      = series[seriesIndex][dataPointIndex];
        const regionData = data.data[seriesIndex];

        return `<div style="padding:8px 8px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;">
          <strong>${region}</strong><br/>
          Exposure: ${value.toFixed(1)}%<br/>
          <small>${regionData.count} events</small>
        </div>`;
      }
    },
    legend: { show: false }
  });

  charts.rrc.render();
}

function renderHDRChart(hdrPct) {
  renderRadialChart('hdr', '#hdrChart', hdrPct, { label: 'HDR %', color: '#ef4444', height: 260 });
}

function renderDSDChart(data) {
  const el = document.querySelector("#dsdChart");
  if (!el) return;

  const series = [
    { name: "Low", data: [parseInt(data.data.Low) || 0] },
    { name: "Medium", data: [parseInt(data.data.Medium) || 0] },
    { name: "High", data: [parseInt(data.data.High) || 0] }
  ];

  if (charts.dsd) {
    charts.dsd.destroy();
    charts.dsd = null;
  }

  charts.dsd = new ApexCharts(el, {
    chart: { type: "bar", height: 256, stacked: true, toolbar: { show: false } },
    series,
    xaxis: { categories: ["Severity Distribution"] },
    colors: ["#10b981", "#f59e0b", "#ef4444"],
    plotOptions: { bar: { horizontal: true } },
    legend: { position: "bottom" },
    dataLabels: { enabled: true }
  });
  charts.dsd.render();
}

// transactions tab

function loadTransactionsView() {
  loadTransactionFilters();
  loadTransactionTable();
  loadTransactionCharts();
  setupTransactionEventListeners();
}

function loadTransactionFilters() {
  // Load locations
  loadChart('getLocations', {}, (data) => {
    const container = document.getElementById('txLocation');
    if (!container) return;

    // Keep reference to Deselect All button
    const deselectBtn = container.querySelector('#deselectAllLocationsBtn');

    // Clear everything else, but then put the button back
    container.innerHTML = '';
    if (deselectBtn) {
      container.appendChild(deselectBtn);
    }

    // 1) "All Locations" first
    const allLabel = document.createElement('label');
    const cbAll = document.createElement('input');
    cbAll.type = 'checkbox';
    cbAll.value = 'ALL';
    cbAll.checked = true;
    allLabel.appendChild(cbAll);
    allLabel.appendChild(document.createTextNode(' All Locations'));
    container.appendChild(allLabel);

    // 2) Each individual location 
    data.locations.forEach(loc => {
      const label = document.createElement('label');
      const cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.value = loc;
      cb.checked = true;

      label.appendChild(cb);
      label.appendChild(document.createTextNode(' ' + loc));
      container.appendChild(label);
    });

    // Initial label text
    updateLocationChipLabel();
  });

  // Load shipping companies
  loadChart('getShippingCompanies', {}, (data) => {
    const container = document.getElementById('txShippingCompany');
    if (!container) return;

    const deselectBtn = container.querySelector('#deselectAllShippingBtn');
    container.innerHTML = '';
    if (deselectBtn) {
      container.appendChild(deselectBtn);
    }

    const allLabel = document.createElement('label');
    const cbAll = document.createElement('input');
    cbAll.type = 'checkbox';
    cbAll.value = 'ALL';
    cbAll.checked = true;
    allLabel.appendChild(cbAll);
    allLabel.appendChild(document.createTextNode(' All Shipping Companies'));
    container.appendChild(allLabel);

    if (data.companies) {
      data.companies.forEach(company => {
        const label = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = company;
        cb.checked = true;

        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + company));
        container.appendChild(label);
      });
    }

    updateShippingCompanyChipLabel();
  });

  // Load receiving companies
  loadChart('getReceivingCompanies', {}, (data) => {
    const container = document.getElementById('txReceivingCompany');
    if (!container) return;

    const deselectBtn = container.querySelector('#deselectAllReceivingBtn');
    container.innerHTML = '';
    if (deselectBtn) {
      container.appendChild(deselectBtn);
    }

    const allLabel = document.createElement('label');
    const cbAll = document.createElement('input');
    cbAll.type = 'checkbox';
    cbAll.value = 'ALL';
    cbAll.checked = true;
    allLabel.appendChild(cbAll);
    allLabel.appendChild(document.createTextNode(' All Receiving Companies'));
    container.appendChild(allLabel);

    if (data.companies) {
      data.companies.forEach(company => {
        const label = document.createElement('label');
        const cb = document.createElement('input');
        cb.type = 'checkbox';
        cb.value = company;
        cb.checked = true;

        label.appendChild(cb);
        label.appendChild(document.createTextNode(' ' + company));
        container.appendChild(label);
      });
    }

    updateReceivingCompanyChipLabel();
  });
}

// Flag to prevent duplicate event listener registration
let transactionListenersSetup = false;

function setupTransactionEventListeners() {
  // Prevent duplicate setup
  if (transactionListenersSetup) return;
  transactionListenersSetup = true;

  const locationChip = document.getElementById('locationChip');
  const locationMenu = document.getElementById('txLocation');

  if (locationChip) {
    locationChip.addEventListener('click', (e) => {
      if (e.target.closest('.multiselect-menu')) return; 
      locationChip.classList.toggle('open');
    });
  }

  if (locationMenu) {
    locationMenu.addEventListener('click', (e) => e.stopPropagation());

    // Handle checkbox changes - just update label, don't apply
    locationMenu.addEventListener('change', (e) => {
      onLocationCheckboxChange(e);
    });
  }

  // Deselect All button handler
  const deselectBtn = document.getElementById('deselectAllLocationsBtn');
  if (deselectBtn) {
    deselectBtn.addEventListener('click', (e) => {
      e.stopPropagation();

      const allCb = getAllLocationsCheckbox();
      const cbs = getLocationCheckboxes();

      // Turn off all checkboxes
      if (allCb) allCb.checked = false;
      cbs.forEach(cb => cb.checked = false);

      updateLocationChipLabel();

      return false;
    });
  }

  // Shipping Company chip open/close
  const shippingChip = document.getElementById('shippingCompanyChip');
  const shippingMenu = document.getElementById('txShippingCompany');

  if (shippingChip) {
    shippingChip.addEventListener('click', (e) => {
      if (e.target.closest('.multiselect-menu')) return;
      shippingChip.classList.toggle('open');
    });
  }

  if (shippingMenu) {
    shippingMenu.addEventListener('click', (e) => e.stopPropagation());
    shippingMenu.addEventListener('change', (e) => {
      onShippingCompanyCheckboxChange(e);
    });
  }

  const deselectShippingBtn = document.getElementById('deselectAllShippingBtn');
  if (deselectShippingBtn) {
    deselectShippingBtn.addEventListener('click', (e) => {
      e.stopPropagation();

      const allCb = getAllShippingCompaniesCheckbox();
      const cbs = getShippingCompanyCheckboxes();

      if (allCb) allCb.checked = false;
      cbs.forEach(cb => cb.checked = false);

      updateShippingCompanyChipLabel();

      return false;
    });
  }

  // Receiving Company chip open/close
  const receivingChip = document.getElementById('receivingCompanyChip');
  const receivingMenu = document.getElementById('txReceivingCompany');

  if (receivingChip) {
    receivingChip.addEventListener('click', (e) => {
      if (e.target.closest('.multiselect-menu')) return;
      receivingChip.classList.toggle('open');
    });
  }

  if (receivingMenu) {
    receivingMenu.addEventListener('click', (e) => e.stopPropagation());
    receivingMenu.addEventListener('change', (e) => {
      onReceivingCompanyCheckboxChange(e);
    });
  }

  const deselectReceivingBtn = document.getElementById('deselectAllReceivingBtn');
  if (deselectReceivingBtn) {
    deselectReceivingBtn.addEventListener('click', (e) => {
      e.stopPropagation();

      const allCb = getAllReceivingCompaniesCheckbox();
      const cbs = getReceivingCompanyCheckboxes();

      if (allCb) allCb.checked = false;
      cbs.forEach(cb => cb.checked = false);

      updateReceivingCompanyChipLabel();

      return false;
    });
  }

  // Apply filters button
  const applyBtn = document.getElementById('applyTransactionFilters');
  if (applyBtn) {
    applyBtn.addEventListener('click', () => {
      loadTransactionTable();
      loadTransactionCharts();
    });
  }

  // Reset filters button
  const resetBtn = document.getElementById('resetTransactionFilters');
  if (resetBtn) {
    resetBtn.addEventListener('click', () => {
      resetTransactionFilters();
    });
  }

  // Single global click handler to close all dropdowns when clicking outside
  document.addEventListener('click', (e) => {
    const locationChip = document.getElementById('locationChip');
    const shippingChip = document.getElementById('shippingCompanyChip');
    const receivingChip = document.getElementById('receivingCompanyChip');

    // Close location dropdown if clicking outside
    if (locationChip && !locationChip.contains(e.target)) {
      locationChip.classList.remove('open');
    }

    // Close shipping company dropdown if clicking outside
    if (shippingChip && !shippingChip.contains(e.target)) {
      shippingChip.classList.remove('open');
    }

    // Close receiving company dropdown if clicking outside
    if (receivingChip && !receivingChip.contains(e.target)) {
      receivingChip.classList.remove('open');
    }
  });
}

function resetTransactionFilters() {
  // Reset time range to "All time"
  const timeRange = document.getElementById('txTimeRange');
  if (timeRange) timeRange.value = '3650';

  // Reset status to "All statuses"
  const status = document.getElementById('txStatus');
  if (status) status.value = 'ALL';

  // Reset location filters - check all
  const allLocationsCb = getAllLocationsCheckbox();
  const locationCbs = getLocationCheckboxes();
  if (allLocationsCb) allLocationsCb.checked = true;
  locationCbs.forEach(cb => cb.checked = true);
  updateLocationChipLabel();

  // Reset shipping company filters - check all
  const allShippingCb = getAllShippingCompaniesCheckbox();
  const shippingCbs = getShippingCompanyCheckboxes();
  if (allShippingCb) allShippingCb.checked = true;
  shippingCbs.forEach(cb => cb.checked = true);
  updateShippingCompanyChipLabel();

  // Reset receiving company filters - check all
  const allReceivingCb = getAllReceivingCompaniesCheckbox();
  const receivingCbs = getReceivingCompanyCheckboxes();
  if (allReceivingCb) allReceivingCb.checked = true;
  receivingCbs.forEach(cb => cb.checked = true);
  updateReceivingCompanyChipLabel();

  // Reload data with reset filters
  loadTransactionTable();
  loadTransactionCharts();
}

function getTransactionFilters() {
  const locationIds = getSelectedLocationIds();
  const shippingCompanyIds = getSelectedShippingCompanyIds();
  const receivingCompanyIds = getSelectedReceivingCompanyIds();
  const mainTimeRange = document.getElementById('txTimeRange')?.value || '3650';

  const filters = {
    time_range: mainTimeRange,
    status: document.getElementById('txStatus')?.value || 'ALL',
    vol_range: mainTimeRange,      
    ot_range: mainTimeRange,       
    status_range: mainTimeRange,  
    exp_range: mainTimeRange       
  };

  if (locationIds) {
    filters.location = locationIds;  
  } else {
    filters.location = 'ALL';  // explicitly set ALL if nothing selected
  }

  // Add shipping company filter
  if (shippingCompanyIds) {
    filters.shipping_company = shippingCompanyIds;
  } else {
    filters.shipping_company = 'ALL';
  }

  // Add receiving company filter
  if (receivingCompanyIds) {
    filters.receiving_company = receivingCompanyIds;
  } else {
    filters.receiving_company = 'ALL';
  }

  return filters;
}

// Location multi-select helper functions
function getAllLocationsCheckbox() {
  return document.querySelector('#txLocation input[value="ALL"]');
}

function getLocationCheckboxes() {
  return Array.from(
    document.querySelectorAll('#txLocation input[type="checkbox"]')
  ).filter(cb => cb.value !== 'ALL');
}

function getSelectedLocationIds() {
  const allCb = getAllLocationsCheckbox();
  const cbs = getLocationCheckboxes();

  if (allCb && allCb.checked) {
    return '';
  }

  const selected = cbs.filter(cb => cb.checked);
  if (selected.length === 0) {
    return '';  // no selection
  }

  // Comma-separated list of selected locations
  return selected.map(cb => cb.value).join(',');
}

function updateLocationChipLabel() {
  const labelSpan = document.getElementById('locationChipLabel');
  if (!labelSpan) return;

  const allCb = getAllLocationsCheckbox();
  const locationCbs = getLocationCheckboxes();
  const selected = locationCbs.filter(cb => cb.checked);

  if (allCb && allCb.checked) {
    labelSpan.textContent = 'All Locations';
    return;
  }

  if (selected.length === 0 && (!allCb || !allCb.checked)) {
    labelSpan.textContent = 'No location selected';
    return;
  }

  if (selected.length === 1) {
    const name = selected[0].parentElement.textContent.trim();
    labelSpan.textContent = name;
  } else {
    labelSpan.textContent = `${selected.length} locations`;
  }
}

function onLocationCheckboxChange(e) {
  const target = e.target;
  if (!target.matches('input[type="checkbox"]')) return;

  const allCb = getAllLocationsCheckbox();
  const locationCbs = getLocationCheckboxes();

  if (target === allCb) {
    // User toggled "All Locations"
    if (allCb.checked) {
      // select everything
      locationCbs.forEach(cb => cb.checked = true);
    }
  } else {
    // User toggled a specific location
    if (allCb && allCb.checked) {
      allCb.checked = false;
    }
  }

  updateLocationChipLabel();
}

// Shipping Company multi-select helper functions
function getAllShippingCompaniesCheckbox() {
  return document.querySelector('#txShippingCompany input[value="ALL"]');
}

function getShippingCompanyCheckboxes() {
  return Array.from(
    document.querySelectorAll('#txShippingCompany input[type="checkbox"]')
  ).filter(cb => cb.value !== 'ALL');
}

function getSelectedShippingCompanyIds() {
  const allCb = getAllShippingCompaniesCheckbox();
  const cbs = getShippingCompanyCheckboxes();

  if (allCb && allCb.checked) {
    return '';
  }

  const selected = cbs.filter(cb => cb.checked);
  if (selected.length === 0) {
    return '';
  }

  return selected.map(cb => cb.value).join('|');
}

function updateShippingCompanyChipLabel() {
  const labelSpan = document.getElementById('shippingCompanyChipLabel');
  if (!labelSpan) return;

  const allCb = getAllShippingCompaniesCheckbox();
  const companyCbs = getShippingCompanyCheckboxes();
  const selected = companyCbs.filter(cb => cb.checked);

  if (allCb && allCb.checked) {
    labelSpan.textContent = 'All Shipping Companies';
    return;
  }

  if (selected.length === 0 && (!allCb || !allCb.checked)) {
    labelSpan.textContent = 'No shipping company selected';
    return;
  }

  if (selected.length === 1) {
    const name = selected[0].parentElement.textContent.trim();
    labelSpan.textContent = name;
  } else {
    labelSpan.textContent = `${selected.length} shipping companies`;
  }
}

function onShippingCompanyCheckboxChange(e) {
  const target = e.target;
  if (!target.matches('input[type="checkbox"]')) return;

  const allCb = getAllShippingCompaniesCheckbox();
  const companyCbs = getShippingCompanyCheckboxes();

  if (target === allCb) {
    if (allCb.checked) {
      companyCbs.forEach(cb => cb.checked = true);
    }
  } else {
    if (allCb && allCb.checked) {
      allCb.checked = false;
    }
  }

  updateShippingCompanyChipLabel();
}

// Receiving Company multi-select helper functions
function getAllReceivingCompaniesCheckbox() {
  return document.querySelector('#txReceivingCompany input[value="ALL"]');
}

function getReceivingCompanyCheckboxes() {
  return Array.from(
    document.querySelectorAll('#txReceivingCompany input[type="checkbox"]')
  ).filter(cb => cb.value !== 'ALL');
}

function getSelectedReceivingCompanyIds() {
  const allCb = getAllReceivingCompaniesCheckbox();
  const cbs = getReceivingCompanyCheckboxes();

  if (allCb && allCb.checked) {
    return '';
  }

  const selected = cbs.filter(cb => cb.checked);
  if (selected.length === 0) {
    return '';
  }

  return selected.map(cb => cb.value).join('|');
}

function updateReceivingCompanyChipLabel() {
  const labelSpan = document.getElementById('receivingCompanyChipLabel');
  if (!labelSpan) return;

  const allCb = getAllReceivingCompaniesCheckbox();
  const companyCbs = getReceivingCompanyCheckboxes();
  const selected = companyCbs.filter(cb => cb.checked);

  if (allCb && allCb.checked) {
    labelSpan.textContent = 'All Receiving Companies';
    return;
  }

  if (selected.length === 0 && (!allCb || !allCb.checked)) {
    labelSpan.textContent = 'No receiving company selected';
    return;
  }

  if (selected.length === 1) {
    const name = selected[0].parentElement.textContent.trim();
    labelSpan.textContent = name;
  } else {
    labelSpan.textContent = `${selected.length} receiving companies`;
  }
}

function onReceivingCompanyCheckboxChange(e) {
  const target = e.target;
  if (!target.matches('input[type="checkbox"]')) return;

  const allCb = getAllReceivingCompaniesCheckbox();
  const companyCbs = getReceivingCompanyCheckboxes();

  if (target === allCb) {
    if (allCb.checked) {
      companyCbs.forEach(cb => cb.checked = true);
    }
  } else {
    if (allCb && allCb.checked) {
      allCb.checked = false;
    }
  }

  updateReceivingCompanyChipLabel();
}


function loadTransactionTable() {
  loadChart('getTransactions', getTransactionFilters(), (data) => {
    const tbody = document.getElementById('txBody');
    if (!tbody) return;

    if (!data.transactions || data.transactions.length === 0) {
      tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;">No transactions found</td></tr>';
      return;
    }

    tbody.innerHTML = data.transactions.map(t => `
      <tr data-shipment-id="${t.transaction_id}">
        <td>${t.transaction_id}</td>
        <td>${t.transaction_date}</td>
        <td>${t.location || 'N/A'}</td>
        <td>${t.shipping_company}</td>
        <td>${t.receiving_company}</td>
        <td class="status-cell" data-current-status="${t.status}">${t.status}</td>
        <td>${t.exposure_score || 'N/A'}</td>
        <td>
          <button class="btn-edit-tx" onclick="editTransaction('${t.transaction_id}', '${t.status}')" style="padding: 4px 8px; font-size: 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f9fafb; cursor: pointer;">
            <i class="fa-solid fa-pen" style="font-size: 10px;"></i> Edit
          </button>
        </td>
      </tr>
    `).join('');
  });
}

function loadTransactionCharts() {
  const filters = getTransactionFilters();

  loadChart('getShipmentVolume', filters, (data) =>
    renderLineChart('txVolume', '#txVolumeChart', data.data, {
      name: 'Volume',
      stroke: { colors: ['#3b82f6'], width: 2, curve: 'smooth' },
      fill: { type: 'solid', opacity: 100 },
      formatter: (value) => Math.round(value) + ' shipments'
    })
  );

  loadChart('getOnTimeChart', filters, (data) =>
    renderLineChart('txOnTime', '#txOnTimeChart', data.data, {
      name: 'On-time %',
      stroke: { colors: ['#3b82f6'], width: 2, curve: 'smooth' },
      fill: { type: 'solid', opacity: 100 },
      yaxis: {
        max: 100,
        min: 0,
        labels: { formatter: (v) => v.toFixed(1) + '%' }
      },
      formatter: (value) => value.toFixed(1) + '%'
    })
  );

  loadChart('getStatusMix', filters, renderStatusChart);

  loadChart('getExposureByLane', filters, (data) =>
    renderBarChart('txExposure', '#txExposureChart', data.data, {
      name: 'Exposure score',
      color: '#8b5cf6',
      height: 275,
      xaxis: {
        labels: {
          rotate: -45,
          rotateAlways: true,
          style: {
            fontSize: '11px',
            cssClass: 'exposure-label'
          },
          trim: false,
          hideOverlappingLabels: false,
          maxHeight: 180,
          formatter: function(value) {
            if (!value) return '';

            // Split by arrow
            const parts = value.split(/\s*→\s*/);
            if (parts.length === 2) {
              // Wrap each part more
              const wrapText = (text, maxLen = 15) => {
                if (text.length <= maxLen) return text;

                // Split by comma first if exists
                if (text.includes(',')) {
                  const commaParts = text.split(',');
                  return commaParts.map(part => part.trim()).join(',\n');
                }

                const words = text.split(' ');
                let lines = [];
                let currentLine = '';

                words.forEach(word => {
                  if ((currentLine + ' ' + word).length > maxLen && currentLine) {
                    lines.push(currentLine);
                    currentLine = word;
                  } else {
                    currentLine = currentLine ? currentLine + ' ' + word : word;
                  }
                });
                if (currentLine) lines.push(currentLine);
                return lines.join('\n');
              };

              return wrapText(parts[0]) + '\n→\n' + wrapText(parts[1]);
            }

            const maxLength = 15;
            const words = value.split(' ');
            let lines = [];
            let currentLine = '';

            words.forEach(word => {
              if ((currentLine + ' ' + word).length > maxLength && currentLine) {
                lines.push(currentLine);
                currentLine = word;
              } else {
                currentLine = currentLine ? currentLine + ' ' + word : word;
              }
            });
            if (currentLine) lines.push(currentLine);

            return lines.join('\n');
          }
        }
      },
      chart: {
        offsetY: 0,
        offsetX: 0
      },
      grid: {
        padding: {
          bottom: 20,
          left: 20,
          right: 20
        }
      },
      plotOptions: {
        bar: {
          columnWidth: "50%",
          borderRadius: 4
        }
      }
    })
  );
}

function renderStatusChart(data) {
  const el = document.querySelector("#txStatusChart");
  if (!el) return;

  const pending = parseInt(data.data.Pending) || 0;
  const onTime = parseInt(data.data.OnTime) || 0;
  const delayed = parseInt(data.data.Delayed) || 0;

  if (pending + onTime + delayed === 0) {
    el.innerHTML = '<div style="padding:40px;text-align:center;color:#6b7280;">No shipment data</div>';
    if (charts.txStatus) {
      charts.txStatus.destroy();
      charts.txStatus = null;
    }
    return;
  }

  if (charts.txStatus) {
    charts.txStatus.updateSeries([{ name: "Shipments", data: [pending, onTime, delayed] }]);
  } else {
    charts.txStatus = new ApexCharts(el, {
      chart: { type: "bar", height: 225, toolbar: { show: false } },
      series: [{ name: "Shipments", data: [pending, onTime, delayed] }],
      xaxis: { categories: ['Pending', 'On Time', 'Delayed'] },
      colors: ["#f59e0b", "#10b981", "#ef4444"],
      plotOptions: { bar: { columnWidth: "50%", borderRadius: 6, distributed: true } },
      legend: { show: false },
      dataLabels: { enabled: true, style: { fontSize: '14px', fontWeight: 'bold' } }
    });
    charts.txStatus.render();
  }
}

// edit transactions 

function editTransaction(shipmentId, currentStatus) {
  const row = document.querySelector(`tr[data-shipment-id="${shipmentId}"]`);
  if (!row) return;

  const cells = row.querySelectorAll('td');
  const currentDate = cells[1].textContent;
  const currentStatusValue = cells[5].textContent;

  // Make date editable
  cells[1].innerHTML = `<input type="date" value="${currentDate}" style="padding: 4px 8px; border: 1px solid #2563eb; border-radius: 4px; font-size: 13px; width: 140px;">`;

  // Make status editable with dropdown
  cells[5].innerHTML = `
    <select style="padding: 4px 8px; border: 1px solid #2563eb; border-radius: 4px; font-size: 13px; width: 110px;">
      <option value="Pending" ${currentStatusValue === 'Pending' ? 'selected' : ''}>Pending</option>
      <option value="On Time" ${currentStatusValue === 'On Time' || currentStatusValue === 'OnTime' ? 'selected' : ''}>On Time</option>
      <option value="Delayed" ${currentStatusValue === 'Delayed' ? 'selected' : ''}>Delayed</option>
    </select>
  `;

  // Change button to Save/Cancel
  const actionCell = cells[7];
  actionCell.innerHTML = `
    <button onclick="saveTransaction('${shipmentId}')" style="padding: 4px 8px; font-size: 12px; border: 1px solid #10b981; border-radius: 6px; background: #10b981; color: white; cursor: pointer;">
      <i class="fa-solid fa-save" style="font-size: 10px;"></i> Save
    </button>
    <button onclick="loadTransactionTable()" style="padding: 4px 8px; font-size: 12px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f9fafb; cursor: pointer; margin-left: 4px;">
      Cancel
    </button>
  `;
}

function saveTransaction(shipmentId) {
  const row = document.querySelector(`tr[data-shipment-id="${shipmentId}"]`);
  if (!row) return;

  const cells = row.querySelectorAll('td');
  const newDate = cells[1].querySelector('input')?.value;
  const newStatus = cells[5].querySelector('select')?.value;

  if (!newDate || !newStatus) {
    alert('Please fill in all fields');
    return;
  }

  ajaxRequest('supply_chain_manager_queries.php', 'POST', {
    action: 'updateTransaction',
    shipment_id: shipmentId,
    promised_date: newDate,
    status: newStatus
  }, function(error, data) {
    if (error || !data.success) {
      alert('Error updating transaction: ' + (data?.message || error));
      return;
    }

    alert('Transaction updated successfully!');
    loadTransactionTable(); // Reload table
    loadTransactionCharts(); // Refresh charts
  });
}