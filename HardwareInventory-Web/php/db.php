<?php
/*
    EARLY STAGE DATABASE FILE

    This starter version only creates the hardware_items table.
    Customer accounts and customer_orders will be added in the later commits
    from NOTES/COMMIT_GUIDE.md.
*/

function get_db() {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite driver is not enabled. In XAMPP, enable extension=pdo_sqlite in php.ini and restart PHP/Apache.');
    }

    $dbFile = __DIR__ . '/hardware_inventory.sqlite';
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('PRAGMA foreign_keys = ON');

    $db->exec("CREATE TABLE IF NOT EXISTS hardware_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_name TEXT NOT NULL,
        category TEXT NOT NULL,
        brand TEXT,
        model TEXT,
        serial_number TEXT,
        quantity INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'Available',
        location TEXT,
        remarks TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");

    return $db;
}

function seed_if_empty($db) {
    $count = (int)$db->query("SELECT COUNT(*) FROM hardware_items")->fetchColumn();

    if ($count === 0) {
        $stmt = $db->prepare("INSERT INTO hardware_items (item_name, category, brand, model, serial_number, quantity, status, location, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Desktop Computer', 'Computer Unit', 'Dell', 'OptiPlex 3090', 'DX-PC-001', 12, 'Available', 'Main Stockroom', 'Ready for release']);
        $stmt->execute(['Network Switch', 'Network Device', 'TP-Link', 'TL-SG1024', 'NET-SW-024', 3, 'Available', 'Network Cabinet', '24-port gigabit switch']);
        $stmt->execute(['LCD Monitor', 'Peripheral', 'Acer', 'V196HQL', 'MON-AC-091', 8, 'Available', 'Display Rack', 'For workstation setup']);
        $stmt->execute(['External SSD', 'Storage Device', 'Kingston', 'XS1000', 'SSD-KG-007', 5, 'Low Stock', 'Accessories Shelf', 'Portable storage unit']);
        $stmt->execute(['Laser Printer', 'Printer', 'Brother', 'HL-L2320D', 'PRN-BR-013', 2, 'Available', 'Printer Area', 'For office use']);
    }
}

function inventory_categories() {
    return ['Computer Unit', 'Peripheral', 'Network Device', 'Storage Device', 'Printer', 'Power Device', 'Other Hardware'];
}

function inventory_statuses() {
    return ['Available', 'In Use', 'Low Stock', 'For Repair', 'Defective', 'Disposed'];
}

function public_inventory_sql() {
    return "status NOT IN ('Defective', 'Disposed')";
}
?>
