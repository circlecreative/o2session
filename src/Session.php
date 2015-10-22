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

namespace O2System;

// ------------------------------------------------------------------------

/**
 * Session Class
 *
 * Porting from CodeIgniter Session Library Class
 *
 * @package        O2System
 * @subpackage     Libraries/Session
 * @category       System Libraries
 * @author         Andrey Andreev
 *                 Steeven Andrian Salim
 * @link           http://codeigniter.com/user_guide/libraries/sessions.html
 */
class Session
{
    /**
     * Userdata array
     *
     * Just a reference to $_SESSION, for BC purposes.
     */
    public $userdata;

    protected $_driver = 'files';
    protected $_config;

    // ------------------------------------------------------------------------

    /**
     * Class constructor
     */
    public function __construct()
    {
        // No sessions under CLI
        if( is_cli() )
        {
            Logger::debug( 'Session: Initialization under CLI aborted.' );

            return;
        }
        elseif( (bool)ini_get( 'session.auto_start' ) )
        {
            Logger::error( 'Session: session.auto_start is enabled in php.ini. Aborting.' );

            return;
        }

        // Initialize Configuration
        $this->_configure();

        if( ! empty( $this->_config[ 'session' ][ 'driver' ] ) )
        {
            $this->_driver = $this->_config[ 'session' ][ 'driver' ];
        }

        $class = 'O2System\Libraries\Session\Drivers\\' . prepare_class_name( $this->_driver );
        $class = new $class( $this->_config );

        if( $class instanceof \SessionHandlerInterface )
        {
            session_set_save_handler( $class, TRUE );
        }
        else
        {
            Logger::error( "Session: Driver '" . $this->_driver . "' doesn't implement Session Handler Interface. Aborting." );

            return;
        }

        // Sanitize the cookie, because apparently PHP doesn't do that for userspace handlers
        if( isset( $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] )
            && (
                ! is_string( $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] )
                OR ! preg_match( '/^[0-9a-f]{40}$/', $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] )
            )
        )
        {
            unset( $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] );
        }

        session_start();

        // Is session ID auto-regeneration configured? (ignoring ajax requests)
        if( ( empty( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) OR strtolower( $_SERVER[ 'HTTP_X_REQUESTED_WITH' ] ) !== 'xmlhttprequest' )
            && ( $regenerate_time = $this->_config[ 'session' ][ 'update_time' ] ) > 0
        )
        {
            if( ! isset( $_SESSION[ '__session_last_regenerate' ] ) )
            {
                $_SESSION[ '__session_last_regenerate' ] = time();
            }
            elseif( $_SESSION[ '__session_last_regenerate' ] < ( time() - $regenerate_time ) )
            {
                $this->regenerate( (bool)$this->_config[ 'session' ][ 'regenerate' ] );
            }
        }
        // Another work-around ... PHP doesn't seem to send the session cookie
        // unless it is being currently created or regenerated
        elseif( isset( $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] ) && $_COOKIE[ $this->_config[ 'cookie' ][ 'name' ] ] === session_id() )
        {
            setcookie(
                $this->_config[ 'cookie' ][ 'name' ],
                session_id(),
                ( empty( $this->_config[ 'cookie' ][ 'lifetime' ] ) ? 0 : time() + $this->_config[ 'cookie' ][ 'lifetime' ] ),
                $this->_config[ 'cookie' ][ 'path' ],
                $this->_config[ 'cookie' ][ 'domain' ],
                $this->_config[ 'cookie' ][ 'secure' ],
                TRUE
            );
        }

        $this->_init_vars();

        Logger::info( "Session: Class initialized using '" . $this->_driver . "' driver." );
    }

    // ------------------------------------------------------------------------

    /**
     * Configuration
     * Handle input parameters and configuration defaults
     *
     * @return    void
     */
    protected function _configure()
    {
        $this->_config[ 'session' ] = Config::session();
        $this->_config[ 'cookie' ] = Config::cookie();

        // Configure Session
        if( empty( $this->_config[ 'session' ][ 'name' ] ) )
        {
            $this->_config[ 'session' ][ 'name' ] = ini_get( 'session.name' );
        }
        else
        {
            ini_set( 'session.name', $this->_config[ 'session' ][ 'name' ] );
        }

        if( empty( $this->_config[ 'session' ][ 'lifetime' ] ) )
        {
            $this->_config[ 'session' ][ 'lifetime' ] = (int)ini_get( 'session.gc_maxlifetime' );
        }
        else
        {
            ini_set( 'session.gc_maxlifetime', $this->_config[ 'session' ][ 'lifetime' ] );
        }

        // Configure Session Cookie
        if( empty( $this->_config[ 'cookie' ][ 'lifetime' ] ) )
        {
            $this->_config[ 'cookie' ][ 'lifetime' ] = (int)$this->_config[ 'session' ][ 'lifetime' ];
        }

        if( empty( $this->_config[ 'cookie' ][ 'name' ] ) )
        {
            $this->_config[ 'cookie' ][ 'name' ] = $this->_config[ 'session' ][ 'name' ];
        }

        session_set_cookie_params(
            $this->_config[ 'cookie' ][ 'lifetime' ],
            $this->_config[ 'cookie' ][ 'path' ],
            $this->_config[ 'cookie' ][ 'domain' ],
            (bool)$this->_config[ 'cookie' ][ 'secure' ],
            TRUE // HttpOnly; Yes, this is intentional and not configurable for security reasons
        );

        // Security is king
        ini_set( 'session.use_trans_sid', 0 );
        ini_set( 'session.use_strict_mode', 1 );
        ini_set( 'session.use_cookies', 1 );
        ini_set( 'session.use_only_cookies', 1 );
        ini_set( 'session.hash_function', 1 );
        ini_set( 'session.hash_bits_per_character', 4 );
    }

    // ------------------------------------------------------------------------

    /**
     * Handle temporary variables
     *
     * Clears old "flash" data, marks the new one for deletion and handles
     * "temp" data deletion.
     *
     * @return    void
     */
    protected function _init_vars()
    {
        if( ! empty( $_SESSION[ '__session_vars' ] ) )
        {
            $current_time = time();

            foreach( $_SESSION[ '__session_vars' ] as $key => &$value )
            {
                if( $value === 'new' )
                {
                    $_SESSION[ '__session_vars' ][ $key ] = 'old';
                }
                // Hacky, but 'old' will (implicitly) always be less than time() ;)
                // DO NOT move this above the 'new' check!
                elseif( $value < $current_time )
                {
                    unset( $_SESSION[ $key ], $_SESSION[ '__session_vars' ][ $key ] );
                }
            }

            if( empty( $_SESSION[ '__session_vars' ] ) )
            {
                unset( $_SESSION[ '__session_vars' ] );
            }
        }

        $this->userdata =& $_SESSION;
    }

    // ------------------------------------------------------------------------

    /**
     * Mark as flash
     *
     * @param    mixed $key Session data key(s)
     *
     * @return    bool
     */
    public function mark_as_flash( $key )
    {
        if( is_array( $key ) )
        {
            for( $i = 0, $c = count( $key ); $i < $c; $i++ )
            {
                if( ! isset( $_SESSION[ $key[ $i ] ] ) )
                {
                    return FALSE;
                }
            }

            $new = array_fill_keys( $key, 'new' );

            $_SESSION[ '__session_vars' ] = isset( $_SESSION[ '__session_vars' ] )
                ? array_merge( $_SESSION[ '__session_vars' ], $new )
                : $new;

            return TRUE;
        }

        if( ! isset( $_SESSION[ $key ] ) )
        {
            return FALSE;
        }

        $_SESSION[ '__session_vars' ][ $key ] = 'new';

        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Get flash keys
     *
     * @return    array
     */
    public function get_flash_keys()
    {
        if( ! isset( $_SESSION[ '__session_vars' ] ) )
        {
            return array();
        }

        $keys = array();
        foreach( array_keys( $_SESSION[ '__session_vars' ] ) as $key )
        {
            is_int( $_SESSION[ '__session_vars' ][ $key ] ) OR $keys[ ] = $key;
        }

        return $keys;
    }

    // ------------------------------------------------------------------------

    /**
     * Unmark flash
     *
     * @param    mixed $key Session data key(s)
     *
     * @return    void
     */
    public function unmark_flash( $key )
    {
        if( empty( $_SESSION[ '__session_vars' ] ) )
        {
            return;
        }

        is_array( $key ) OR $key = array( $key );

        foreach( $key as $k )
        {
            if( isset( $_SESSION[ '__session_vars' ][ $k ] ) && ! is_int( $_SESSION[ '__session_vars' ][ $k ] ) )
            {
                unset( $_SESSION[ '__session_vars' ][ $k ] );
            }
        }

        if( empty( $_SESSION[ '__session_vars' ] ) )
        {
            unset( $_SESSION[ '__session_vars' ] );
        }
    }

    // ------------------------------------------------------------------------

    /**
     * Mark as temp
     *
     * @param    mixed $key Session data key(s)
     * @param    int   $ttl Time-to-live in seconds
     *
     * @return    bool
     */
    public function mark_as_temp( $key, $ttl = 300 )
    {
        $ttl += time();

        if( is_array( $key ) )
        {
            $temp = array();

            foreach( $key as $k => $v )
            {
                // Do we have a key => ttl pair, or just a key?
                if( is_int( $k ) )
                {
                    $k = $v;
                    $v = $ttl;
                }
                else
                {
                    $v += time();
                }

                if( ! isset( $_SESSION[ $k ] ) )
                {
                    return FALSE;
                }

                $temp[ $k ] = $v;
            }

            $_SESSION[ '__session_vars' ] = isset( $_SESSION[ '__session_vars' ] )
                ? array_merge( $_SESSION[ '__session_vars' ], $temp )
                : $temp;

            return TRUE;
        }

        if( ! isset( $_SESSION[ $key ] ) )
        {
            return FALSE;
        }

        $_SESSION[ '__session_vars' ][ $key ] = $ttl;

        return TRUE;
    }

    // ------------------------------------------------------------------------

    /**
     * Get temp keys
     *
     * @return    array
     */
    public function get_temp_keys()
    {
        if( ! isset( $_SESSION[ '__session_vars' ] ) )
        {
            return array();
        }

        $keys = array();
        foreach( array_keys( $_SESSION[ '__session_vars' ] ) as $key )
        {
            is_int( $_SESSION[ '__session_vars' ][ $key ] ) && $keys[ ] = $key;
        }

        return $keys;
    }

    // ------------------------------------------------------------------------

    /**
     * Unmark flash
     *
     * @param    mixed $key Session data key(s)
     *
     * @return    void
     */
    public function unmark_temp( $key )
    {
        if( empty( $_SESSION[ '__session_vars' ] ) )
        {
            return;
        }

        is_array( $key ) OR $key = array( $key );

        foreach( $key as $k )
        {
            if( isset( $_SESSION[ '__session_vars' ][ $k ] ) && is_int( $_SESSION[ '__session_vars' ][ $k ] ) )
            {
                unset( $_SESSION[ '__session_vars' ][ $k ] );
            }
        }

        if( empty( $_SESSION[ '__session_vars' ] ) )
        {
            unset( $_SESSION[ '__session_vars' ] );
        }
    }

    // ------------------------------------------------------------------------

    /**
     * __get()
     *
     * @param    string $key 'session_id' or a session data key
     *
     * @return    mixed
     */
    public function __get( $key )
    {
        // Note: Keep this order the same, just in case somebody wants to
        //       use 'session_id' as a session data key, for whatever reason
        if( isset( $_SESSION[ $key ] ) )
        {
            return $_SESSION[ $key ];
        }
        elseif( $key === 'session_id' )
        {
            return session_id();
        }

        return NULL;
    }

    // ------------------------------------------------------------------------

    /**
     * __set()
     *
     * @param    string $key   Session data key
     * @param    mixed  $value Session data value
     *
     * @return    void
     */
    public function __set( $key, $value )
    {
        $_SESSION[ $key ] = $value;
    }

    // ------------------------------------------------------------------------

    /**
     * Session destroy
     *
     * Legacy Session compatibility method
     *
     * @return    void
     */
    public function destroy()
    {
        session_destroy();
    }

    // ------------------------------------------------------------------------

    /**
     * Session regenerate
     *
     * Legacy Session compatibility method
     *
     * @param    bool $destroy Destroy old session data flag
     *
     * @return    void
     */
    public function regenerate( $destroy = FALSE )
    {
        $_SESSION[ '__session_last_regenerate' ] = time();
        session_regenerate_id( $destroy );
    }

    // ------------------------------------------------------------------------

    /**
     * Get userdata reference
     *
     * Legacy Session compatibility method
     *
     * @returns    array
     */
    public function &get_userdata()
    {
        return $_SESSION;
    }

    // ------------------------------------------------------------------------

    /**
     * Userdata (fetch)
     *
     * Legacy Session compatibility method
     *
     * @param    string $key Session data key
     *
     * @return    mixed    Session data value or NULL if not found
     */
    public function userdata( $key = NULL )
    {
        if( isset( $key ) )
        {
            return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : NULL;
        }
        elseif( empty( $_SESSION ) )
        {
            return array();
        }

        $userdata = array();
        $_exclude = array_merge(
            array( '__session_vars' ),
            $this->get_flash_keys(),
            $this->get_temp_keys()
        );

        foreach( array_keys( $_SESSION ) as $key )
        {
            if( ! in_array( $key, $_exclude, TRUE ) )
            {
                $userdata[ $key ] = $_SESSION[ $key ];
            }
        }

        return $userdata;
    }

    // ------------------------------------------------------------------------

    /**
     * Set userdata
     *
     * Legacy Session compatibility method
     *
     * @param    mixed $data  Session data key or an associative array
     * @param    mixed $value Value to store
     *
     * @return    void
     */
    public function set_userdata( $data, $value = NULL )
    {
        if( is_array( $data ) )
        {
            foreach( $data as $key => &$value )
            {
                $_SESSION[ $key ] = $value;
            }

            return;
        }

        $_SESSION[ $data ] = $value;
    }

    // ------------------------------------------------------------------------

    /**
     * Unset userdata
     *
     * Legacy Session compatibility method
     *
     * @param    mixed $data Session data key(s)
     *
     * @return    void
     */
    public function unset_userdata( $key )
    {
        if( is_array( $key ) )
        {
            foreach( $key as $k )
            {
                unset( $_SESSION[ $k ] );
            }

            return;
        }

        unset( $_SESSION[ $key ] );
    }

    // ------------------------------------------------------------------------

    /**
     * All userdata (fetch)
     *
     * Legacy Session compatibility method
     *
     * @return    array    $_SESSION, excluding flash data items
     */
    public function all_userdata()
    {
        return $this->userdata();
    }

    // ------------------------------------------------------------------------

    /**
     * Has userdata
     *
     * Legacy Session compatibility method
     *
     * @param    string $key Session data key
     *
     * @return    bool
     */
    public function has_userdata( $key )
    {
        return isset( $_SESSION[ $key ] );
    }

    // ------------------------------------------------------------------------

    /**
     * Flashdata (fetch)
     *
     * Legacy Session compatibility method
     *
     * @param    string $key Session data key
     *
     * @return    mixed    Session data value or NULL if not found
     */
    public function flashdata( $key = NULL )
    {
        if( isset( $key ) )
        {
            return ( isset( $_SESSION[ '__session_vars' ], $_SESSION[ '__session_vars' ][ $key ], $_SESSION[ $key ] ) && ! is_int( $_SESSION[ '__session_vars' ][ $key ] ) )
                ? $_SESSION[ $key ]
                : NULL;
        }

        $flashdata = array();

        if( ! empty( $_SESSION[ '__session_vars' ] ) )
        {
            foreach( $_SESSION[ '__session_vars' ] as $key => &$value )
            {
                is_int( $value ) OR $flashdata[ $key ] = $_SESSION[ $key ];
            }
        }

        return $flashdata;
    }

    // ------------------------------------------------------------------------

    /**
     * Set flashdata
     *
     * Legacy Session compatibiliy method
     *
     * @param    mixed $data  Session data key or an associative array
     * @param    mixed $value Value to store
     *
     * @return    void
     */
    public function set_flashdata( $data, $value = NULL )
    {
        $this->set_userdata( $data, $value );
        $this->mark_as_flash( is_array( $data ) ? array_keys( $data ) : $data );
    }

    // ------------------------------------------------------------------------

    /**
     * Keep flashdata
     *
     * Legacy Session compatibility method
     *
     * @param    mixed $key Session data key(s)
     *
     * @return    void
     */
    public function keep_flashdata( $key )
    {
        $this->mark_as_flash( $key );
    }

    // ------------------------------------------------------------------------

    /**
     * Temp data (fetch)
     *
     * Legacy Session compatibility method
     *
     * @param    string $key Session data key
     *
     * @return    mixed    Session data value or NULL if not found
     */
    public function tempdata( $key = NULL )
    {
        if( isset( $key ) )
        {
            return ( isset( $_SESSION[ '__session_vars' ], $_SESSION[ '__session_vars' ][ $key ], $_SESSION[ $key ] ) && is_int( $_SESSION[ '__session_vars' ][ $key ] ) )
                ? $_SESSION[ $key ]
                : NULL;
        }

        $tempdata = array();

        if( ! empty( $_SESSION[ '__session_vars' ] ) )
        {
            foreach( $_SESSION[ '__session_vars' ] as $key => &$value )
            {
                is_int( $value ) && $tempdata[ $key ] = $_SESSION[ $key ];
            }
        }

        return $tempdata;
    }

    // ------------------------------------------------------------------------

    /**
     * Set tempdata
     *
     * Legacy Session compatibility method
     *
     * @param    mixed $data  Session data key or an associative array of items
     * @param    mixed $value Value to store
     * @param    int   $ttl   Time-to-live in seconds
     *
     * @return    void
     */
    public function set_tempdata( $data, $value = NULL, $ttl = 300 )
    {
        $this->set_userdata( $data, $value );
        $this->mark_as_temp( is_array( $data ) ? array_keys( $data ) : $data, $ttl );
    }

    // ------------------------------------------------------------------------

    /**
     * Unset tempdata
     *
     * Legacy Session compatibility method
     *
     * @param    mixed $key Session data key(s)
     *
     * @return    void
     */
    public function unset_tempdata( $key )
    {
        $this->unmark_temp( $key );
    }

    public function php_started()
    {
        if( php_sapi_name() !== 'cli' )
        {
            if( version_compare( phpversion(), '5.4.0', '>=' ) )
            {
                return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
            }
            else
            {
                return session_id() === '' ? FALSE : TRUE;
            }
        }

        return FALSE;
    }

    // --------------------------------------------------------------------

    /**
     * @param null  $user_id
     * @param null  $status
     * @param array $action
     *
     * @return $this|void
     */
    public function start_log( $user_id = NULL, $status = NULL, $action = array() )
    {
        if( $user_id == NULL ) return;

        if( $status == NULL && empty( $action ) ) return;

        $session_start = now();

        $session_log = array(
            'user_id'    => $user_id,
            'user_agent' => substr( $this->CI->input->user_agent(), 0, 120 ),
            'id'         => $this->userdata[ 'session_id' ],
            'start'      => $session_start,
            'status'     => $status,
            'ip_address' => $this->CI->input->ip_address(),
        );

        $session_log = array_merge( $session_log, $action );

        $this->CI->db->query( $this->CI->db->insert_string( 'system_users_audit_trails', $session_log ) );

        $this->set_userdata( 'log_id', $this->CI->db->insert_id() );
        $this->set_userdata( 'log_start', $session_start );

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * End Log
     */
    public function end_log()
    {
        if( empty( $this->userdata[ 'log_id' ] ) ) return;

        $this->CI->db->query( $this->CI->db->update_string( 'system_users_audit_trails', array(
            'end'      => now(),
            'duration' => $this->userdata[ 'log_start' ] - $this->now,
        ),
                                                            array( 'id' => $this->userdata[ 'log_id' ] )
        ) );

        $this->unset_userdata( array( 'log_id', 'log_start' ) );
    }

    // --------------------------------------------------------------------

    /**
     * @param bool $reset
     *
     * @return bool|int
     */
    public function attempt( $reset = FALSE )
    {
        if( $reset === 'reset' )
        {
            $this->unset_userdata( 'attempt' );

            return FALSE;
        }
        else
        {
            $attempt = ( empty( $this->userdata[ 'attempt' ] ) ? 1 : $this->userdata[ 'attempt' ] );
            $this->set_userdata( 'attempt', $attempt + 1 );

            return $attempt;
        }
    }

}