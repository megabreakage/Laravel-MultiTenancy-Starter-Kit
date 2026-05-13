-- Grant the app user privileges to create and manage tenant databases.
-- Stancl Tenancy creates databases with the 'tenant_' prefix by default.
GRANT ALL PRIVILEGES ON `tenant_%`.* TO 'api_kit'@'%';
GRANT CREATE ON *.* TO 'api_kit'@'%';
FLUSH PRIVILEGES;
