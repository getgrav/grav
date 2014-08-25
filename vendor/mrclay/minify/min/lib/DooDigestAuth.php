<?php
/**
 * DooDigestAuth class file.
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @link http://www.doophp.com/
 * @copyright Copyright &copy; 2009 Leng Sheng Hong
 * @license http://www.doophp.com/license
 */

/**
 * Handles HTTP digest authentication
 *
 * <p>HTTP digest authentication can be used with the URI router.
 * HTTP digest is much more recommended over the use of HTTP Basic auth which doesn't provide any encryption.
 * If you are running PHP on Apache in CGI/FastCGI mode, you would need to
 * add the following line to your .htaccess for digest auth to work correctly.</p>
 * <code>RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]</code>
 *
 * <p>This class is tested under Apache 2.2 and Cherokee web server. It should work in both mod_php and cgi mode.</p>
 *
 * @author Leng Sheng Hong <darkredz@gmail.com>
 * @version $Id: DooDigestAuth.php 1000 2009-07-7 18:27:22
 * @package doo.auth
 * @since 1.0
 */
class DooDigestAuth{

    /**
     * Authenticate against a list of username and passwords.
     *
     * <p>HTTP Digest Authentication doesn't work with PHP in CGI mode,
     * you have to add this into your .htaccess <code>RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]</code></p>
     *
     * @param string $realm Name of the authentication session
     * @param array $users An assoc array of username and password: array('uname1'=>'pwd1', 'uname2'=>'pwd2')
     * @param string $fail_msg Message to be displayed if the User cancel the login
     * @param string $fail_url URL to be redirect if the User cancel the login
     * @return string The username if login success.
     */
    public static function http_auth($realm, $users, $fail_msg=NULL, $fail_url=NULL){
        $realm = "Restricted area - $realm";

        //user => password
        //$users = array('admin' => '1234', 'guest' => 'guest');
        if(!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) && strpos($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 'Digest')===0){
            $_SERVER['PHP_AUTH_DIGEST'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
            header('WWW-Authenticate: Digest realm="'.$realm.
                   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
            header('HTTP/1.1 401 Unauthorized');
            if($fail_msg!=NULL)
                die($fail_msg);
            if($fail_url!=NULL)
                die("<script>window.location.href = '$fail_url'</script>");
            exit;
        }

        // analyze the PHP_AUTH_DIGEST variable
        if (!($data = self::http_digest_parse($_SERVER['PHP_AUTH_DIGEST'])) || !isset($users[$data['username']])){
            header('WWW-Authenticate: Digest realm="'.$realm.
                   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
            header('HTTP/1.1 401 Unauthorized');
            if($fail_msg!=NULL)
                die($fail_msg);
            if($fail_url!=NULL)
                die("<script>window.location.href = '$fail_url'</script>");
            exit;
        }

        // generate the valid response
        $A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
        $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
        $valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

        if ($data['response'] != $valid_response){
            header('HTTP/1.1 401 Unauthorized');
            header('WWW-Authenticate: Digest realm="'.$realm.
                   '",qop="auth",nonce="'.uniqid().'",opaque="'.md5($realm).'"');
            if($fail_msg!=NULL)
                die($fail_msg);
            if($fail_url!=NULL)
                die("<script>window.location.href = '$fail_url'</script>");
            exit;
        }

        // ok, valid username & password
        return $data['username'];
    }

    /**
     * Method to parse the http auth header, works with IE.
     *
     * Internet Explorer returns a qop="xxxxxxxxxxx" in the header instead of qop=xxxxxxxxxxx as most browsers do.
     *
     * @param string $txt header string to parse
     * @return array An assoc array of the digest auth session
     */
    private static function http_digest_parse($txt)
    {
        $res = preg_match("/username=\"([^\"]+)\"/i", $txt, $match);
        $data['username'] = (isset($match[1]))?$match[1]:null;
        $res = preg_match('/nonce=\"([^\"]+)\"/i', $txt, $match);
        $data['nonce'] = $match[1];
        $res = preg_match('/nc=([0-9]+)/i', $txt, $match);
        $data['nc'] = $match[1];
        $res = preg_match('/cnonce=\"([^\"]+)\"/i', $txt, $match);
        $data['cnonce'] = $match[1];
        $res = preg_match('/qop=([^,]+)/i', $txt, $match);
        $data['qop'] = str_replace('"','',$match[1]);
        $res = preg_match('/uri=\"([^\"]+)\"/i', $txt, $match);
        $data['uri'] = $match[1];
        $res = preg_match('/response=\"([^\"]+)\"/i', $txt, $match);
        $data['response'] = $match[1];
        return $data;
    }


}
