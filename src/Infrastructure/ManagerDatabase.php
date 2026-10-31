<?php

declare(strict_types=1);

namespace App\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ManagerDatabase
{
    private ?Connection $connection = null;

    public function __construct(#[Autowire('%env(MANAGER_DATABASE_URL)%')] #[\SensitiveParameter] private readonly string $url) {}

    public function connection(): Connection
    {
        if ($this->url === '') { throw new \LogicException('Configure MANAGER_DATABASE_URL with the dedicated runtime role.'); }
        if ($this->connection !== null) { return $this->connection; }
        try {
            $parameters = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql']))->parse($this->url);
            if (($parameters['driver'] ?? null) !== 'pdo_pgsql' || ($parameters['user'] ?? '') !== 'querylens_manager') {
                throw new \LogicException('Dedicated role required.');
            }
            $parameters['driverOptions'][\PDO::ATTR_TIMEOUT] = 3;
            $connection = DriverManager::getConnection($parameters);
            $role = $connection->fetchAssociative("SELECT current_user AS name, rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls FROM pg_roles WHERE rolname = current_user");
            if (!$role || $role['name'] !== 'querylens_manager' || $role['rolsuper'] || $role['rolcreatedb'] || $role['rolcreaterole'] || $role['rolreplication'] || $role['rolbypassrls']
                || $connection->fetchOne('SELECT count(*) FROM pg_auth_members WHERE member = (SELECT oid FROM pg_roles WHERE rolname = current_user)') != 0
                || $connection->fetchOne("SELECT count(*) FROM pg_shdepend WHERE refclassid = 'pg_authid'::regclass AND refobjid = (SELECT oid FROM pg_roles WHERE rolname = current_user) AND deptype = 'o'") != 0) {
                throw new \LogicException('Unsafe role.');
            }
            $connection->executeStatement("SET statement_timeout = '5s'");
            $connection->executeStatement("SET lock_timeout = '1s'");
            $connection->executeStatement("SET TIME ZONE 'UTC'");
            if ($connection->fetchOne('SELECT supported FROM manager.compatibility') !== true) {
                throw new \LogicException('Unsupported shared schema.');
            }
            return $this->connection = $connection;
        } catch (\Throwable) {
            // Discard DBAL/PDO errors: DSNs, SQL parameters and credentials must not reach logs.
            throw new \RuntimeException('Manager database is unavailable or its role is unsafe.');
        }
    }
}
