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

namespace O2System\Libraries\Session\Drivers;

// ------------------------------------------------------------------------

use O2System\Core\Loader;
use O2System\Libraries\Session\Interfaces\Driver;
use O2System\Libraries\Session\Interfaces\Handler;

/**
 * CodeIgniter Session Database Driver
 *
 * @package       CodeIgniter
 * @subpackage    Libraries
 * @category      Sessions
 * @author        Andrey Andreev
 * @link          http://codeigniter.com/user_guide/libraries/sessions.html
 */
class Database extends Driver implements \SessionHandlerInterface
{
    /**
     * DB object
     *
     * @var    object
     */
    protected $_db;

    /**
     * Row exists flag
     *
     * @var    bool
     */
    protected $_row_exists = FALSE;

    /**
     * Lock "driver" flag
     *
     * @var    string
     */
    protected $_platform;

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

        $this->_db = Loader::db();

        if( $this->_db->pconnect )
        {
            throw new \Exception( 'Configured database connection is persistent. Aborting.' );
        }
        elseif( $this->_db->cache_on )
        {
            throw new \Exception( 'Configured database connection has cache enabled. Aborting.' );
        }

        $db_driver = $this->_db->db_driver . ( empty( $this->_db->sub_db_driver ) ? '' : '_' . $this->_db->sub_db_driver );
        if( strpos( $db_driver, 'mysql' ) !== FALSE )
        {
            $this->_platform = 'mysql';
        }
        elseif( in_array( $db_driver, array( 'postgre', 'pdo_pgsql' ), TRUE ) )
        {
            $this->_platform = 'postgre';
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Open
     *
     * Initializes the database connection
     *
     * @param    string $save_path Table name
     * @param    string $name      Session cookie name, unused
     *
     * @return    bool
     */
    public function open( $save_path, $name )
    {
        return empty( $this->_db->conn_id )
            ? (bool)$this->_db->db_connect()
            : TRUE;
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
        if( $this->_get_lock( $session_id ) !== FALSE )
        {
            // Needed by write() to detect session_regenerate_id() calls
            $this->_session_id = $session_id;

            $this->_db
                ->select( 'data' )
                ->from( $this->_config[ 'session' ][ 'save_path' ] )
                ->where( 'id', $session_id );

            if( $this->_config[ 'session' ][ 'match_ip' ] )
            {
                $this->_db->where( 'ip_address', $_SERVER[ 'REMOTE_ADDR' ] );
            }

            if( ( $result = $this->_db->get()->row() ) === NULL )
            {
                $this->_fingerprint = md5( '' );

                return '';
            }

            // PostgreSQL's variant of a BLOB datatype is Bytea, which is a
            // PITA to work with, so we use base64-encoded data in a TEXT
            // field instead.
            $result = ( $this->_platform === 'postgre' )
                ? base64_decode( rtrim( $result->data ) )
                : $result->data;

            $this->_fingerprint = md5( $result );
            $this->_row_exists = TRUE;

            return $result;
        }

        $this->_fingerprint = md5( '' );

        return '';
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
        // Was the ID regenerated?
        if( $session_id !== $this->_session_id )
        {
            if( ! $this->_release_lock() OR ! $this->_get_lock( $session_id ) )
            {
                return FALSE;
            }

            $this->_row_exists = FALSE;
            $this->_session_id = $session_id;
        }
        elseif( $this->_lock === FALSE )
        {
            return FALSE;
        }

        if( $this->_row_exists === FALSE )
        {
            $insert_data = array(
                'id'         => $session_id,
                'ip_address' => $_SERVER[ 'REMOTE_ADDR' ],
                'start'      => time(),
                'data'       => ( $this->_platform === 'postgre' ? base64_encode( $session_data ) : $session_data )
            );

            if( $this->_db->insert( $this->_config[ 'session' ][ 'save_path' ], $insert_data ) )
            {
                $this->_fingerprint = md5( $session_data );

                return $this->_row_exists = TRUE;
            }

            return FALSE;
        }

        $this->_db->where( 'id', $session_id );
        if( $this->_config[ 'session' ][ 'match_ip' ] )
        {
            $this->_db->where( 'ip_address', $_SERVER[ 'REMOTE_ADDR' ] );
        }

        $update_data = array( 'timestamp' => time() );
        if( $this->_fingerprint !== md5( $session_data ) )
        {
            $update_data[ 'data' ] = ( $this->_platform === 'postgre' )
                ? base64_encode( $session_data )
                : $session_data;
        }

        if( $this->_db->update( $this->_config[ 'session' ][ 'save_path' ], $update_data ) )
        {
            $this->_fingerprint = md5( $session_data );

            return TRUE;
        }

        return FALSE;
    }

    // ------------------------------------------------------------------------

    /**
     * Close
     *
     * Releases locks
     *
     * @return    bool
     */
    public function close()
    {
        return ( $this->_lock )
            ? $this->_release_lock()
            : TRUE;
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
        if( $this->_lock )
        {
            $this->_db->where( 'id', $session_id );
            if( $this->_config[ 'session' ][ 'match_ip' ] )
            {
                $this->_db->where( 'ip_address', $_SERVER[ 'REMOTE_ADDR' ] );
            }

            return $this->_db->delete( $this->_config[ 'session' ][ 'save_path' ] )
                ? ( $this->close() && $this->_cookie_destroy() )
                : FALSE;
        }

        return ( $this->close() && $this->_cookie_destroy() );
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
        return $this->_db->delete( $this->_config[ 'session' ][ 'save_path' ], 'timestamp < ' . ( time() - $maxlifetime ) );
    }

    // ------------------------------------------------------------------------

    /**
     * Get lock
     *
     * Acquires a lock, depending on the underlying platform.
     *
     * @param    string $session_id Session ID
     *
     * @return    bool
     */
    protected function _get_lock( $session_id )
    {
        if( $this->_platform === 'mysql' )
        {
            $arg = $session_id . ( $this->_config[ 'session' ][ 'match_ip' ] ? '_' . $_SERVER[ 'REMOTE_ADDR' ] : '' );
            if( $this->_db->query( "SELECT GET_LOCK('" . $arg . "', 300) AS ci_session_lock" )->row()->ci_session_lock )
            {
                $this->_lock = $arg;

                return TRUE;
            }

            return FALSE;
        }
        elseif( $this->_platform === 'postgre' )
        {
            $arg = "hashtext('" . $session_id . "')" . ( $this->_config[ 'session' ][ 'match_ip' ] ? ", hashtext('" . $_SERVER[ 'REMOTE_ADDR' ] . "')" : '' );
            if( $this->_db->simple_query( 'SELECT pg_advisory_lock(' . $arg . ')' ) )
            {
                $this->_lock = $arg;

                return TRUE;
            }

            return FALSE;
        }

        return parent::_get_lock( $session_id );
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
        if( ! $this->_lock )
        {
            return TRUE;
        }

        if( $this->_platform === 'mysql' )
        {
            if( $this->_db->query( "SELECT RELEASE_LOCK('" . $this->_lock . "') AS ci_session_lock" )->row()->ci_session_lock )
            {
                $this->_lock = FALSE;

                return TRUE;
            }

            return FALSE;
        }
        elseif( $this->_platform === 'postgre' )
        {
            if( $this->_db->simple_query( 'SELECT pg_advisory_unlock(' . $this->_lock . ')' ) )
            {
                $this->_lock = FALSE;

                return TRUE;
            }

            return FALSE;
        }

        return parent::_release_lock();
    }
}

/* End of file Database.php */
/* Location: ./o2system/libraries/sessiion/drivers/Database.php */
