<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$sales = [];
$customers = [];
$products = [];
$message = '';
$error = '';
$saleDetails = null;

// Get all customers for dropdown
$result = $conn->query("SELECT customer_id, CONCAT(first_name, ' ', last_name) AS full_name FROM customers ORDER BY full_name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
}

// Get all products for dropdown
$result = $conn->query("SELECT product_id, product_name, price, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY product_name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// View sale details
if ($action === 'view' && $id > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, CONCAT(c.first_name, ' ', c.last_name) AS customer_name, 
               u.full_name AS salesperson
        FROM sales s
        JOIN customers c ON s.customer_id = c.customer_id
        JOIN users u ON s.user_id = u.user_id
        WHERE s.sale_id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $saleDetails = $result->fetch_assoc();
        
        // Get sale items
        $stmt = $conn->prepare("
            SELECT si.*, p.product_name
            FROM sale_items si
            JOIN products p ON si.product_id = p.product_id
            WHERE si.sale_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $saleDetails['items'] = [];
        while ($item = $result->fetch_assoc()) {
            $saleDetails['items'][] = $item;
        }
    }
}

// Handle form submission for adding new sale
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_sale'])) {
    $customer_id = (int)$_POST['customer_id'];
    $payment_method = sanitize($_POST['payment_method']);
    $notes = sanitize($_POST['notes']);
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    $unit_prices = $_POST['unit_price'] ?? [];
    
    // Validate
    if ($customer_id <= 0) {
        $error = "Please select a customer.";
    } elseif (empty($product_ids)) {
        $error = "Please add at least one product.";
    } else {
        // Begin transaction
        $conn->begin_transaction();
        try {
            $total_amount = 0;
            for ($i = 0; $i < count($product_ids); $i++) {
                if (isset($quantities[$i]) && $quantities[$i] > 0) {
                    $total_amount += $quantities[$i] * $unit_prices[$i];
                }
            }
            
            // Insert sale record
            $user_id = $_SESSION['user_id'];
            $stmt = $conn->prepare("INSERT INTO sales (user_id, customer_id, total_amount, payment_method, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iidss", $user_id, $customer_id, $total_amount, $payment_method, $notes);
            
            if (!$stmt->execute()) {
                throw new Exception("Error creating sale: " . $conn->error);
            }
            
            $sale_id = $conn->insert_id;
            
            // Insert sale items
            for ($i = 0; $i < count($product_ids); $i++) {
                $product_id = (int)$product_ids[$i];
                $quantity = (int)$quantities[$i];
                $unit_price = (float)$unit_prices[$i];
                $subtotal = $quantity * $unit_price;
                
                if ($quantity <= 0) continue;
                
                // Check stock availability
                $stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
                $stmt->bind_param("i", $product_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                
                if ($row['stock_quantity'] < $quantity) {
                    throw new Exception("Not enough stock for product ID " . $product_id);
                }
                
                // Insert sale item
                $stmt = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidd", $sale_id, $product_id, $quantity, $unit_price, $subtotal);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error adding sale item: " . $conn->error);
                }
                
                // Update product stock
                $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
                $stmt->bind_param("ii", $quantity, $product_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error updating product stock: " . $conn->error);
                }
            }
            
            // Commit transaction
            $conn->commit();
            $message = "Sale recorded successfully.";
            
            // Redirect to prevent form resubmission
            header("Location: sales.php?success=1");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error = $e->getMessage();
        }
    }
}

// Success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Sale recorded successfully.";
}

// Get all sales for listing
$query = "
    SELECT s.sale_id, s.sale_date, s.total_amount, s.payment_method,
           CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
           u.full_name AS salesperson
    FROM sales s
    JOIN customers c ON s.customer_id = c.customer_id
    JOIN users u ON s.user_id = u.user_id
    ORDER BY s.sale_date DESC";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Sales Management System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="dashboard.php" class="logo">Sales Management System</a>
            <nav>
                    <a href="dashboard.php">Dashboard</a>
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
            <h1 class="page-title">Sales Management</h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($action === 'new'): ?>
                <!-- New Sale Form -->
                <div class="card mb-3">
                    <div class="card-header">
                        Record New Sale
                    </div>
                    <div class="card-body">
                        <form method="post" action="sales.php" id="saleForm">
                            <div class="form-group">
                                <label for="customer_id" class="form-label">Customer</label>
                                <select id="customer_id" name="customer_id" class="form-control" required>
                                    <option value="">-- Select Customer --</option>
                                    <?php foreach ($customers as $customer): ?>
                                        <option value="<?php echo $customer['customer_id']; ?>">
                                            <?php echo htmlspecialchars($customer['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="payment_method" class="form-label">Payment Method</label>
                                <select id="payment_method" name="payment_method" class="form-control" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Check">Check</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea id="notes" name="notes" class="form-control"></textarea>
                            </div>
                            
                            <div class="sale-items-container">
                                <h3>Sale Items</h3>
                                <div id="sale-items">
                                    <div class="sale-item">
                                        <div class="row">
                                            <div class="col">
                                                <label>Product</label>
                                                <select name="product_id[]" class="form-control product-select" required>
                                                    <option value="">-- Select Product --</option>
                                                    <?php foreach ($products as $product): ?>
                                                        <option value="<?php echo $product['product_id']; ?>" 
                                                                data-price="<?php echo $product['price']; ?>"
                                                                data-stock="<?php echo $product['stock_quantity']; ?>">
                                                            <?php echo htmlspecialchars($product['product_name']); ?> 
                                                            ($<?php echo number_format($product['price'], 2); ?>, 
                                                            Stock: <?php echo $product['stock_quantity']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col">
                                                <label>Quantity</label>
                                                <input type="number" name="quantity[]" class="form-control quantity-input" min="1" required>
                                            </div>
                                            <div class="col">
                                                <label>Unit Price</label>
                                                <input type="number" name="unit_price[]" class="form-control price-input" step="0.01" min="0" required>
                                            </div>
                                            <div class="col">
                                                <label>Subtotal</label>
                                                <input type="text" class="form-control subtotal-display" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" id="add-item" class="btn btn-secondary">Add Another Item</button>
                            </div>
                            
                            <div class="form-group mt-3">
                                <div class="total-container">
                                    <strong>Total Amount: $<span id="total-amount">0.00</span></strong>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="add_sale" class="btn btn-primary">Save Sale</button>
                                <a href="sales.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif ($action === 'view' && $saleDetails): ?>
                <!-- Sale Details View -->
                <div class="card mb-3">
                    <div class="card-header">
                        <div class="d-flex justify-content-between">
                            <h3>Sale Details #<?php echo $saleDetails['sale_id']; ?></h3>
                            <a href="sales.php" class="btn btn-secondary">Back to Sales</a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sale-info">
                            <p><strong>Customer:</strong> <?php echo htmlspecialchars($saleDetails['customer_name']); ?></p>
                            <p><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($saleDetails['sale_date'])); ?></p>
                            <p><strong>Salesperson:</strong> <?php echo htmlspecialchars($saleDetails['salesperson']); ?></p>
                            <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($saleDetails['payment_method']); ?></p>
                            <?php if (!empty($saleDetails['notes'])): ?>
                                <p><strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($saleDetails['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <h4>Items</h4>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($saleDetails['items'] as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                    <td><?php echo $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-right">Total:</th>
                                    <th>$<?php echo number_format($saleDetails['total_amount'], 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <!-- Sales List -->
                <div class="actions mb-3">
                    <a href="sales.php?action=new" class="btn btn-primary">Record New Sale</a>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        Sales Records
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Salesperson</th>
                                    <th>Amount</th>
                                    <th>Payment Method</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($sales)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No sales records found.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($sales as $sale): ?>
                                    <tr>
                                        <td><?php echo $sale['sale_id']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($sale['sale_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sale['salesperson']); ?></td>
                                        <td>$<?php echo number_format($sale['total_amount'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($sale['payment_method']); ?></td>
                                        <td>
                                            <a href="sales.php?action=view&id=<?php echo $sale['sale_id']; ?>" class="btn btn-sm btn-info">View</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Sales Management System</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const saleForm = document.getElementById('saleForm');
            const addItemBtn = document.getElementById('add-item');
            const saleItemsContainer = document.getElementById('sale-items');
            const totalAmountDisplay = document.getElementById('total-amount');
            
            if (addItemBtn) {
                // Add new item row
                addItemBtn.addEventListener('click', function() {
                    const newItem = saleItemsContainer.firstElementChild.cloneNode(true);
                    const inputs = newItem.querySelectorAll('input, select');
                    inputs.forEach(input => {
                        input.value = '';
                    });
                    saleItemsContainer.appendChild(newItem);
                    setupEventListeners();
                });
                
                // Setup initial event listeners
                function setupEventListeners() {
                    // Product selection change
                    document.querySelectorAll('.product-select').forEach(select => {
                        select.addEventListener('change', function() {
                            const row = this.closest('.sale-item');
                            const priceInput = row.querySelector('.price-input');
                            const quantityInput = row.querySelector('.quantity-input');
                            
                            if (this.selectedIndex > 0) {
                                const option = this.options[this.selectedIndex];
                                const price = option.getAttribute('data-price');
                                priceInput.value = price;
                                updateSubtotal(row);
                            } else {
                                priceInput.value = '';
                                quantityInput.value = '';
                                updateSubtotal(row);
                            }
                        });
                    });
                    
                    // Quantity or price change
                    document.querySelectorAll('.quantity-input, .price-input').forEach(input => {
                        input.addEventListener('input', function() {
                            const row = this.closest('.sale-item');
                            updateSubtotal(row);
                        });
                    });
                }
                
                // Update subtotal and total
                function updateSubtotal(row) {
                    const quantityInput = row.querySelector('.quantity-input');
                    const priceInput = row.querySelector('.price-input');
                    const subtotalDisplay = row.querySelector('.subtotal-display');
                    
                    if (quantityInput.value && priceInput.value) {
                        const quantity = parseFloat(quantityInput.value);
                        const price = parseFloat(priceInput.value);
                        const subtotal = quantity * price;
                        subtotalDisplay.value = '$' + subtotal.toFixed(2);
                    } else {
                        subtotalDisplay.value = '';
                    }
                    
                    updateTotal();
                }
                
                function updateTotal() {
                    let total = 0;
                    document.querySelectorAll('.sale-item').forEach(row => {
                        const quantityInput = row.querySelector('.quantity-input');
                        const priceInput = row.querySelector('.price-input');
                        
                        if (quantityInput.value && priceInput.value) {
                            const quantity = parseFloat(quantityInput.value);
                            const price = parseFloat(priceInput.value);
                            total += quantity * price;
                        }
                    });
                    
                    totalAmountDisplay.textContent = total.toFixed(2);
                }
                
                // Initial setup
                setupEventListeners();
            }
        });
    </script>
</body>
</html>