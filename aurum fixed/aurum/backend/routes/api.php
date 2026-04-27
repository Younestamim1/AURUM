<?php
// routes/api.php  — all routes in one place
require_once __DIR__ . '/Router.php';

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/HotelsController.php';
require_once __DIR__ . '/../controllers/BookingsController.php';
require_once __DIR__ . '/../controllers/OwnerPropertiesController.php';
require_once __DIR__ . '/../controllers/AnalyticsController.php';
require_once __DIR__ . '/../controllers/AIConciergeController.php';

$router = new Router();

// ── Auth ──────────────────────────────────────────────────────
$router->post('/auth/login',        fn() => (new AuthController())->login());
$router->post('/auth/register',     fn() => (new AuthController())->register());
$router->post('/auth/admin/login',  fn() => (new AuthController())->adminLogin());

// ── Hotels ───────────────────────────────────────────────────
$router->get('/hotels',             fn()        => (new HotelsController())->getAll());
$router->get('/hotels/:id',         fn(int $id) => (new HotelsController())->getById($id));

// ── Bookings ─────────────────────────────────────────────────
$router->post('/bookings',          fn() => (new BookingsController())->create());
$router->get('/bookings',           fn() => (new BookingsController())->getUserBookings());

// ── Owner Properties ─────────────────────────────────────────
$router->get('/owner/properties',          fn()        => (new OwnerPropertiesController())->getAll());
$router->post('/owner/properties',         fn()        => (new OwnerPropertiesController())->create());
$router->put('/owner/properties/:id',      fn(int $id) => (new OwnerPropertiesController())->update($id));
$router->delete('/owner/properties/:id',   fn(int $id) => (new OwnerPropertiesController())->delete($id));

// ── Analytics ────────────────────────────────────────────────
$router->get('/analytics/dashboard',       fn() => (new AnalyticsController())->getDashboard());

// ── AI Concierge ─────────────────────────────────────────────
$router->post('/ai/concierge',             fn() => (new AIConciergeController())->chat());

return $router;
