<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$products = [];
$categories = [];
$message = '';
$error = '';
$product = null;

// Get all product categories
$result = $conn->query("SELECT * FROM product_categories ORDER BY category_name");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Handle actions
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product']) || isset($_POST['update_product'])) {
        $product_name = sanitize($_POST['product_name']);
        $category_id = (int)$_POST['category_id'];
        $description = sanitize($_POST['description']);
        $price = (float)$_POST['price'];
        $stock_quantity = (int)$_POST['stock_quantity'];
        
        // Validate data
        if (empty($product_name) || $price <= 0) {
            $error = "Product name and a positive price are required.";
        } else {
            if (isset($_POST['add_product'])) {
                // Add new product
                $stmt = $conn->prepare("INSERT INTO products (product_name, category_id, description, price, stock_quantity) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sisdi", $product_name, $category_id, $description, $price, $stock_quantity);
                
                if ($stmt->execute()) {
                    $message = "Product added successfully.";
                } else {
                    $error = "Error adding product: " . $conn->error;
                }
            } else if (isset($_POST['update_product'])) {
                // Update existing product
                $product_id = (int)$_POST['product_id'];
                $stmt = $conn->prepare("UPDATE products SET product_name = ?, category_id = ?, description = ?, price = ?, stock_quantity = ? WHERE product_id = ?");
                $stmt->bind_param("sisdii", $product_name, $category_id, $description, $price, $stock_quantity, $product_id);
                
                if ($stmt->execute()) {
                    $message = "Product updated successfully.";
                } else {
                    $error = "Error updating product: " . $conn->error;
                }
            }
        }
    } else if (isset($_POST['delete_product'])) {
        $product_id = (int)$_POST['product_id'];
        
        // Check if the product is used in any sales
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sale_items WHERE product_id = ?");
        $stmt->bind_param("i", $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['count'] > 0) {
            $error = "Cannot delete product because it's used in sales records. Consider updating instead.";
        } else {
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $product_id);
            
            if ($stmt->execute()) {
                $message = "Product deleted successfully.";
            } else {
                $error = "Error deleting product: " . $conn->error;
            }
        }
    }
}

// Handle product edit
if ($action === 'edit' && $id > 0) {
    $stmt = $conn->prepare("SELECT * FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
    }
}

// Get all products for listing
$query = "
    SELECT p.*, c.category_name 
    FROM products p
    LEFT JOIN product_categories c ON p.category_id = c.category_id
    ORDER BY p.product_name";
$result = $conn->query($query);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - Sales Management System</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>
        <div class="container">
            <a href="dashboard.php" class="logo">Sales Management System</a>
            <nav>
                    <a href="dashboard.php">Dashboard</a>
                    <a href="products.php">Products</a>
                    <a href="customers.php">Customers</a>
                    <a href="sales.php">Sales</a>
                    <?php if (isAdmin()): ?>
                    <li><a href="reports.php">Reports</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <h1 class="page-title">Product Management</h1>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card mb-3">
                <div class="card-header">
                    <?php echo $product ? 'Edit Product' : 'Add New Product'; ?>
                </div>
                <div class="card-body">
                    <form method="post" action="products.php">
                        <?php if ($product): ?>
                            <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="product_name" class="form-label">Product Name</label>
                            <input type="text" id="product_name" name="product_name" class="form-control" value="<?php echo $product ? htmlspecialchars($product['product_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="category_id" class="form-label">Category</label>
                            <select id="category_id" name="category_id" class="form-control">
                                <option value="0">-- None --</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['category_id']; ?>" <?php echo ($product && $product['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="description" class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="3"><?php echo $product ? htmlspecialchars($product['description']) : ''; ?></textarea>
                        </div>
                        
                        <div class="flex" style="gap: 20px;">
                            <div class="form-group" style="flex: 1;">
                                <label for="price" class="form-label">Price</label>
                                <input type="number" id="price" name="price" class="form-control" step="0.01" min="0.01" value="<?php echo $product ? htmlspecialchars($product['price']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group" style="flex: 1;">
                                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                                <input type="number" id="stock_quantity" name="stock_quantity" class="form-control" min="0" value="<?php echo $product ? htmlspecialchars($product['stock_quantity']) : 0; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <?php if ($product): ?>
                                <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                                <a href="products.php" class="btn">Cancel</a>
                            <?php else: ?>
                                <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    Product List
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($products) > 0): ?>
                                    <?php foreach ($products as $p): ?>
                                        <tr>
                                            <td><?php echo $p['product_id']; ?></td>
                                            <td><?php echo htmlspecialchars($p['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($p['category_name'] ?? 'None'); ?></td>
                                            <td><?php echo formatCurrency($p['price']); ?></td>
                                            <td><?php echo $p['stock_quantity']; ?></td>
                                            <td>
                                                <a href="products.php?action=edit&id=<?php echo $p['product_id']; ?>" class="btn btn-sm">Edit</a>
                                                <form method="post" action="products.php" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                    <input type="hidden" name="product_id" value="<?php echo $p['product_id']; ?>">
                                                    <button type="submit" name="delete_product" class="btn btn-sm btn-danger">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center">No products found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy;Sales Management System</p>
        </div>
    </footer>
</body>
</html>