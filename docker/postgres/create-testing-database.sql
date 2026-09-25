SELECT 'CREATE DATABASE emp_testing'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'emp_testing')\gexec
