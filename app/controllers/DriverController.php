<?php

require_once __DIR__ . '/../models/Operations.php';
require_once __DIR__ . '/../models/Proof.php';

class DriverController
{
    private function isModalRequest(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'REGC-Modal';
    }

    private function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
        exit;
    }

    public function index(): void
    {
        requireRole(['driver', 'administrator']);
        $user = authUser();

        $driver = null;
        if ($user['role'] === 'driver') {
            $stmt = db()->prepare('SELECT * FROM drivers WHERE user_id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $driver = $stmt->fetch();
        }

        $driverId = $driver ? (int) $driver['id'] : null;

        $pdo = db();
        if ($driverId) {
            // Active shipments
            $activeStmt = $pdo->prepare("SELECT s.*, c.contact_person, c.company_name, c.contact_number, t.plate_number, t.truck_model
                FROM shipments s
                JOIN customers c ON c.id = s.customer_id
                LEFT JOIN trucks t ON t.id = s.truck_id
                WHERE s.driver_id = ? AND s.current_status IN ('assigned','pickup','in_transit','arrived')
                ORDER BY s.id DESC");
            $activeStmt->execute([$driverId]);
            $activeShipments = $activeStmt->fetchAll();

            // Completed shipments
            $completedStmt = $pdo->prepare("SELECT s.*, c.contact_person, c.company_name, t.plate_number
                FROM shipments s
                JOIN customers c ON c.id = s.customer_id
                LEFT JOIN trucks t ON t.id = s.truck_id
                WHERE s.driver_id = ? AND s.current_status = 'delivered'
                ORDER BY s.updated_at DESC
                LIMIT 15");
            $completedStmt->execute([$driverId]);
            $completedShipments = $completedStmt->fetchAll();

            // Assigned Truck
            $truckStmt = $pdo->prepare("SELECT * FROM trucks WHERE assigned_driver_id = ? OR id IN (SELECT truck_id FROM shipments WHERE driver_id = ? AND current_status <> 'delivered') LIMIT 1");
            $truckStmt->execute([$driverId, $driverId]);
            $assignedTruck = $truckStmt->fetch();
        } else {
            // Admin viewing driver workspace or driver without linked record
            $activeShipments = $pdo->query("SELECT s.*, c.contact_person, c.company_name, c.contact_number, t.plate_number, t.truck_model, d.full_name AS driver_name
                FROM shipments s
                JOIN customers c ON c.id = s.customer_id
                LEFT JOIN trucks t ON t.id = s.truck_id
                LEFT JOIN drivers d ON d.id = s.driver_id
                WHERE s.current_status IN ('assigned','pickup','in_transit','arrived')
                ORDER BY s.id DESC")->fetchAll();
            $completedShipments = [];
            $assignedTruck = null;
        }

        $module = 'driver';
        require __DIR__ . '/../views/driver-dashboard.php';
    }

    public function updateStatus(): void
    {
        requireRole(['driver', 'administrator']);
        verifyCsrf();

        $shipmentId = (int) ($_POST['id'] ?? 0);
        $nextStatus = trim((string) ($_POST['status'] ?? ''));
        $remarks = trim((string) ($_POST['remarks'] ?? ''));

        try {
            Operations::advanceShipment($shipmentId, $nextStatus, $remarks);
            $_SESSION['notice'] = 'Delivery status successfully updated to ' . ucwords(str_replace('_', ' ', $nextStatus)) . '.';

            if ($this->isModalRequest()) {
                $this->json(['redirect' => 'index.php?route=driver']);
            }
            header('Location: index.php?route=driver');
            exit;
        } catch (DomainException $e) {
            if ($this->isModalRequest()) {
                $this->json(['message' => $e->getMessage()], 422);
            }
            $_SESSION['operation_error'] = $e->getMessage();
            header('Location: index.php?route=driver');
            exit;
        }
    }

    public function updateGps(): void
    {
        $lat = filter_input(INPUT_POST, 'lat', FILTER_VALIDATE_FLOAT);
        $lng = filter_input(INPUT_POST, 'lng', FILTER_VALIDATE_FLOAT);

        // Also accept JSON body for REST APIs / GPS trackers
        if ($lat === null || $lng === null) {
            $json = json_decode(file_get_contents('php://input'), true);
            if (is_array($json)) {
                $lat = filter_var($json['lat'] ?? null, FILTER_VALIDATE_FLOAT);
                $lng = filter_var($json['lng'] ?? null, FILTER_VALIDATE_FLOAT);
                if (isset($json['driver_id'])) {
                    $_POST['driver_id'] = $json['driver_id'];
                }
            }
        }

        if ($lat === null || $lat === false || $lng === null || $lng === false) {
            $this->json(['error' => 'Invalid coordinates. Require numeric lat (-90 to 90) and lng (-180 to 180).'], 400);
        }

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $this->json(['error' => 'Coordinates out of geographic range.'], 400);
        }

        $driverId = null;

        if (isLoggedIn()) {
            $user = authUser();
            if ($user['role'] === 'driver') {
                $stmt = db()->prepare('SELECT id FROM drivers WHERE user_id = ? LIMIT 1');
                $stmt->execute([$user['id']]);
                $driverId = $stmt->fetchColumn() ?: null;
            } elseif ($user['role'] === 'administrator' && !empty($_POST['driver_id'])) {
                $driverId = (int) $_POST['driver_id'];
            }
        } else {
            // External GPS API Device / Webhook / Mobile Web App authentication
            $apiKey = $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? ($json['api_key'] ?? '');
            $configuredKey = defined('GPS_API_KEY') ? GPS_API_KEY : '';
            if ($apiKey !== '' && $configuredKey !== '' && hash_equals($configuredKey, (string) $apiKey)) {
                $driverId = (int) ($_POST['driver_id'] ?? $_GET['driver_id'] ?? ($json['driver_id'] ?? 0));
                if (!$driverId && !empty($_POST['license_number'])) {
                    $stmt = db()->prepare('SELECT id FROM drivers WHERE license_number = ? LIMIT 1');
                    $stmt->execute([trim($_POST['license_number'])]);
                    $driverId = $stmt->fetchColumn() ?: null;
                }
            } else {
                $this->json(['error' => 'Unauthorized GPS API call. Provide valid session or api_key.'], 401);
            }
        }

        if (!$driverId) {
            $this->json(['error' => 'Driver record not found.'], 404);
        }

        db()->prepare('UPDATE drivers SET gps_lat = ?, gps_lng = ?, gps_updated_at = NOW() WHERE id = ?')
            ->execute([$lat, $lng, $driverId]);

        $this->json([
            'ok' => true,
            'driver_id' => (int) $driverId,
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    public function map(): void
    {
        requireRole(['administrator']);

        $pdo = db();

        // Fetch active drivers with GPS data
        $drivers = $pdo->query("
            SELECT d.id, d.full_name, d.gps_lat, d.gps_lng, d.gps_updated_at, d.status,
                   s.tracking_number, s.pickup_location, s.destination, s.current_status AS shipment_status,
                   s.pickup_lat, s.pickup_lng, s.destination_lat, s.destination_lng
            FROM drivers d
            LEFT JOIN shipments s ON s.driver_id = d.id AND s.current_status IN ('pickup','in_transit','arrived')
            WHERE d.status IN ('available','assigned','on_delivery')
            ORDER BY d.full_name
        ")->fetchAll();

        $module = 'driver';
        $mapsKey = defined('GOOGLE_MAPS_API_KEY') ? GOOGLE_MAPS_API_KEY : '';
        require __DIR__ . '/../views/driver-map.php';
    }

    public function mobileGps(): void
    {
        requireRole(['driver', 'administrator']);

        $pdo = db();
        $selectedDriverId = null;
        $user = authUser();
        if ($user['role'] === 'driver') {
            $stmt = $pdo->prepare('SELECT id, full_name, license_number, status, gps_lat, gps_lng, gps_token FROM drivers WHERE user_id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $drivers = array_values(array_filter([$stmt->fetch()]));
            $selectedDriverId = $drivers ? (int) $drivers[0]['id'] : null;
        } else {
            $drivers = $pdo->query("SELECT id, full_name, license_number, status, gps_lat, gps_lng, gps_token FROM drivers ORDER BY full_name")->fetchAll();
        }

        // Ensure every driver has a GPS token (generate on-the-fly if missing)
        foreach ($drivers as &$d) {
            if (empty($d['gps_token'])) {
                $token = bin2hex(random_bytes(24));
                $pdo->prepare('UPDATE drivers SET gps_token = ? WHERE id = ?')->execute([$token, $d['id']]);
                $d['gps_token'] = $token;
            }
        }
        unset($d);

        $driverGpsToken = ($drivers && $selectedDriverId) ? ($drivers[0]['gps_token'] ?? '') : '';

        require __DIR__ . '/../views/mobile-gps.php';
    }

    /**
     * Public GPS update endpoint authenticated via per-driver gps_token.
     * Works cross-network without a login session — driver just needs to hold the token URL.
     */
    public function updateGpsToken(): void
    {
        $json = json_decode(file_get_contents('php://input'), true);
        $lat = filter_var($json['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lng = filter_var($json['lng'] ?? null, FILTER_VALIDATE_FLOAT);
        $gpsToken = trim((string) ($json['gps_token'] ?? ''));
        $driverId = (int) ($json['driver_id'] ?? 0);

        if ($lat === false || $lng === false || $lat === null || $lng === null) {
            $this->json(['error' => 'Invalid coordinates.'], 400);
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            $this->json(['error' => 'Coordinates out of range.'], 400);
        }
        if (!$gpsToken || !$driverId) {
            $this->json(['error' => 'Missing gps_token or driver_id.'], 401);
        }

        // Validate token against driver record
        $stmt = db()->prepare('SELECT id FROM drivers WHERE id = ? AND gps_token = ? LIMIT 1');
        $stmt->execute([$driverId, $gpsToken]);
        $found = $stmt->fetchColumn();

        if (!$found) {
            $this->json(['error' => 'Invalid GPS token for this driver.'], 403);
        }

        db()->prepare('UPDATE drivers SET gps_lat = ?, gps_lng = ?, gps_updated_at = NOW() WHERE id = ?')
            ->execute([$lat, $lng, $driverId]);

        $this->json([
            'ok'        => true,
            'driver_id' => $driverId,
            'lat'       => (float) $lat,
            'lng'       => (float) $lng,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }
}

