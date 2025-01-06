<?php

namespace YonisSavary\Cube\Http;

class StatusCode
{

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/100
     */
    const CONTINUE = 100;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/101
     */
    const SWITCHING_PROTOCOLS = 101;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/102
     */
    const PROCESSING = 102;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/103
     */
    const EARLY_HINTS = 103;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/200
     */
    const OK = 200;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/201
     */
    const CREATED = 201;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/202
     */
    const ACCEPTED = 202;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/203
     */
    const NON_AUTHORITATIVE_INFORMATION = 203;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/204
     */
    const NO_CONTENT = 204;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/205
     */
    const RESET_CONTENT = 205;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/206
     */
    const PARTIAL_CONTENT = 206;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/207
     */
    const MULTI_STATUS = 207;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/208
     */
    const ALREADY_REPORTED = 208;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/226
     */
    const IM_USED = 226;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/300
     */
    const MULTIPLE_CHOICES = 300;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/301
     */
    const MOVED_PERMANENTLY = 301;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/302
     */
    const FOUND = 302;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/303
     */
    const SEE_OTHER = 303;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/304
     */
    const NOT_MODIFIED = 304;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/305
     */
    const USE_PROXY = 305;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/306
     */
    const UNUSED = 306;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/307
     */
    const TEMPORARY_REDIRECT = 307;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/308
     */
    const PERMANENT_REDIRECT = 308;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/400
     */
    const BAD_REQUEST = 400;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/401
     */
    const UNAUTHORIZED = 401;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/402
     */
    const PAYMENT_REQUIRED = 402;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/403
     */
    const FORBIDDEN = 403;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/404
     */
    const NOT_FOUND = 404;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/405
     */
    const METHOD_NOT_ALLOWED = 405;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/406
     */
    const NOT_ACCEPTABLE = 406;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/407
     */
    const PROXY_AUTHENTICATION_REQUIRED = 407;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/408
     */
    const REQUEST_TIMEOUT = 408;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/409
     */
    const CONFLICT = 409;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/410
     */
    const GONE = 410;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/411
     */
    const LENGTH_REQUIRED = 411;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/412
     */
    const PRECONDITION_FAILED = 412;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/413
     */
    const CONTENT_TOO_LARGE = 413;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/414
     */
    const URI_TOO_LONG = 414;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/415
     */
    const UNSUPPORTED_MEDIA_TYPE = 415;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/416
     */
    const RANGE_NOT_SATISFIABLE = 416;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/417
     */
    const EXPECTATION_FAILED = 417;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/418
     */
    const IM_A_TEAPOT = 418;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/421
     */
    const MISDIRECTED_REQUEST = 421;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/422
     */
    const UNPROCESSABLE_CONTENT = 422;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/423
     */
    const LOCKED = 423;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/424
     */
    const FAILED_DEPENDENCY = 424;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/425
     */
    const TOO_EARLY = 425;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/426
     */
    const UPGRADE_REQUIRED = 426;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/428
     */
    const PRECONDITION_REQUIRED = 428;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/429
     */
    const TOO_MANY_REQUESTS = 429;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/431
     */
    const REQUEST_HEADER_FIELDS_TOO_LARGE = 431;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/451
     */
    const UNAVAILABLE_FOR_LEGAL_REASONS = 451;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/500
     */
    const INTERNAL_SERVER_ERROR = 500;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/501
     */
    const NOT_IMPLEMENTED = 501;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/502
     */
    const BAD_GATEWAY = 502;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/503
     */
    const SERVICE_UNAVAILABLE = 503;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/504
     */
    const GATEWAY_TIMEOUT = 504;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/505
     */
    const HTTP_VERSION_NOT_SUPPORTED = 505;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/506
     */
    const VARIANT_ALSO_NEGOTIATES = 506;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/507
     */
    const INSUFFICIENT_STORAGE = 507;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/508
     */
    const LOOP_DETECTED = 508;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/510
     */
    const NOT_EXTENDED = 510;

    /**
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/511
     */
    const NETWORK_AUTHENTICATION_REQUIRED = 511;


}