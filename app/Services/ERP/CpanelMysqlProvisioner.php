<?php
namespace App\Services\ERP;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Calls cPanel UAPI using fixed executable/operations; NEVER interpolates shell strings.
 * The cPanel password is supplied through a private temporary file, not process arguments.
 */
class CpanelMysqlProvisioner
{
    private const BIN = '/usr/bin/uapi';
    private const OPERATIONS = [
        'get_restrictions', 'list_databases', 'list_users',
        'create_database', 'create_user', 'set_privileges_on_database',
    ];

    protected function invoke(string $operation, array $parameters = []): array
    {
        if (!in_array($operation, self::OPERATIONS, true)) {
            throw new RuntimeException('Unsupported cPanel operation');
        }
        $arguments = [self::BIN, '--output=json', 'Mysql', $operation];
        $passwordFile = null;
        try {
            foreach ($parameters as $name => $value) {
                if (!in_array($name, ['name', 'password', 'user', 'database', 'privileges'], true)) {
                    throw new RuntimeException('Unsupported parameter');
                }
                if ($name === 'password') {
                    $passwordFile = tempnam(storage_path('app'), 'erp-db-');
                    if ($passwordFile === false) throw new RuntimeException('Cannot create private credential file');
                    chmod($passwordFile, 0600);
                    if (file_put_contents($passwordFile, $value) !== strlen($value)) {
                        throw new RuntimeException('Cannot write private credential file');
                    }
                    $arguments[] = 'password=@'.$passwordFile;
                } else {
                    $arguments[] = $name.'='.rawurlencode($value);
                }
            }
            $process = new Process($arguments, base_path());
            $process->setTimeout(45);
            $process->run();
            $payload = json_decode($process->getOutput(), true);
            if (!$process->isSuccessful() || !is_array($payload) || ($payload['result']['status'] ?? 0) !== 1) {
                // Never include UAPI output: some versions can echo submitted arguments.
                throw new RuntimeException('cPanel MySQL operation failed: '.$operation);
            }
            return $payload['result']['data'] ?? [];
        } finally {
            if ($passwordFile !== null && is_file($passwordFile)) @unlink($passwordFile);
        }
    }

    public function prefix(): string
    {
        $info = $this->invoke('get_restrictions');
        $prefix = $info['prefix'] ?? null;
        if (!is_string($prefix) || !preg_match('/^[a-zA-Z0-9_]{1,16}$/D', $prefix)) {
            throw new RuntimeException('Invalid cPanel MySQL prefix');
        }
        return $prefix;
    }

    public function databaseExists(string $name): bool
    {
        foreach ($this->invoke('list_databases') as $entry) {
            if (is_array($entry) && ($entry['database'] ?? $entry['name'] ?? null) === $name) return true;
        }
        return false;
    }

    public function userExists(string $name): bool
    {
        foreach ($this->invoke('list_users') as $entry) {
            if (is_array($entry) && ($entry['user'] ?? $entry['name'] ?? null) === $name) return true;
        }
        return false;
    }

    public function createDatabase(string $name): void
    {
        $this->invoke('create_database', ['name' => $name]);
    }

    public function createUser(string $name, string $password): void
    {
        $this->invoke('create_user', ['name' => $name, 'password' => $password]);
    }

    public function grant(string $user, string $database): void
    {
        $this->invoke('set_privileges_on_database', [
            'user' => $user, 'database' => $database, 'privileges' => 'ALL PRIVILEGES'
        ]);
    }
}
