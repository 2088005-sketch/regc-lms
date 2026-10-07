# REGC Trucking Services LMS - Operations

PHP 8+, MySQL/MariaDB, Bootstrap 5, and JavaScript. Uses PDO prepared statements, secure sessions, CSRF protection, and activity logging. Enable PHP's PDO MySQL and mbstring extensions (included in XAMPP).

## Run locally

1. Place this folder in `C:\xampp\htdocs\regc-lms`.
2. Start Apache and MySQL in XAMPP.
3. For a **new installation only**, import `database/schema.sql`, then run `C:\xampp\php\php.exe database\seed.php` from this folder.
4. Open `http://localhost/regc-lms/` and sign in.

Existing Phase 1 databases already contain the required tables. **No schema reimport or migration is needed** for these modules.

Default administrator: `admin@regc.local` / `Admin@12345`. After signing in, open **My account → Change password** before deployment. The setup and diagnostic scripts are command-line only and return 404 through Apache.

## Workflow

1. **Customers:** add contact details and company information; search, edit, deactivate, or delete unused records.
2. **Trucks:** register plates, capacity, model, registration details, and availability.
3. **Drivers:** record contact information, license details, and availability. Driver records do not automatically create login accounts.
4. **Shipments:** select a customer, enter cargo and route details, optionally schedule pickup and expected delivery, and save. A unique tracking number is generated automatically.
5. Assign an available truck and driver together. Weight must fit the truck's capacity; known registration/license expiration dates must cover pickup. Each resource handles one active shipment at a time.
6. Open a shipment and advance it through **Assigned -> Pickup -> In transit -> Arrived -> Delivered**. Optional remarks are added to its history. Delivery makes its truck and driver available again.
7. **Tracking:** search the tracking number to view the route, current status, assignment, and updates.

These management and tracking screens require an administrator login. Tracking is an internal status timeline, not public tracking or live GPS.

Use **My account** to review the signed-in profile and change the administrator password. Logout is protected by both POST-only routing and a CSRF token.

Add/edit actions, shipment assignment, delivery status updates, and delete confirmations open in dialogs. Validation errors stay in the dialog with entered values preserved; successful changes refresh the current view. Dialogs support Escape, keyboard focus, and mobile scrolling. Search/filter controls stay on the page. Direct form URLs still work as a fallback when JavaScript is unavailable.

Shipment details and assignments can be edited before pickup. Unassigning both resources returns a shipment to Pending. Only unassigned pending shipments can be deleted. Customers, trucks, and drivers referenced by shipments are retained to preserve delivery history. Assigned resource statuses are controlled by the shipment workflow.

## Verification

Run `C:\xampp\php\php.exe tests/operations.php` with MySQL running. The integration suite creates and removes its own randomly named `regc_test_*` database; its database user needs CREATE/DROP permissions. Application data is not modified. Tests cover CRUD, validation, capacity, assignment rollback, availability, status progression, history, deletion restrictions, search, and view escaping.

Run `C:\xampp\php\php.exe tests/http-smoke.php` with Apache and MySQL running to check live routes, login/logout, navigation, validation, and CSRF. This uses the documented default administrator and creates normal login/logout audit entries; it does not create operational records. An optional first argument overrides the base URL (ending in `index.php`). PHP cURL is needed for this test.

Run `C:\xampp\php\php.exe debug-login.php` for a safe database and session diagnostic. It reports counts and connectivity without printing account details or password hashes.

Proof-of-delivery uploads, driver self-service, public tracking, and GPS integration are outside this phase.
