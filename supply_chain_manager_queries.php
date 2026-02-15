<?php
// Set header for JSON response
header('Content-Type: application/json');

// Database connection variables
$servername = "mydb.itap.purdue.edu";
$username = "g1151934";
$password = "group24";
$database = $username;

// Create a connection
$conn = new mysqli($servername, $username, $password, $database);

// Check the connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]);
    exit();
}

// Get action from GET or POST
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit();
}

$response = ['success' => false];

switch ($action) {

    // Load first company, that is alphabetically first - Anderson Ltd
    case 'getFirstCompany':
        $sql = "SELECT CompanyID as company_id, CompanyName as company_name
                FROM Company
                ORDER BY CompanyName
                LIMIT 1";
        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = ['success' => true, 'company' => $row];
        } else {
            $response = ['success' => false, 'error' => 'No companies found'];
        }
        break;



    // Get alerts
    case 'getAlerts':
        $company_id = $conn->real_escape_string($_GET['company_id']);

        // Get disruptions by impact level for this company with full details
        $sql = "SELECT
                CASE
                    WHEN ic.ImpactLevel = 'High' THEN CONCAT('High Impact: ', dc.CategoryName)
                    WHEN ic.ImpactLevel = 'Medium' THEN CONCAT('Medium Impact: ', dc.CategoryName)
                    ELSE CONCAT('Low Impact: ', dc.CategoryName)
                END as title,
                CONCAT(
                    'Occurred on ', DATE_FORMAT(de.EventDate, '%b %d, %Y'),
                    CASE
                        WHEN de.EventRecoveryDate IS NOT NULL
                        THEN CONCAT(' - Recovered on ', DATE_FORMAT(de.EventRecoveryDate, '%b %d, %Y'))
                        ELSE ' - Recovery ongoing'
                    END
                ) as description,
                CASE
                    WHEN ic.ImpactLevel = 'High' THEN 'bad'
                    WHEN ic.ImpactLevel = 'Medium' THEN 'warn'
                    ELSE 'good'
                END as severity,
                ic.ImpactLevel as impact_level,
                dc.CategoryName as category,
                de.EventDate as event_date,
                de.EventRecoveryDate as recovery_date,
                CASE
                    WHEN de.EventRecoveryDate IS NULL THEN 'Ongoing'
                    WHEN de.EventRecoveryDate > CURDATE() THEN 'Ongoing'
                    ELSE 'Recovered'
                END as status
                FROM ImpactsCompany ic
                JOIN DisruptionEvent de ON ic.EventID = de.EventID
                JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                WHERE ic.AffectedCompanyID = '$company_id'
                ORDER BY
                    CASE ic.ImpactLevel
                        WHEN 'High' THEN 1
                        WHEN 'Medium' THEN 2
                        ELSE 3
                    END,
                    de.EventDate DESC
                LIMIT 10";

        $result = $conn->query($sql);
        $alerts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $alerts[] = $row;
            }
        }

        $response = ['success' => true, 'alerts' => $alerts];
        break;

    // get company info
    case 'getCompanyInfo':
        $company_id = $conn->real_escape_string($_GET['company_id']);

        // Main company info
        $sql = "SELECT c.CompanyID, c.CompanyName as company_name, c.Type as company_type,
                       c.TierLevel as tier_level,
                       CONCAT_WS(', ', l.City, l.CountryName) as address
                FROM Company c
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                WHERE c.CompanyID = '$company_id'";

        $result = $conn->query($sql);
        if ($result && $company = $result->fetch_assoc()) {

            // Get suppliers (upstream dependencies)
            $sql_suppliers = "SELECT c.CompanyID, c.CompanyName as company_name, c.Type as company_type
                             FROM DependsOn d
                             JOIN Company c ON d.UpstreamCompanyID = c.CompanyID
                             WHERE d.DownstreamCompanyID = '$company_id'";
            $result_suppliers = $conn->query($sql_suppliers);
            $company['suppliers'] = [];
            if ($result_suppliers) {
                while ($row = $result_suppliers->fetch_assoc()) {
                    $company['suppliers'][] = $row;
                }
            }

            // Get customers (downstream dependencies)
            $sql_customers = "SELECT c.CompanyID, c.CompanyName as company_name, c.Type as company_type
                             FROM DependsOn d
                             JOIN Company c ON d.DownstreamCompanyID = c.CompanyID
                             WHERE d.UpstreamCompanyID = '$company_id'";
            $result_customers = $conn->query($sql_customers);
            $company['customers'] = [];
            if ($result_customers) {
                while ($row = $result_customers->fetch_assoc()) {
                    $company['customers'][] = $row;
                }
            }

            // Get financial health with trend calculation
            $sql_fin = "SELECT
                current.HealthScore as health_score,
                CONCAT(current.Quarter, ' Q', current.RepYear) as assessment_date,
                COALESCE(current.HealthScore - prev.HealthScore, 0) as trend
            FROM FinancialReport current
            LEFT JOIN FinancialReport prev ON prev.CompanyID = current.CompanyID
                AND (prev.RepYear * 4 + CAST(SUBSTRING(prev.Quarter, 2) AS UNSIGNED)) =
                    (current.RepYear * 4 + CAST(SUBSTRING(current.Quarter, 2) AS UNSIGNED)) - 1
            WHERE current.CompanyID = '$company_id'
            ORDER BY current.RepYear DESC, current.Quarter DESC
            LIMIT 1";
            $result_fin = $conn->query($sql_fin);
            if ($result_fin && $fin = $result_fin->fetch_assoc()) {
                $company['financial_health'] = $fin;
            }

            // Get capacity or routes
            if ($company['company_type'] == 'Manufacturer') {
                $sql_cap = "SELECT FactoryCapacity as capacity FROM Manufacturer WHERE CompanyID = '$company_id'";
                $result_cap = $conn->query($sql_cap);
                if ($result_cap && $cap = $result_cap->fetch_assoc()) {
                    $company['capacity'] = $cap['capacity'];
                }
            } elseif ($company['company_type'] == 'Distributor' || $company['company_type'] == 'Logistics Provider') {
                $sql_routes = "SELECT COUNT(DISTINCT CONCAT(FromCompanyID, '-', ToCompanyID)) as routes_count
                              FROM OperatesLogistics
                              WHERE DistributorID = '$company_id'";
                $result_routes = $conn->query($sql_routes);
                if ($result_routes && $routes = $result_routes->fetch_assoc()) {
                    $company['routes_count'] = $routes['routes_count'];
                }
            }

            // Get products
            $sql_products = "SELECT DISTINCT p.Category
                            FROM SuppliesProduct sp
                            JOIN Product p ON sp.ProductID = p.ProductID
                            WHERE sp.SupplierID = '$company_id'";
            $result_products = $conn->query($sql_products);
            $company['products'] = [];
            if ($result_products) {
                while ($row = $result_products->fetch_assoc()) {
                    $company['products'][] = $row['Category'];
                }
            }

            $response = ['success' => true, 'company' => $company];
        }
        break;

    // get the KPIs
    case 'getKPIs':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);

        $sql = "SELECT
                COALESCE(ROUND((SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 1), 0) as on_time_pct,
                COALESCE(ROUND(AVG(CASE WHEN s.ActualDate > s.PromisedDate THEN DATEDIFF(s.ActualDate, s.PromisedDate) ELSE 0 END), 1), 0) as avg_delay,
                COALESCE(ROUND(STDDEV(CASE WHEN s.ActualDate > s.PromisedDate THEN DATEDIFF(s.ActualDate, s.PromisedDate) ELSE 0 END), 1), 0) as std_delay,
                COUNT(*) as total_shipments
                FROM Shipping s
                WHERE (s.SourceCompanyID = '$company_id' OR s.DestinationCompanyID = '$company_id')
                AND s.ActualDate IS NOT NULL
                AND s.PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";

        $result = $conn->query($sql);
        if ($result && $kpis = $result->fetch_assoc()) {
            // Get disruption count
            $sql_dis = "SELECT COUNT(*) as disruption_count
                       FROM DisruptionEvent de
                       JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                       WHERE ic.AffectedCompanyID = '$company_id'
                       AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
            $result_dis = $conn->query($sql_dis);
            if ($result_dis && $dis = $result_dis->fetch_assoc()) {
                $kpis['disruption_count'] = $dis['disruption_count'];
            } else {
                $kpis['disruption_count'] = 0;
            }

            $response = ['success' => true, 'kpis' => $kpis];
        } else {
            $response = ['success' => true, 'kpis' => [
                'on_time_pct' => 0,
                'avg_delay' => 0,
                'std_delay' => 0,
                'disruption_count' => 0,
                'total_shipments' => 0
            ]];
        }
        break;

    // get the on-time delivery chart for company info tab
    case 'getOnTimeDelivery':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);

        $date_filter = '';
        if ($days < 3650) {
            $date_filter = "AND PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        $sql = "SELECT
                COALESCE(ROUND((SUM(CASE WHEN ActualDate <= PromisedDate THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0)) * 100, 1), 0) as on_time_pct,
                COUNT(*) as total_shipments
                FROM Shipping
                WHERE (SourceCompanyID = '$company_id' OR DestinationCompanyID = '$company_id')
                AND ActualDate IS NOT NULL
                $date_filter";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = ['success' => true, 'on_time_pct' => $row['on_time_pct'], 'total_shipments' => $row['total_shipments']];
        } else {
            $response = ['success' => true, 'on_time_pct' => 0, 'total_shipments' => 0];
        }
        break;

    // get the delay metrics
    case 'getDelayMetrics':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);

        // Build date filter - only if not all time
        $date_filter = '';
        if ($days < 3650) {
            $date_filter = "AND PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        $sql = "SELECT
                COALESCE(ROUND(AVG(CASE WHEN ActualDate > PromisedDate THEN DATEDIFF(ActualDate, PromisedDate) ELSE 0 END), 1), 0) as avg_delay,
                COALESCE(ROUND(STDDEV(CASE WHEN ActualDate > PromisedDate THEN DATEDIFF(ActualDate, PromisedDate) ELSE 0 END), 1), 0) as std_delay
                FROM Shipping
                WHERE (SourceCompanyID = '$company_id' OR DestinationCompanyID = '$company_id')
                AND ActualDate IS NOT NULL
                $date_filter";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            // Get trend data 
            $period_days = floor($days / 4);
            $trend_data = [];
            for ($i = 0; $i < 4; $i++) {
                $start = $days - ($i + 1) * $period_days;
                $end = $days - $i * $period_days;

                $trend_date_filter = '';
                if ($days < 3650) {
                    $trend_date_filter = "AND PromisedDate BETWEEN DATE_SUB(CURDATE(), INTERVAL $end DAY) AND DATE_SUB(CURDATE(), INTERVAL $start DAY)";
                } else {
                    $trend_date_filter = "AND 1=1";
                }

                $sql_trend = "SELECT COALESCE(ROUND(AVG(CASE WHEN ActualDate > PromisedDate THEN DATEDIFF(ActualDate, PromisedDate) ELSE 0 END), 1), 0) as avg_delay
                             FROM Shipping
                             WHERE (SourceCompanyID = '$company_id' OR DestinationCompanyID = '$company_id')
                             AND ActualDate IS NOT NULL
                             $trend_date_filter";

                $result_trend = $conn->query($sql_trend);
                if ($result_trend && $trend = $result_trend->fetch_assoc()) {
                    $trend_data[] = $trend;
                } else {
                    $trend_data[] = ['avg_delay' => 0];
                }
            }

            $response = [
                'success' => true,
                'avg_delay' => $row['avg_delay'],
                'std_delay' => $row['std_delay'],
                'trend_data' => array_reverse($trend_data)
            ];
        } else {
            $response = [
                'success' => true,
                'avg_delay' => 0,
                'std_delay' => 0,
                'trend_data' => []
            ];
        }
        break;

    // get disruption distribution
    case 'getDisruptionDistribution':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);

        $sql = "SELECT dc.CategoryName as category, COUNT(*) as count
                FROM DisruptionEvent de
                JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                WHERE ic.AffectedCompanyID = '$company_id'
                AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                GROUP BY dc.CategoryName
                ORDER BY count DESC";

        $result = $conn->query($sql);
        $distribution = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $distribution[$row['category']] = $row['count'];
            }
        }

        $response = ['success' => true, 'distribution' => $distribution];
        break;

    // get financial health
    case 'getFinancialHealth':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);

        $sql = "SELECT CONCAT(Quarter, ' ', RepYear) as month, HealthScore as health_score
                FROM FinancialReport
                WHERE CompanyID = '$company_id'
                AND STR_TO_DATE(CONCAT(RepYear, '-', Quarter * 3, '-01'), '%Y-%m-%d') >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                ORDER BY RepYear, Quarter";

        $result = $conn->query($sql);
        $history = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }
        }

        $response = ['success' => true, 'history' => $history];
        break;

    // get recent transactions
    case 'getRecentTransactions':
        $company_id = $conn->real_escape_string($_GET['company_id']);
        $days = intval($_GET['days']);
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;

        // Shipping transactions
        $sql_shipping = "SELECT s.ShipmentID as transaction_id,
                       DATE_FORMAT(s.PromisedDate, '%Y-%m-%d') as date,
                       CONCAT('To ', c.CompanyName) as company_name,
                       CASE
                           WHEN s.ActualDate IS NULL THEN 'Pending'
                           WHEN s.ActualDate <= s.PromisedDate THEN 'On Time'
                           ELSE 'Delayed'
                       END as status,
                       'shipping' as type
                FROM Shipping s
                JOIN Company c ON s.DestinationCompanyID = c.CompanyID
                WHERE s.SourceCompanyID = '$company_id'
                AND s.PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                ORDER BY s.PromisedDate DESC
                LIMIT $limit";

        // Receiving transactions
        $sql_receiving = "SELECT r.ReceivingID as transaction_id,
                        DATE_FORMAT(r.ReceivedDate, '%Y-%m-%d') as date,
                        CONCAT('From ', c.CompanyName) as company_name,
                        'Received' as status,
                        'receiving' as type
                 FROM Receiving r
                 JOIN Shipping s ON r.ShipmentID = s.ShipmentID
                 JOIN Company c ON s.SourceCompanyID = c.CompanyID
                 WHERE r.ReceiverCompanyID = '$company_id'
                 AND r.ReceivedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                 ORDER BY r.ReceivedDate DESC
                 LIMIT $limit";

        // Adjustments
$sql_adjustments = "SELECT ia.AdjustmentID as transaction_id,
                          DATE_FORMAT(ia.AdjustmentDate, '%Y-%m-%d') as date,
                          ia.Reason as company_name,
                          'Completed' as status,
                          'adjustment' as type
                   FROM InventoryAdjustment ia
                   WHERE ia.CompanyID = '$company_id'
                   AND ia.AdjustmentDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)
                   ORDER BY ia.AdjustmentDate DESC
                   LIMIT $limit";

        $transactions = [];

        $result = $conn->query($sql_shipping);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }

        $result = $conn->query($sql_receiving);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }

        $result = $conn->query($sql_adjustments);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }

        $response = ['success' => true, 'transactions' => $transactions];
        break;

    // search company bar.
    case 'searchCompanies':
        $search = $conn->real_escape_string($_GET['search']);

        $sql = "SELECT CompanyID as company_id, CompanyName as company_name
                FROM Company
                WHERE CompanyName LIKE '%$search%'
                ORDER BY CompanyName";
                // LIMIT 10";

        $result = $conn->query($sql);
        $companies = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }
        }

        $response = ['success' => true, 'companies' => $companies];
        break;

    // Update company info - Change the data in the company info tab
    case 'updateCompany':
        $company_id = $conn->real_escape_string($_POST['company_id']);
        $company_name = $conn->real_escape_string($_POST['company_name']);
        $tier_level = isset($_POST['tier_level']) ? $conn->real_escape_string($_POST['tier_level']) : null;

        $updates = [];
        $updates[] = "CompanyName = '$company_name'";

        if ($tier_level !== null) {
            $updates[] = "TierLevel = '$tier_level'";
        }

        $sql = "UPDATE Company SET " . implode(', ', $updates) . " WHERE CompanyID = '$company_id'";

        if ($conn->query($sql)) {
            $response = ['success' => true, 'message' => 'Company updated successfully'];
        } else {
            $response = ['success' => false, 'message' => $conn->error];
        }
        break;

    // disruption event queries

    case 'getAllCompanies':
        $sql = "SELECT CompanyID as company_id, CompanyName as company_name FROM Company ORDER BY CompanyName";
        $result = $conn->query($sql);
        $companies = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row;
            }
        }
        $response = ['success' => true, 'companies' => $companies];
        break;

    case 'getRegions':
        $sql = "SELECT DISTINCT ContinentName FROM Location WHERE ContinentName IS NOT NULL ORDER BY ContinentName";
        $result = $conn->query($sql);
        $regions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $regions[] = $row['ContinentName'];
            }
        }
        $response = ['success' => true, 'regions' => $regions];
        break;

    // Disruption Frequency
    case 'getDF':
        $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
        $region = isset($_GET['region']) ? $conn->real_escape_string($_GET['region']) : '';
        $tier = isset($_GET['tier']) ? $conn->real_escape_string($_GET['tier']) : '';
        $days = intval($_GET['df_period']);

        $data = [];
        $where = [];

        // Handle multiple company IDs
        if ($company_ids) {
            $ids = array_map('trim', explode(',', $company_ids));
            $escaped_ids = array_map(function($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $ids);
            $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
        }

        if ($region) $where[] = "l.ContinentName = '$region'";
        if ($tier) $where[] = "c.TierLevel = '$tier'";

        // Time filter
        if ($days >= 3650) {
            $where[] = "de.EventDate >= '2020-01-01'";
        } else {
            $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        $where_clause = implode(' AND ', $where);

        $sql = "SELECT
                    YEAR(de.EventDate) as year,
                    MONTH(de.EventDate) as month,
                    COUNT(*) as disruption_count
                FROM DisruptionEvent de
                JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                WHERE $where_clause
                GROUP BY YEAR(de.EventDate), MONTH(de.EventDate)
                ORDER BY year ASC, month ASC";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $year = intval($row['year']);
                $month = intval($row['month']);
                $disruption_count = intval($row['disruption_count']);

                $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $df = $days_in_month > 0 ? round($disruption_count / $days_in_month, 2) : 0;

                $date = DateTime::createFromFormat('Y-n', $year . '-' . $month);
                $period_label = $date->format('M Y');

                $data[] = [
                    'period' => $period_label,
                    'count' => $df,
                    'total_disruptions' => $disruption_count,
                    'days_in_period' => $days_in_month
                ];
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

    // Average Recovery Time
    case 'getART':
        $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
        $region = isset($_GET['region']) ? $conn->real_escape_string($_GET['region']) : '';
        $tier = isset($_GET['tier']) ? $conn->real_escape_string($_GET['tier']) : '';
        $art_type = isset($_GET['art_type']) ? $conn->real_escape_string($_GET['art_type']) : 'all';
        $art_group_by = isset($_GET['art_group_by']) ? $conn->real_escape_string($_GET['art_group_by']) : 'supplier';
        $days = intval($_GET['period']);


        $date_from = isset($_GET['date_from']) ? $conn->real_escape_string($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? $conn->real_escape_string($_GET['date_to'])   : '';

    $where = [];
        $where = [];

        // Handle multiple company IDs
        if ($company_ids) {
            $ids = array_map('trim', explode(',', $company_ids));
            $escaped_ids = array_map(function($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $ids);
            $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
        }

        if ($region) $where[] = "l.ContinentName = '$region'";
        if ($tier) $where[] = "c.TierLevel = '$tier'";
        if ($art_type != 'all') $where[] = "dc.CategoryName LIKE '%$art_type%'";
        $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        $where[] = "de.EventRecoveryDate IS NOT NULL";

        $where_clause = implode(' AND ', $where);

        // Determine grouping
        if ($art_group_by === 'region') {
            $sql = "SELECT l.ContinentName as label,
                           ROUND(AVG(TIMESTAMPDIFF(DAY, de.EventDate, de.EventRecoveryDate)), 1) as avg_recovery_time
                   FROM DisruptionEvent de
                   JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                   JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                   JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                   LEFT JOIN Location l ON c.LocationID = l.LocationID
                   WHERE $where_clause AND l.ContinentName IS NOT NULL
                   GROUP BY l.ContinentName
                   ORDER BY avg_recovery_time DESC
                   LIMIT 10";
        } else {
            // Group by supplier (company)
            $sql = "SELECT c.CompanyName as label,
                           ROUND(AVG(TIMESTAMPDIFF(DAY, de.EventDate, de.EventRecoveryDate)), 1) as avg_recovery_time
                   FROM DisruptionEvent de
                   JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                   JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                   JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                   LEFT JOIN Location l ON c.LocationID = l.LocationID
                   WHERE $where_clause
                   GROUP BY c.CompanyID, c.CompanyName
                   ORDER BY avg_recovery_time DESC
                   LIMIT 10";
        }

        $result = $conn->query($sql);
        $data = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'label' => $row['label'],
                    'avg_recovery_time' => floatval($row['avg_recovery_time'] ?: 0)
                ];
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

    // Total Downtime
    case 'getTD':
        $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
        $region = isset($_GET['region']) ? $conn->real_escape_string($_GET['region']) : '';
        $tier = isset($_GET['tier']) ? $conn->real_escape_string($_GET['tier']) : '';
        $td_type = isset($_GET['td_type']) ? $conn->real_escape_string($_GET['td_type']) : 'all';
        $td_group_by = isset($_GET['td_group_by']) ? $conn->real_escape_string($_GET['td_group_by']) : 'supplier';
        $days = intval($_GET['period']);

        $where = [];

        // Handle multiple company IDs
        if ($company_ids) {
            $ids = array_map('trim', explode(',', $company_ids));
            $escaped_ids = array_map(function($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $ids);
            $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
        }

        if ($region) $where[] = "l.ContinentName = '$region'";
        if ($tier) $where[] = "c.TierLevel = '$tier'";
        if ($td_type != 'all') $where[] = "dc.CategoryName LIKE '%$td_type%'";
        $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        $where[] = "de.EventRecoveryDate IS NOT NULL";

        $where_clause = implode(' AND ', $where);

        // Determine grouping
        if ($td_group_by === 'region') {
            $sql = "SELECT l.ContinentName as label,
                           SUM(TIMESTAMPDIFF(DAY, de.EventDate, de.EventRecoveryDate)) as total_downtime
                   FROM DisruptionEvent de
                   JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                   JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                   JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                   LEFT JOIN Location l ON c.LocationID = l.LocationID
                   WHERE $where_clause AND l.ContinentName IS NOT NULL
                   GROUP BY l.ContinentName
                   ORDER BY total_downtime DESC
                   LIMIT 10";
        } else {
            // Group by supplier (company)
            $sql = "SELECT c.CompanyName as label,
                           SUM(TIMESTAMPDIFF(DAY, de.EventDate, de.EventRecoveryDate)) as total_downtime
                   FROM DisruptionEvent de
                   JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
                   JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                   JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                   LEFT JOIN Location l ON c.LocationID = l.LocationID
                   WHERE $where_clause
                   GROUP BY c.CompanyID, c.CompanyName
                   ORDER BY total_downtime DESC
                   LIMIT 10";
        }

        $result = $conn->query($sql);
        $data = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = [
                    'label' => $row['label'],
                    'total_downtime' => intval($row['total_downtime'] ?: 0)
                ];
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

// Regional Risk Concentration
case 'getRRC':
    $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
    $tier        = isset($_GET['tier']) && $_GET['tier'] != '' ? $conn->real_escape_string($_GET['tier']) : '';
    $days        = isset($_GET['period']) ? intval($_GET['period']) : 3650;
    $region      = isset($_GET['region']) && $_GET['region'] != ''
                   ? $conn->real_escape_string($_GET['region'])
                   : '';

    $where = [];

    // Handle multiple company IDs
    if ($company_ids) {
        $ids = array_map('trim', explode(',', $company_ids));
        $escaped_ids = array_map(function($id) use ($conn) {
            return "'" . $conn->real_escape_string($id) . "'";
        }, $ids);
        $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
    }

    if ($tier) {
        $where[] = "c.TierLevel = '$tier'";
    }

    if ($region) {
        $where[] = "l.ContinentName = '$region'";
    }

    // Only filter by date if not all-time
    if ($days < 3650) {
        $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    }

    $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

    // total unique disruption events
    $sql_total = "SELECT COUNT(DISTINCT de.EventID) AS total
                  FROM DisruptionEvent de";

    if ($where_clause) {
        $sql_total .= "
            JOIN ImpactsCompany ic ON de.EventID = ic.EventID
            JOIN Company c       ON ic.AffectedCompanyID = c.CompanyID
            JOIN Location l      ON c.LocationID = l.LocationID
            $where_clause";
    }

    $result_total      = $conn->query($sql_total);
    $total_disruptions = 1;
    if ($result_total && $row_total = $result_total->fetch_assoc()) {
        $total_disruptions = max(1, intval($row_total['total']));
    }

    // count of unique events per region 
    $sql = "SELECT
                l.ContinentName AS region,
                COUNT(DISTINCT de.EventID) AS region_count
            FROM DisruptionEvent de
            JOIN ImpactsCompany ic ON de.EventID = ic.EventID
            JOIN Company c         ON ic.AffectedCompanyID = c.CompanyID
            JOIN Location l        ON c.LocationID = l.LocationID";

    $where_region = [];

    if ($company_ids) {
        $ids = array_map('trim', explode(',', $company_ids));
        $escaped_ids = array_map(function($id) use ($conn) {
            return "'" . $conn->real_escape_string($id) . "'";
        }, $ids);
        $where_region[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
    }

    if ($tier) {
        $where_region[] = "c.TierLevel = '$tier'";
    }

    if ($region) {
        $where_region[] = "l.ContinentName = '$region'";
    }

    if ($days < 3650) {
        $where_region[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
    }

    $where_region[] = "l.ContinentName IS NOT NULL";

    $sql .= " WHERE " . implode(' AND ', $where_region);
    $sql .= " GROUP BY l.ContinentName
              ORDER BY region_count DESC";

    $result = $conn->query($sql);
    $data   = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $count      = (int)$row['region_count'];
            $percentage = round(($count / $total_disruptions) * 100, 1); 

            $data[] = [
                'region'     => $row['region'],
                'value'      => $percentage,   
                'count'      => $count,        
                'risk_score' => $percentage
            ];
        }
    }

    $response = [
        'success' => true,
        'data'    => $data,
        'total'   => $total_disruptions
    ];
    break;




    // High-Impact Disruption Rate
    case 'getHDR':
        $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
        $region = isset($_GET['region']) && $_GET['region'] != '' ? $conn->real_escape_string($_GET['region']) : '';
        $tier = isset($_GET['tier']) && $_GET['tier'] != '' ? $conn->real_escape_string($_GET['tier']) : '';
        $days = intval($_GET['hdr_period']);

        $where = [];

        // Handle multiple company IDs
        if ($company_ids) {
            $ids = array_map('trim', explode(',', $company_ids));
            $escaped_ids = array_map(function($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $ids);
            $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
        }

        if ($region) $where[] = "l.ContinentName = '$region'";
        if ($tier) $where[] = "c.TierLevel = '$tier'";

        // Only filter by date if not looking at all time
        if ($days < 3650) {
            $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        // HDR = (N_high_impact / N_disruptions) × 100%
        $sql = "SELECT
                COUNT(DISTINCT de.EventID) as total_disruptions,
                COUNT(DISTINCT CASE WHEN ic.ImpactLevel = 'High' THEN de.EventID END) as high_impact_disruptions,
                COALESCE(ROUND((COUNT(DISTINCT CASE WHEN ic.ImpactLevel = 'High' THEN de.EventID END) / NULLIF(COUNT(DISTINCT de.EventID), 0)) * 100, 1), 0) as hdr_pct
                FROM DisruptionEvent de
                JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                $where_clause";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = [
                'success' => true,
                'hdr_pct' => $row['hdr_pct'],
                'total_events' => $row['total_disruptions'],
                'high_events' => $row['high_impact_disruptions']
            ];
        } else {
            $response = ['success' => true, 'hdr_pct' => 0, 'total_events' => 0, 'high_events' => 0];
        }
        break;

    // Disruption Severity Distribution
    case 'getDSD':
        $company_ids = isset($_GET['company_ids']) ? $_GET['company_ids'] : '';
        $region = isset($_GET['region']) && $_GET['region'] != '' ? $conn->real_escape_string($_GET['region']) : '';
        $tier = isset($_GET['tier']) && $_GET['tier'] != '' ? $conn->real_escape_string($_GET['tier']) : '';
        $days = intval($_GET['period']);

        $where = [];

        // Handle multiple company IDs
        if ($company_ids) {
            $ids = array_map('trim', explode(',', $company_ids));
            $escaped_ids = array_map(function($id) use ($conn) {
                return "'" . $conn->real_escape_string($id) . "'";
            }, $ids);
            $where[] = "ic.AffectedCompanyID IN (" . implode(',', $escaped_ids) . ")";
        }

        if ($region) $where[] = "l.ContinentName = '$region'";
        if ($tier) $where[] = "c.TierLevel = '$tier'";

        // Only filter by date if not looking at all time
        if ($days < 3650) {
            $where[] = "de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT ic.ImpactLevel, COUNT(*) as count
                FROM DisruptionEvent de
                JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
                LEFT JOIN Location l ON c.LocationID = l.LocationID
                $where_clause
                GROUP BY ic.ImpactLevel";

        $result = $conn->query($sql);
        $data = ['Low' => 0, 'Medium' => 0, 'High' => 0];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $impactLevel = $row['ImpactLevel'];
                if (isset($data[$impactLevel])) {
                    $data[$impactLevel] = intval($row['count']);
                }
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

    // transactions tab queries

    case 'getLocations':
        $sql = "SELECT DISTINCT l.City
                FROM Location l
                JOIN Company c ON l.LocationID = c.LocationID
                JOIN Shipping s ON c.CompanyID = s.SourceCompanyID
                WHERE l.City IS NOT NULL
                ORDER BY l.City";
        $result = $conn->query($sql);
        $locations = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $locations[] = $row['City'];
            }
        }
        $response = ['success' => true, 'locations' => $locations];
        break;

    case 'getShippingCompanies':
        $sql = "SELECT DISTINCT TRIM(c.CompanyName) as CompanyName
                FROM Company c
                JOIN Shipping s ON c.CompanyID = s.SourceCompanyID
                WHERE c.CompanyName IS NOT NULL AND TRIM(c.CompanyName) != ''
                ORDER BY TRIM(c.CompanyName)";
        $result = $conn->query($sql);
        $companies = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row['CompanyName'];
            }
        }
        $response = ['success' => true, 'companies' => $companies];
        break;

    case 'getReceivingCompanies':
        $sql = "SELECT DISTINCT TRIM(c.CompanyName) as CompanyName
                FROM Company c
                JOIN Shipping s ON c.CompanyID = s.DestinationCompanyID
                WHERE c.CompanyName IS NOT NULL AND TRIM(c.CompanyName) != ''
                ORDER BY TRIM(c.CompanyName)";
        $result = $conn->query($sql);
        $companies = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $companies[] = $row['CompanyName'];
            }
        }
        $response = ['success' => true, 'companies' => $companies];
        break;

    case 'getDistributors':
        $sql = "SELECT DISTINCT c.CompanyID as company_id, c.CompanyName as company_name
                FROM Company c
                WHERE c.Type IN ('Distributor', 'Logistics Provider')
                ORDER BY c.CompanyName";
        $result = $conn->query($sql);
        $distributors = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $distributors[] = $row;
            }
        }
        $response = ['success' => true, 'distributors' => $distributors];
        break;

    case 'getTransactions':
        $days = intval($_GET['time_range']);
        $location = isset($_GET['location']) && $_GET['location'] != 'ALL' ? $_GET['location'] : '';
        $status = isset($_GET['status']) && $_GET['status'] != 'ALL' ? $conn->real_escape_string($_GET['status']) : '';
        $shipping_company = isset($_GET['shipping_company']) && $_GET['shipping_company'] != 'ALL' ? $_GET['shipping_company'] : '';
        $receiving_company = isset($_GET['receiving_company']) && $_GET['receiving_company'] != 'ALL' ? $_GET['receiving_company'] : '';

        $where = [];
        if ($days < 3650) {
            $where[] = "s.PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        // Filter by status 
        if ($status) {
            if ($status == 'Pending') {
                $where[] = "s.ActualDate IS NULL";
            } elseif ($status == 'OnTime') {
                $where[] = "s.ActualDate IS NOT NULL AND s.ActualDate <= s.PromisedDate";
            } elseif ($status == 'Delayed') {
                $where[] = "s.ActualDate IS NOT NULL AND s.ActualDate > s.PromisedDate";
            }
        }

        // Filter by location(s) 
        if ($location) {
            $locations = array_map('trim', explode(',', $location));
            $escaped_locations = array_map(function($loc) use ($conn) {
                return "'" . $conn->real_escape_string($loc) . "'";
            }, $locations);
            $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
        }

        // Filter by shipping company
        if ($shipping_company) {
            $companies = array_map('trim', explode('|', $shipping_company));
            $escaped_companies = array_map(function($comp) use ($conn) {
                return "'" . $conn->real_escape_string($comp) . "'";
            }, $companies);
            $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
        }

        // Filter by receiving company
        if ($receiving_company) {
            $companies = array_map('trim', explode('|', $receiving_company));
            $escaped_companies = array_map(function($comp) use ($conn) {
                return "'" . $conn->real_escape_string($comp) . "'";
            }, $companies);
            $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
        }

        $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT s.ShipmentID as transaction_id,
              DATE_FORMAT(s.PromisedDate, '%Y-%m-%d') as transaction_date,
              CONCAT(l.City, ', ', l.CountryName) as location,
              c1.CompanyName as shipping_company,
              c2.CompanyName as receiving_company,
              CASE
                  WHEN s.ActualDate IS NULL THEN 'Pending'
                  WHEN s.ActualDate <= s.PromisedDate THEN 'On Time'
                  ELSE 'Delayed'
              END as status,
              CASE
                  WHEN s.ActualDate > s.PromisedDate THEN DATEDIFF(s.ActualDate, s.PromisedDate)
                  ELSE 0
              END as exposure_score
       FROM Shipping s
       JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
       JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
       LEFT JOIN Location l ON c1.LocationID = l.LocationID
       $where_clause
       ORDER BY s.PromisedDate DESC";

        $result = $conn->query($sql);
        $transactions = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        }

        $response = ['success' => true, 'transactions' => $transactions];
        break;

    case 'getShipmentVolume':
        $days = intval($_GET['vol_range']);
        $location = isset($_GET['location']) && $_GET['location'] != 'ALL' ? $_GET['location'] : '';
        $distributor = isset($_GET['distributor']) && $_GET['distributor'] != 'ALL' ? $conn->real_escape_string($_GET['distributor']) : '';
        $shipping_company = isset($_GET['shipping_company']) && $_GET['shipping_company'] != 'ALL' ? $_GET['shipping_company'] : '';
        $receiving_company = isset($_GET['receiving_company']) && $_GET['receiving_company'] != 'ALL' ? $_GET['receiving_company'] : '';

        $data = [];

        // For very long ranges (>90 days), use weekly aggregation instead of daily
        // For "All time" (3650 days), show ALL data in table aggregated by month
        if ($days >= 3650) {
            $where = [];
            if ($location) {
                $locations = array_map('trim', explode(',', $location));
                $escaped_locations = array_map(function($loc) use ($conn) {
                    return "'" . $conn->real_escape_string($loc) . "'";
                }, $locations);
                $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
            }
            if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";
            if ($shipping_company) {
                $companies = array_map('trim', explode('|', $shipping_company));
                $escaped_companies = array_map(function($comp) use ($conn) {
                    return "'" . $conn->real_escape_string($comp) . "'";
                }, $companies);
                $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
            }
            if ($receiving_company) {
                $companies = array_map('trim', explode('|', $receiving_company));
                $escaped_companies = array_map(function($comp) use ($conn) {
                    return "'" . $conn->real_escape_string($comp) . "'";
                }, $companies);
                $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
            }
            $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT DATE_FORMAT(s.PromisedDate, '%Y-%m') as month_key, DATE_FORMAT(s.PromisedDate, '%b %Y') as period, COUNT(*) as volume FROM Shipping s JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID LEFT JOIN Location l ON c1.LocationID = l.LocationID $where_clause GROUP BY month_key, period ORDER BY month_key ASC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = ['period' => $row['period'], 'volume' => $row['volume']];
                }
            }
        } elseif ($days > 90) {
            // Weekly aggregation for 91-3649 days
            $weeks = ceil($days / 7);

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $start_day = $i * 7;
                $end_day = ($i + 1) * 7;

                $where = ["s.PromisedDate BETWEEN DATE_SUB(CURDATE(), INTERVAL $end_day DAY) AND DATE_SUB(CURDATE(), INTERVAL $start_day DAY)"];

                // Handle multiple locations
                if ($location) {
                    $locations = array_map('trim', explode(',', $location));
                    $escaped_locations = array_map(function($loc) use ($conn) {
                        return "'" . $conn->real_escape_string($loc) . "'";
                    }, $locations);
                    $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
                }

                if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";

                // Handle shipping company filter
                if ($shipping_company) {
                    $companies = array_map('trim', explode('|', $shipping_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                // Handle receiving company filter
                if ($receiving_company) {
                    $companies = array_map('trim', explode('|', $receiving_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                $where_clause = implode(' AND ', $where);

                $sql = "SELECT COUNT(*) as volume
                       FROM Shipping s
                       JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
                       JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
                       LEFT JOIN Location l ON c1.LocationID = l.LocationID
                       WHERE $where_clause";

                $result = $conn->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    // Format week label
                    $week_start = date('M d', strtotime("-$end_day days"));
                    $week_end = date('M d', strtotime("-$start_day days"));
                    $data[] = ['period' => "$week_start", 'volume' => $row['volume']];
                }
            }
        } else {
            // Daily data for <= 90 days
            for ($i = $days - 1; $i >= 0; $i--) {
                $where = ["DATE(s.PromisedDate) = DATE_SUB(CURDATE(), INTERVAL $i DAY)"];

                // Handle multiple locations
                if ($location) {
                    $locations = array_map('trim', explode(',', $location));
                    $escaped_locations = array_map(function($loc) use ($conn) {
                        return "'" . $conn->real_escape_string($loc) . "'";
                    }, $locations);
                    $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
                }

                if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";

                // Handle shipping company filter
                if ($shipping_company) {
                    $companies = array_map('trim', explode('|', $shipping_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                // Handle receiving company filter
                if ($receiving_company) {
                    $companies = array_map('trim', explode('|', $receiving_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                $where_clause = implode(' AND ', $where);

                $sql = "SELECT COUNT(*) as volume
                       FROM Shipping s
                       JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
                       JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
                       LEFT JOIN Location l ON c1.LocationID = l.LocationID
                       WHERE $where_clause";

                $result = $conn->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    // Format date label - show individual days
                    $date_label = date('M d', strtotime("-$i days"));
                    $data[] = ['period' => $date_label, 'volume' => $row['volume']];
                }
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

    case 'getOnTimeChart':
        $days = intval($_GET['ot_range']);
        $location = isset($_GET['location']) && $_GET['location'] != 'ALL' ? $_GET['location'] : '';
        $distributor = isset($_GET['distributor']) && $_GET['distributor'] != 'ALL' ? $conn->real_escape_string($_GET['distributor']) : '';
        $shipping_company = isset($_GET['shipping_company']) && $_GET['shipping_company'] != 'ALL' ? $_GET['shipping_company'] : '';
        $receiving_company = isset($_GET['receiving_company']) && $_GET['receiving_company'] != 'ALL' ? $_GET['receiving_company'] : '';

        $data = [];

        // For very long ranges (>90 days), use weekly aggregation instead of daily
        // For "All time" (3650 days), show ALL data in table aggregated by month
        if ($days >= 3650) {
            $where = [];
            if ($location) {
                $locations = array_map('trim', explode(',', $location));
                $escaped_locations = array_map(function($loc) use ($conn) {
                    return "'" . $conn->real_escape_string($loc) . "'";
                }, $locations);
                $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
            }
            if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";
            if ($shipping_company) {
                $companies = array_map('trim', explode('|', $shipping_company));
                $escaped_companies = array_map(function($comp) use ($conn) {
                    return "'" . $conn->real_escape_string($comp) . "'";
                }, $companies);
                $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
            }
            if ($receiving_company) {
                $companies = array_map('trim', explode('|', $receiving_company));
                $escaped_companies = array_map(function($comp) use ($conn) {
                    return "'" . $conn->real_escape_string($comp) . "'";
                }, $companies);
                $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
            }
            $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
            $where_clause .= ($where_clause ? ' AND' : 'WHERE') . ' s.ActualDate IS NOT NULL';
            $sql = "SELECT DATE_FORMAT(s.PromisedDate, '%Y-%m') as month_key, DATE_FORMAT(s.PromisedDate, '%b %Y') as period, ROUND((SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as on_time_pct FROM Shipping s JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID LEFT JOIN Location l ON c1.LocationID = l.LocationID $where_clause GROUP BY month_key, period ORDER BY month_key ASC";
            $result = $conn->query($sql);
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $data[] = ['period' => $row['period'], 'on_time_pct' => $row['on_time_pct'] ?: 0];
                }
            }
        } elseif ($days > 90) {
            $weeks = ceil($days / 7);

            for ($i = $weeks - 1; $i >= 0; $i--) {
                $start_day = $i * 7;
                $end_day = ($i + 1) * 7;

                $where = [
                    "s.PromisedDate BETWEEN DATE_SUB(CURDATE(), INTERVAL $end_day DAY) AND DATE_SUB(CURDATE(), INTERVAL $start_day DAY)",
                    "s.ActualDate IS NOT NULL"
                ];

                // Handle multiple locations
                if ($location) {
                    $locations = array_map('trim', explode(',', $location));
                    $escaped_locations = array_map(function($loc) use ($conn) {
                        return "'" . $conn->real_escape_string($loc) . "'";
                    }, $locations);
                    $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
                }

                if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";

                // Handle shipping company filter
                if ($shipping_company) {
                    $companies = array_map('trim', explode('|', $shipping_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                // Handle receiving company filter
                if ($receiving_company) {
                    $companies = array_map('trim', explode('|', $receiving_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                $where_clause = implode(' AND ', $where);

                $sql = "SELECT ROUND((SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as on_time_pct
                       FROM Shipping s
                       JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
                       JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
                       LEFT JOIN Location l ON c1.LocationID = l.LocationID
                       WHERE $where_clause";

                $result = $conn->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    // Format week label
                    $week_start = date('M d', strtotime("-$end_day days"));
                    $week_end = date('M d', strtotime("-$start_day days"));
                    $data[] = ['period' => "$week_start", 'on_time_pct' => $row['on_time_pct'] ?: 0];
                }
            }
        } else {
            // Daily data for <= 90 days
            for ($i = $days - 1; $i >= 0; $i--) {
                $where = [
                    "DATE(s.PromisedDate) = DATE_SUB(CURDATE(), INTERVAL $i DAY)",
                    "s.ActualDate IS NOT NULL"
                ];

                // Handle multiple locations
                if ($location) {
                    $locations = array_map('trim', explode(',', $location));
                    $escaped_locations = array_map(function($loc) use ($conn) {
                        return "'" . $conn->real_escape_string($loc) . "'";
                    }, $locations);
                    $where[] = "l.City IN (" . implode(',', $escaped_locations) . ")";
                }

                if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";

                // Handle shipping company filter
                if ($shipping_company) {
                    $companies = array_map('trim', explode('|', $shipping_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                // Handle receiving company filter
                if ($receiving_company) {
                    $companies = array_map('trim', explode('|', $receiving_company));
                    $escaped_companies = array_map(function($comp) use ($conn) {
                        return "'" . $conn->real_escape_string($comp) . "'";
                    }, $companies);
                    $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
                }

                $where_clause = implode(' AND ', $where);

                $sql = "SELECT ROUND((SUM(CASE WHEN s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as on_time_pct
                       FROM Shipping s
                       JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
                       JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
                       LEFT JOIN Location l ON c1.LocationID = l.LocationID
                       WHERE $where_clause";

                $result = $conn->query($sql);
                if ($result && $row = $result->fetch_assoc()) {
                    // Format date label - show individual days
                    $date_label = date('M d', strtotime("-$i days"));
                    $data[] = ['period' => $date_label, 'on_time_pct' => $row['on_time_pct'] ?: 0];
                }
            }
        }

        $response = ['success' => true, 'data' => $data];
        break;

    case 'getStatusMix':
        $days = intval($_GET['status_range']);
        $location = isset($_GET['location']) && $_GET['location'] != 'ALL' ? $_GET['location'] : '';
        $distributor = isset($_GET['distributor']) && $_GET['distributor'] != 'ALL' ? $conn->real_escape_string($_GET['distributor']) : '';
        $shipping_company = isset($_GET['shipping_company']) && $_GET['shipping_company'] != 'ALL' ? $_GET['shipping_company'] : '';
        $receiving_company = isset($_GET['receiving_company']) && $_GET['receiving_company'] != 'ALL' ? $_GET['receiving_company'] : '';

        // First, get total count to verify data exists
        $sql_count = "SELECT COUNT(*) as total FROM Shipping";
        $result_count = $conn->query($sql_count);
        $total_in_db = 0;
        if ($result_count && $row_count = $result_count->fetch_assoc()) {
            $total_in_db = $row_count['total'];
        }

        $where = array();

        if ($days < 3650) {
            $where[] = "PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";
        }

        // Handle multiple locations
        if ($location) {
            $locations = array_map('trim', explode(',', $location));
            $escaped_locations = array_map(function($loc) use ($conn) {
                return "'" . $conn->real_escape_string($loc) . "'";
            }, $locations);
            $where[] = "EXISTS (SELECT 1 FROM Company c JOIN Location l ON c.LocationID = l.LocationID
                        WHERE c.CompanyID = Shipping.SourceCompanyID AND l.City IN (" . implode(',', $escaped_locations) . "))";
        }

        if ($distributor) {
            $where[] = "(SourceCompanyID = '$distributor' OR DestinationCompanyID = '$distributor')";
        }

        // Handle shipping company filter
        if ($shipping_company) {
            $companies = array_map('trim', explode('|', $shipping_company));
            $escaped_companies = array_map(function($comp) use ($conn) {
                return "'" . $conn->real_escape_string($comp) . "'";
            }, $companies);
            $where[] = "EXISTS (SELECT 1 FROM Company c WHERE c.CompanyID = Shipping.SourceCompanyID
                        AND c.CompanyName IN (" . implode(',', $escaped_companies) . "))";
        }

        // Handle receiving company filter
        if ($receiving_company) {
            $companies = array_map('trim', explode('|', $receiving_company));
            $escaped_companies = array_map(function($comp) use ($conn) {
                return "'" . $conn->real_escape_string($comp) . "'";
            }, $companies);
            $where[] = "EXISTS (SELECT 1 FROM Company c WHERE c.CompanyID = Shipping.DestinationCompanyID
                        AND c.CompanyName IN (" . implode(',', $escaped_companies) . "))";
        }

        $where_clause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';


        $sql = "SELECT
                COALESCE(SUM(CASE WHEN ActualDate IS NULL THEN 1 ELSE 0 END), 0) as `Pending`,
                COALESCE(SUM(CASE WHEN ActualDate IS NOT NULL AND ActualDate <= PromisedDate THEN 1 ELSE 0 END), 0) as `OnTime`,
                COALESCE(SUM(CASE WHEN ActualDate IS NOT NULL AND ActualDate > PromisedDate THEN 1 ELSE 0 END), 0) as `Delayed`,
                COUNT(*) as `Total`
               FROM Shipping
               $where_clause";

        $result = $conn->query($sql);
        if ($result && $row = $result->fetch_assoc()) {
            $response = array(
                'success' => true,
                'data' => $row,
                'total_in_db' => $total_in_db
            );
        } else {
            $response = array(
                'success' => true,
                'data' => array('Pending' => 0, 'OnTime' => 0, 'Delayed' => 0, 'Total' => 0),
                'total_in_db' => $total_in_db,
                'error' => $conn->error
            );
        }
        break;

    case 'getExposureByLane':
    $days = intval($_GET['exp_range']);
    $location = isset($_GET['location']) && $_GET['location'] != 'ALL' ? $_GET['location'] : '';
    $distributor = isset($_GET['distributor']) && $_GET['distributor'] != 'ALL' ? $conn->real_escape_string($_GET['distributor']) : '';
    $shipping_company = isset($_GET['shipping_company']) && $_GET['shipping_company'] != 'ALL' ? $_GET['shipping_company'] : '';
    $receiving_company = isset($_GET['receiving_company']) && $_GET['receiving_company'] != 'ALL' ? $_GET['receiving_company'] : '';

    $where = ["s.PromisedDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)"];

    // Handle multiple locations
    if ($location) {
        $locations = array_map('trim', explode(',', $location));
        $escaped_locations = array_map(function($loc) use ($conn) {
            return "'" . $conn->real_escape_string($loc) . "'";
        }, $locations);
        $where[] = "l1.City IN (" . implode(',', $escaped_locations) . ")";
    }

    if ($distributor) $where[] = "(s.SourceCompanyID = '$distributor' OR s.DestinationCompanyID = '$distributor')";

    // Handle shipping company filter
    if ($shipping_company) {
        $companies = array_map('trim', explode('|', $shipping_company));
        $escaped_companies = array_map(function($comp) use ($conn) {
            return "'" . $conn->real_escape_string($comp) . "'";
        }, $companies);
        $where[] = "TRIM(c1.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
    }

    // Handle receiving company filter
    if ($receiving_company) {
        $companies = array_map('trim', explode('|', $receiving_company));
        $escaped_companies = array_map(function($comp) use ($conn) {
            return "'" . $conn->real_escape_string($comp) . "'";
        }, $companies);
        $where[] = "TRIM(c2.CompanyName) IN (" . implode(',', $escaped_companies) . ")";
    }

    $where_clause = implode(' AND ', $where);

    // First get shipping lanes
    $sql = "SELECT
            CONCAT(l1.City, ' → ', l2.City) as lane,
            c1.CompanyID as source_company,
            c2.CompanyID as dest_company
           FROM Shipping s
           JOIN Company c1 ON s.SourceCompanyID = c1.CompanyID
           JOIN Company c2 ON s.DestinationCompanyID = c2.CompanyID
           LEFT JOIN Location l1 ON c1.LocationID = l1.LocationID
           LEFT JOIN Location l2 ON c2.LocationID = l2.LocationID
           WHERE $where_clause
           GROUP BY l1.City, l2.City, c1.CompanyID, c2.CompanyID";

    $result = $conn->query($sql);
    $data = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $lane = $row['lane'];
            $source_id = $row['source_company'];
            $dest_id = $row['dest_company'];

            // Count disruptions affecting this lane
            $sql_disruptions = "SELECT
                COUNT(DISTINCT de.EventID) as total_disruptions,
                SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END) as high_impact_count
                FROM DisruptionEvent de
                JOIN ImpactsCompany ic ON de.EventID = ic.EventID
                WHERE (ic.AffectedCompanyID = '$source_id' OR ic.AffectedCompanyID = '$dest_id')
                AND de.EventDate >= DATE_SUB(CURDATE(), INTERVAL $days DAY)";

            $result_dis = $conn->query($sql_disruptions);
            if ($result_dis && $dis_row = $result_dis->fetch_assoc()) {
                $total = intval($dis_row['total_disruptions']);
                $high = intval($dis_row['high_impact_count']);
                $exposure_score = $total + (2 * $high);

                if ($exposure_score > 0) {
                    $data[] = [
                        'lane' => $lane,
                        'exposure_score' => $exposure_score
                    ];
                }
            }
        }
    }

    // Sort by exposure score descending
    usort($data, function($a, $b) {
        return $b['exposure_score'] - $a['exposure_score'];
    });

    // Limit to top 10
    $data = array_slice($data, 0, 10);

    $response = ['success' => true, 'data' => $data];
    break;

    // update transactions
    case 'updateTransaction':
        $shipment_id = $conn->real_escape_string($_POST['shipment_id']);
        $status = $conn->real_escape_string($_POST['status']);
        $promised_date = isset($_POST['promised_date']) ? $conn->real_escape_string($_POST['promised_date']) : null;

        $updates = [];

        // Update PromisedDate if provided
        if ($promised_date) {
            $updates[] = "PromisedDate = '$promised_date'";
        }

        if ($status === 'Pending') {
            $updates[] = "ActualDate = NULL";
        } elseif ($status === 'On Time' || $status === 'OnTime') {
            if ($promised_date) {
                $updates[] = "ActualDate = '$promised_date'";
            } else {
                $updates[] = "ActualDate = PromisedDate";
            }
        } else { // Delayed
            if ($promised_date) {
                $updates[] = "ActualDate = DATE_ADD('$promised_date', INTERVAL 3 DAY)";
            } else {
                $updates[] = "ActualDate = DATE_ADD(PromisedDate, INTERVAL 3 DAY)";
            }
        }

        if (empty($updates)) {
            $response = ['success' => false, 'message' => 'No fields to update'];
        } else {
            $sql = "UPDATE Shipping SET " . implode(', ', $updates) . " WHERE ShipmentID = '$shipment_id'";

            if ($conn->query($sql)) {
                $response = ['success' => true, 'message' => 'Transaction updated successfully'];
            } else {
                $response = ['success' => false, 'message' => $conn->error];
            }
        }
        break;

    default:
        $response = ['success' => false, 'error' => 'Invalid action: ' . $action];
        break;
}

echo json_encode($response);
$conn->close();
?>