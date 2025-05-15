<?php

declare(strict_types=1);

namespace orms;

use Exception;
use Memcached;

/**
 * MemcachedManager instantiates the Memcached object and either creates a new server or immediately returns true.
 */
class MemcachedManager {
    private $id;
    private $obj;

    public function __construct($id) {
        $this->id = $id;
        $this->obj = new Memcached($id);
    }

    /**
     * Manage server connections of the Memcached instance
     * https://www.php.net/manual/en/memcached.addserver.php#110003
     * 
     * Host and port are used to confirm uniqueness of a given server within the pool.
     * 
     */
    public function serverConnection($host, $port): bool 
    {
        $servers = $this->obj->getServerList();
        if (is_array($servers)) {
            foreach ($servers as $server) {
                if ($server['host'] == $host && $server['port'] == $port) {
                    return true;
                }
            }
        }
        return $this->obj->addServer($host, $port);
    }

    /**
     * Getter for the memcached instance itself
     */
    public function getMemcached(): Memcached 
    {
        return $this->obj;
    }
}
