<?php
/**
 * O2System
 *
 * An open source application development framework for PHP 5.4 or newer
 *
 * This content is released under the MIT License (MIT)
 *
 * Copyright (c) 2014, PT. Lingkar Kreasi (Circle Creative).
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package        O2System
 * @author         Steeven Andrian Salim
 * @copyright      Copyright (c) 2005 - 2014, PT. Lingkar Kreasi (Circle Creative).
 * @license        http://circle-creative.com/products/o2system/license.html
 * @license        http://opensource.org/licenses/MIT	MIT License
 * @link           http://circle-creative.com
 * @since          Version 2.0
 * @filesource
 */

// ------------------------------------------------------------------------

namespace O2System\Session\Drivers;

// ------------------------------------------------------------------------

use O2System\Core\Gears\Logger;
use O2System\Session\Interfaces\Driver;
use O2System\Session\Interfaces\Handler;

/**
 * CodeIgniter Session Redis Driver
 *
 * @package       CodeIgniter
 * @subpackage    Libraries
 * @category      Sessions
 * @author        Andrey Andreev
 * @link          http://codeigniter.com/user_guide/libraries/sessions.html
 */
class Redis extends Driver implements \SessionHandlerInterface
{

    /**
     * phpRedis instance
     *
     * @var    resource
     */
    protected $_redis;

    /**
     * Key prefix
     *
     * @var    string
     */
    protected $_key_prefix = 'ci_session:';

    /**
     * Lock key
     *
     * @var    string
     */
    protected $_lock_key;

    // ------------------------------------------------------------------------

    /**
     * Class constructor
     *
     * @param    array $params Configuration parameters
     *
     * @return    void
     */
    public function __construct( &$params )
    {
        parent::__construct( $params );

        if( empty( $this->_config[ 'session' ][ 'save_path' ] ) )
        {
            Logger::error( 'Session: No Redis save path configured.' );
        }
        elseif( preg_match( '#(?:tcp://)?([^:?]+)(?:\:(\d+))?(\?.+)?#', $this->_config[ 'session' ][ 'save_path' ], $matches ) )
        {
            isset( $matches[ 3 ] ) OR $matches[ 3 ] = ''; // Just to avoid undefined index notices below
            $this->_config[ 'session' ][ 'save_path' ] = array(
                'host'     => $matches[ 1 ],
                'port'     => empty( $matches[ 2 ] ) ? NULL : $matches[ 2 ],
                'password' => preg_match( '#auth=([^\s&]+)#', $matches[ 3 ], $match ) ? $match[ 1 ] : NULL,
                'database' => preg_match( '#database=(\d+)#', $matches[ 3 ], $match ) ? (int)$match[ 1 ] : NULL,
                'timeout'  => preg_match( '#timeout=(\d+\.\d+)#', $matches[ 3 ], $match ) ? (float)$match[ 1 ] : NULL
            );

            preg_match( '#prefix=([^\s&]+)#', $matches[ 3 ], $match ) && $this->_key_prefix = $match[ 1 ];
        }
        else
        {
            Logger::error( 'Session: Invalid Redis save path format: ' . $this->_config[ 'session' ][ 'save_path' ] );
        }

        if( $this->_config[ 'session' ][ 'match_ip' ] === TRUE )
        {
            $this->_key_prefix .= $_SERVER[ 'REMOTE_ADDR' ] . ':';
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Open
     *
     * Sanitizes save_path and initializes connection.
     *
     * @param    string $save_path Server path
     * @param    string $name      Session cookie name, unused
     *
     * @return    bool
     */
    public function open( $save_path, $name )
    {
        if( empty( $this->_config[ 'session' ][ 'save_path' ] ) )
        {
            return FALSE;
        }

        $redis = new Redis();
        if( ! $redis->connect( $this->_config[ 'session' ][ 'save_path' ][ 'host' ], $this->_config[ 'session' ][ 'save_path' ][ 'port' ], $this->_config[ 'session' ][ 'save_path' ][ 'timeout' ] ) )
        {
            Logger::error( 'Session: Unable to connect to Redis with the configured settings.' );
        }
        elseif( isset( $this->_config[ 'session' ][ 'save_path' ][ 'password' ] ) && ! $redis->auth( $this->_config[ 'session' ][ 'save_path' ][ 'password' ] ) )
        {
            Logger::error( 'Session: Unable to authenticate to Redis instance.' );
        }
        elseif( isset( $this->_config[ 'session' ][ 'save_path' ][ 'database' ] ) && ! $redis->select( $this->_config[ 'session' ][ 'save_path' ][ 'database' ] ) )
        {
            Logger::error( 'Session: Unable to select Redis database with index ' . $this->_config[ 'session' ][ 'save_path' ][ 'database' ] );
        }
        else
        {
            $this->_redis = $redis;

            return TRUE;
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Read
     *
     * Reads session data and acquires a lock
     *
     * @param    string $session_id Session ID
     *
     * @return    string    Serialized session data
     */
    public function read( $session_id )
    {
        if( isset( $this->_redis ) && $this->_get_lock( $session_id ) )
        {
            // Needed by write() to detect session_regenerate_id() calls
            $this->_session_id = $session_id;

            $session_data = (string)$this->_redis->get( $this->_key_prefix . $session_id );
            $this->_fingerprint = md5( $session_data );

            return $session_data;
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Write
     *
     * Writes (create / update) session data
     *
     * @param    string $session_id   Session ID
     * @param    string $session_data Serialized session data
     *
     * @return    bool
     */
    public function write( $session_id, $session_data )
    {
        if( ! isset( $this->_redis ) )
        {
            return FALSE;
        }
        // Was the ID regenerated?
        elseif( $session_id !== $this->_session_id )
        {
            if( ! $this->_release_lock() OR ! $this->_get_lock( $session_id ) )
            {
                return FALSE;
            }

            $this->_fingerprint = md5( '' );
            $this->_session_id = $session_id;
        }

        if( isset( $this->_lock_key ) )
        {
            $this->_redis->setTimeout( $this->_lock_key, 300 );
            if( $this->_fingerprint !== ( $fingerprint = md5( $session_data ) ) )
            {
                if( $this->_redis->set( $this->_key_prefix . $session_id, $session_data, $this->_config[ 'session' ][ 'lifetime' ] ) )
                {
                    $this->_fingerprint = $fingerprint;

                    return TRUE;
                }

                return FALSE;
            }

            return $this->_redis->setTimeout( $this->_key_prefix . $session_id, $this->_config[ 'session' ][ 'lifetime' ] );
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Close
     *
     * Releases locks and closes connection.
     *
     * @return    bool
     */
    public function close()
    {
        if( isset( $this->_redis ) )
        {
            try
            {
                if( $this->_redis->ping() === '+PONG' )
                {
                    isset( $this->_lock_key ) && $this->_redis->delete( $this->_lock_key );
                    if( ! $this->_redis->close() )
                    {
                        return FALSE;
                    }
                }
            }
            catch( RedisException $e )
            {
                Logger::error( 'Session: Got RedisException on close(): ' . $e->getMessage() );
            }

            $this->_redis = NULL;

            return TRUE;
        }

        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Destroy
     *
     * Destroys the current session.
     *
     * @param    string $session_id Session ID
     *
     * @return    bool
     */
    public function destroy( $session_id )
    {
        if( isset( $this->_redis, $this->_lock_key ) )
        {
            if( ( $result = $this->_redis->delete( $this->_key_prefix . $session_id ) ) !== 1 )
            {
                Logger::debug( 'Session: Redis::delete() expected to return 1, got ' . var_export( $result, TRUE ) . ' instead.' );
            }

            return $this->_cookie_destroy();
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Garbage Collector
     *
     * Deletes expired sessions
     *
     * @param    int $maxlifetime Maximum lifetime of sessions
     *
     * @return    bool
     */
    public function gc( $maxlifetime )
    {
        // Not necessary, Redis takes care of that.
        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Get lock
     *
     * Acquires an (emulated) lock.
     *
     * @param    string $session_id Session ID
     *
     * @return    bool
     */
    protected function _get_lock( $session_id )
    {
        if( isset( $this->_lock_key ) )
        {
            return $this->_redis->setTimeout( $this->_lock_key, 300 );
        }

        // 30 attempts to obtain a lock, in case another request already has it
        $lock_key = $this->_key_prefix . $session_id . ':lock';
        $attempt = 0;
        do
        {
            if( ( $ttl = $this->_redis->ttl( $lock_key ) ) > 0 )
            {
                sleep( 1 );
                continue;
            }

            if( ! $this->_redis->setex( $lock_key, 300, time() ) )
            {
                Logger::error( 'Session: Error while trying to obtain lock for ' . $this->_key_prefix . $session_id );

                return FALSE;
            }

            $this->_lock_key = $lock_key;
            break;
        }
        while( $attempt++ < 30 );

        if( $attempt === 30 )
        {
            Logger::error( 'Session: Unable to obtain lock for ' . $this->_key_prefix . $session_id . ' after 30 attempts, aborting.' );

            return FALSE;
        }
        elseif( $ttl === -1 )
        {
            Logger::debug( 'Session: Lock for ' . $this->_key_prefix . $session_id . ' had no TTL, overriding.' );
        }

        $this->_lock = TRUE;

        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Release lock
     *
     * Releases a previously acquired lock
     *
     * @return    bool
     */
    protected function _release_lock()
    {
        if( isset( $this->_redis, $this->_lock_key ) && $this->_lock )
        {
            if( ! $this->_redis->delete( $this->_lock_key ) )
            {
                Logger::error( 'Session: Error while trying to free lock for ' . $this->_lock_key );

                return FALSE;
            }

            $this->_lock_key = NULL;
            $this->_lock = FALSE;
        }

        return TRUE;
    }

}

/* End of file Redis.php */
/* Location: ./o2system/libraries/sessiion/drivers/Redis.php */