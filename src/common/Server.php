<?php
/**
 * Created by PhpStorm.
 * User: ������
 * Date: 01.10.2015
 * Time: 19:48
 */

namespace Saw;


class Server extends Net
{

    public static function socket_server($recursive = false)
    {
        self::$_socket_type = 1;
        if (self::$_socket = socket_create(self::$socket_domain, SOCK_STREAM, self::$socket_domain > 1 ? getprotobyname('tcp') : 0)) {
            if (socket_bind(self::$_socket, self::$socket_address, self::$port)) {
                if (socket_listen(self::$_socket)) {
                    socket_set_nonblock(self::$_socket);
                    return true;
                } else {
                    throw new \Exception(socket_strerror(socket_last_error(self::$_socket)));
                }
            } else {
                $error = socket_last_error(self::$_socket);
                socket_clear_error(self::$_socket);
                error_log('error: ' . $error);
                switch ($error) {
                    case SOCKET_EADDRINUSE:
                        self::socket_close(); // closing socket and try restart
                        if (!$recursive)
                            return self::socket_server(true);
                        break;
                    default:
                        throw new \Exception(socket_strerror($error));
                }
            }
        }
        trigger_error('LISTEN SOCKET CREATE FAILED!', E_USER_ERROR);
        return false;
    }
}