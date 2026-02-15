<?php
$servername = "mydb.itap.purdue.edu";
$username = "g1151934";
$password = "group24";
$database = $username;

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed: ' . $conn->connect_error]));
}

//new end points for company modal
if (isset($_GET['action'])) {
    $modern_action = $_GET['action'];
    $modern_result = ['success' => false];
    
    switch ($modern_action) {
        case 'searchCompanies':
            $term = $conn->real_escape_string($_GET['term']);
            $sql = "SELECT c.CompanyID as company_id, c.CompanyName as company_name, c.Type as company_type,
                           CONCAT_WS(', ', l.City, l.CountryName) as address
                    FROM Company c
                    LEFT JOIN Location l ON c.LocationID = l.LocationID
                    WHERE c.CompanyName LIKE '%$term%'
                    ORDER BY c.CompanyName";
                    //LIMIT 10";
            $result = $conn->query($sql);
            $companies = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $companies[] = $row;
                }
            }
            $modern_result = ['success' => true, 'companies' => $companies];
            echo json_encode($modern_result);
            $conn->close();
            exit();
            
        case 'getCompanyInfo':
            if (!isset($_GET['company_id'])) {
                echo json_encode(['success' => false, 'error' => 'No company_id provided']);
                $conn->close();
                exit();
            }
            
            $company_id = $conn->real_escape_string($_GET['company_id']);
            
            // Main company info
            $sql = "SELECT c.CompanyID, c.CompanyName as company_name, c.Type as company_type, 
                           c.TierLevel as tier_level,
                           CONCAT_WS(', ', l.City, l.CountryName) as address
                    FROM Company c
                    LEFT JOIN Location l ON c.LocationID = l.LocationID
                    WHERE c.CompanyID = '$company_id'";
            
            $result = $conn->query($sql);
            
            if (!$result) {
                echo json_encode(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
                $conn->close();
                exit();
            }
            
            if ($company = $result->fetch_assoc()) {
                
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
                } else {
                    $company['financial_health'] = null;
                }
                
                // Get capacity or routes depending on company type
                if ($company['company_type'] == 'Manufacturer') {
                    $sql_cap = "SELECT FactoryCapacity as capacity FROM Manufacturer WHERE CompanyID = '$company_id'";
                    $result_cap = $conn->query($sql_cap);
                    if ($result_cap && $cap = $result_cap->fetch_assoc()) {
                        $company['capacity'] = $cap['capacity'];
                    } else {
                        $company['capacity'] = null;
                    }
                } elseif ($company['company_type'] == 'Distributor' || $company['company_type'] == 'Logistics Provider') {
                    $sql_routes = "SELECT COUNT(DISTINCT CONCAT(FromCompanyID, '-', ToCompanyID)) as routes_count
                                  FROM OperatesLogistics
                                  WHERE DistributorID = '$company_id'";
                    $result_routes = $conn->query($sql_routes);
                    if ($result_routes && $routes = $result_routes->fetch_assoc()) {
                        $company['routes_count'] = $routes['routes_count'];
                    } else {
                        $company['routes_count'] = 0;
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
                
                $modern_result = ['success' => true, 'company' => $company];
            } else {
                $modern_result = ['success' => false, 'error' => 'Company not found with ID: ' . $company_id];
            }
            
            echo json_encode($modern_result);
            $conn->close();
            exit();
            
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
            
            $modern_result = ['success' => true, 'transactions' => $transactions];
            echo json_encode($modern_result);
            $conn->close();
            exit();
    }
}

// Get the value from the q variable within the get request sent by AJAX
$tmp = $_GET['q'];

// Convert the comma-delimited string into an array of strings.
$tmp = explode(',', $tmp);

// Extract action and parameters from the array
$action = $tmp[0];

// Initialize result variable
$result = null;
$rows = [];




switch ($action) {
    
// Date range assisting query for distributors
    case 'get_datelimits':
        $sql = "SELECT 
        MIN(PromisedDate) as mindate
        FROM Shipping";
    $result = mysqli_query($conn, $sql);
    break;

// Date range assisting query for financials
     case 'get_quarterlimits':
        $sql = "SELECT 
        MIN(RepYear*4 + Quarter) as min
        FROM FinancialReport";
    $result = mysqli_query($conn, $sql);
    break;

//Distrubutors Tab
//Top disributors sorted by volume over user defined time period 
    case 'get_top_distributors':
        $start_date = $conn->real_escape_string($tmp[1]);
        $end_date = $conn->real_escape_string($tmp[2]);
        $sql = "SELECT 
    c.CompanyName AS DistributorName,
    SUM(s.Quantity) AS TotalVolume,
    ROUND(
        AVG(
            CASE 
                WHEN s.ActualDate IS NOT NULL AND s.ActualDate <= s.PromisedDate 
                THEN 1 ELSE 0 
            END
        ) * 100, 2
    ) AS OnTimePercent
      FROM Shipping s
      JOIN Distributor d ON s.DistributorID = d.CompanyID
      JOIN Company c ON c.CompanyID = d.CompanyID
      WHERE s.PromisedDate BETWEEN '$start_date' AND '$end_date'
      AND s.ActualDate IS NOT NULL
      GROUP BY s.DistributorID
      ORDER BY TotalVolume DESC";
    $result = mysqli_query($conn, $sql);
    break;

//Disruption count across top performers by volume over user-defined time period
    case 'get_disrupt_topdis':
        $start_date = $conn->real_escape_string($tmp[1]);
        $end_date = $conn->real_escape_string($tmp[2]);
    $sql = "SELECT c.CompanyName AS DistributorName,
    COALESCE(s.TotalVolume, 0) AS TotalVolume,
    COALESCE(e.TotalEvents, 0) AS TotalEvents
    FROM Company c
    JOIN Distributor d ON c.CompanyID = d.CompanyID

    LEFT JOIN (
    SELECT
        DistributorID,
        SUM(Quantity) AS TotalVolume
      FROM Shipping
    WHERE PromisedDate BETWEEN '$start_date' AND '$end_date'
      AND ActualDate IS NOT NULL
    GROUP BY DistributorID
) AS s ON s.DistributorID = d.CompanyID

LEFT JOIN (
    SELECT
        ic.AffectedCompanyID,
        COUNT(*) AS TotalEvents
    FROM DisruptionEvent de
    JOIN ImpactsCompany ic ON ic.EventID = de.EventID
    WHERE de.EventDate BETWEEN '$start_date' AND '$end_date'
      AND ic.ImpactLevel = 'High'
    GROUP BY ic.AffectedCompanyID
) AS e ON e.AffectedCompanyID = d.CompanyID

ORDER BY TotalVolume DESC";
      $result = mysqli_query($conn, $sql);
        break;

//Distributors sorted by average delay length
    case 'get_disrupt_delay':
        $start_date = $conn->real_escape_string($tmp[1]);
        $end_date = $conn->real_escape_string($tmp[2]);
    $sql = "SELECT d.CompanyID, c.CompanyName AS DistributorName,
            ROUND(AVG(DATEDIFF(s.ActualDate, s.PromisedDate)), 2) AS AvgDelayDays,
            COUNT(*) AS Shipments
            FROM Shipping s, Distributor d, Company c
            WHERE s.DistributorID = d.CompanyID
            AND c.CompanyID = d.CompanyID
            AND PromisedDate BETWEEN '$start_date' AND '$end_date'
            AND s.ActualDate IS NOT NULL
            GROUP BY d.CompanyID, c.CompanyName
            HAVING Shipments > 0
            ORDER BY AvgDelayDays DESC, Shipments DESC";
        $result = mysqli_query($conn, $sql);
        break;


    //Actions bar queries
    //most critical companies sort decesending
    case 'get_most_crit':
        $sql = "SELECT
         c.CompanyID,
         c.CompanyName,
         COALESCE(ds.downstream_cnt, 0) AS downstream_companies,
         COALESCE(hi.hi_cnt, 0)         AS high_impact_count,
         (COALESCE(ds.downstream_cnt,0) * COALESCE(hi.hi_cnt,0)) AS criticality
         FROM Company c
         LEFT JOIN (
         SELECT UpstreamCompanyID AS CompanyID, COUNT(DISTINCT DownstreamCompanyID) AS downstream_cnt
         FROM DependsOn
         GROUP BY UpstreamCompanyID
         ) ds ON ds.CompanyID = c.CompanyID
         LEFT JOIN (
         SELECT ic.AffectedCompanyID AS CompanyID, COUNT(*) AS hi_cnt
         FROM DisruptionEvent de
         JOIN ImpactsCompany ic ON ic.EventID = de.EventID
         WHERE ic.ImpactLevel = 'High'
         GROUP BY ic.AffectedCompanyID
        ) hi ON hi.CompanyID = c.CompanyID
        ORDER BY criticality DESC, downstream_companies DESC, high_impact_count DESC";
        $result = mysqli_query($conn, $sql);
        break;
    
//All company info
    case 'get_company':
        $name = $conn->real_escape_string($tmp[1]);
        $sql = "SELECT * FROM Company WHERE CompanyName LIKE '%" . $name . "%' LIMIT 10";
        $result = mysqli_query($conn, $sql);
        break;

//Any company over user defined time period with all their shipment aggregates
    case 'get_companymetrics':
        $name = $conn->real_escape_string($tmp[1]);
        $start_date = $conn->real_escape_string($tmp[2]);
        $end_date = $conn->real_escape_string($tmp[3]);
        $sql = "SELECT c.CompanyName AS CompanyName,
    SUM(s.Quantity) AS TotalVolume,
    ROUND(AVG(CASE WHEN s.ActualDate IS NOT NULL AND s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) * 100, 2) AS OnTimePercent,
    ROUND(AVG(DATEDIFF(s.ActualDate, s.PromisedDate)), 2) AS AvgDelayDays,
    COUNT(*) AS TotalShipments
    FROM Company c, Distributor d, Shipping s
    WHERE d.CompanyID = s.DistributorID
    AND c.CompanyID = d.CompanyID
    AND s.PromisedDate BETWEEN '$start_date' AND '$end_date'
    AND c.CompanyName LIKE '%" . $name . "%'
    GROUP BY c.CompanyName";
        $result = mysqli_query($conn, $sql);
        break;

//Shipping lane sorted by total volume and average ontime over user defined time period 
    case 'get_top_lanes':
        $start_date = $conn->real_escape_string($tmp[1]);
        $end_date = $conn->real_escape_string($tmp[2]);
        $sql = "SELECT CONCAT(Origin.CountryName, ' - ', Destination.CountryName) AS ShippingLane,
    SUM(s.Quantity) AS TotalVolume,
    ROUND(AVG(CASE WHEN s.ActualDate IS NOT NULL AND s.ActualDate <= s.PromisedDate THEN 1 ELSE 0 END) * 100, 2) AS OnTimePercent
      FROM Shipping s
      JOIN Location Origin ON s.OriginLocationID = Origin.LocationID
      JOIN Location Destination ON s.DestinationLocationID = Destination.LocationID
      WHERE s.PromisedDate BETWEEN '$start_date' AND '$end_date'
      AND s.ActualDate IS NOT NULL
      GROUP BY ShippingLane
      ORDER BY TotalVolume DESC;";
        $result = mysqli_query($conn, $sql);
        break;

//All suppliers to user specified company
        case 'get_depends_on':
        $name = $conn->real_escape_string($tmp[1]);
        $sql = "SELECT c1.CompanyName AS DependentCompany,
               c2.CompanyName AS SuppliersTo
        FROM DependsOn d, Company c1, Company c2
        WHERE d.DownstreamCompanyID = c1.CompanyID AND d.UpstreamCompanyID = c2.CompanyID
        AND c1.CompanyName = '$name'";
        $result = mysqli_query($conn, $sql);
        break;

//All companies that depend on company user specified
    case 'get_depended_on_by':
        $name = $conn->real_escape_string($tmp[1]);
        $sql = "SELECT 
            c1.CompanyName AS SuppliesTo,
            c2.CompanyName AS Customer
        FROM DependsOn d
        JOIN Company c1 ON d.UpstreamCompanyID = c1.CompanyID
        JOIN Company c2 ON d.DownstreamCompanyID = c2.CompanyID
        WHERE c1.CompanyName = '$name'";
        $result = mysqli_query($conn, $sql);
        break;


//Financials tab
    
//Average financial score across all companies and user specified time period
    case 'get_financial_score':
        $start_quarter = intval(substr($tmp[1], 1));
        $end_quarter   = intval(substr($tmp[2], 1));
        $start_year = intval($tmp[3]);
        $end_year   = intval($tmp[4]);
        $sql = "SELECT AVG(Healthscore) AS AvgHealth
                FROM FinancialReport f
                WHERE (f.RepYear*4  + CAST(SUBSTRING(f.Quarter, 2) AS UNSIGNED))
        BETWEEN ($start_year*4 + $start_quarter)
                AND ($end_year*4   + $end_quarter)";
        $result = mysqli_query($conn, $sql);
        break;

//Average financial health by company over user defined time period
case 'get_avg_financialhealth':
     $start_quarter = intval(substr($tmp[1], 1));
    $end_quarter   = intval(substr($tmp[2], 1));
    $start_year = intval($tmp[3]);
    $end_year   = intval($tmp[4]);

    $sql = "SELECT c.CompanyID, c.CompanyName,
       ROUND(AVG(fr.HealthScore), 2) AS avg_health
       FROM FinancialReport fr
       JOIN Company c ON c.CompanyID = fr.CompanyID
       WHERE (fr.RepYear*4  + CAST(SUBSTRING(fr.Quarter, 2) AS UNSIGNED) 
       )
        BETWEEN ($start_year*4 + $start_quarter)
                AND ($end_year*4   + $end_quarter)
        GROUP BY c.CompanyID, c.CompanyName
        ORDER BY avg_health DESC";
      $result = mysqli_query($conn, $sql);
        break;

//Average financial health sorted by type of company, over user defined time period sorted highest to lowest
case 'get_avg_financialhealth2':
    $start_quarter = intval(substr($tmp[1], 1));
    $end_quarter   = intval(substr($tmp[2], 1));
    $start_year = intval($tmp[3]);
    $end_year   = intval($tmp[4]);
    $sql = "SELECT c.Type AS company_type,
       ROUND(AVG(fr.HealthScore), 2) AS avg_health
       FROM FinancialReport fr
       JOIN Company c ON c.CompanyID = fr.CompanyID
       WHERE (fr.RepYear*4 + CAST(SUBSTRING(fr.Quarter, 2) AS UNSIGNED)
       ) BETWEEN ($start_year*4 + $start_quarter)
                 AND ($end_year*4   + $end_quarter)
        GROUP BY c.Type
        ORDER BY avg_health DESC";
      $result = mysqli_query($conn, $sql);
        break;
    
//Average financial score across all companies sorted by regions over user defined time period
    case 'get_financial_regionscores':
           $start_quarter = intval(substr($tmp[1], 1));
           $end_quarter   = intval(substr($tmp[2], 1));
           $start_year = intval($tmp[3]);
           $end_year   = intval($tmp[4]);
        $sql = "SELECT l.ContinentName, AVG(f.Healthscore) AS HealthScore
                FROM FinancialReport f
                JOIN Company c ON c.CompanyID = f.CompanyID
                JOIN Location l ON l.LocationID = c.LocationID
                WHERE (f.RepYear*4 + CAST(SUBSTRING(f.Quarter, 2) AS UNSIGNED)
                ) BETWEEN ($start_year*4 + $start_quarter)
                 AND ($end_year*4   + $end_quarter)
                 GROUP BY l.ContinentName";
        $result = mysqli_query($conn, $sql);
        break; 
    

//Average financial score across all companies sorted by Quarter, Year, and region
    case 'get_financial_regioncomps':
           $region = $conn->real_escape_string($tmp[1]);
           $start_quarter = intval(substr($tmp[2], 1));
           $end_quarter   = intval(substr($tmp[3], 1));
           $start_year = intval($tmp[4]);
           $end_year   = intval($tmp[5]);
        $sql = "SELECT c.CompanyName AS CompanyName, AVG(f.Healthscore) AS HealthScore
                FROM FinancialReport f
                JOIN Company c ON c.CompanyID = f.CompanyID
                JOIN Location l ON l.LocationID = c.LocationID
                WHERE (f.RepYear*4 + CAST(SUBSTRING(f.Quarter, 2) AS UNSIGNED)
                ) BETWEEN ($start_year*4 + $start_quarter)
                 AND ($end_year*4   + $end_quarter) AND l.ContinentName = '$region'
                 GROUP BY c.CompanyID";
        $result = mysqli_query($conn, $sql);
        break; 

// Disruptions Tab Queries 

// Disruption frequency over time
case 'get_disruption_date_limits':
    $sql = "SELECT MIN(EventDate) as min_date, MAX(EventDate) as max_date FROM DisruptionEvent";
    $result = mysqli_query($conn, $sql);
    break;


/* calculates the disruptions frequency 
case 'get_disruption_frequency':
    $days = intval($tmp[1]); 
    $start_date = getDateNDaysAgo($days);
    
    $sql = "SELECT 
        DATE(EventDate) AS disruption_day,
        COUNT(EventID) AS daily_count
        FROM DisruptionEvent
        WHERE EventDate >= '$start_date'
        GROUP BY disruption_day
        ORDER BY disruption_day ASC";
    $result = mysqli_query($conn, $sql);
    break; */
case 'get_disruption_frequency_range':
    $start_raw = isset($tmp[1]) ? $tmp[1] : '';
    $end_raw = isset($tmp[2]) ? $tmp[2] : '';
    
    $start_date = $conn->real_escape_string($start_raw);
    $end_date = $conn->real_escape_string($end_raw);

    $sql = "SELECT 
        DATE(EventDate) AS disruption_day,
        COUNT(EventID) AS daily_count
        FROM DisruptionEvent
        WHERE EventDate BETWEEN '$start_date' AND '$end_date'
        GROUP BY disruption_day
        ORDER BY disruption_day ASC";
    $result = mysqli_query($conn, $sql);
    break;

case 'get_all_companies':
    $sql = "SELECT CompanyName FROM Company ORDER BY CompanyName ASC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
       
    }
    break;

// Regional disruption overview (for RRC calculation)
case 'get_regional_disruptions':
    $start_raw = isset($tmp[1]) ? $tmp[1] : '';
    $end_raw = isset($tmp[2]) ? $tmp[2] : '';

    $start_date = $conn->real_escape_string($start_raw);
    $end_date = $conn->real_escape_string($end_raw);
    
    $sql = "SELECT 
        l.ContinentName AS Region,
        COUNT(de.EventID) AS total_disruptions,
        SUM(CASE WHEN ic.ImpactLevel = 'High' THEN 1 ELSE 0 END) AS high_impact_disruptions
        FROM DisruptionEvent de
        JOIN ImpactsCompany ic ON de.EventID = ic.EventID
        JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
        JOIN Location l ON c.LocationID = l.LocationID
        WHERE de.EventDate BETWEEN '$start_date' AND '$end_date'
        GROUP BY l.ContinentName
        ORDER BY total_disruptions DESC";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        $rows[] = ['error' => mysqli_error($conn)];
    }
    break;
// calaculates disruption severtity for a company 
case 'get_disruption_severity':
    $start_date = getDateOneYearAgo();
    
    $sql = "SELECT 
        ic.ImpactLevel AS severity,
        COUNT(ic.EventID) AS severity_count
        FROM ImpactsCompany ic
        JOIN DisruptionEvent de ON ic.EventID = de.EventID
        WHERE de.EventDate >= '$start_date'
        GROUP BY ic.ImpactLevel
        ORDER BY FIELD(ic.ImpactLevel, 'Low', 'Medium', 'High')";
    $result = mysqli_query($conn, $sql);
    break;

case 'get_all_events':
    // Get list of events for dropdown
    $sql = "SELECT 
        de.EventID, 
        de.EventDate, 
        dc.CategoryName 
    FROM DisruptionEvent de
    JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
    ORDER BY de.EventDate DESC";
    $result = mysqli_query($conn, $sql);
    break;
// calculates disruptions by a specific event     
case 'get_companies_by_event':
    $event_id = intval($tmp[1]);
    $sql = "SELECT 
        c.CompanyName, 
        ic.ImpactLevel 
    FROM ImpactsCompany ic
    JOIN Company c ON ic.AffectedCompanyID = c.CompanyID
    WHERE ic.EventID = $event_id
    ORDER BY field(ic.ImpactLevel, 'High', 'Medium', 'Low'), c.CompanyName";
    $result = mysqli_query($conn, $sql);
    break;

// fetches disruptions for a specific company 
case 'get_company_disruptions':
    $company_name = $conn->real_escape_string($tmp[1]);
    $sql = "SELECT 
        de.EventID,
        dc.CategoryName,
        de.EventDate,
        de.EventRecoveryDate,
        ic.ImpactLevel
    FROM Company c
    JOIN ImpactsCompany ic ON c.CompanyID = ic.AffectedCompanyID
    JOIN DisruptionEvent de ON ic.EventID = de.EventID
    JOIN DisruptionCategory dc ON de.CategoryID = dc.CategoryID
    WHERE c.CompanyName = '$company_name'
    ORDER BY de.EventDate DESC";
    $result = mysqli_query($conn, $sql);
    break;

//grabs all locations for the location drop down in create company
case 'get_locations':
    $sql = "SELECT LocationID, City, CountryName, ContinentName
            FROM Location";
    $result = mysqli_query($conn, $sql);
    break;

//inserts user defined info for create company
case 'insert_newcomp':
    $name = $conn->real_escape_string($tmp[1]);
    $location = (int)$tmp[2];
    $tier = $conn->real_escape_string($tmp[3]);
    $type = $conn->real_escape_string($tmp[4]);
    $sql = "INSERT INTO Company (CompanyName, LocationID, TierLevel, Type)
    VALUES ('$name', $location, '$tier', '$type')";
    mysqli_query($conn, $sql);
    $newId = mysqli_insert_id($conn);  //From AI
    echo json_encode(["CompanyID" => $newId]);
    exit;
    break;

default:
     // Invalid action
    $rows[] = ['error' => 'Invalid action specified'];
    break;


} 
// Convert the table into individual rows and reformat.
// Only process if result is not null and action didn't already populate rows
if ($result && empty($rows)) {
    while ($row = mysqli_fetch_array($result)) {
        $rows[] = $row;
    }
}


// Print as plain-text the result of the JSON encoding of our SQL rows for use by the JS AJAX process
// that calls this php file.
echo json_encode($rows);

// Helper functions
function getDateNDaysAgo($days) {
    return date('Y-m-d', strtotime("-$days days"));
}

function getDateOneYearAgo() {
    return date('Y-m-d', strtotime("-1 year"));
}

$conn->close();
?>