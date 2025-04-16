<?php
require_once 'config.php';
requireLogin();

// Get statistics for dashboard
$stats = [];

// Total customers
$result = $conn->query("SELECT COUNT(*) as total FROM customers");
$stats['total_customers'] = $result->fetch_assoc()['total'];

// Total products
$result = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $result->fetch_assoc()['total'];

// Total sales
$result = $conn->query("SELECT COUNT(*) as total FROM sales");
$stats['total_sales'] = $result->fetch_assoc()['total'];

// Total revenue
$result = $conn->query("SELECT SUM(total_amount) as total FROM sales");
$row = $result->fetch_assoc();
$stats['total_revenue'] = $row['total'] ? $row['total'] : 0;

// Recent sales
$recentSales = [];
$result = $conn->query("
    SELECT s.sale_id, s.sale_date, s.total_amount, 
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
           u.full_name AS salesperson
    FROM sales s
    JOIN customers c ON s.customer_id = c.customer_id
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC
    LIMIT 5
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recentSales[] = $row;
    }
}

// Top products
$topProducts = [];
$result = $conn->query("
    SELECT p.product_name, SUM(si.quantity) as total_quantity, 
           SUM(si.subtotal) as total_revenue
    FROM sale_items si
    JOIN products p ON si.product_id = p.product_id
    GROUP BY si.product_id
    ORDER BY total_quantity DESC
    LIMIT 5
");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $topProducts[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sales Management System</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
    <header>
        <div class="container">
            <a href="dashboard.php" class="logo">Sales Management System</a>
            <nav>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="customers.php">Customers</a></li>
                    <li><a href="sales.php">Sales</a></li>
                    <?php if (isAdmin()): ?>
                    <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <h1 class="page-title">Dashboard</h1>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-label">Customers</div>
                    <div class="stat-value"><?php echo $stats['total_customers']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Products</div>
                    <div class="stat-value"><?php echo $stats['total_products']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Sales</div>
                    <div class="stat-value"><?php echo $stats['total_sales']; ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value"><?php echo formatCurrency($stats['total_revenue']); ?></div>
                </div>
            </div>
            
            <div class="flex" style="gap: 20px; flex-wrap: wrap;">
                <div class="card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        Recent Sales
                    </div>
                    <div class="card-body">
                        <?php if (count($recentSales) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Salesperson</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentSales as $sale): ?>
                                        <tr>
                                            <td><?php echo $sale['sale_id']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                            <td><?php echo $sale['customer_name']; ?></td>
                                            <td><?php echo $sale['salesperson']; ?></td>
                                            <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mt-2 text-right">
                                <a href="sales.php" class="btn btn-sm">View All Sales</a>
                            </div>
                        <?php else: ?>
                            <p>No sales recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card" style="flex: 1; min-width: 300px;">
                    <div class="card-header">
                        Top Selling Products
                    </div>
                    <div class="card-body">
                        <?php if (count($topProducts) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Units Sold</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topProducts as $product): ?>
                                        <tr>
                                            <td><?php echo $product['product_name']; ?></td>
                                            <td><?php echo $product['total_quantity']; ?></td>
                                            <td><?php echo formatCurrency($product['total_revenue']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mt-2 text-right">
                                <a href="products.php" class="btn btn-sm">View All Products</a>
                            </div>
                        <?php else: ?>
                            <p>No product sales recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if (isAdmin() && count($recentSales) > 0): ?>
            <div class="card mt-3">
                <div class="card-header">
                    Sales Overview
                </div>
                <div class="card-body">
                    <canvas id="salesChart" width="400" height="200"></canvas>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="flex flex-between mt-3">
                <a href="sales.php?action=new" class="btn btn-primary">Record New Sale</a>
                <?php if (isAdmin()): ?>
                <a href="reports.php" class="btn">Generate Reports</a>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy;Sales Management System</p>
        </div>
    </footer>

    <?php if (isAdmin() && count($recentSales) > 0): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Sample data for chart - in a real app this would come from database
            const ctx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['January', 'February', 'March', 'April', 'May', 'June'],
                    datasets: [{
                        label: 'Sales Revenue',
                        data: [12000, 19000, 15000, 21000, 18000, 25000],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
    <?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make stat cards clickable
    const customerStats = document.querySelector('.stat-card:nth-child(1)');
    if (customerStats) {
        customerStats.style.cursor = 'pointer';
        customerStats.addEventListener('click', function() {
            window.location.href = 'customers.php';
        });
    }
    
    const productStats = document.querySelector('.stat-card:nth-child(2)');
    if (productStats) {
        productStats.style.cursor = 'pointer';
        productStats.addEventListener('click', function() {
            window.location.href = 'products.php';
        });
    }
    
    const salesStats = document.querySelector('.stat-card:nth-child(3)');
    if (salesStats) {
        salesStats.style.cursor = 'pointer';
        salesStats.addEventListener('click', function() {
            window.location.href = 'sales.php';
        });
    }
    
    const revenueStats = document.querySelector('.stat-card:nth-child(4)');
    if (revenueStats && document.querySelector('a[href="reports.php"]')) {
        revenueStats.style.cursor = 'pointer';
        revenueStats.addEventListener('click', function() {
            window.location.href = 'reports.php';
        });
    }
    
    // Make recent sales table rows clickable
    const saleRows = document.querySelectorAll('table tbody tr');
    saleRows.forEach(row => {
        const saleId = row.querySelector('td:first-child')?.textContent;
        if (saleId) {
            row.classList.add('recent-sale-row');
            row.addEventListener('click', function() {
                window.location.href = 'sales.php?action=view&id=' + saleId;
            });
        }
    });
    
    // Toast notification system
    function showToast(message, type = 'success') {
        // Create toast container if it doesn't exist
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Icon based on type
        const icon = document.createElement('span');
        icon.className = 'toast-icon';
        icon.textContent = type === 'success' ? '✓' : '✗';
        
        // Message content
        const content = document.createElement('div');
        content.className = 'toast-content';
        content.textContent = message;
        
        // Close button
        const closeBtn = document.createElement('button');
        closeBtn.className = 'toast-close';
        closeBtn.textContent = '×';
        closeBtn.addEventListener('click', function() {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        });
        
        // Add elements to toast
        toast.appendChild(icon);
        toast.appendChild(content);
        toast.appendChild(closeBtn);
        
        // Add toast to container
        toastContainer.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => {
                toast.remove();
            }, 300);
        }, 5000);
    }
    
    // Show toast if message in URL
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('msg')) {
        const message = urlParams.get('msg');
        switch (message) {
            case 'login_success':
                showToast('Welcome back! You have successfully logged in.');
                break;
            case 'sale_added':
                showToast('Sale has been successfully recorded.');
                break;
            case 'sale_updated':
                showToast('Sale has been updated successfully.');
                break;
        }
    }
    
    if (urlParams.has('error')) {
        const error = urlParams.get('error');
        switch (error) {
            case 'unauthorized':
                showToast('You do not have permission to access this section.', 'error');
                break;
        }
    }
});
</script>
</body>
</html>