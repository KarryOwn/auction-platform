-- Bootstrap testing database for Laravel/Pest under Sail.
-- This script is executed by postgres only on first container initialization.

SELECT 'CREATE DATABASE testing OWNER sail'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'testing')\gexec

ALTER DATABASE testing OWNER TO sail;
GRANT ALL PRIVILEGES ON DATABASE testing TO sail;

\connect testing

ALTER SCHEMA public OWNER TO sail;
GRANT ALL ON SCHEMA public TO sail;

-- Ensure tables/sequences/functions created by bootstrap/migrations are accessible.
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON TABLES TO sail;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON SEQUENCES TO sail;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT ALL ON FUNCTIONS TO sail;
