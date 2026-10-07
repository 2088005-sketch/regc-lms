-- GPS Token migration: adds per-driver GPS auth token for cross-network tracking
ALTER TABLE drivers
  ADD COLUMN IF NOT EXISTS gps_token VARCHAR(64) NULL AFTER gps_updated_at,
  ADD INDEX IF NOT EXISTS idx_driver_gps_token (gps_token);

