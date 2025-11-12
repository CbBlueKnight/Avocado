<?php
session_start();

// Demo accounts with hierarchical access
$valid_users = [
    'owner' => [
        'password' => 'owner123', 
        'role' => 'owner', 
        'name' => 'Cafe Owner',
        'permissions' => ['sales_reports', 'menu_management', 'staff_management', 'inventory_management', 'void_transactions', 'all_reports']
    ],
    'manager' => [
        'password' => 'manager123', 
        'role' => 'manager', 
        'name' => 'Cafe Manager',
        'permissions' => ['menu_management', 'staff_management', 'inventory_management', 'void_transactions']
    ],
    'cashier1' => [
        'password' => 'cashier123', 
        'role' => 'cashier', 
        'name' => 'Cashier 1',
        'permissions' => ['order_processing']
    ],
    'cashier2' => [
        'password' => 'cashier123', 
        'role' => 'cashier', 
        'name' => 'Cashier 2',
        'permissions' => ['order_processing']
    ]
];

// REMOVED: PHP session menu items initialization
// Only initialize empty arrays for other session data
if (!isset($_SESSION['menu_items'])) {
    $_SESSION['menu_items'] = []; // Empty array - data will come from Firebase
}

if (!isset($_SESSION['transactions'])) {
    $_SESSION['transactions'] = [];
}

if (!isset($_SESSION['staff_accounts'])) {
    $_SESSION['staff_accounts'] = [
        'cashier1' => $valid_users['cashier1'],
        'cashier2' => $valid_users['cashier2']
    ];
}

if (!isset($_SESSION['inventory'])) {
    $_SESSION['inventory'] = [
        'coffee_beans' => ['quantity' => 100, 'unit' => 'kg'],
        'milk' => ['quantity' => 50, 'unit' => 'liters'],
        'sugar' => ['quantity' => 30, 'unit' => 'kg']
    ];
}

// Handle login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (isset($valid_users[$username]) && $valid_users[$username]['password'] === $password) {
        $_SESSION['user'] = $valid_users[$username];
        $_SESSION['logged_in'] = true;
        header('Location: admin.php');
        exit;
    } else {
        $error = "Invalid username or password";
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Other handlers remain the same...
// Handle menu management (Owner & Manager)
if (isset($_POST['action']) && $_POST['action'] === 'add_menu_item' && isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['owner', 'manager'])) {
    $new_item = [
        'id' => uniqid(),
        'name' => $_POST['item_name'],
        'price' => floatval($_POST['item_price']),
        'category' => $_POST['item_category'],
        'description' => $_POST['item_description'],
        'image' => $_POST['item_emoji'],
        'stock' => intval($_POST['item_stock'])
    ];
    
    $_SESSION['menu_items'][] = $new_item;
    $success = "Menu item added successfully!";
}

// Handle menu deletion (Owner & Manager)
if (isset($_POST['action']) && $_POST['action'] === 'delete_menu_item' && isset($_SESSION['user']) && in_array($_SESSION['user']['role'], ['owner', 'manager'])) {
    $item_id = $_POST['item_id'];
    $_SESSION['menu_items'] = array_filter($_SESSION['menu_items'], function($item) use ($item_id) {
        return $item['id'] !== $item_id;
    });
    $success = "Menu item deleted successfully!";
}

// Handle order processing (Cashier)
if (isset($_POST['action']) && $_POST['action'] === 'process_order' && isset($_SESSION['user']) && $_SESSION['user']['role'] === 'cashier') {
    $order_items = json_decode($_POST['order_items'], true);
    $total_amount = floatval($_POST['total_amount']);
    
    $transaction = [
        'id' => uniqid(),
        'timestamp' => date('Y-m-d H:i:s'),
        'cashier' => $_SESSION['user']['name'],
        'items' => $order_items,
        'total' => $total_amount,
        'voided' => false
    ];
    
    $_SESSION['transactions'][] = $transaction;
    $success = "Order processed successfully! Total: ₱" . number_format($total_amount, 2);
}

// Check if user is logged in
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$current_user = $logged_in ? $_SESSION['user'] : null;

// Get categories will be handled by JavaScript from Firebase
$categories = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cafe Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.0/firebase-firestore-compat.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        :root {
            --primary-color: #8B4513;
            --secondary-color: #D2691E;
            --accent-color: #f4a261;
            --light-color: #f8f9fa;
            --dark-color: #2d1b0e;
            --text-color: #5a3921;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--dark-color) 100%);
            min-height: 100vh;
            overflow: hidden;
        }

        /* Login Container Styles */
        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            padding: 20px;
        }

        .login-button-container {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }

        .login-button {
            padding: 14px 40px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 150px;
        }

        .login-button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
            padding: 40px 35px;
        }

        /* Cashier Fullscreen Dashboard */
        .cashier-dashboard {
            height: 100vh;
            display: flex;
            background: white;
        }

        /* Left Panel - Order Summary */
        .order-panel {
            flex: 1;
            background: #f8f9fa;
            border-right: 3px solid var(--primary-color);
            display: flex;
            flex-direction: column;
            max-width: 400px;
        }

        .order-header {
            background: var(--primary-color);
            color: white;
            padding: 15px 20px;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn-small {
            background: var(--danger-color);
            color: white;
            border: none;
            border-radius: 8px;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        .logout-btn-small:hover {
            background: #c82333;
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }

        .header-info {
            flex: 1;
        }

        .header-info h2 {
            margin-bottom: 5px;
            font-size: 1.5em;
        }

        .header-info p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9em;
        }

        .current-order {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }

        .order-items {
            margin-bottom: 20px;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: white;
            margin-bottom: 8px;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .item-price {
            color: var(--text-color);
            font-size: 0.9em;
        }

        .item-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-btn {
            width: 30px;
            height: 30px;
            border: none;
            background: var(--primary-color);
            color: white;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-display {
            font-weight: bold;
            min-width: 30px;
            text-align: center;
        }

        .order-total {
            background: white;
            padding: 20px;
            border-top: 2px solid #e9ecef;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1.1em;
        }

        .grand-total {
            font-size: 1.3em;
            font-weight: bold;
            color: var(--primary-color);
            border-top: 2px solid #e9ecef;
            padding-top: 10px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .checkout-btn {
            background: var(--success-color);
            color: white;
        }

        .clear-btn {
            background: var(--danger-color);
            color: white;
        }

        /* Right Panel - Product Selection */
        .products-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            background: white;
        }

        .categories-header {
            background: linear-gradient(135deg, #654321, #8B4513);
            padding: 20px;
            color: white;
        }

        .categories-title h2 {
            margin: 0 0 5px 0;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .categories-subtitle {
            opacity: 0.9;
            font-size: 0.80em;
        }

        /* Enhanced Categories Navigation */
        .categories-nav {
            background: linear-gradient(135deg, #8B4513, #D2691E);
            padding: 15px 20px;
            border-bottom: 3px solid #654321;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .category-buttons-container {
            display: flex;
            gap: 10px;
            justify-content: space-between;
            overflow-x: auto;
            padding: 5px 0;
        }

        .category-btn-large {
            flex: 1;
            min-width: 120px;
            padding: 12px 8px;
            border: none;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid transparent;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .category-btn-large:hover {
            background: white;
            border-color: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.15);
        }

        .category-btn-large.active {
            background: #ffd700;
            border-color: #ffd700;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 215, 0, 0.4);
        }

        .category-icon {
            font-size: 24px;
            color: #8B4513;
            width: 50px;
            height: 50px;
            background: rgba(139, 69, 19, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .category-btn-large.active .category-icon {
            background: rgba(139, 69, 19, 0.2);
            color: #8B4513;
        }

        .category-text {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .category-name {
            font-weight: 700;
            font-size: 14px;
            color: #8B4513;
        }

        .category-count {
            font-size: 11px;
            color: #666;
            font-weight: 600;
        }

        /* Category Sections */
        .products-container {
            flex: 1;
            overflow-y: auto;
            position: relative;
        }

        .category-section {
            display: none;
            padding: 20px;
            animation: fadeIn 0.3s ease-in;
        }

        .category-section.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 20px;
            border-radius: 15px;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .category-count-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.95em;
            font-weight: 600;
        }

        /* Category-specific colors */
        .all-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        .espresso-header {
            background: linear-gradient(135deg, #6f4e37, #8b5a2b);
        }

        .frappe-header {
            background: linear-gradient(135deg, #4a8c7a, #5ba38d);
        }

        .milktea-header {
            background: linear-gradient(135deg, #d4a574, #e6bc8d);
        }

        .food-header {
            background: linear-gradient(135deg, #e27c60, #f4a261);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .product-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            position: relative;
        }

        .product-card:hover:not(.out-of-stock) {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .product-card.out-of-stock {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .product-emoji {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .product-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-color);
        }

        .product-price {
            color: var(--primary-color);
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .product-description {
            color: #666;
            font-size: 0.9em;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .product-stock {
            font-size: 0.8em;
            color: var(--text-color);
        }

        .out-of-stock-badge {
            background: var(--danger-color);
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            margin-top: 5px;
            display: inline-block;
        }

        /* Category badges on product cards */
        .product-category-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 8px;
            border-radius: 10px;
            font-size: 0.7em;
            font-weight: 600;
            color: white;
        }

        .category-espresso {
            background: #6f4e37;
        }

        .category-frappe {
            background: #4a8c7a;
        }

        .category-milktea {
            background: #d4a574;
        }

        .category-food {
            background: #e27c60;
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Common Styles */
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo i {
            font-size: 48px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 15px;
        }

        .logo h1 {
            font-size: 28px;
            color: var(--dark-color);
            font-weight: 700;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
            font-size: 14px;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }

        input, select, textarea {
            width: 100%;
            padding: 14px 15px 14px 45px;
            border: 2px solid #e1e5ee;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafbfc;
        }

        input:focus, select:focus, textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            background: white;
            box-shadow: 0 0 0 3px rgba(139, 69, 19, 0.1);
        }

        button {
            padding: 14px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        button:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 69, 19, 0.3);
        }

        .error-message {
            background: #ffe6e6;
            color: var(--danger-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid var(--danger-color);
        }

        .success-message {
            background: #e6ffe6;
            color: var(--success-color);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14px;
            border-left: 4px solid var(--success-color);
        }

        .role-badge {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 25px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }

        .owner-badge { background: linear-gradient(135deg, var(--primary-color), #654321); }
        .manager-badge { background: linear-gradient(135deg, var(--secondary-color), #a0522d); }
        .cashier-badge { background: linear-gradient(135deg, var(--accent-color), #e76f51); }

        /* Loading States */
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #8B4513;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .category-buttons-container {
                flex-wrap: wrap;
            }
            
            .category-btn-large {
                flex: 1 1 calc(33.333% - 10px);
                min-width: 100px;
            }
        }

        @media (max-width: 768px) {
            .cashier-dashboard {
                flex-direction: column;
            }
            
            .order-panel {
                max-width: none;
                height: 40vh;
            }
            
            .products-panel {
                height: 60vh;
            }
            
            .category-buttons-container {
                flex-wrap: nowrap;
                overflow-x: auto;
                justify-content: flex-start;
            }
            
            .category-btn-large {
                flex: 0 0 auto;
                min-width: 140px;
            }
            
            .section-header {
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }

        @media (max-width: 480px) {
            .category-btn-large {
                min-width: 120px;
                padding: 12px 8px;
            }
            
            .category-icon {
                width: 40px;
                height: 40px;
                font-size: 20px;
            }
            
            .category-name {
                font-size: 12px;
            }
            
            .category-count {
                font-size: 10px;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php if (!$logged_in): ?>
    <!-- Login Form (unchanged) -->
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <i class="fas fa-coffee"></i>
                <h1>Cafe Management System</h1>
                <p>Hierarchical Access Portal</p>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-with-icon">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-with-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                </div>
                <div class="login-button-container">
                    <button type="submit" class="login-button">Sign In <i class="fas fa-sign-in-alt"></i></button>
                </div>
            </form>
            
            <div style="text-align: center; margin-top: 25px; color: var(--text-color); font-size: 13px; opacity: 0.7;">
                <p>Demo Accounts: owner/owner123, manager/manager123, cashier1/cashier123</p>
            </div>
        </div>
    </div>
    
    <?php elseif ($current_user['role'] === 'cashier'): ?>
    <!-- Cashier Fullscreen Dashboard - UPDATED FOR FIREBASE -->
    <div class="cashier-dashboard">
        <!-- Left Panel - Order Summary -->
        <div class="order-panel">
            <!-- Header with Logout Button -->
            <div class="order-header">
                <div class="header-top">
                    <button class="logout-btn-small" onclick="location.href='admin.php?action=logout'" title="Logout">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                    <div class="header-info">
                        <h2>Current Order</h2>
                        <p><?php echo $current_user['name']; ?> | <?php echo date('M j, Y g:i A'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="current-order">
                <div class="order-items" id="orderItems">
                    <!-- Order items will be populated by JavaScript -->
                    <div style="text-align: center; color: #666; padding: 40px 20px;">
                        <i class="fas fa-shopping-cart" style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No items in order</p>
                    </div>
                </div>
            </div>
            
            <div class="order-total">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span id="subtotal">₱0.00</span>
                </div>
                <div class="total-row">
                    <span>Tax (12%):</span>
                    <span id="tax">₱0.00</span>
                </div>
                <div class="total-row grand-total">
                    <span>Total:</span>
                    <span id="grandTotal">₱0.00</span>
                </div>
                
                <div class="action-buttons">
                    <button class="action-btn clear-btn" onclick="clearOrder()">
                        <i class="fas fa-trash"></i> Clear
                    </button>
                    <button class="action-btn checkout-btn" onclick="processOrder()">
                        <i class="fas fa-credit-card"></i> Checkout
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Product Selection - NOW DYNAMIC FROM FIREBASE -->
        <div class="products-panel">
            <!-- Enhanced Categories Header -->
            <div class="categories-header">
                <div class="categories-title">
                    <h2><i class="fas fa-list"></i> Menu Categories</h2>
                    <span class="categories-subtitle">Click to browse products</span>
                </div>
            </div>
            
            <!-- Loading State -->
            <div id="loadingState" class="loading-overlay" style="display: flex;">
                <div style="text-align: center;">
                    <div class="loading-spinner" style="width: 40px; height: 40px; border-width: 4px; margin-bottom: 15px;"></div>
                    <p>Loading menu from Firebase...</p>
                </div>
            </div>
            
            <!-- Enhanced Category Buttons - NOW DYNAMIC -->
            <div class="categories-nav" id="categoriesNav" style="display: none;">
                <div class="category-buttons-container" id="categoryButtons">
                    <!-- Category buttons will be populated by JavaScript -->
                </div>
            </div>
            
            <!-- Category Sections - NOW DYNAMIC -->
            <div class="products-container" id="productsContainer" style="display: none;">
                <!-- All sections will be populated by JavaScript -->
            </div>

            <!-- Error State -->
            <div id="errorState" class="loading-overlay" style="display: none;">
                <div style="text-align: center; color: var(--danger-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3em; margin-bottom: 15px;"></i>
                    <h3>Failed to Load Menu</h3>
                    <p>Unable to connect to Firebase. Please refresh the page.</p>
                    <button onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; background: var(--primary-color); color: white; border: none; border-radius: 8px; cursor: pointer;">
                        Retry
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
    <!-- Admin/Manager Dashboard (unchanged) -->
    <div style="display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px;">
        <div style="width: 100%; max-width: 500px; background: white; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); overflow: hidden;">
            <div style="padding: 35px 30px; max-height: 90vh; overflow-y: auto;">
                <div class="logo">
                    <i class="fas fa-coffee"></i>
                    <h1>Cafe Management System</h1>
                    <p>Welcome, <?php echo $current_user['name']; ?></p>
                </div>
                
                <div class="user-info" style="text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #fffaf0, #fef5e7); border-radius: 15px; border: 2px dashed var(--primary-color);">
                    <h2><?php echo $current_user['name']; ?></h2>
                    <div class="role-badge <?php echo $current_user['role']; ?>-badge">
                        <?php echo ucfirst($current_user['role']); ?>
                    </div>
                    <p>Access Level: <?php echo strtoupper($current_user['role']); ?></p>
                </div>

                <?php if (isset($success)): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <!-- Owner Only: Sales Reports -->
                <?php if ($current_user['role'] === 'owner'): ?>
                <div class="access-section" style="margin: 25px 0; padding: 20px; background: #fffaf0; border-radius: 15px; border-left: 5px solid var(--primary-color);">
                    <h3 style="color: var(--dark-color); margin-bottom: 15px; display: flex; align-items: center; gap: 10px;"><i class="fas fa-chart-line"></i> Sales Reports</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="generate_report">
                        <div class="form-group">
                            <label>Report Type</label>
                            <select name="report_type" required>
                                <option value="daily">Daily Report</option>
                                <option value="weekly">Weekly Report</option>
                                <option value="monthly">Monthly Report</option>
                                <option value="yearly">Yearly Report</option>
                            </select>
                        </div>
                        <button type="submit">Generate Report</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <a href="admin.php?action=logout" style="display: block; text-decoration: none;">
                    <button class="logout-btn" style="background: var(--danger-color); margin-top: 20px; width: 100%;">Logout <i class="fas fa-sign-out-alt"></i></button>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Current order data
        let currentOrder = [];
        let firebaseMenuItems = [];

        // Firebase configuration
        const firebaseConfig = {
            apiKey: "AIzaSyCSRi9IyNkK6DA6YYfnAdzI9LigkgTVG24",
            authDomain: "cafe-iyah-5869e.firebaseapp.com",
            databaseURL: "https://cafe-iyah-5869e-default-rtdb.asia-southeast1.firebasedatabase.app",
            projectId: "cafe-iyah-5869e",
            storageBucket: "cafe-iyah-5869e.firebasestorage.app",
            messagingSenderId: "737248847652",
            appId: "1:737248847652:web:f7ed666e68ca3dd4e975b1",
            measurementId: "G-ZKF5NMVYH6"
        };

        // Initialize Firebase
        firebase.initializeApp(firebaseConfig);
        const db = firebase.firestore();

        // Enhanced function to fetch menu items from Firebase
        async function fetchMenuItems() {
            console.log("Fetching menu items from Firebase...");
            
            try {
                const snapshot = await db.collection('menu_items').get();
                console.log("Firebase query completed. Documents found:", snapshot.size);
                
                if (snapshot.empty) {
                    console.log("No menu items found in Firebase Firestore");
                    showErrorState();
                    return;
                }
                
                firebaseMenuItems = snapshot.docs.map(doc => {
                    const data = doc.data();
                    console.log("Processing item:", doc.id, data);
                    return {
                        id: doc.id,
                        ...data
                    };
                });
                
                console.log("Final firebaseMenuItems array:", firebaseMenuItems);
                
                // Hide loading state and show content
                hideLoadingState();
                
                // Update the UI with Firebase data
                updateCategoryButtons();
                updateMenuDisplay();
                
            } catch (error) {
                console.error('Error fetching menu items:', error);
                showErrorState();
            }
        }

        function hideLoadingState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('categoriesNav').style.display = 'block';
            document.getElementById('productsContainer').style.display = 'block';
        }

        function showErrorState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('errorState').style.display = 'flex';
        }

        // Update category buttons dynamically
        function updateCategoryButtons() {
            const categories = getCategoriesFromMenu();
            const categoryButtons = document.getElementById('categoryButtons');
            
            let buttonsHTML = `
                <button class="category-btn-large active" onclick="filterProducts('all')" data-category="all">
                    <div class="category-icon">
                        <i class="fas fa-th-large"></i>
                    </div>
                    <div class="category-text">
                        <span class="category-name">All Items</span>
                        <span class="category-count">${firebaseMenuItems.length}</span>
                    </div>
                </button>
            `;
            
            categories.forEach(category => {
                const count = firebaseMenuItems.filter(item => item.category === category).length;
                const categoryId = category.toLowerCase().replace(' ', '-');
                const icon = getCategoryIcon(category);
                
                buttonsHTML += `
                    <button class="category-btn-large" onclick="filterProducts('${category}')" data-category="${categoryId}">
                        <div class="category-icon">
                            <i class="fas fa-${icon}"></i>
                        </div>
                        <div class="category-text">
                            <span class="category-name">${category}</span>
                            <span class="category-count">${count}</span>
                        </div>
                    </button>
                `;
            });
            
            categoryButtons.innerHTML = buttonsHTML;
        }

        function getCategoriesFromMenu() {
            const categories = [...new Set(firebaseMenuItems.map(item => item.category))];
            return categories.filter(category => category); // Remove empty categories
        }

function getCategoryIcon(category) {
    const iconMap = {
        'Espresso': 'coffee',
        'Frappe': 'glass-whiskey',
        'Milk Tea': 'mug-hot',
        'Food': 'utensils'
    };
    return iconMap[category] || 'cube';
}

        // Update menu display with Firebase data
        function updateMenuDisplay() {
            const productsContainer = document.getElementById('productsContainer');
            
            // Create All Items section
            let sectionsHTML = createCategorySection('all', 'All Menu Items', 'th-large', firebaseMenuItems);
            
            // Create category-specific sections
            const categories = getCategoriesFromMenu();
            categories.forEach(category => {
                const categoryItems = firebaseMenuItems.filter(item => item.category === category);
                const categoryId = category.toLowerCase().replace(' ', '-');
                const icon = getCategoryIcon(category);
                const description = getCategoryDescription(category);
                
                sectionsHTML += createCategorySection(categoryId, category, icon, categoryItems, description);
            });
            
            productsContainer.innerHTML = sectionsHTML;
        }

        function getCategoryDescription(category) {
            const descriptions = {
                'Espresso': 'coffee drinks',
                'Frappe': 'blended drinks',
                'Milk Tea': 'tea drinks',
                'Food': 'food items'
            };
            return descriptions[category] || 'items';
        }

function createCategorySection(categoryId, categoryName, icon, items, description = 'products') {
    // Fix for milk tea category - remove spaces and hyphens for CSS class
    const cssCategoryId = categoryId.replace(/[\s-]/g, '').toLowerCase();
    const headerClass = `${cssCategoryId}-header`;
    const count = items.length;
    
    return `
        <div class="category-section ${categoryId === 'all' ? 'active' : ''}" id="category-${categoryId}">
            <div class="section-header ${headerClass}">
                <h3><i class="fas fa-${icon}"></i> ${categoryName}</h3>
                <span class="category-count-badge">${count} ${description} available</span>
            </div>
            <div class="products-grid">
                ${items.map(item => `
                    <div class="product-card ${item.stock <= 0 ? 'out-of-stock' : ''}" 
                         onclick="addToOrder('${item.id}')"
                         data-category="${item.category}">
                        <div class="product-emoji">${item.image}</div>
                        <div class="product-name">${item.name}</div>
                        <div class="product-price">₱${item.price.toFixed(2)}</div>
                        <div class="product-description">${item.description}</div>
                        <div class="product-category-badge category-${item.category.toLowerCase().replace(/[\s-]/g, '')}">
                            ${item.category}
                        </div>
                        <div class="product-stock">
                            Stock: ${item.stock}
                            ${item.stock <= 0 ? '<div class="out-of-stock-badge">Out of Stock</div>' : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

        // Add item to order (using Firebase data)
        function addToOrder(itemId) {
            console.log("Adding to order, item ID:", itemId);
            
            const item = firebaseMenuItems.find(i => i.id === itemId);
            console.log("Found item:", item);
            
            if (!item) {
                console.error("Item not found in firebaseMenuItems:", itemId);
                return;
            }
            
            if (item.stock <= 0) {
                alert('Item out of stock!');
                return;
            }
            
            const existingItem = currentOrder.find(i => i.id === itemId);
            
            if (existingItem) {
                existingItem.quantity++;
            } else {
                currentOrder.push({
                    id: item.id,
                    name: item.name,
                    price: item.price,
                    quantity: 1,
                    image: item.image
                });
            }
            
            updateOrderDisplay();
        }

        // Update order display
        function updateOrderDisplay() {
            const orderItems = document.getElementById('orderItems');
            const subtotalEl = document.getElementById('subtotal');
            const taxEl = document.getElementById('tax');
            const grandTotalEl = document.getElementById('grandTotal');
            
            if (currentOrder.length === 0) {
                orderItems.innerHTML = `
                    <div style="text-align: center; color: #666; padding: 40px 20px;">
                        <i class="fas fa-shopping-cart" style="font-size: 3em; margin-bottom: 15px; opacity: 0.3;"></i>
                        <p>No items in order</p>
                    </div>
                `;
                subtotalEl.textContent = '₱0.00';
                taxEl.textContent = '₱0.00';
                grandTotalEl.textContent = '₱0.00';
                return;
            }
            
            let itemsHTML = '';
            let subtotal = 0;
            
            currentOrder.forEach(item => {
                const itemTotal = item.price * item.quantity;
                subtotal += itemTotal;
                
                itemsHTML += `
                    <div class="order-item">
                        <div class="item-info">
                            <div class="item-name">${item.name}</div>
                            <div class="item-price">₱${item.price.toFixed(2)} each</div>
                        </div>
                        <div class="item-quantity">
                            <button class="quantity-btn" onclick="updateQuantity('${item.id}', -1)">-</button>
                            <span class="quantity-display">${item.quantity}</span>
                            <button class="quantity-btn" onclick="updateQuantity('${item.id}', 1)">+</button>
                            <button class="quantity-btn" onclick="removeItem('${item.id}')" style="background: var(--danger-color); margin-left: 10px;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                `;
            });
            
            const tax = subtotal * 0.12;
            const grandTotal = subtotal + tax;
            
            orderItems.innerHTML = itemsHTML;
            subtotalEl.textContent = `₱${subtotal.toFixed(2)}`;
            taxEl.textContent = `₱${tax.toFixed(2)}`;
            grandTotalEl.textContent = `₱${grandTotal.toFixed(2)}`;
        }

        // Update quantity
        function updateQuantity(itemId, change) {
            const item = currentOrder.find(i => i.id === itemId);
            
            if (item) {
                item.quantity += change;
                
                if (item.quantity <= 0) {
                    currentOrder = currentOrder.filter(i => i.id !== itemId);
                }
            }
            
            updateOrderDisplay();
        }

        // Remove item from order
        function removeItem(itemId) {
            currentOrder = currentOrder.filter(i => i.id !== itemId);
            updateOrderDisplay();
        }

        // Enhanced category filtering
function filterProducts(category) {
    // Update active category button
    document.querySelectorAll('.category-btn-large').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Show selected category section
    document.querySelectorAll('.category-section').forEach(section => {
        section.classList.remove('active');
    });
    
    // Convert category to proper ID format (handle "Milk Tea" -> "milk-tea")
    const categoryId = category.toLowerCase().replace(' ', '-');
    document.getElementById(`category-${categoryId}`).classList.add('active');
    
    // Update URL for refresh persistence
    history.replaceState(null, null, `#${category}`);
}

        // Clear current order
        function clearOrder() {
            if (currentOrder.length === 0) return;
            
            if (confirm('Clear current order?')) {
                currentOrder = [];
                updateOrderDisplay();
            }
        }

        // Process order with Firebase stock update
        async function processOrder() {
            if (currentOrder.length === 0) {
                alert('Please add items to the order first.');
                return;
            }
            
            const subtotal = currentOrder.reduce((sum, item) => sum + (item.price * item.quantity), 0);
            const tax = subtotal * 0.12;
            const grandTotal = subtotal + tax;
            
            if (confirm(`Process order for ₱${grandTotal.toFixed(2)}?`)) {
                try {
                    // Update stock in Firebase
                    const batch = db.batch();
                    
                    for (const orderItem of currentOrder) {
                        const itemRef = db.collection('menu_items').doc(orderItem.id);
                        const item = firebaseMenuItems.find(i => i.id === orderItem.id);
                        
                        if (item) {
                            const newStock = item.stock - orderItem.quantity;
                            batch.update(itemRef, { stock: newStock });
                        }
                    }
                    
                    await batch.commit();
                    
                    // Add transaction to Firebase
                    await db.collection('transactions').add({
                        timestamp: new Date(),
                        cashier: '<?php echo $current_user['name']; ?>',
                        items: currentOrder,
                        total: grandTotal,
                        voided: false
                    });
                    
                    // Refresh menu items to get updated stock
                    await fetchMenuItems();
                    
                    // Clear current order
                    currentOrder = [];
                    updateOrderDisplay();
                    
                    alert('Order processed successfully!');
                    
                } catch (error) {
                    console.error('Error processing order:', error);
                    alert('Error processing order. Please try again.');
                }
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log("DOM loaded, checking user role...");
            
            // Only fetch from Firebase if user is cashier
            <?php if ($logged_in && $current_user['role'] === 'cashier'): ?>
            console.log("User is cashier, initializing Firebase...");
            fetchMenuItems();
            <?php else: ?>
            console.log("User is not cashier, skipping Firebase initialization");
            <?php endif; ?>
            
            updateOrderDisplay();
        });
    </script>
</body>
</html>