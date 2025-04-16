<?php
require_once 'config.php';
requireLogin();

// Process actions for customers
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Handle view, edit, new actions
    if ($action == 'view' && isset($_GET['id'])) {
        $customerId = (int)$_GET['id'];
        // Get customer details
        $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $customer = $stmt->get_result()->fetch_assoc();
        
        // Get customer purchase history
        $stmt = $conn->prepare("
            SELECT s.sale_id, s.sale_date, s.total_amount, u.full_name as salesperson
            FROM sales s 
            JOIN users u ON s.user_id = u.user_id
            WHERE s.customer_id = ?
            ORDER BY s.sale_date DESC
        ");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $purchaseHistory = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Handle new/edit form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize all inputs
        $firstName = sanitize($_POST['first_name']);
        $lastName = sanitize($_POST['last_name']);
        $email = sanitize($_POST['email']);
        $phone = isset($_POST['phone']) ? sanitize($_POST['phone']) : '';
        $address = isset($_POST['address']) ? sanitize($_POST['address']) : '';
        
        if ($action == 'edit' && isset($_GET['id'])) {
            // Update existing customer
            $customerId = (int)$_GET['id'];
            $stmt = $conn->prepare("
                UPDATE customers 
                SET first_name = ?, last_name = ?, email = ?, phone = ?, address = ?
                WHERE customer_id = ?
            ");
            $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $address, $customerId);
            $stmt->execute();
            
            header("Location: customers.php?msg=updated");
            exit();
        } elseif ($action == 'new') {
            // Create new customer
            $stmt = $conn->prepare("
                INSERT INTO customers (first_name, last_name, email, phone, address)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssss", $firstName, $lastName, $email, $phone, $address);
            $stmt->execute();
            
            header("Location: customers.php?msg=added");
            exit();
        }
    }
    
    // Handle delete action
    if ($action == 'delete' && isset($_GET['id']) && isAdmin()) {
        $customerId = (int)$_GET['id'];
        
        // Check if customer has any sales before deleting
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sales WHERE customer_id = ?");
        $stmt->bind_param("i", $customerId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] > 0) {
            header("Location: customers.php?error=has_sales");
            exit();
        } else {
            $stmt = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
            $stmt->bind_param("i", $customerId);
            $stmt->execute();
            
            header("Location: customers.php?msg=deleted");
            exit();
        }
    }
}

// Get all customers for the main listing
$customers = [];
$query = "SELECT * FROM customers ORDER BY last_name, first_name";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Process messages and errors
$message = '';
$error = '';

if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'added':
            $message = 'Customer added successfully!';
            break;
        case 'updated':
            $message = 'Customer updated successfully!';
            break;
        case 'deleted':
            $message = 'Customer deleted successfully!';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'has_sales':
            $error = 'Cannot delete customer with associated sales records.';
            break;
        case 'unauthorized':
            $error = 'You are not authorized to perform this action.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customers - Sales Management System</title>
    <link rel="stylesheet" href="styles.css">
    <script defer>
        document.addEventListener('DOMContentLoaded', function() {
            // Interactive table row clicking
            const tableRows = document.querySelectorAll('.customer-row');
            tableRows.forEach(row => {
                row.addEventListener('click', function() {
                    window.location.href = 'customers.php?action=view&id=' + this.dataset.id;
                });
            });
            
            // Alerts auto-hide after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
            
            // Search functionality
            const searchInput = document.getElementById('customer-search');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.customer-row');
                    
                    rows.forEach(row => {
                        const customerName = row.querySelector('.customer-name').textContent.toLowerCase();
                        const customerEmail = row.querySelector('.customer-email').textContent.toLowerCase();
                        
                        if (customerName.includes(searchTerm) || customerEmail.includes(searchTerm)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
        });
    </script>
</head>
<body>
    <header>
        <div class="container">
            <a href="dashboard.php" class="logo">Sales Management System</a>
            <nav>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="products.php">Products</a></li>
                    <li><a href="customers.php" class="active">Customers</a></li>
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
            <h1 class="page-title">Customer Management</h1>
            
            <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade-in">
                <?php echo $message; ?>
                <button class="close">&times;</button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade-in">
                <?php echo $error; ?>
                <button class="close">&times;</button>
            </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['action']) && $_GET['action'] == 'view' && isset($customer)): ?>
            <!-- Single Customer View -->
            <div class="card customer-detail-card">
                <div class="card-header">
                    Customer Details
                    <div>
                        <a href="customers.php?action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm">Edit</a>
                        <?php if (isAdmin()): ?>
                        <a href="customers.php?action=delete&id=<?php echo $customer['customer_id']; ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('Are you sure you want to delete this customer?');">Delete</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="customer-info">
                        <div class="info-group">
                            <span class="info-label">Name:</span>
                            <span class="info-value"><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Email:</span>
                            <span class="info-value"><?php echo $customer['email']; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Phone:</span>
                            <span class="info-value"><?php echo $customer['phone'] ?: 'N/A'; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Address:</span>
                            <span class="info-value"><?php echo $customer['address'] ?: 'N/A'; ?></span>
                        </div>
                        <div class="info-group">
                            <span class="info-label">Created:</span>
                            <span class="info-value"><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <h3 class="section-title mt-3">Purchase History</h3>
                    <?php if (count($purchaseHistory) > 0): ?>
                    <div class="table-container mt-2">
                        <table>
                            <thead>
                                <tr>
                                    <th>Sale ID</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Salesperson</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchaseHistory as $sale): ?>
                                <tr>
                                    <td><?php echo $sale['sale_id']; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?></td>
                                    <td><?php echo formatCurrency($sale['total_amount']); ?></td>
                                    <td><?php echo $sale['salesperson']; ?></td>
                                    <td><a href="sales.php?action=view&id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm">View</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p>No purchase history available for this customer.</p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <a href="customers.php" class="btn">Back to Customers</a>
                        <a href="sales.php?action=new&customer_id=<?php echo $customer['customer_id']; ?>" class="btn btn-primary">Create New Sale</a>
                    </div>
                </div>
            </div>
            
            <?php elseif (isset($_GET['action']) && ($_GET['action'] == 'edit' || $_GET['action'] == 'new')): ?>
            <!-- Customer Form (Edit/New) -->
            <?php
            $isEdit = $_GET['action'] == 'edit';
            $formCustomer = [];
            
            if ($isEdit && isset($_GET['id'])) {
                $customerId = (int)$_GET['id'];
                $stmt = $conn->prepare("SELECT * FROM customers WHERE customer_id = ?");
                $stmt->bind_param("i", $customerId);
                $stmt->execute();
                $formCustomer = $stmt->get_result()->fetch_assoc();
                
                if (!$formCustomer) {
                    header("Location: customers.php?error=not_found");
                    exit();
                }
            }
            ?>
            
            <div class="card">
                <div class="card-header">
                    <?php echo $isEdit ? 'Edit Customer' : 'Add New Customer'; ?>
                </div>
                <div class="card-body">
                    <form action="customers.php?action=<?php echo $isEdit ? 'edit&id=' . $formCustomer['customer_id'] : 'new'; ?>" method="POST" class="form-container" style="max-width: 100%;">
                        <div class="flex gap-2" style="flex-wrap: wrap;">
                            <div class="form-group" style="flex: 1; min-width: 200px;">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" id="first_name" name="first_name" class="form-control" required value="<?php echo $isEdit ? $formCustomer['first_name'] : ''; ?>">
                            </div>
                            <div class="form-group" style="flex: 1; min-width: 200px;">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" id="last_name" name="last_name" class="form-control" required value="<?php echo $isEdit ? $formCustomer['last_name'] : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" id="email" name="email" class="form-control" required value="<?php echo $isEdit ? $formCustomer['email'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $isEdit ? $formCustomer['phone'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"><?php echo $isEdit ? $formCustomer['address'] : ''; ?></textarea>
                        </div>
                        
                        <div class="flex flex-between mt-3">
                            <a href="customers.php" class="btn">Cancel</a>
                            <button type="submit" class="btn btn-primary"><?php echo $isEdit ? 'Update Customer' : 'Add Customer'; ?></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <?php else: ?>
            <!-- Customer Listing -->
            <div class="flex flex-between mb-3">
                <div class="search-container">
                    <input type="text" id="customer-search" class="form-control" placeholder="Search customers...">
                </div>
                <div>
                    <a href="customers.php?action=new" class="btn btn-primary">Add New Customer</a>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Customer List
                </div>
                <div class="card-body">
                    <?php if (count($customers) > 0): ?>
                    <div class="table-container">
                        <table class="table-interactive">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr class="customer-row" data-id="<?php echo $customer['customer_id']; ?>">
                                    <td><?php echo $customer['customer_id']; ?></td>
                                    <td class="customer-name"><?php echo $customer['first_name'] . ' ' . $customer['last_name']; ?></td>
                                    <td class="customer-email"><?php echo $customer['email']; ?></td>
                                    <td><?php echo $customer['phone'] ?: 'N/A'; ?></td>
                                    <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                                    <td>
                                        <a href="customers.php?action=view&id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm">View</a>
                                        <a href="customers.php?action=edit&id=<?php echo $customer['customer_id']; ?>" class="btn btn-sm">Edit</a>
                                        <?php if (isAdmin()): ?>
                                        <a href="customers.php?action=delete&id=<?php echo $customer['customer_id']; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this customer?');">Delete</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p>No customers found. <a href="customers.php?action=new">Add your first customer</a>.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy;Sales Management System</p>
        </div>
    </footer>

    <script>
        // Close alert buttons
        document.querySelectorAll('.alert .close').forEach(button => {
            button.addEventListener('click', function() {
                this.parentElement.style.opacity = '0';
                setTimeout(() => {
                    this.parentElement.style.display = 'none';
                }, 300);
            });
        });
    </script>
</body>
</html>