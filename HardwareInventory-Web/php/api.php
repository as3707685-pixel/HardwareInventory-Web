<?php
/*
    EARLY STAGE API FILE

    Current capabilities:
    - Check API status
    - List public inventory
    - List admin inventory
    - Add, update, and delete inventory items

    In later commits, customer orders and stock deduction will be added.
*/

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Token');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list_public';

function input($key, $default = '') {
    return trim($_POST[$key] ?? $_GET[$key] ?? $default);
}

function json_response($success, $message = '', $data = null, $statusCode = 200) {
    http_response_code($statusCode);
    $payload = ['success' => $success];
    if ($message !== '') { $payload['message'] = $message; }
    if ($data !== null) { $payload['data'] = $data; }
    echo json_encode($payload);
    exit;
}

function require_admin_api_token() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $headerToken = '';
    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'x-admin-token') {
            $headerToken = trim($value);
            break;
        }
    }

    $token = $headerToken !== '' ? $headerToken : input('admin_token');
    if ($token !== ADMIN_API_TOKEN) {
        json_response(false, 'Access denied.', null, 403);
    }
}

function item_payload($includeId = false) {
    $itemName = input('item_name');
    $category = input('category');
    $brand = input('brand');
    $model = input('model');
    $serial = input('serial_number');
    $quantityRaw = input('quantity', '0');
    $status = input('status', 'Available');
    $location = input('location');
    $remarks = input('remarks');

    if ($includeId && (int)input('id') <= 0) {
        json_response(false, 'Select a valid item first.', null, 422);
    }
    if ($itemName === '') {
        json_response(false, 'Item name is required.', null, 422);
    }
    if ($category === '') {
        json_response(false, 'Category is required.', null, 422);
    }
    if (!is_numeric($quantityRaw)) {
        json_response(false, 'Quantity must be a number.', null, 422);
    }

    $quantity = (int)$quantityRaw;
    if ($quantity < 0) {
        json_response(false, 'Quantity cannot be negative.', null, 422);
    }

    $allowedStatus = inventory_statuses();
    if (!in_array($status, $allowedStatus, true)) {
        $status = 'Available';
    }

    $payload = [
        'item_name' => $itemName,
        'category' => $category,
        'brand' => $brand,
        'model' => $model,
        'serial_number' => $serial,
        'quantity' => $quantity,
        'status' => $status,
        'location' => $location,
        'remarks' => $remarks
    ];

    if ($includeId) {
        $payload['id'] = (int)input('id');
    }

    return $payload;
}

function inventory_rows($db, $admin = false) {
    $q = input('q');
    $category = input('category');
    $status = input('status');

    $sql = $admin
        ? "SELECT * FROM hardware_items WHERE 1=1"
        : "SELECT id, item_name, category, brand, model, quantity, status, location, remarks, updated_at FROM hardware_items WHERE " . public_inventory_sql();

    $params = [];

    if ($q !== '') {
        if ($admin) {
            $sql .= " AND (item_name LIKE :q OR category LIKE :q OR brand LIKE :q OR model LIKE :q OR status LIKE :q OR location LIKE :q OR serial_number LIKE :q)";
        } else {
            $sql .= " AND (item_name LIKE :q OR category LIKE :q OR brand LIKE :q OR model LIKE :q OR status LIKE :q OR location LIKE :q)";
        }
        $params[':q'] = '%' . $q . '%';
    }

    if ($category !== '' && $category !== 'All Categories') {
        $sql .= " AND category = :category";
        $params[':category'] = $category;
    }

    if ($status !== '' && $status !== 'All Statuses') {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }

    $sql .= $admin ? " ORDER BY id DESC" : " ORDER BY item_name ASC, id DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $db = get_db();
    seed_if_empty($db);

    if ($action === 'ping') {
        json_response(true, 'API is online');
    }

    if ($action === 'list' || $action === 'list_public' || $action === 'customer_list') {
        json_response(true, '', inventory_rows($db, false));
    }

    if ($action === 'list_admin') {
        require_admin_api_token();
        json_response(true, '', inventory_rows($db, true));
    }

    if ($action === 'add') {
        require_admin_api_token();
        $item = item_payload(false);

        $stmt = $db->prepare("INSERT INTO hardware_items (item_name, category, brand, model, serial_number, quantity, status, location, remarks, created_at, updated_at)
                              VALUES (:item_name, :category, :brand, :model, :serial_number, :quantity, :status, :location, :remarks, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $stmt->execute($item);

        json_response(true, 'Item has been added.');
    }

    if ($action === 'update') {
        require_admin_api_token();
        $item = item_payload(true);

        $exists = $db->prepare("SELECT COUNT(*) FROM hardware_items WHERE id = :id");
        $exists->execute([':id' => $item['id']]);
        if ((int)$exists->fetchColumn() === 0) {
            json_response(false, 'Item was not found.', null, 404);
        }

        $stmt = $db->prepare("UPDATE hardware_items SET
            item_name = :item_name,
            category = :category,
            brand = :brand,
            model = :model,
            serial_number = :serial_number,
            quantity = :quantity,
            status = :status,
            location = :location,
            remarks = :remarks,
            updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $stmt->execute($item);

        json_response(true, 'Item has been updated.');
    }

    if ($action === 'delete') {
        require_admin_api_token();

        $id = (int)input('id');
        if ($id <= 0) {
            json_response(false, 'Select a valid item first.', null, 422);
        }

        $stmt = $db->prepare("DELETE FROM hardware_items WHERE id = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            json_response(false, 'Item was not found.', null, 404);
        }

        json_response(true, 'Item has been deleted.');
    }

    json_response(false, 'Unknown action. This early-stage version does not include customer order endpoints yet.', null, 404);
} catch (Exception $ex) {
    json_response(false, $ex->getMessage(), null, 500);
}
?>
