<?php
/*
 * This file implements JWT and ExceptionJWT classes for working with JSON Web Tokens.
 *
 * (c) Jan Andrle <andrle.jan@centrum.cz>
 * …originally/inspired by
 * (c) Jitendra Adhikari <jiten.adhikary@gmail.com>
 *     <https://github.com/adhocore>
 *
 * Licensed under MIT license.
 */
if(!function_exists('hash_equals')) {
  function hash_equals($str1, $str2) {
    if(strlen($str1) != strlen($str2)) {
      return false;
    } else {
      $res = $str1 ^ $str2;
      $ret = 0;
      for($i = strlen($res) - 1; $i >= 0; $i--) $ret |= ord($res[$i]);
      return !$ret;
    }
  }
}
class ExceptionJWT extends \InvalidArgumentException {
    static $messages= array(
        'key_empty'=> array('Signing key cannot be empty.', 10),
        'key_invalid'=> array('Invalid key: Should be resource of private key.', 12),
        'algo_unsupported'=> array('Unsupported algo %s.', 20),
        'algo_missing'=> array('Missing header algo.', 22),
        'invalid_maxage'=> array('Token maxAge should be greater than 0.', 30),
        'invalid_leeway'=> array('Token leeway should be between 0-120.', 32),
        'json_failed'=> array('JSON encode failed.', 40),
        'json_decode'=> array('JSON decode failed (JWT can be corrupted).', 40),
        'token_invalid'=> array('Token contains incomplete segments.', 50),
        'token_expired'=> array('Token expired.', 52),
        'token_not_now'=> array('Token not active yet.', 54),
        'signature_failed'=> array('Signature failed (JWT can be corrupted).', 60),
        'kid_unknown'=> array('Token ID key is unknown.', 70),
    );
    /**
     * @param "key_empty"||"key_invalid"||"algo_unsupported"||"algo_missing"||"invalid_maxage"||"invalid_leeway"||"json_failed"||"json_decode"||"token_invalid"||"token_expired"||"token_not_now"||"kid_unknown"||"signature_failed" $error_id
     * */
    public function __construct($error_id, $error_detail= NULL){
        list($msg, $id)= self::$messages[$error_id];
        $msg= str_replace('%s', $error_detail, $msg);
        parent::__construct($msg, $id);
        return $this;
    }
}
/**
 * JSON Web Token (JWT) implementation in PHP5.5+.
 * @author   Jan Andrle <andrle.jan@centrum.cz>
 * @license  MIT
 */
class JWT {
    /** @var array Supported Signing algorithms. */
    protected $algos = array(
        'HS256' => 'sha256',
        'HS384' => 'sha384',
        'HS512' => 'sha512',
        'RS256' => \OPENSSL_ALGO_SHA256,
        'RS384' => \OPENSSL_ALGO_SHA384,
        'RS512' => \OPENSSL_ALGO_SHA512,
    );

    /** @var string|resource|OpenSSLAsymmetricKey The signature key. */
    protected $key;

    /** @var array The list of supported keys with id. */
    protected $keys = array();

    /** @var int|null Use setTestTimestamp() to set custom value for time(). Useful for testability. */
    protected $timestamp = null;

    /** @var string The JWT signing algorithm. Defaults to HS256. */
    protected $algo = 'HS256';

    /** @var int The JWT TTL in seconds. Defaults to 1 hour. */
    protected $maxAge = 3600;

    /** @var int Grace period in seconds to allow for clock skew. Defaults to 0 seconds. */
    protected $leeway = 0;

    /** @var string|null The passphrase for RSA signing (optional). */
    protected $passphrase;

    /**
     * Constructor.
     *
     * @param string|resource $key    The signature key. For RS* it should be file path or resource of private key.
     * @param string          $algo   The algorithm to sign/verify the token.
     * @param int             $maxAge The TTL of token to be used to determine expiry if `iat` claim is present.
     *                                This is also used to provide default `exp` claim in case it is missing.
     * @param int             $leeway Leeway for clock skew. Shouldnot be more than 2 minutes (120s).
     * @param string          $pass   The passphrase (only for RS* algos).
     */
    public function __construct($key, $algo = 'HS256', $maxAge = 3600, $leeway = 0, $pass = null)
    {
        $this->validateConfig($key, $algo, $maxAge, $leeway);

        if (\is_array($key)) {
            $this->registerKeys($key);
            $key = \reset($key); // use first one!
        }

        $this->key        = $key;
        $this->algo       = $algo;
        $this->maxAge     = $maxAge;
        $this->leeway     = $leeway;
        $this->passphrase = $pass;
        return $this;
    }

    /**
     * Register keys for `kid` support.
     *
     * @param array $keys Use format: ['<kid>' => '<key data>', '<kid2>' => '<key data2>']
     *
     * @return self
     */
    public function registerKeys(array $keys)
    {
        $this->keys = \array_merge($this->keys, $keys);

        return $this;
    }

    /**
     * Encode payload as JWT token.
     *
     * @param array $payload
     * @param array $header  Extra header (if any) to append.
     *
     * @return string URL safe JWT token.
     */
    public function encode(array $payload, array $header = array())
    {
        $header = array('typ' => 'JWT', 'alg' => $this->algo) + $header;

        $this->validateKid($header);

        if (!isset($payload['iat']) && !isset($payload['exp'])) {
            $payload['exp'] = ($this->timestamp ? $this->timestamp : \time()) + $this->maxAge;
        }

        $header    = $this->urlSafeEncode($header);
        $payload   = $this->urlSafeEncode($payload);
        $signature = $this->urlSafeEncode($this->sign($header . '.' . $payload));

        return $header . '.' . $payload . '.' . $signature;
    }

    /**
     * Decode JWT token and return original payload.
     *
     * @param string $token
     *
     * @return array
     */
    public function decode($token)
    {
        if (\substr_count($token, '.') < 2) {
            throw new ExceptionJWT('token_invalid');
        }

        $token = \explode('.', $token, 3);
        $this->validateHeader((array) $this->urlSafeDecode($token[0]));

        // Validate signature.
        if (!$this->verify($token[0] . '.' . $token[1], $token[2])) {
            throw new ExceptionJWT('signature_failed');
        }

        $payload = (array) $this->urlSafeDecode($token[1]);

        $this->validateTimestamps($payload);

        return $payload;
    }

    /**
     * Spoof current timestamp for testing.
     *
     * @param int|null $timestamp
     */
    public function setTestTimestamp($timestamp = null)
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Sign the input with configured key and return the signature.
     *
     * @param string $input
     *
     * @return string
     */
    protected function sign($input)
    {
        // HMAC SHA.
        if (\substr($this->algo, 0, 2) === 'HS') {
            return \hash_hmac($this->algos[$this->algo], $input, $this->key, true);
        }

        $this->validateKey();

        \openssl_sign($input, $signature, $this->key, $this->algos[$this->algo]);

        return $signature;
    }

    /**
     * Verify the signature of given input.
     *
     * @param string $input
     * @param string $signature
     *
     * @throws ExceptionJWT When key is invalid.
     *
     * @return bool
     */
    protected function verify($input, $signature)
    {
        $algo = $this->algos[$this->algo];

        // HMAC SHA.
        if (\substr($this->algo, 0, 2) === 'HS') {
            return \hash_equals($this->urlSafeEncode(\hash_hmac($algo, $input, $this->key, true)), $signature);
        }

        $this->validateKey();

        $_pubKey = \openssl_pkey_get_details($this->key);
        $pubKey = $_pubKey['key'];

        return \openssl_verify($input, $this->urlSafeDecode($signature, false), $pubKey, $algo) === 1;
    }

    /**
     * URL safe base64 encode.
     *
     * First serialized the payload as json if it is an array.
     *
     * @param array|string $data
     *
     * @throws ExceptionJWT When JSON encode fails.
     *
     * @return string
     */
    protected function urlSafeEncode($data)
    {
        if (\is_array($data)) {
            $data = \str_replace("\\/", "/", self::json_encode($data));
        }

        return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * URL safe base64 decode.
     *
     * @param array|string $data
     * @param bool         $asJson Whether to parse as JSON (defaults to true).
     *
     * @throws ExceptionJWT When JSON encode fails.
     *
     * @return array|\stdClass|string
     */
    protected function urlSafeDecode($data, $asJson = true)
    {
        $data_base= \base64_decode(\strtr($data, '-_', '+/'));
        if (!$asJson) return $data_base;

        $data = self::json_decode($data_base);

        return $data;
    }
    /**
     * Throw up if input parameters invalid.
     *
     * @codeCoverageIgnore
     */
    protected function validateConfig($key, $algo, $maxAge, $leeway)
    {
        if(empty($key))
            throw new ExceptionJWT('key_empty');
        if(!isset($this->algos[$algo]))
            throw new ExceptionJWT('algo_unsupported', $algo);
        if($maxAge < 1)
            throw new ExceptionJWT('invalid_maxage');
        if($leeway < 0 || $leeway > 120)
            throw new ExceptionJWT('invalid_leeway');
    }

    /**
     * Throw up if header invalid.
     */
    protected function validateHeader(array $header)
    {
        if(empty($header['alg']))
            throw new ExceptionJWT('algo_missing');
        if(empty($this->algos[$header['alg']]))
            throw new ExceptionJWT('algo_unsupported', '(header missing)');
        $this->validateKid($header);
    }

    /**
     * Throw up if kid exists and invalid.
     */
    protected function validateKid(array $header)
    {
        if(!isset($header['kid']))
            return;
        if(empty($this->keys[$header['kid']]))
            throw new ExceptionJWT('kid_unknown');
        $this->key = $this->keys[$header['kid']];
    }

    /**
     * Throw up if timestamp claims like iat, exp, nbf are invalid.
     */
    protected function validateTimestamps(array $payload)
    {
        $timestamp = $this->timestamp ? $this->timestamp : \time();
        $checks    = array(
            array('exp', $this->leeway /*          */ , 'token_expired'),
            array('iat', $this->maxAge - $this->leeway, 'token_expired'),
            array('nbf', $this->maxAge - $this->leeway, 'token_not_now'),
        );

        foreach ($checks as $checks_nth) {
            list($key, $offset, $error_id)= $checks_nth;
            if (isset($payload[$key])) {
                $offset += $payload[$key];
                $fail    = $key === 'nbf' ? $timestamp <= $offset : $timestamp >= $offset;

                if($fail) throw new ExceptionJWT($error_id);
            }
        }
    }

    /**
     * Throw up if key is not resource or file path to private key.
     */
    protected function validateKey()
    {
        if (\is_string($key = $this->key)) {
            if (\substr($key, 0, 7) !== 'file://') {
                $key = 'file://' . $key;
            }

            $this->key = \openssl_get_privatekey($key, $this->passphrase ? $this->passphrase : '');
        }

        if (!\is_resource($this->key))
            throw new ExceptionJWT('key_invalid');
    }

    static function json_decode($json){
        $data= \json_decode($json, true);
        if(is_array($data)) return $data;
        
        throw new ExceptionJWT('json_decode');
    }
    static function json_encode($json){
        $data= \json_encode($json);
        if(!is_null($data) && $data !== false) return $data;
        
        throw new ExceptionJWT('json_encode');
    }
}
