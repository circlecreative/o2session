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

use O2System\Session\Interfaces\Driver;
use O2System\Session\Interfaces\Handler;

/**
 * CodeIgniter Session Files Driver
 *
 * @package       CodeIgniter
 * @subpackage    Libraries
 * @category      Sessions
 * @author        Andrey Andreev
 * @link          http://codeigniter.com/user_guide/libraries/sessions.html
 */
class Files extends Driver implements \SessionHandlerInterface
{

    /**
     * Save path
     *
     * @var    string
     */
    protected $_save_path;

    /**
     * File handle
     *
     * @var    resource
     */
    protected $_file_handle;

    /**
     * File name
     *
     * @var    resource
     */
    protected $_file_path;

    /**
     * File new flag
     *
     * @var    bool
     */
    protected $_file_new;

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

        $this->_config[ 'session' ][ 'save_path' ] = Config::cache( 'path' ) . $this->_config[ 'session' ][ 'save_path' ];

        if( isset( $this->_config[ 'session' ][ 'save_path' ] ) )
        {
            $this->_config[ 'session' ][ 'save_path' ] = rtrim( $this->_config[ 'session' ][ 'save_path' ], '/\\' );
            ini_set( 'session.save_path', $this->_config[ 'session' ][ 'save_path' ] );
        }
        else
        {
            $this->_config[ 'session' ][ 'save_path' ] = rtrim( ini_get( 'session.save_path' ), '/\\' );
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Open
     *
     * Sanitizes the save_path directory.
     *
     * @param    string $save_path Path to session files' directory
     * @param    string $name      Session cookie name
     *
     * @return    bool
     */
    public function open( $save_path, $name )
    {
        if( ! is_dir( $save_path ) )
        {
            if( ! mkdir( $save_path, Config::permissions( 'folder' ), TRUE ) )
            {
                throw new \Exception( "Session: Configured save path '" . $save_path . "' is not a directory, doesn't exist or cannot be created." );
            }
        }
        elseif( ! is_writable( $save_path ) )
        {
            throw new \Exception( "Session: Configured save path '" . $save_path . "' is not writable by the PHP process." );
        }

        $this->_config[ 'session' ][ 'save_path' ] = $save_path;
        $this->_file_path = $this->_config[ 'session' ][ 'save_path' ] . DIRECTORY_SEPARATOR
                            . $name // we'll use the session cookie name as a prefix to avoid collisions
                            . ( $this->_config[ 'session' ][ 'match_ip' ] ? md5( $_SERVER[ 'REMOTE_ADDR' ] ) : '' );

        $this->_file_path = str_replace( '/', DS, $this->_file_path );

        return TRUE;
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
        // If there is no session_id then just return FALSE
        // for avoiding apache crashing
        if( empty( $session_id ) ) return FALSE;

        // This might seem weird, but PHP 5.6 introduces session_reset(),
        // which re-reads session data
        if( $this->_file_handle === NULL )
        {
            // Just using fopen() with 'c+b' mode would be perfect, but it is only
            // available since PHP 5.2.6 and we have to set permissions for new files,
            // so we'd have to hack around this ...
            if( ( $this->_file_new = ! file_exists( $this->_file_path . $session_id ) ) === TRUE )
            {
                if( ( $this->_file_handle = fopen( $this->_file_path . $session_id, 'w+b' ) ) === FALSE )
                {
                    Logger::error( "Session: File '" . $this->_file_path . $session_id . "' doesn't exist and cannot be created." );

                    return FALSE;
                }
            }
            elseif( ( $this->_file_handle = fopen( $this->_file_path . $session_id, 'r+b' ) ) === FALSE )
            {
                Logger::error( "Session: Unable to open file '" . $this->_file_path . $session_id . "'." );

                return FALSE;
            }

            if( flock( $this->_file_handle, LOCK_EX ) === FALSE )
            {
                Logger::error( "Session: Unable to obtain lock for file '" . $this->_file_path . $session_id . "'." );
                fclose( $this->_file_handle );
                $this->_file_handle = NULL;

                return FALSE;
            }

            // Needed by write() to detect session_regenerate_id() calls
            $this->_session_id = $session_id;

            if( $this->_file_new )
            {
                chmod( $this->_file_path . $session_id, Config::permissions( 'file' ) );
                $this->_fingerprint = md5( '' );

                return '';
            }
        }
        else
        {
            rewind( $this->_file_handle );
        }

        $session_data = '';
        for( $read = 0, $length = filesize( $this->_file_path . $session_id ); $read < $length; $read += strlen( $buffer ) )
        {
            if( ( $buffer = fread( $this->_file_handle, $length - $read ) ) === FALSE )
            {
                break;
            }

            $session_data .= $buffer;
        }

        $this->_fingerprint = md5( $session_data );

        return $session_data;
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
        // If the two IDs don't match, we have a session_regenerate_id() call
        // and we need to close the old handle and open a new one
        if( $session_id !== $this->_session_id && ( ! $this->close() OR $this->read( $session_id ) === FALSE ) )
        {
            return FALSE;
        }

        if( ! is_resource( $this->_file_handle ) )
        {
            return FALSE;
        }
        elseif( $this->_fingerprint === md5( $session_data ) )
        {
            return ( $this->_file_new )
                ? TRUE
                : touch( $this->_file_path . $session_id );
        }

        if( ! $this->_file_new )
        {
            ftruncate( $this->_file_handle, 0 );
            rewind( $this->_file_handle );
        }

        if( ( $length = strlen( $session_data ) ) > 0 )
        {
            for( $written = 0; $written < $length; $written += $result )
            {
                if( ( $result = fwrite( $this->_file_handle, substr( $session_data, $written ) ) ) === FALSE )
                {
                    break;
                }
            }

            if( ! is_int( $result ) )
            {
                $this->_fingerprint = md5( substr( $session_data, 0, $written ) );
                Logger::error( 'Session: Unable to write data.' );

                return FALSE;
            }
        }

        $this->_fingerprint = md5( $session_data );

        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Close
     *
     * Releases locks and closes file descriptor.
     *
     * @return    bool
     */
    public function close()
    {
        if( is_resource( $this->_file_handle ) )
        {
            flock( $this->_file_handle, LOCK_UN );
            fclose( $this->_file_handle );

            $this->_file_handle = $this->_file_new = $this->_session_id = NULL;

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
        if( $this->close() )
        {
            return file_exists( $this->_file_path . $session_id )
                ? ( unlink( $this->_file_path . $session_id ) && $this->_cookie_destroy() )
                : TRUE;
        }
        elseif( $this->_file_path !== NULL )
        {
            clearstatcache();

            return file_exists( $this->_file_path . $session_id )
                ? ( unlink( $this->_file_path . $session_id ) && $this->_cookie_destroy() )
                : TRUE;
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
        if( ! is_dir( $this->_config[ 'session' ][ 'save_path' ] ) OR ( $directory = opendir( $this->_config[ 'session' ][ 'save_path' ] ) ) === FALSE )
        {
            Logger::debug( "Session: Garbage collector couldn't list files under directory '" . $this->_config[ 'session' ][ 'save_path' ] . "'." );

            return FALSE;
        }

        $ts = time() - $maxlifetime;

        $pattern = sprintf(
            '/^%s[0-9a-f]{%d}$/',
            preg_quote( $this->_config[ 'cookie' ][ 'name' ], '/' ),
            ( $this->_config[ 'session' ][ 'match_ip' ] === TRUE ? 72 : 40 )
        );

        while( ( $file = readdir( $directory ) ) !== FALSE )
        {
            // If the filename doesn't match this pattern, it's either not a session file or is not ours
            if( ! preg_match( $pattern, $file )
                OR ! is_file( $this->_config[ 'session' ][ 'save_path' ] . DIRECTORY_SEPARATOR . $file )
                OR ( $mtime = filemtime( $this->_config[ 'session' ][ 'save_path' ] . DIRECTORY_SEPARATOR . $file ) ) === FALSE
                OR $mtime > $ts
            )
            {
                continue;
            }

            unlink( $this->_config[ 'session' ][ 'save_path' ] . DIRECTORY_SEPARATOR . $file );
        }

        closedir( $directory );

        return TRUE;
    }

    public function create_sid()
    {
        session_regenerate_id();

        return session_id();
    }

}

/* End of file Files.php */
/* Location: ./o2system/libraries/sessiion/drivers/Files.php */
