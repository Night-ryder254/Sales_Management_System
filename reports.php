<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.html");
    exit();
}

$conn = new mysqli('localhost', 'root', '', 'sales_management');

// Query to generate sales report
$query = "
    SELECT 
        products.name AS product_name,
        products.price,
        SUM(sales.quantity) AS total_sold,
        (products.stock + SUM(sales.quantity)) AS initial_stock,
        products.stock AS current_stock
    FROM sales
    JOIN products ON sales.product_id = products.id
    GROUP BY sales.product_id
    ORDER BY total_sold DESC";
$results = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Report</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <h1>Sales Report</h1>
    <table border="1">
        <thead>
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Initial Stock</th>
                <th>Current Stock</th>
                <th>Total Sold</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $results->fetch_assoc()): ?>
                <tr>
                    <td><?= $row['product_name'] ?></td>
                    <td>$<?= $row['price'] ?></td>
                    <td><?= $row['initial_stock'] ?></td>
                    <td><?= $row['current_stock'] ?></td>
                    <td><?= $row['total_sold'] ?> <?= $row['total_sold'] > 10 ? "(High Demand)" : "" ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
