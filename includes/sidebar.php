<?php
// Function to check active state
if (!function_exists('isActive')) {
    function isActive($link)
    {
        if (empty($link))
            return false;
        // Extract path from BASE_URL if needed, but usually link contains module path
        $current_page = $_SERVER['PHP_SELF'];
        // Check if the link is part of the current path
        // Remove BASE_URL from link for comparison if strict, but 'contains' is usually safer for active state
        // link: /modules/products/list.php
        // current: /eamp_pos/modules/products/list.php
        // simple strpos usually works
        $link_path = parse_url($link, PHP_URL_PATH);
        if (!$link_path)
            return false;

        // Normalize logic
        $clean_link = str_replace(BASE_URL, '', $link);
        // Rough match
        return strpos($current_page, $clean_link) !== false;
    }
}

$user_role = $_SESSION['user_role'] ?? 'cashier';

// Define Menu Structure
$menu_items = [
    [
        'type' => 'header',
        'title' => 'Main'
    ],
    [
        'title' => 'Dashboard',
        'icon' => 'bi-speedometer2',
        'link' => BASE_URL . '/modules/dashboard/index.php',
        'roles' => ['admin', 'manager', 'cashier']
    ],
    [
        'title' => 'POS',
        'icon' => 'bi-cart-check',
        'link' => BASE_URL . '/modules/sales/pos.php',
        'roles' => ['admin', 'manager', 'cashier']
    ],
    [
        'type' => 'header',
        'title' => 'Management'
    ],
    [
        'title' => 'Products',
        'icon' => 'bi-box-seam',
        'id' => 'products_menu',
        'roles' => ['admin', 'manager'],
        'submenu' => [
            ['title' => 'Product List', 'link' => BASE_URL . '/modules/products/list.php'],
            ['title' => 'Suppliers', 'link' => BASE_URL . '/modules/products/suppliers.php'],
            ['title' => 'Add Product', 'link' => BASE_URL . '/modules/products/add.php'],
            ['title' => 'Barcode Gen', 'link' => BASE_URL . '/modules/products/barcode_gen.php'],
            ['title' => 'Import CSV', 'link' => BASE_URL . '/modules/products/import.php']
        ]
    ],
    [
        'title' => 'Inventory',
        'icon' => 'bi-clipboard-data',
        'id' => 'inventory_menu',
        'roles' => ['admin', 'manager'],
        'submenu' => [
            ['title' => 'Stock Overview', 'link' => BASE_URL . '/modules/inventory/view_stock.php'],
            ['title' => 'Receive Stock', 'link' => BASE_URL . '/modules/inventory/receive.php'],
            ['title' => 'Transfer', 'link' => BASE_URL . '/modules/inventory/transfer.php'],
            ['title' => 'Adjustments', 'link' => BASE_URL . '/modules/inventory/adjust.php'],
            ['title' => 'Stock Take', 'link' => BASE_URL . '/modules/inventory/stocktake.php'],
            ['title' => 'Low Stock Alerts', 'link' => BASE_URL . '/modules/inventory/low_stock.php']
        ]
    ],
    [
        'title' => 'Sales',
        'icon' => 'bi-receipt',
        'id' => 'sales_menu',
        'roles' => ['admin', 'manager', 'cashier'],
        'submenu' => [
            ['title' => 'Sales History', 'link' => BASE_URL . '/modules/sales/history.php'],
            ['title' => 'Debt Mgmt', 'link' => BASE_URL . '/modules/sales/debt.php'],
            ['title' => 'Returns', 'link' => BASE_URL . '/modules/sales/return.php']
        ]
    ],
    [
        'type' => 'header',
        'title' => 'Business'
    ],
    [
        'title' => 'Finance',
        'icon' => 'bi-cash-stack',
        'id' => 'finance_menu',
        'roles' => ['admin', 'manager'],
        'submenu' => [
            ['title' => 'Accounts', 'link' => BASE_URL . '/modules/finance/accounts.php'],
            ['title' => 'Fund Transfers', 'link' => BASE_URL . '/modules/finance/transfer.php'],
            ['title' => 'Expenses', 'link' => BASE_URL . '/modules/finance/expenses.php'],
            ['title' => 'Cash Register', 'link' => BASE_URL . '/modules/finance/reconciliation.php'],
            ['title' => 'Profit & Loss', 'link' => BASE_URL . '/modules/finance/profit_loss.php']
        ]
    ],
    [
        'title' => 'Reports',
        'icon' => 'bi-graph-up',
        'id' => 'reports_menu',
        'roles' => ['admin', 'manager'],
        'submenu' => [
            ['title' => 'Reports Center', 'link' => BASE_URL . '/modules/reports/index.php'],
            ['title' => 'Daily Reports', 'link' => BASE_URL . '/modules/reports/daily.php'],
            ['title' => 'Sales Reports', 'link' => BASE_URL . '/modules/reports/sales.php'],
            ['title' => 'Inventory', 'link' => BASE_URL . '/modules/reports/inventory.php'],
            ['title' => 'Expiry', 'link' => BASE_URL . '/modules/reports/expiry.php'],
            ['title' => 'Staff Perf.', 'link' => BASE_URL . '/modules/reports/staff.php'],
            ['title' => 'Product Perf.', 'link' => BASE_URL . '/modules/reports/products.php'],
            ['title' => 'Shop Comparison', 'link' => BASE_URL . '/modules/reports/shop_comparison.php']
        ]
    ],
    [
        'title' => 'Admin',
        'icon' => 'bi-shield-lock',
        'id' => 'admin_menu',
        'roles' => ['admin'],
        'submenu' => [
            ['title' => 'Control Panel', 'link' => BASE_URL . '/modules/settings/admin.php'],
            ['title' => 'Manage Shops', 'link' => BASE_URL . '/modules/settings/shops.php'],
            ['title' => 'Users', 'link' => BASE_URL . '/modules/settings/users.php'],
            ['title' => 'System Settings', 'link' => BASE_URL . '/modules/settings/preferences.php'],
            ['title' => 'Backup', 'link' => BASE_URL . '/modules/settings/backup.php'],
            ['title' => 'SMS Settings', 'link' => BASE_URL . '/modules/settings/sms.php'],
            ['title' => 'Audit Logs', 'link' => BASE_URL . '/modules/settings/audit_logs.php']
        ]
    ]
];

?>

<div class="sidebar d-flex flex-column p-0 flex-shrink-0 bg-dark text-white" style="width: 250px;">
    <div class="p-3 text-center border-bottom border-secondary">
        <h4 class="m-0 text-white"><?php echo $SYSTEM_SETTINGS['company_name'] ?? APP_NAME; ?></h4>
    </div>

    <div class="overflow-auto custom-scrollbar" style="flex: 1;">
        <ul class="nav nav-pills flex-column mb-auto pt-2">
            <?php foreach ($menu_items as $item): ?>

                <?php
                // Skip if role doesn't match
                if (isset($item['roles']) && !in_array($user_role, $item['roles'])) {
                    continue;
                }
                ?>

                <!-- Header -->
                <?php if (isset($item['type']) && $item['type'] == 'header'): ?>
                    <li class="nav-item mt-3 mb-1 px-3 text-uppercase small text-white-50 fw-bold">
                        <?php echo $item['title']; ?>
                    </li>

                    <!-- Regular Link -->
                <?php elseif (!isset($item['submenu'])): ?>
                    <li class="nav-item">
                        <a href="<?php echo $item['link']; ?>"
                            class="nav-link text-white <?php echo isActive($item['link']) ? 'active bg-primary' : ''; ?> px-3 py-2 rounded-0">
                            <i class="<?php echo $item['icon']; ?> me-2"></i>
                            <?php echo $item['title']; ?>
                        </a>
                    </li>

                    <!-- Dropdown Menu -->
                <?php else: ?>
                    <?php
                    // Check if any child is active
                    $is_active_parent = false;
                    foreach ($item['submenu'] as $sub) {
                        if (isActive($sub['link'])) {
                            $is_active_parent = true;
                            break;
                        }
                    }
                    ?>
                    <li class="nav-item">
                        <a class="nav-link text-white px-3 py-2 rounded-0 d-flex justify-content-between align-items-center <?php echo $is_active_parent ? '' : 'collapsed'; ?>"
                            data-bs-toggle="collapse" href="#<?php echo $item['id']; ?>" role="button"
                            aria-expanded="<?php echo $is_active_parent ? 'true' : 'false'; ?>"
                            aria-controls="<?php echo $item['id']; ?>">
                            <span>
                                <i class="<?php echo $item['icon']; ?> me-2"></i>
                                <?php echo $item['title']; ?>
                            </span>
                            <i class="bi bi-chevron-down small" style="font-size: 0.8rem;"></i>
                        </a>
                        <div class="collapse <?php echo $is_active_parent ? 'show' : ''; ?>" id="<?php echo $item['id']; ?>">
                            <ul class="nav flex-column bg-secondary bg-opacity-25 py-1">
                                <?php foreach ($item['submenu'] as $sub): ?>
                                    <li class="nav-item">
                                        <a href="<?php echo $sub['link']; ?>"
                                            class="nav-link text-white ps-5 py-1 <?php echo isActive($sub['link']) ? 'active bg-primary bg-opacity-50' : ''; ?>"
                                            style="font-size: 0.95rem;">
                                            <?php echo $sub['title']; ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </li>
                <?php endif; ?>

            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
    /* Custom sidebar styles for dropdown chevron rotation if desired, but default is okay */
    .sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .sidebar .nav-link.active {
        background-color: var(--primary-color) !important;
    }

    .sidebar .nav-link[aria-expanded="true"] .bi-chevron-down {
        transform: rotate(180deg);
    }

    .sidebar .bi-chevron-down {
        transition: transform 0.2s;
    }

    /* Custom Scrollbar */
    .custom-scrollbar::-webkit-scrollbar {
        width: 5px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #343a40;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #6c757d;
        border-radius: 5px;
    }
</style>